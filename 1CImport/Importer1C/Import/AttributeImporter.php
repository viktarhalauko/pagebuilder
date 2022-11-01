<?php


namespace App\Importer1C\Import;


use App\Attribute;
use Illuminate\Support\Facades\Date;

/**
 * Class AttributeImporter
 * @package App\Importer1C\Import
 */
class AttributeImporter extends AbstractImporter
{

    /**
     * @param $index
     * @param array $row
     * @param $created_id
     * @return int
     */
    function importRow($index, array $row, &$created_id)
    {

        $res = Attribute::updateOrCreate(['import_id' => $row['ИД1С']], [
                'title' => trim($row['Наименование']),
                'deleted_at' =>  null, //$row["Удалено"] == 'True' ? Date::now() : null,
                'import_id' => $row['ИД1С']
            ]);

        if (! $res ) return self::IMPORT_ROW_ERROR;

        $created_id = $res->id;

        return $res->wasRecentlyCreated ? self::IMPORT_ROW_CREATED : self::IMPORT_ROW_UPDATED;
    }

}