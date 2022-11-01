<?php


namespace App\LandingBuilder\Blocks;
use App\Page;

/**
 * Interface BlockInterface
 * @package App\LandingBuilder\Blocks
 */
interface BlockInterface
{
    /**
     * @return mixed
     */
    public function getName();

    /**
     * @param $blockData
     * @param Page $page
     * @return mixed
     */
    public function renderFrontBlock($blockData, Page $page);

    /**
     * @return mixed
     */
    public function getErrors() ;

}