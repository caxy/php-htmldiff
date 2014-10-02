<?php

namespace Caxy\HtmlDiff\Table;

use Caxy\HtmlDiff\AbstractDiff;
use Caxy\HtmlDiff\HtmlDiff;

/**
 * @todo Add getters to TableMatch entity
 * @todo Move applicable functions to new table classes
 * @todo find matches of row/cells in order to handle row/cell additions/deletions
 * @todo clean up way to iterate between new and old cells
 * @todo Make sure diffed table keeps <tbody> or other table structure elements
 */
class TableDiff extends AbstractDiff
{
    protected $oldTable = null;
    protected $newTable = null;
    protected $diffTable = null;
    protected $diffDom = null;

    protected $newRowOffsets = 0;
    protected $oldRowOffsets = 0;

    protected $cellValues = array();

    public function build()
    {
        $this->buildTableDoms();

        $this->diffDom = new \DOMDocument();

        $this->normalizeFormat();

        $this->indexCellValues($this->newTable);

        $matches = $this->getMatches();

        $this->diffTableContent();

        return $this->content;
    }

    protected function normalizeFormat()
    {
        $oldRows = $this->oldTable->getRows();
        $newRows = $this->newTable->getRows();

        foreach ($newRows as $rowIndex => $newRow) {
            $oldRow = isset($oldRows[$rowIndex]) ? $oldRows[$rowIndex] : null;

            if (!$oldRow) {
                continue;
            }

            $newRowOffset = 0;
            $oldRowOffset = 0;

            foreach ($newRow->getCells() as $cellIndex => $newCell) {
                $oldCell = $oldRow->getCell($cellIndex);

                if ($oldCell) {
                    $oldNode = $oldCell->getDomNode();
                    $newNode = $newCell->getDomNode();

                    $oldRowspan = $oldNode->getAttribute('rowspan') ?: 1;
                    $oldColspan = $oldNode->getAttribute('colspan') ?: 1;
                    $newRowspan = $newNode->getAttribute('rowspan') ?: 1;
                    $newColspan = $newNode->getAttribute('colspan') ?: 1;

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
                    $insertArray[] = new TableRow($blankRow);
                }

                $this->oldTable->insertRows($insertArray, $rowIndex + 1);
            } elseif ($newRowOffset > 0 && isset($newRows[$rowIndex + 1])) {
                $blankRow = $this->diffDom->createElement('tr');

                $insertArray = array();
                for ($i = 0; $i < $newRowOffset; $i++) {
                    $insertArray[] = new TableRow($blankRow);
                }
                $this->newTable->insertRows($insertArray, $rowIndex + 1);
            }
        }
    }

    protected function diffTableContent()
    {
        $this->diffDom = new \DOMDocument();
        $this->diffTable = $this->diffDom->importNode($this->newTable->getDomNode()->cloneNode(false), false);
        $this->diffDom->appendChild($this->diffTable);

        $oldRows = $this->oldTable->getRows();
        $newRows = $this->newTable->getRows();

        foreach ($newRows as $index => $row) {
            $rowDom = $this->diffRows(
                $this->oldTable->getRow($index),
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
        $diffRow = $this->diffDom->importNode($rowToClone->getDomNode()->cloneNode(false), false);

        $oldCells = $oldRow ? $oldRow->getCells() : array();
        $newCells = $newRow ? $newRow->getCells() : array();

        foreach ($newCells as $index => $cell) {
            $diffCell = $this->diffCells(
                $oldRow->getCell($index),
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

    protected function getNewCellNode(TableCell $oldCell = null, TableCell $newCell = null)
    {
        // If only one cell exists, use it
        if (!$oldCell || !$newCell) {
            $clone = $newCell
                ? $newCell->getDomNode()->cloneNode(false)
                : $oldCell->getDomNode()->cloneNode(false);
        } else {
            $oldNode = $oldCell->getDomNode();
            $newNode = $newCell->getDomNode();

            $clone = $newNode->cloneNode(false);

            $oldRowspan = $oldNode->getAttribute('rowspan') ?: 1;
            $oldColspan = $oldNode->getAttribute('colspan') ?: 1;
            $newRowspan = $newNode->getAttribute('rowspan') ?: 1;
            $newColspan = $newNode->getAttribute('colspan') ?: 1;

            $clone->setAttribute('rowspan', max($oldRowspan, $newRowspan));
            $clone->setAttribute('colspan', max($oldColspan, $newColspan));
        }

        return $this->diffDom->importNode($clone);
    }

    protected function diffCells($oldCell, $newCell)
    {
        $diffCell = $this->getNewCellNode($oldCell, $newCell);
        
        $oldContent = $oldCell ? $this->getInnerHtml($oldCell->getDomNode()) : '';
        $newContent = $newCell ? $this->getInnerHtml($newCell->getDomNode()) : '';

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

        $tableNode = $dom->getElementsByTagName('table')->item(0);

        $table = new Table($tableNode);

        $this->parseTable($table);

        return $table;
    }

    protected function parseTable(Table $table, \DOMNode $node = null)
    {
        if ($node === null) {
            $node = $table->getDomNode();
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'tr') {
                $row = new TableRow($child);
                $table->addRow($row);

                $this->parseTableRow($row);
            } else {
                $this->parseTable($table, $child);
            }
        }
    }

    protected function parseTableRow(TableRow $row)
    {
        $node = $row->getDomNode();

        foreach ($node->childNodes as $child) {
            if (in_array($child->nodeName, array('td', 'th'))) {
                $cell = new TableCell($child);
                $row->addCell($cell);
            }
        }
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

    protected function indexCellValues(Table $table)
    {
        foreach ($table->getRows() as $rowIndex => $row) {
            foreach ($row->getCells() as $cellIndex => $cell) {
                $value = trim($cell->getDomNode()->textContent);

                if (!isset($this->cellValues[$value])) {
                    $this->cellValues[$value] = array();
                }

                $this->cellValues[$value][] = new TablePosition($rowIndex, $cellIndex);
            }
        }
    }

    protected function getMatches()
    {
        $matches = array();

        $oldRowCount = count($this->oldTable->getRows());
        $newRowCount = count($this->newTable->getRows());

        $startInOld = new TablePosition(0, 0);
        $startInNew = new TablePosition(0, 0);
        $endInOld = new TablePosition($oldRowCount - 1, count($this->oldTable->getRow($oldRowCount - 1)->getCells()) - 1);
        $endInNew = new TablePosition($newRowCount - 1, count($this->newTable->getRow($newRowCount - 1)->getCells()) - 1);

        $this->findMatches($startInOld, $endInOld, $startInNew, $endInNew, $matches);

        return $matches;
    }

    protected function findMatches($startInOld, $endInOld, $startInNew, $endInNew, &$matches)
    {
        $match = $this->findMatch($startInOld, $endInOld, $startInNew, $endInNew);
        if ($match !== null) {
            if (TablePosition::compare($startInOld, $match->getStartInOld()) < 0 &&
                TablePosition::compare($startInNew, $match->getStartInNew()) < 0
            ) {
                $this->findMatches($startInOld, $match->getStartInOld(), $startInNew, $match->getStartInNew(), $matches);
            }

            $matches[] = $match;

            if (TablePosition::compare($match->getEndInOld(), $endInOld) < 0 &&
                TablePosition::compare($match->getEndInNew(), $endInNew) < 0
            ) {
                $this->findMatches($match->getEndInOld(), $endInOld, $match->getEndInNew(), $endInNew, $matches);
            }
        }
    }

    protected function findMatch($startInOld, $endInOld, $startInNew, $endInNew)
    {
        $bestMatchInOld = $startInOld;
        $bestMatchInNew = $startInNew;
        $bestMatchSize = 0;
        $matchLengthAt = array();

        $currentPos = $startInOld;

        while ($currentPos && TablePosition::compare($currentPos, $endInOld) < 0) {
            $newMatchLengthAt = array();
            $oldCell = $this->oldTable->getCellByPosition($currentPos);

            $value = trim($oldCell->getDomNode()->textContent);

            if (!isset($this->cellValues[$value])) {
                $matchLengthAt = $newMatchLengthAt;
                $currentPos = $this->oldTable->getPositionAfter($currentPos);
                continue;
            }
            
            foreach ($this->cellValues[$value] as $posInNew) {
                if (TablePosition::compare($posInNew, $startInNew) < 0) {
                    continue;
                }
                if (TablePosition::compare($posInNew, $endInNew) >= 0) {
                    break;
                }

                $posBefore = $this->newTable->getPositionBefore($posInNew);

                $newMatchLength = 1 + (isset($matchLengthAt[(string)$posBefore]) ? $matchLengthAt[(string)$posBefore] : 0);
                $newMatchLengthAt[(string)$posInNew] = $newMatchLength;

                if ($newMatchLength > $bestMatchSize) {
                    $bestMatchInOld = $this->oldTable->getPositionBefore($currentPos, $newMatchLength - 1);
                    $bestMatchInNew = $this->newTable->getPositionBefore($posInNew, $newMatchLength - 1);
                    $bestMatchSize = $newMatchLength;
                }
            }
            $matchLengthAt = $newMatchLengthAt;

            $currentPos = $this->oldTable->getPositionAfter($currentPos);
        }

        if ($bestMatchSize != 0) {
            $bestEndInOld = $this->oldTable->getPositionAfter($bestMatchInOld, $bestMatchSize);
            $bestEndInNew = $this->newTable->getPositionAfter($bestMatchInNew, $bestMatchSize);
            return new TableMatch($bestMatchInOld, $bestMatchInNew, $bestEndInOld, $bestEndInNew);
        }

        return null;
    }
}
