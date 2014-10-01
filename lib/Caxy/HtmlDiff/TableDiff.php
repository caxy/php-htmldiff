<?php

namespace Caxy\HtmlDiff;

/**
 * @todo clean up way to iterate between new and old cells
 * @todo Make sure diffed table keeps <tbody> or other table structure elements
 * @todo find matches of row/cells in order to handle row/cell additions/deletions
 */
class TableDiff extends AbstractDiff
{
    protected $oldRows = array();
    protected $newRows = array();

    protected $oldTable = null;
    protected $newTable = null;
    protected $diffTable = null;
    protected $diffDom = null;

    protected $newRowOffsets = 0;
    protected $oldRowOffsets = 0;

    public function build()
    {
        $this->splitInputsToWords();
        $this->buildTableDoms();

        $this->diffDom = new \DOMDocument();

        $this->normalizeFormat();

        //print_r($this->newTable);die();

        $this->diffTableContent();

        return $this->content;
    }

    protected function normalizeFormat()
    {
        $oldRows = $this->oldTable['children'];
        $newRows = $this->newTable['children'];

        foreach ($newRows as $rowIndex => $newRow) {
            $oldRow = isset($oldRows[$rowIndex]) ? $oldRows[$rowIndex] : null;

            if (!$oldRow) {
                continue;
            }

            $newRowOffset = 0;
            $oldRowOffset = 0;

            foreach ($newRow['children'] as $cellIndex => $newCell) {
                $oldCell = isset($oldRow['children'][$cellIndex]) ? $oldRow['children'][$cellIndex] : null;

                if ($oldCell) {
                    $oldRowspan = $oldCell->getAttribute('rowspan') ?: 1;
                    $oldColspan = $oldCell->getAttribute('colspan') ?: 1;
                    $newRowspan = $newCell->getAttribute('rowspan') ?: 1;
                    $newColspan = $newCell->getAttribute('colspan') ?: 1;

                    if ($oldRowspan > $newRowspan) {
                        // add placeholders in next row of new rows
                        $offset = $oldRowspan - $newRowspan;
                        if ($offset > $newRowOffset) {
                            $newRowOffset = $offset;
                        }
                    } elseif ($newRowspan > $oldRowspan) {
                        $offset = $newRowspan - $oldRowspan;
                        if ($offset > $oldRowOffset) {
                            $oldRowOffset = $offset;
                        }
                    }
                }
            }

            if ($oldRowOffset > 0 && isset($newRows[$rowIndex + 1])) {
                $blankRow = $this->diffDom->createElement('tr');

                $insertArray = array();
                for ($i = 0; $i < $oldRowOffset; $i++) {
                    $insertArray[] = array('dom' => $blankRow, 'children' => array());
                }

                array_splice($this->oldTable['children'], $rowIndex + 1, 0, $insertArray);
            } elseif ($newRowOffset > 0 && isset($newRows[$rowIndex + 1])) {
                $blankRow = $this->diffDom->createElement('tr');

                $insertArray = array();
                for ($i = 0; $i < $newRowOffset; $i++) {
                    $insertArray[] = array('dom' => $blankRow, 'children' => array());
                }
                array_splice($this->newTable['children'], $rowIndex + 1, 0, $insertArray);
            }
        }
    }

    protected function diffTableContent()
    {
        $this->diffDom = new \DOMDocument();
        $this->diffTable = $this->diffDom->importNode($this->newTable['dom']->cloneNode(false), false);
        $this->diffDom->appendChild($this->diffTable);

        $oldRows = $this->oldTable['children'];
        $newRows = $this->newTable['children'];

        foreach ($newRows as $index => $row) {
            $rowDom = $this->diffRows(
                isset($oldRows[$index]) ? $oldRows[$index] : null,
                $row
            );

            $this->diffTable->appendChild($rowDom);
        }

        if (count($oldRows) > count($newRows)) {
            foreach (array_slice($oldRows, count($newRows)) as $row) {
                $rowDom = $this->diffRows(
                    $row,
                    null
                );

                $this->diffTable->appendChild($rowDom);
            }
        }

        $this->content = $this->htmlFromNode($this->diffTable);
    }

    protected function diffRows($oldRow, $newRow)
    {
        // create tr dom element
        $rowToClone = $newRow ?: $oldRow;
        $diffRow = $this->diffDom->importNode($rowToClone['dom']->cloneNode(false), false);

        $oldCells = $oldRow ? $oldRow['children'] : array();
        $newCells = $newRow ? $newRow['children'] : array();

        foreach ($newCells as $index => $cell) {
            $diffCell = $this->diffCells(
                isset($oldCells[$index]) ? $oldCells[$index] : null,
                $cell
            );

            $diffRow->appendChild($diffCell);
        }

        if (count($oldCells) > count($newCells)) {
            foreach (array_slice($oldCells, count($newCells)) as $cell) {
                $diffCell = $this->diffCells(
                    $cell,
                    null
                );

                $diffRow->appendChild($diffCell);
            }
        }

        return $diffRow;
    }

    protected function getNewCellNode(\DOMElement $oldCell = null, \DOMElement $newCell = null)
    {
        // If only one cell exists, use it
        if (!$oldCell || !$newCell) {
            $clone = $newCell ? $newCell->cloneNode(false) : $oldCell->cloneNode(false);
        } else {
            $clone = $newCell->cloneNode(false);

            $oldRowspan = $oldCell->getAttribute('rowspan') ?: 1;
            $oldColspan = $oldCell->getAttribute('colspan') ?: 1;
            $newRowspan = $newCell->getAttribute('rowspan') ?: 1;
            $newColspan = $newCell->getAttribute('colspan') ?: 1;

            $clone->setAttribute('rowspan', max($oldRowspan, $newRowspan));
            $clone->setAttribute('colspan', max($oldColspan, $newColspan));
        }

        return $this->diffDom->importNode($clone);
    }

    protected function diffCells($oldCell, $newCell)
    {
        $diffCell = $this->getNewCellNode($oldCell, $newCell);
        
        $oldContent = $oldCell ? $this->getInnerHtml($oldCell) : '';
        $newContent = $newCell ? $this->getInnerHtml($newCell) : '';

        $htmlDiff = new HtmlDiff($oldContent, $newContent, $this->encoding, $this->specialCaseChars, $this->groupDiffs);

        $diff = $htmlDiff->build();

        $this->setInnerHtml($diffCell, $diff);

        return $diffCell;
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
            if (in_array($child->nodeName, array('td', 'th'))) {
                $row[] = $child;
            }
        }

        return $row;
    }

    protected function getInnerHtml($node)
    {
        $innerHtml = '';
        $children = $node->childNodes;

        foreach ($children as $child) {
            $innerHtml .= $this->htmlFromNode($child);
        }

        return $innerHtml;
    }

    protected function htmlFromNode($node)
    {
        $domDocument = new \DOMDocument();
        $newNode = $domDocument->importNode($node, true);
        $domDocument->appendChild($newNode);
        return trim($domDocument->saveHTML());
    }

    protected function setInnerHtml($node, $html)
    {
        $doc = new \DOMDocument();
        $doc->loadHTML($html);
        $fragment = $node->ownerDocument->createDocumentFragment();
        $root = $doc->getElementsByTagName('body')->item(0);
        foreach ($root->childNodes as $child) {
            $fragment->appendChild($node->ownerDocument->importNode($child, true));
        }
        
        $node->appendChild($fragment);
    }
}
