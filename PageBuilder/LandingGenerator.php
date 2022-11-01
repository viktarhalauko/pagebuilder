<?php

namespace App\LandingBuilder;


use App\Landing;

class LandingGenerator
{

    protected $blocks = [];

    protected $html = '';

    function __construct()
    {
        $this->blocks = Landing::getTreeWhere('active', '=', '1')->sortBy('position');
    }

    public function Render()
    {

        if ($this->blocks) {
            /** @var Landing $block */
            foreach ($this->blocks as $block) {
                $this->html .= $this->renderBlock($block);
            }
        }

        return $this->html;

    }

    function getEncodedBlockData($block)
    {
        return json_decode($block->body);
    }


    function renderTwoColumnBlock(Landing $block)
    {


        if ($block->active == 0) {
            return '';
        }
        $children = $block->getChildren()->sortBy('position');


        $leftColumn = $this->renderColumn($children->get(0), 'left');
        $rightColumn = $this->renderColumn($children->get(1), 'right');

        $rightBlock = $this->getEncodedBlockData($children->get(1));
        $leftBlock = $this->getEncodedBlockData($children->get(0));

        $textBlockFirst = false;

        foreach ($rightBlock as $block) {
            if (isset($block->active) &&  ($block->active== 1)) {
                if ($block->type == 'OneColumnBlock') {
                    $textBlockFirst = true;
                    break;
                }
            }
        }

        foreach ($leftBlock as $block) {
            if (isset($block->active) &&  ($block->active== 1)) {
                if ($block->type == 'OneColumnBlock') {
                    $textBlockFirst = false;
                    break;
                }
            }
        }


        return view('landing.TwoColumns', ['leftColumn' => $leftColumn, 'rightColumn' => $rightColumn, 'textBlockFirst' => $textBlockFirst])->render();
    }


    function renderColumn($column, $columnType = '')
    {

        $html = '';

        $data = $this->getEncodedBlockData($column);

        if (!$data) {
            return '';
        }

        foreach ($data as $key => $item) {

            if (isset($item->active) && $item->active == 1) {
                $html .= view('landing.twoColumnsItems.' . $item->type, ['blockData' => $item, 'key' => $key, 'column_type' => $columnType])->render();
            }

        }
        return $html;
    }

    public function renderBlock(Landing $block)
    {
        if ($block->hasChildren()) {
            return $this->renderTwoColumnBlock($block);
        } else {

            if(in_array($block->block_type, ['left_column', 'right_column'])){
                return '';
            }
            return $this->renderOneColumnBlock($block);
        }

    }

    protected function renderOneColumnBlock($block)
    {
        $blockData = $this->getEncodedBlockData($block);

        /** empty block */
        if (!isset($blockData[0])) {
            return '';
        }

        return view('landing.' . $block->block_type, ['blockData' => $blockData[0]])->render();
    }

}