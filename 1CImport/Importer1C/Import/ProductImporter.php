<?php

namespace App\Importer1C\Import;


use App\Attribute;
use App\Category;
use App\Collection;
use App\Color;
use App\Image;
use App\Importer1C\Interfaces\Extractor;
use App\Pattern;
use App\Product;
use App\Size;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/**
 * Class ProductImporter
 * @package App\Importer1C\Import
 */
class ProductImporter extends AbstractImporter
{

    /**
     * @var null
     */
    protected $imagesDirSrc = null;
    /**
     * @var string|null
     */
    public $imagesPatternsDirDst = null;
    /**
     * @var string|null
     */
    public $imagesProductDirDst = null;

    /**
     * @var Storage
     */
    public $storage = null;

    /**
     * ProductImporter constructor.
     * @param Extractor $data
     * @param null $imagesDir
     */
    public function __construct(Extractor $data, $imagesDir = null)
    {
        $this->storage = Storage::disk('local');

        $this->imagesDirSrc = $this->storage->exists($imagesDir) ? $imagesDir : null;

        $this->imagesPatternsDirDst = 'public/patterns';
        $this->imagesProductDirDst = 'public/products';

        parent::__construct($data);
    }

    /**
     * @var string[]
     */
    public $canBeMultiple = ["Коллекция",];

    /**
     * @var string[]
     */
    public $skipAttributes = ["Размер",];

    /**
     * @var string
     */
    public $rowTitleField = "ID_Товара";

    /**
     * @param int $index
     * @param array $row
     * @param int $created_id
     * @return int
     */
    function importRow($index, array $row, &$created_id)
    {

        $hash = self::hash($row);

        try {
            $product = Product::firstOrNew(['hash' => $hash], [
                'active' => 1
            ]);

            $product->price = $row['Цена'];
            $product->price_old = $row['СтараяЦена'];
            $product->name = $row['Товар'];
            $product->description = $row['ОписаниеТовара'];
            $product->deleted_at = $row['Товар_Удален'] == 'True' ? Date::now() : null;
            $product->hash = $hash;
            $product->accounting_id = $row["ID_Товара"];
            $product->sku = (int) $row['Товар'];
            $product->save();


            $created_id = $product->id;

            $attributes = array_slice($row, 17, null, true);

            $attached = [];

            foreach ($attributes as $a_key => $a_val) {

                $parentAttribute = Attribute::where(['title' => $a_key])->first();

                if (!$parentAttribute) {
                    $this->logAttributeNotExists($index, $a_key);
                    continue;
                }

                if (empty(trim($a_val))) continue;

                $a_val = self::stupidExplode($a_val);

                foreach ($a_val as $a_val_key => $a_val_one) {

                    if (empty($a_val_one)) continue;
                    $key_value = 0;
                    if (!is_numeric($a_val_key)) {
                        $key_value = 0;
                        $pos = Attribute::where(['parent_id' => $parentAttribute->id])->count();
                        $valueAttribute = Attribute::firstOrCreate(['import_id' => $a_val_key], [
                            'parent_id' => $parentAttribute->id,
                            'title' => $a_val_one,
                            'position' => $pos,
                            'import_id' => $a_val_key,
                        ]);

                        if ($valueAttribute->title != $a_val_one) {
                            $valueAttribute->title = $a_val_one;
                            $valueAttribute->save();
                        }

                    } else {
                        $valueAttribute = Attribute::firstOrCreate(['title' => $a_val_one, 'parent_id' => $parentAttribute->id], [
                            'parent_id' => $parentAttribute->id,
                            'title' => $a_val_one,
                            'position' => Attribute::where(['parent_id' => $parentAttribute->id])->count()
                        ]);
                    }


                    $this->logAddAttribute($product, $valueAttribute);
                    $attached[$valueAttribute->id] = ['type' => $key_value ? 1 : 0];

                }

            }

            $product->attributes()->sync($attached);

            $cats = array_map('trim', explode('%%%', $row['ГруппаТовара']));
            $this->importCategories($product, $cats, $index);

            $this->importColor($product, $row);
            $this->importSize($product, $row['Размер']);
            $this->importImages($product, $row);
            $this->importCollections($product, self::stupidExplode($row['Коллекция']));


        } catch (\Exception $e) {
            return self::IMPORT_ROW_ERROR;
        }

        return $product->wasRecentlyCreated ? self::IMPORT_ROW_CREATED : self::IMPORT_ROW_UPDATED;
    }

    /**
     * @param $index
     * @param $cat_id
     */
    private function logCategoryNotExists($index, $cat_id)
    {
        $this->logger->info("Нет категории $cat_id для строки $index");
    }

    /**
     * @param $index
     * @param $attr_id
     */
    private function logAttributeNotExists($index, $attr_id)
    {
        $this->logger->info("Нет атрибута $attr_id для строки $index");
    }


    /**
     * @param Product $product
     * @param Attribute $attr
     */
    private function logAddAttribute(Product $product, Attribute $attr)
    {
        $this->logger->info("Назначен аттрибут $attr->import_id со значением $attr->title");
    }


    /**
     * @param $row
     * @return string
     */
    private static function hash($row)
    {
        return md5($row["ID_Товара"] . $row["ID_Цвета"]);
    }


    /**
     * @param $key
     * @return bool
     */
    private function canBeMultiple($key)
    {
        return in_array($key, $this->canBeMultiple);
    }

    /**
     * @param Product $product
     * @param $value
     */
    private function importSize(Product $product, $value)
    {
        $sizes = explode(",", $value);

        if (empty($sizes)) return;

        $attached = [];
        foreach ($sizes as $size) {

            @list($title, $quantity) = explode("/", $size);
            $quantity = $quantity ?: 0;
            $size = Size::firstOrNew(['title' => $title], ['title' => $title]);
            (!$size->id) and $size->position = Size::max('position') + 1;
            $size->save();
            $attached[$size->id] = ['quantity' => $quantity];
            $this->logSize($product, $size, $quantity);
        }
        $product->sizes()->sync($attached);
    }

    /**
     * @param Product $product
     * @param array $categories
     * @param $product_index
     */
    private function importCategories(Product $product, array $categories, $product_index)
    {

        if (empty($categories)) return;

        $ids = [];
        foreach ($categories as $import_id) {
            /** @var Category|null $category */
            $category = Category::where('import_id', $import_id)->first();
            $this->logCategoryNotExists($product_index, $import_id);
            $ids[] = $category->id;
        }
        $product->categories()->sync($ids);
        isset($ids[0]) and $product->default_category()->associate($ids[0]);
        $product->save();
    }

    /**
     * @param Product $product
     * @param array $collections
     */
    private function importCollections(Product $product, array $collections)
    {
        $ids = [];
        foreach ($collections as $import_id => $value) {
            $collection = Collection::firstOrNew(['import_id' => $import_id], ['title' => $value, 'active' => 1]);
            (!$collection->id) and $collection->position = Collection::max('position') + 1;
            $collection->save();
            $ids[] = $collection->id;
        }
        $product->collections()->sync($ids);
    }

    /**
     * @param Product $product
     * @param $row
     */
    private function importColor(Product $product, $row)
    {


        if ($row['ID_Цвета']) {

            $color = Color::firstOrNew(['import_id' => $row['ID_Цвета']]);

            $color->title = $row['Цвет'];
            $color->code = $row['КодЦвета'];
            $color->import_id = $row['ID_Цвета'];
            $color->save();

            if ($row['ID_паттерна']) {
                $pattern = Pattern::firstOrNew(['import_id' => $row['ID_паттерна']], [
                    'title' => $row['Паттерн'],
                    'import_id' => $row['ID_паттерна'],
                    'file' => $row['ИмяИзображенияПаттерна'],
                ]);


                if ($this->storage->exists($this->imagesDirSrc . "/" . $pattern->file)) {

                    if ($this->storage->exists($this->imagesPatternsDirDst . "/" . $pattern->file)) {
                        $this->storage->delete($this->imagesPatternsDirDst . "/" . $pattern->file);
                    }

                    $this->storage->copy($this->imagesDirSrc . "/" . $pattern->file, $this->imagesPatternsDirDst . "/" . $pattern->file);
                }

                $pattern->save();


                $color->Pattern()->associate($pattern);
                $color->save();
            }

            $product->colors()->sync($color);
        }

        $colorForFilterAttr = Attribute::firstOrCreate(['title' => Attribute::FILTER_COLOR], [
            'title' => Attribute::FILTER_COLOR
        ]);


        $product->attributes()->detach($product->attributes()->where('parent_id', $colorForFilterAttr->id)->pluck('attributes.id'));

        $colorsAttached = [];

        $colorsForFilter = self::stupidExplode($row['ЦветДляФильтра']);

        foreach ($colorsForFilter as $key => $color) {

            $colorAttribute = Attribute::updateOrCreate(['import_id' => $key], [
                'title' => $color,
                'import_id' => $key,
                'parent_id' => $colorForFilterAttr->id,
            ]);


            $colorsAttached[] = $colorAttribute->id;
        }

        $product->attributes()->attach($colorsAttached);

    }

    /**
     * @param Product $product
     * @param $row
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function importImages(Product $product, $row)
    {
        $imagesFileNames = explode(',', $row['ИмяФотографии']);
        $oldImages = $product->images;

        foreach ($oldImages as $oldImage) {
            if (!in_array($oldImage->file, $imagesFileNames)) {
                if ($oldImage->id === $product->default_image_id) {
                    $product->default_image_id = null;
                    $product->save();
                }

                $oldImage->removeFiles();
                $oldImage->delete();
                $this->logImageRemove($oldImage);
            }
        }

        \DB::table('images')->whereProductId($product->id)->increment('position');
        foreach ($imagesFileNames as $imagesFileName) {

            $path = $this->imagesDirSrc . '/' . $imagesFileName;
            if ($imagesFileName != '' && $this->storage->exists($path)) {

                $image = Image::firstOrNew(['file' => $imagesFileName]);

                if (!$image->id) {
                    $image->file = $imagesFileName;
                    $image->product_id = $product->id;
                    $image->save();
                    $image->updateFile($this->storage->readStream($path));
                }

                if (
                    isset($row['ИмяФотографииОсновной']) && $row['ИмяФотографииОсновной']
                ) {

                    if ($row['ИмяФотографииОсновной'] == $image->file) {

                        //Image::whereProductId($product->id)->increment('position');


                        $product->default_image_id = $image->id;
                        $product->save();

                        $image->position = 0;
                        $image->save();

                    }

                } else {

                    if (!$product->default_image_id) {
                        $product->default_image_id = $image->id;
                        $product->save();
                    }
                }

            }

        }


    }

    /**
     * @param Product $product
     * @param Size $size
     * @param $quantity
     */
    protected function logSize(Product $product, Size $size, $quantity)
    {
        $this->logger->info(($size->wasRecentlyCreated ? "Добавлен" : "Обновлен") . " размер: " . $size->title . ". Остатки: " . $quantity);
    }

    /**
     * @param Image $image
     */
    protected function logImageRemove(Image $image)
    {
        $this->logger->info('Image was deleted: ' . $image->id);
    }

    /**
     * @param $value
     * @return array|null
     */
    protected static function stupidExplode($value): ?array
    {
        $preValue = explode("%%%", $value);
        $newValue = [];
        if (count($preValue) % 2) return [];

        for ($i = 1; $i < count($preValue); $i = $i + 2) {
            $newValue[$preValue[$i]] = $preValue[$i - 1];
        }

        return $newValue;
    }

}
