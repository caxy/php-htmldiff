<?php

namespace Caxy\HtmlDiff;

class TableDiff extends AbstractDiff
{
    protected $oldRows = array();
    protected $newRows = array();

    protected $oldTable = null;
    protected $newTable = null;
    protected $diffTable = null;

    public function build()
    {
        $this->splitInputsToWords();
        $this->buildTableDoms();

        $this->diffTableContent();

        return $this->content;
    }

    protected function diffTableContent()
    {
        $this->diffTable = new \DOMDocument();

        $oldRows = $this->oldTable['children'];
        $newRows = $this->newTable['children'];

        foreach ($newRows as $index => $row) {
            if (isset($oldRows[$index])) {
                $this->diffRows($oldRows[$index], $row);
            } else {
                
            }
        }
    }

    protected function diffRows($oldRow, $newRow)
    {
        
    }

    protected function insertRow($row)
    {
        
    }

    protected function buildTableDoms()
    {
        $this->oldTable = $this->parseTableStructure($this->oldText);
        $this->newTable = $this->parseTableStructure($this->newText);
    }

    protected function parseTableStructure($text)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML($text);

        $table = $dom->getElementsByTagName('table')->item(0);

        $rows = $this->parseTable($table);

        return array(
            'dom' => $table,
            'children' => $rows
        );
    }

    protected function parseTable(\DOMNode $node)
    {
        $rows = array();
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'tr') {
                $rows[] = array(
                    'dom' => $child,
                    'children' => $this->parseTableRow($child)
                );
            } else {
                $rows = array_merge($rows, $this->parseTable($child, $rows));
            }
        }

        return $rows;
    }

    protected function parseTableRow(\DOMNode $node)
    {
        $row = array();
        foreach ($node->childNodes as $child) {
            if (in_array($child->name, array('td', 'th'))) {
                $row[] = $child;
            }
        }

        return $row;
    }

    protected function getInnerHtml($node)
    {
        $body = $node->ownerDocument->documentElement->firstChild->firstChild;
        $document = new \DOMDocument();
        $document->appendChild($document->importNode($body, true));
        return $document->saveHTML();
    }
}
