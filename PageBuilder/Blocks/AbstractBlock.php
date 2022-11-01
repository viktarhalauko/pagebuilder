<?php
/**
 * Created by PhpStorm.
 * User: victor
 * Date: 22.03.18
 * Time: 0:17
 */

namespace App\LandingBuilder\Blocks;

use App\LandingBuilder\ResourceLink;

/**
 * Class AbstractBlock
 * @package App\LandingBuilder\Blocks
 */
abstract class AbstractBlock implements BlockInterface
{

    /**
     * @var
     */
    protected $errors;

    /**
     * @var
     */
    protected $validationRules;

    /**
     * @var null
     */
    protected $data;

    /**
     * AbstractBlock constructor.
     * @param null $data
     */
    function __construct($data = null)
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return __CLASS__;
    }

    /**
     * @return mixed
     */
    function getErrors()
    {
        return $this->errors;
    }

    /**
     *
     */
    function resetErrors()
    {
        $this->errors = [];
    }


    /**
     * @param $error
     */
    function setErrors($error)
    {
        $this->errors = $error;
    }

    /**
     * @param $blockId
     * @return array
     */
    function getGeneralRules($blockId)
    {
        return [
            'blocks.' . $blockId . '.title' => 'nullable|string',
            'blocks.' . $blockId . '.heading_type' => 'nullable|in:div,h1,h2,h3,h4,h5,h6',
            'blocks.' . $blockId . '.buttons.*.style' => 'nullable|string|in:style1,style2',
           // 'blocks.' . $blockId . '.buttons.*.target' => 'required|in:url,page',
            'blocks.' . $blockId . '.buttons.*.text' => 'nullable|string',
            'blocks.' . $blockId . '.buttons.*.url' => 'nullable|string',
            'blocks.' . $blockId . '.buttons.*.page' => 'nullable|string',

        ];
    }

    /**
     * @param $blockID
     * @return mixed
     */
    abstract function getValidationRules($blockID);

    /**
     * @param $button
     */
    function setButtonsData($button)
    {


        if ($button->target == 'page') {

            $url = ResourceLink::getPageLink($button->page);
            $button->url = ResourceLink::getPageLink($button->page);
            if (!$url) {
                $button->active = 0;
            }
        } else {
            if (!$button->url) {
                $button->active = 0;
            }
        }


    }

    /**
     * @return null
     */
    function transformData()
    {
        return $this->data;
    }

}