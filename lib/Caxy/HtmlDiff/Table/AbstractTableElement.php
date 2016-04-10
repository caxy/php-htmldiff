<?php

namespace Caxy\HtmlDiff\Table;

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
     * @param \DOMElement|null $domNode
     */
    public function __construct(\DOMElement $domNode = null)
    {
        $this->domNode = $domNode;
    }

    /**
     * @return \DOMElement
     */
    public function getDomNode()
    {
        return $this->domNode;
    }

    /**
     * @param \DOMElement $domNode
     *
     * @return $this
     */
    public function setDomNode(\DOMElement $domNode)
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
     * @param \DOMDocument $domDocument
     *
     * @return \DOMElement
     */
    public function cloneNode(\DOMDocument $domDocument)
    {
        return $domDocument->importNode($this->getDomNode()->cloneNode(false), false);
    }

    /**
     * @param \DOMElement $node
     *
     * @return string
     */
    public static function htmlFromNode($node)
    {
        $domDocument = new \DOMDocument();
        $newNode = $domDocument->importNode($node, true);
        $domDocument->appendChild($newNode);

        return trim($domDocument->saveHTML());
    }
}
