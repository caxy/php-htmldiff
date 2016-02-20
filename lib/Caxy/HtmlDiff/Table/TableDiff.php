<?php

use Caxy\HtmlDiff\Table\TableCell;

namespace Caxy\HtmlDiff\Table;

use Caxy\HtmlDiff\AbstractDiff;
use Caxy\HtmlDiff\HtmlDiff;

/**
 * @todo Add getters to TableMatch entity
 * @todo Move applicable functions to new table classes
 * @todo find matches of row/cells in order to handle row/cell additions/deletions
 * @todo clean up way to iterate between new and old cells
 * @todo Make sure diffed table keeps <tbody> or other table structure elements
 * @todo Encoding
 */
class TableDiff extends AbstractDiff
{
    /**
     * @var null|Table
     */
    protected $oldTable = null;

    /**
     * @var null|Table
     */
    protected $newTable = null;

    /**
     * @var null|Table
     */
    protected $diffTable = null;

    /**
     * @var null|\DOMDocument
     */
    protected $diffDom = null;

    /**
     * @var int
     */
    protected $newRowOffsets = 0;

    /**
     * @var int
     */
    protected $oldRowOffsets = 0;

    /**
     * @var array
     */
    protected $cellValues = array();

    /**
     * @var \HTMLPurifier
     */
    protected $purifier;
    
    public function __construct($oldText, $newText, $encoding, $specialCaseTags, $groupDiffs)
    {
        parent::__construct($oldText, $newText, $encoding, $specialCaseTags, $groupDiffs);

        $config = \HTMLPurifier_Config::createDefault();
//        $config->set('Cache.SerializerPath', $this->container->get('kernel')->getCacheDir());
//        $config->set('Cache.SerializerPermissions', 0775);
//        $this->addTagTransform('b', 'strong');
//        $this->addTagTransform('i', 'em');
        $this->purifier = new \HTMLPurifier($config);
    }

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

            $newCells = $newRow->getCells();
            $oldCells = $oldRow->getCells();

            foreach ($newCells as $cellIndex => $newCell) {
                $oldCell = isset($oldCells[$cellIndex]) ? $oldCells[$cellIndex] : null;

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

                    $oldColOffset = 0;
                    $newColOffset = 0;
                    if ($oldColspan > $newColspan) {
                        // add placeholders in next cells
                        $newColOffset = $oldColspan - $newColspan;
                    } elseif ($newColspan > $oldColspan) {
                        $oldColOffset = $newColspan - $oldColspan;
                    }

                    // @todo: Figure out colspan
//                    if ($oldColOffset > 0 && isset($newCells[$cellIndex + 1])) {
//                        $blankCell = $this->diffDom->createElement('td');
//
//                        $insertArray = array();
//                        for ($i = 0; $i < $oldColOffset; $i++) {
//                            $insertArray[] = new TableCell($blankCell);
//                        }
//
//                        $oldRow->insertCells($insertArray, $cellIndex + 1);
//                    } elseif ($newColOffset > 0 && isset($oldCells[$cellIndex + 1])) {
//                        $blankCell = $this->diffDom->createElement('td');
//
//                        $insertArray = array();
//                        for ($i = 0; $i < $newColOffset; $i++) {
//                            $insertArray[] = new TableCell($blankCell);
//                        }
//
//                        $newRow->insertCells($insertArray, $cellIndex + 1);
//                    }
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
            list($rowDom, $extraRow) = $this->diffRows(
                $this->oldTable->getRow($index),
                $row
            );

            $this->diffTable->appendChild($rowDom);

            if ($extraRow) {
                $this->diffTable->appendChild($extraRow);
            }
        }

        if (count($oldRows) > count($newRows)) {
            foreach (array_slice($oldRows, count($newRows)) as $row) {
                list($rowDom, $extraRow) = $this->diffRows(
                    $row,
                    null
                );

                $this->diffTable->appendChild($rowDom);

                if ($extraRow) {
                    $this->diffTable->appendChild($extraRow);
                }
            }
        }

        $this->content = $this->htmlFromNode($this->diffTable);
    }

    /**
     * @param TableRow|null $oldRow
     * @param TableRow|null $newRow
     *
     * @return \DOMNode
     */
    protected function diffRows($oldRow, $newRow)
    {
        // create tr dom element
        $rowToClone = $newRow ?: $oldRow;
        $diffRow = $this->diffDom->importNode($rowToClone->getDomNode()->cloneNode(false), false);

        $oldCells = $oldRow ? $oldRow->getCells() : array();
        $newCells = $newRow ? $newRow->getCells() : array();

        // @todo: Figure out what we're doing with the cells
        $columnsWithColspanDifferences = 0;

        $indexInOld = 0;
        $indexInNew = 0;

        $newCellCount = count($newCells);
        $oldCellCount = count($oldCells);

        $extraRow = null;

        $expandCells = array();

        $currentOffset = 0;

        $virtualColInOld = 0;
        $virtualColInNew = 0;

        $indexInDiff = 0;

        while ($indexInNew < $newCellCount) {
            /* @var $newCell TableCell */
            $newCell = $newCells[$indexInNew];
            /* @var $oldCell TableCell */
            $oldCell = isset($oldCells[$indexInOld]) ? $oldCells[$indexInOld] : null;

            $newColspan = $newCell->getColspan();

            if ($virtualColInOld > $virtualColInNew) {
                // Add old cell, and catch up the new cells.
                $targetCol = $virtualColInOld;

                while ($virtualColInNew < $targetCol && $newCell) {
                    $newDiffCell = $this->diffCells(null, $newCell);
                    $extraRow->appendChild($newDiffCell);
                    $virtualColInNew += $newCell->getColspan();
                    $indexInNew++;
                    $newCell = $newRow->getCell($indexInNew);
                }

                continue;
            } elseif ($virtualColInNew > $virtualColInOld) {
                // Add new cell, and catch up the old cells.
                $targetCol = $virtualColInNew;

                while ($virtualColInOld < $targetCol && $oldCell) {
                    $oldDiffCell = $this->diffCells($oldCell, null);
                    $diffRow->appendChild($oldDiffCell);
                    $indexInDiff++;
                    $virtualColInOld += $oldCell->getColspan();
                    $indexInOld++;
                    $oldCell = $oldRow->getCell($indexInOld);
                }

                continue;
            }

            if ($oldCell) {
                $oldColspan = $oldCell->getColspan();

                if ($newColspan != $oldColspan) {
                    // colspans are different, so the way we output diffs is a little odd.
                    if (null === $extraRow) {
                        $extraRow = $this->diffDom->importNode($rowToClone->getDomNode()->cloneNode(false), false);
                    }

                    if ($oldColspan > $newColspan) {
                        // Add old cell, and catch up the new cells.
                        $targetCol = $virtualColInOld + $oldColspan;

                        $oldDiffCell = $this->diffCells($oldCell, null);
                        $diffRow->appendChild($oldDiffCell);
                        $indexInDiff++;
                        $virtualColInOld += $oldColspan;
                        $indexInOld++;

                        while ($virtualColInNew < $targetCol && $newCell) {
                            $newDiffCell = $this->diffCells(null, $newCell);
                            $extraRow->appendChild($newDiffCell);
                            $virtualColInNew += $newCell->getColspan();
                            $indexInNew++;
                            $newCell = $newRow->getCell($indexInNew);
                        }
                    } else {
                        // Add new cell, and catch up the old cells.
                        $targetCol = $virtualColInNew + $newColspan;

                        $newDiffCell = $this->diffCells(null, $newCell);
                        $extraRow->appendChild($newDiffCell);
                        $virtualColInNew += $newColspan;
                        $indexInNew++;

                        while ($virtualColInOld < $targetCol && $oldCell) {
                            $oldDiffCell = $this->diffCells($oldCell, null);
                            $diffRow->appendChild($oldDiffCell);
                            $indexInDiff++;
                            $virtualColInOld += $oldCell->getColspan();
                            $indexInOld++;
                            $oldCell = $oldRow->getCell($indexInOld);
                        }
                    }
                } else {
                    $diffCell = $this->diffCells($oldCell, $newCell);
                    $diffRow->appendChild($diffCell);

                    $expandCells[] = $diffCell;
                    $indexInNew++;
                    $indexInOld++;
                    $indexInDiff++;
                    $virtualColInNew += $newColspan;
                    $virtualColInOld += $oldColspan;
                }
            } else {
                // new cell all alone
                $newDiffCell = $this->diffCells(null, $newCell);
                $diffRow->appendChild($newDiffCell);

                $expandCells[] = $newDiffCell;

                $virtualColInNew += $newColspan;
                $indexInNew++;
                $indexInDiff++;
            }
        }

        while ($indexInOld < $oldCellCount) {
            $diffCell = $this->diffCells($oldCells[$indexInOld], null);
            $diffRow->appendChild($diffCell);

            $expandCells[] = $diffCell;
            $indexInDiff++;
            $indexInOld++;
        }

        foreach ($expandCells as $expandCell) {
            $expandCell->setAttribute('rowspan', $expandCell->getAttribute('rowspan') + 1);
        }

        return array($diffRow, $extraRow);
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
        $this->oldTable = $this->parseTableStructure(mb_convert_encoding($this->oldText, 'HTML-ENTITIES', 'UTF-8'));
        $this->newTable = $this->parseTableStructure(mb_convert_encoding($this->newText, 'HTML-ENTITIES', 'UTF-8'));
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
        // DOMDocument::loadHTML does not allow empty strings.
        if (strlen($html) === 0) {
            $html = '<span class="empty"></span>';
        }

        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($this->purifier->purify($html), 'HTML-ENTITIES', 'UTF-8'));
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
