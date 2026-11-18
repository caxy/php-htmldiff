<?php

namespace Caxy\HtmlDiff\Table;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

/**
 * Class AbstractTableElement.
 */
abstract class AbstractTableElement
{
    /**
     * @var \DOMElement
     */
    protected $domNode;

    /**
     * AbstractTableElement constructor.
     *
     * @param Element|null $domNode
     */
    public function __construct(?Element $domNode = null)
    {
        $this->domNode = $domNode;
    }

    /**
     * @return Element
     */
    public function getDomNode()
    {
        return $this->domNode;
    }

    /**
     * @param Element $domNode
     *
     * @return $this
     */
    public function setDomNode(Element $domNode)
    {
        $this->domNode = $domNode;

        return $this;
    }

    /**
     * @return string
     */
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

    /**
     * @param string $name
     *
     * @return string
     */
    public function getAttribute($name)
    {
        return $this->domNode->getAttribute($name);
    }

    /**
     * @param HTMLDocument $domDocument
     *
     * @return Node
     */
    public function cloneNode(HTMLDocument $domDocument)
    {
        return $domDocument->importNode($this->getDomNode()->cloneNode(false), false);
    }

    /**
     * @param HTMLDocument $node
     *
     * @return string
     */
    public static function htmlFromNode($node)
    {

        $domDocument = HTMLDocument::createFromString('<!DOCTYPE html><html><body></body></html>');
        $newNode = $domDocument->importNode($node, true);
        $domDocument->querySelector('body')->appendChild($newNode);

        return trim($domDocument->saveHTML());
    }
}
