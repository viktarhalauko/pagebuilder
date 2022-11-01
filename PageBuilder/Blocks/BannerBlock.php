<?php


namespace App\LandingBuilder\Blocks;

use App;
use App\Page;
use App\LandingBuilder\ResourceLink;

/**
 * Class ImageAndTextBlock
 * @package App\LandingBuilder\Blocks
 */
class BannerBlock extends AbstractBlock
{

    /**
     * @var array
     */
    protected $errors = [];


    /**
     * @param $blockData
     * @param Page $page
     * @return mixed|string
     * @throws \Throwable
     */
    public function renderFrontBlock($blockData, Page $page)
    {

        if (empty($blockData->active)) {
            return '';
        }

        if (isset($blockData->buttons)) {
            foreach ($blockData->buttons as $button) {
                $this->setButtonsData($button);
            }
        }


        $data = compact('blockData', 'page');
        return view('blocks.ImageAndTextBlockTemplate', $data)->render();
    }


    /**
     * @param $blockId
     * @return array|mixed
     */
    function getValidationRules($blockId)
    {

        $rules = $this->getGeneralRules($blockId);

        $rules["blocks." . $blockId . ".text.0"] = 'nullable|string';
        return $rules;

    }

}