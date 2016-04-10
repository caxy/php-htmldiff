<?php

namespace Caxy\HtmlDiff\ListDiff;

class DiffList
{
    protected $listType;

    protected $listItems = array();

    protected $attributes = array();

    protected $startTag;

    protected $endTag;

    public function __construct($listType, $startTag, $endTag, $listItems = array(), $attributes = array())
    {
        $this->listType = $listType;
        $this->startTag = $startTag;
        $this->endTag = $endTag;
        $this->listItems = $listItems;
        $this->attributes = $attributes;
    }

    /**
     * @return mixed
     */
    public function getListType()
    {
        return $this->listType;
    }

    /**
     * @param mixed $listType
     *
     * @return DiffList
     */
    public function setListType($listType)
    {
        $this->listType = $listType;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStartTag()
    {
        return $this->startTag;
    }

    public function getStartTagWithDiffClass($class = 'diff-list')
    {
        return str_replace('>', ' class="'.$class.'">', $this->startTag);
    }

    /**
     * @param mixed $startTag
     */
    public function setStartTag($startTag)
    {
        $this->startTag = $startTag;
    }

    /**
     * @return mixed
     */
    public function getEndTag()
    {
        return $this->endTag;
    }

    /**
     * @param mixed $endTag
     */
    public function setEndTag($endTag)
    {
        $this->endTag = $endTag;
    }

    /**
     * @return mixed
     */
    public function getListItems()
    {
        return $this->listItems;
    }

    /**
     * @param mixed $listItems
     *
     * @return DiffList
     */
    public function setListItems($listItems)
    {
        $this->listItems = $listItems;

        return $this;
    }
}
