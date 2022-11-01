<?php


namespace App\LandingBuilder;


use App\Landing;
use App\LandingBuilder\Blocks\AbstractBlock;
use App\LandingBuilder\Blocks\BannerBlock;
use App\LandingBuilder\Blocks\BigImageAndTextBlock;
use App\LandingBuilder\Blocks\BigOneColumnBlock;
use App\LandingBuilder\Blocks\ImageAndTextBlock;
use App\LandingBuilder\Blocks\OneColumnBlock;
use App\LandingBuilder\Blocks\Slider4XBlock;

use Illuminate\Http\Request;


use Validator;

/**
 * Class BlockHandler
 * @package App\LandingBuilder
 */
class BlockHandler
{


    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var array
     */
    protected $validationRules = [];

    /**
     * @var Landing
     */
    protected $landing;

    /**
     * BlockHandler constructor.
     * @param Landing $landing
     */
    function __construct(Landing $landing)
    {
        $this->landing = $landing;
    }

    /**
     * @return array
     */
    function getErrors()
    {
        return $this->errors;
    }


    /**
     * @param Request $request
     * @return bool
     * @throws \Exception
     */
    public function validateBlocks(Request $request)
    {


        $blocks = $request->input('blocks', []);
        // empty landing
        if (!$blocks) {
            return true;
        }

        if (!is_array($blocks)) {
            $this->errors = 'Invalid data';
            return false;

        }

        foreach ($blocks as $key => $blockData) {

            if (!isset($blockData['type']) || !is_string($blockData['type'])) {

                $this->errors = 'Block type does not exist';
                return false;
            }

            if (!$this->isBlockAllowed($blockData['type'])) {
                $this->errors = 'Block type ' . $blockData['type'] . ' is not registered';
                return false;
            }

            $block = $this->blockFactory($blockData['type']);

            $blockRules = $block->getValidationRules($key);

            $this->validationRules += $blockRules;

        }


        $validator = Validator::make($request->all(), $this->validationRules);


        if ($validator->fails()) {

            $this->errors = $validator->errors();
            return false;

        }
        return true;

    }


    /**
     * @param $blockName
     * @param null $data
     * @return AbstractBlock
     * @throws \Exception
     */
    function blockFactory($blockName, $data = null): AbstractBlock
    {

        $blockName = 'App\LandingBuilder\Blocks\\' . $blockName;

        if (!class_exists($blockName)) {
            throw  new \Exception('Block does not exists');
        }
        return new $blockName($data);

    }

    /**
     * @param $blockName
     * @return bool
     */
    function isBlockAllowed($blockName)
    {
        return in_array($blockName, $this->getAllowedBlocks());
    }


    /**
     * @param $class
     * @return mixed
     */
    public function getName($class)
    {
        $path = explode('\\', $class);
        return array_pop($path);
    }

    /**
     * @return array
     */
    function getAllowedBlocks()
    {
        $classNameList = [];

        foreach ($this->allowedBlocksClassList() as $class){
            $classNameList[] = $this->getName($class);
        }
        return $classNameList;
    }

    /**
     * @return array
     */
    function allowedBlocksClassList()
    {
        return [
            BannerBlock::class,
            OneColumnBlock::class,
            Slider4XBlock::class,
            ImageAndTextBlock::class,
            BigImageAndTextBlock::class,
            BigOneColumnBlock::class
        ];
    }


    /**
     * @param Request $request
     * @return string
     * @throws \Exception
     */
    function getSerializedBlocks(Request $request)
    {
        $blocks = [];
        $blockList = $request->input('blocks', []);


        foreach ($blockList as $key => $block) {

            $blockObj = $this->blockFactory($block['type'], $block);

            $blocks[] = $blockObj->transformData();
        }

        return json_encode($blocks);

    }

}