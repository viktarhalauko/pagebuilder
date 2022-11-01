<?php


namespace App\LandingBuilder\Blocks;

use App\Page;

/**
 * Class Slider4XBlock
 * @package App\LandingBuilder\Blocks
 */
class Slider4XBlock extends AbstractBlock
{
    /**
     * @param $blockID
     * @return array|mixed
     */
    function getValidationRules($blockID)
    {
        return [];
    }


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


        if (!empty($blockData->slides)) {


            $count = 0;

            foreach ($blockData->slides as $slide) {

                if (!empty($slide->active)) {
                    $count++;
                }
                if (isset($slide->buttons)) {
                    foreach ($slide->buttons as $button) {
                        $this->setButtonsData($button);
                    }
                }
            }
            $blockData->conutActiveSliders = $count;
            if ($count == 0) {
                return '';
            }

        } else {
            return '';
        }


        $data = compact('blockData', 'page');
        return view('blocks.Slider4XBlockTemplate', $data)->render();

    }

    /**
     * @return null
     */
    function transformData()
    {

        // reset keys to make json array instead object
        if (!empty($this->data['slides'])) {

            // replace
            $this->data['slides'] = array_values($this->data['slides']);
        }

        return $this->data;
    }


}