<?php

namespace Caxy\HtmlDiff\Table;

abstract class AbstractTableElement
{
    /**
     * @var \DOMElement
     */
    protected $domNode;

    public function __construct(\DOMElement $domNode = null)
    {
        $this->domNode = $domNode;
    }

    public function getDomNode()
    {
        return $this->domNode;
    }

    public function setDomNode(\DOMElement $domNode)
    {
        $this->domNode = $domNode;

        return $this;
    }

    public function getInnerHtml()
    {
        $innerHtml = '';

        if ($this->domNode) {
            foreach ($this->domNode->childNodes as $child) {
                $innerHtml .= static::htmlFromNode($child);
            }
        }

        return $innerHtml;
    }

    public function getAttribute($name) {
        return $this->domNode->getAttribute($name);
    }

    public static function htmlFromNode($node)
    {
        $domDocument = new \DOMDocument();
        $newNode = $domDocument->importNode($node, true);
        $domDocument->appendChild($newNode);
        return trim($domDocument->saveHTML());
    }
}
