<?php
namespace App\LandingBuilder;

use App\Landing;


/**
 * Class PageTree
 * @package App\LandingBuilder
 */
class LandingTree
{

    /**
     * @var array
     */
    static $list = [];
    /**
     * @var
     */
    static $breadcrumbs;

    /**
     * @param null $parentId
     * @param $lang
     * @return array
     */
    public static function getLandingList($parentId = null, $lang)
    {

        self::recursiveList($parentId, $lang);
        return self::$list;

    }

    /**
     * @param null $parentId
     * @param $lang
     */
    public static function recursiveList($parentId = null, $lang)
    {

        $parents = Landing::where('parent_id', '=', $parentId)->orderBy('position')->get(['id', 'real_depth', 'title']);
        /** @var Page $item */
        foreach ($parents as $item) {
            self::$list[] = ['id' => $item->id, 'title' => $item->title, 'title' => $item->title, 'real_depth' => $item->real_depth];
          //  self::recursiveList($item->id, $lang);
        }
    }


    /**
     * @param Page $page
     * @return array
     */
    static public function getBreadcrumbs(Page $page)
    {
        self::$breadcrumbs[] = $page;
        $parent = $page->getParent();
        if($page->getParent()){
            self::getBreadcrumbs($parent);
        }
        return array_reverse(self::$breadcrumbs);
    }
}