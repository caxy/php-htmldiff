<?php

namespace Caxy\HtmlDiff\ListDiff;

class DiffListItem
{
    protected $attributes = array();

    protected $text;

    protected $startTag;

    protected $endTag;

    public function __construct($text, $attributes = array(), $startTag, $endTag)
    {
        $this->text = $text;
        $this->attributes = $attributes;
        $this->startTag = $startTag;
        $this->endTag = $endTag;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param array $attributes
     *
     * @return DiffListItem
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param mixed $text
     *
     * @return DiffListItem
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStartTag()
    {
        return $this->startTag;
    }

    public function getStartTagWithDiffClass($class = 'normal')
    {
        return str_replace('>', ' class="'.$class.'">', $this->startTag);
    }

    /**
     * @param mixed $startTag
     *
     * @return DiffListItem
     */
    public function setStartTag($startTag)
    {
        $this->startTag = $startTag;

        return $this;
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
     *
     * @return DiffListItem
     */
    public function setEndTag($endTag)
    {
        $this->endTag = $endTag;

        return $this;
    }

    public function getHtml($class = 'normal', $wrapTag = null)
    {
        $startWrap = $wrapTag ? sprintf('<%s>', $wrapTag) : '';
        $endWrap = $wrapTag ? sprintf('</%s>', $wrapTag) : '';

        return sprintf('%s%s%s%s%s', $this->getStartTagWithDiffClass($class), $startWrap, $this->getInnerHtml(), $endWrap, $this->endTag);
    }

    public function getInnerHtml()
    {
        return implode('', $this->text);
    }

    public function __toString()
    {
        return $this->getHtml();
    }
}
