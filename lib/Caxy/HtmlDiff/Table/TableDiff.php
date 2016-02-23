<?php

namespace Caxy\HtmlDiff\Table;

use Caxy\HtmlDiff\AbstractDiff;
use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\Operation;

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

    protected $strategy = self::STRATEGY_MATCHING;

    public function __construct($oldText, $newText, $encoding, $specialCaseTags, $groupDiffs)
    {
        parent::__construct($oldText, $newText, $encoding, $specialCaseTags, $groupDiffs);

        $config = \HTMLPurifier_Config::createDefault();
        $this->purifier = new \HTMLPurifier($config);
    }

    public function build()
    {
        $this->buildTableDoms();

        $this->diffDom = new \DOMDocument();

        $this->normalizeFormat();

        $this->indexCellValues($this->newTable);

//        $matches = $this->getMatches();

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
                    $newRowspan = $newNode->getAttribute('rowspan') ?: 1;

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

        $oldMatchData = array();
        $newMatchData = array();

        /* @var $oldRow TableRow */
        foreach ($oldRows as $oldIndex => $oldRow) {
            $oldMatchData[$oldIndex] = array();

            // Get match percentages
            /* @var $newRow TableRow */
            foreach ($newRows as $newIndex => $newRow) {
                if (!array_key_exists($newIndex, $newMatchData)) {
                    $newMatchData[$newIndex] = array();
                }

                // similar_text
                $percentage = $this->getMatchPercentage($oldRow, $newRow);

                $oldMatchData[$oldIndex][$newIndex] = $percentage;
                $newMatchData[$newIndex][$oldIndex] = $percentage;
            }
        }

        // new solution for diffing rows
        switch ($this->strategy) {
            case self::STRATEGY_MATCHING:
                $matches = $this->getRowMatches($oldMatchData, $newMatchData);
                $this->diffTableRowsWithMatches($oldRows, $newRows, $matches);
                break;

            case self::STRATEGY_RELATIVE:
                $this->diffTableRows($oldRows, $newRows, $oldMatchData);
                break;

            default:
                $matches = $this->getRowMatches($oldMatchData, $newMatchData);
                $this->diffTableRowsWithMatches($oldRows, $newRows, $matches);
                break;
        }

        $this->content = $this->htmlFromNode($this->diffTable);
    }

    /**
     * @param TableRow[] $oldRows
     * @param TableRow[] $newRows
     * @param RowMatch[] $matches
     */
    protected function diffTableRowsWithMatches($oldRows, $newRows, $matches)
    {
        $operations = array();

        $indexInOld = 0;
        $indexInNew = 0;

        $oldRowCount = count($oldRows);
        $newRowCount = count($newRows);

        $matches[] = new RowMatch($newRowCount, $oldRowCount, $newRowCount, $oldRowCount);

        // build operations
        foreach ($matches as $match) {
            $matchAtIndexInOld = $indexInOld === $match->getStartInOld();
            $matchAtIndexInNew = $indexInNew === $match->getStartInNew();

            $action = 'equal';

            if (!$matchAtIndexInOld && !$matchAtIndexInNew) {
                $action = 'replace';
            } elseif ($matchAtIndexInOld && !$matchAtIndexInNew) {
                $action = 'insert';
            } elseif (!$matchAtIndexInOld && $matchAtIndexInNew) {
                $action = 'delete';
            }

            if ($action !== 'equal') {
                $operations[] = new Operation($action, $indexInOld, $match->getStartInOld(), $indexInNew, $match->getStartInNew());
            }

            $operations[] = new Operation('equal', $match->getStartInOld(), $match->getEndInOld(), $match->getStartInNew(), $match->getEndInNew());

            $indexInOld = $match->getEndInOld();
            $indexInNew = $match->getEndInNew();
        }

        $appliedRowSpans = array();

        // process operations
        foreach ($operations as $operation) {
            switch ($operation->action) {
                case 'equal':
                    $this->processEqualOperation($operation, $oldRows, $newRows, $appliedRowSpans);
                    break;

                case 'delete':
                    $this->processDeleteOperation($operation, $oldRows, $newRows, $appliedRowSpans);
                    break;

                case 'insert':
                    $this->processInsertOperation($operation, $oldRows, $newRows, $appliedRowSpans);
                    break;

                case 'replace':
                    $this->processReplaceOperation($operation, $oldRows, $newRows, $appliedRowSpans);
                    break;
            }
        }
    }

    protected function processInsertOperation(Operation $operation, $oldRows, $newRows, &$appliedRowSpans, $forceExpansion = false)
    {
        $targetRows = array_slice($newRows, $operation->startInNew, $operation->endInNew - $operation->startInNew);
        foreach ($targetRows as $row) {
            $this->diffAndAppendRows(null, $row, $appliedRowSpans, $forceExpansion);
        }
    }

    protected function processDeleteOperation($operation, $oldRows, $newRows, &$appliedRowSpans, $forceExpansion = false)
    {
        $targetRows = array_slice($oldRows, $operation->startInOld, $operation->endInOld - $operation->startInOld);
        foreach ($targetRows as $row) {
            $this->diffAndAppendRows($row, null, $appliedRowSpans, $forceExpansion);
        }
    }

    protected function processEqualOperation($operation, $oldRows, $newRows, &$appliedRowSpans)
    {
        $targetOldRows = array_values(array_slice($oldRows, $operation->startInOld, $operation->endInOld - $operation->startInOld));
        $targetNewRows = array_values(array_slice($newRows, $operation->startInNew, $operation->endInNew - $operation->startInNew));

        foreach ($targetNewRows as $index => $newRow) {
            if (!isset($targetOldRows[$index])) {
                continue;
            }

            $this->diffAndAppendRows($targetOldRows[$index], $newRow, $appliedRowSpans);
        }
    }

    protected function processReplaceOperation($operation, $oldRows, $newRows, &$appliedRowSpans)
    {
        $this->processDeleteOperation($operation, $oldRows, $newRows, $appliedRowSpans, true);
        $this->processInsertOperation($operation, $oldRows, $newRows, $appliedRowSpans, true);
    }

    protected function getRowMatches($oldMatchData, $newMatchData)
    {
        $matches = array();

        $startInOld = 0;
        $startInNew = 0;
        $endInOld = count($oldMatchData);
        $endInNew = count($newMatchData);

        $this->findRowMatches($newMatchData, $startInOld, $endInOld, $startInNew, $endInNew, $matches);

        return $matches;
    }

    protected function findRowMatches($newMatchData, $startInOld, $endInOld, $startInNew, $endInNew, &$matches)
    {
        $match = $this->findRowMatch($newMatchData, $startInOld, $endInOld, $startInNew, $endInNew);
        if ($match !== null) {
            if ($startInOld < $match->getStartInOld() &&
                $startInNew < $match->getStartInNew()
            ) {
                $this->findRowMatches(
                    $newMatchData,
                    $startInOld,
                    $match->getStartInOld(),
                    $startInNew,
                    $match->getStartInNew(),
                    $matches
                );
            }

            $matches[] = $match;

            if ($match->getEndInOld() < $endInOld &&
                $match->getEndInNew() < $endInNew
            ) {
                $this->findRowMatches($newMatchData, $match->getEndInOld(), $endInOld, $match->getEndInNew(), $endInNew, $matches);
            }
        }
    }

    protected function findRowMatch($newMatchData, $startInOld, $endInOld, $startInNew, $endInNew)
    {
        $bestMatch = null;
        $bestPercentage = 0;

        foreach ($newMatchData as $newIndex => $oldMatches) {
            if ($newIndex < $startInNew) {
                continue;
            }

            if ($newIndex >= $endInNew) {
                break;
            }
            foreach ($oldMatches as $oldIndex => $percentage) {
                if ($oldIndex < $startInOld) {
                    continue;
                }

                if ($oldIndex >= $endInOld) {
                    break;
                }

                if ($percentage > $bestPercentage) {
                    $bestPercentage = $percentage;
                    $bestMatch = array(
                        'oldIndex' => $oldIndex,
                        'newIndex' => $newIndex,
                        'percentage' => $percentage,
                    );
                }
            }
        }

        if ($bestMatch !== null) {
            return new RowMatch($bestMatch['newIndex'], $bestMatch['oldIndex'], $bestMatch['newIndex'] + 1, $bestMatch['oldIndex'] + 1, $bestMatch['percentage']);
        }

        return null;
    }

    /**
     * @param $oldRows
     * @param $newRows
     * @param $oldMatchData
     */
    protected function diffTableRows($oldRows, $newRows, $oldMatchData)
    {
        $appliedRowSpans = array();
        $currentIndexInOld = 0;
        $oldCount = count($oldRows);
        $newCount = count($newRows);
        $difference = max($oldCount, $newCount) - min($oldCount, $newCount);

        foreach ($newRows as $newIndex => $row) {
            $oldRow = $this->oldTable->getRow($currentIndexInOld);

            if ($oldRow) {
                $matchPercentage = $oldMatchData[$currentIndexInOld][$newIndex];

                // does the old row match better?
                $otherMatchBetter = false;
                foreach ($oldMatchData[$currentIndexInOld] as $index => $percentage) {
                    if ($index > $newIndex && $percentage > $matchPercentage) {
                        $otherMatchBetter = $index;
                    }
                }

                if (false !== $otherMatchBetter && $newCount > $oldCount && $difference > 0) {
                    // insert row as new
                    $this->diffAndAppendRows(null, $row, $appliedRowSpans);
                    $difference--;

                    continue;
                }

                $nextOldIndex = array_key_exists($currentIndexInOld + 1, $oldRows) ? $currentIndexInOld + 1 : null;

                $replacement = false;

                if ($nextOldIndex !== null &&
                    $oldMatchData[$nextOldIndex][$newIndex] > $matchPercentage &&
                    $oldMatchData[$nextOldIndex][$newIndex] > $this->matchThreshold
                ) {
                    // Following row in old is better match, use that.
                    $this->diffAndAppendRows($oldRows[$currentIndexInOld], null, $appliedRowSpans, true);

                    $currentIndexInOld++;
                    $matchPercentage = $oldMatchData[$currentIndexInOld];
                    $replacement = true;
                }

                $this->diffAndAppendRows($oldRows[$currentIndexInOld], $row, $appliedRowSpans, $replacement);
                $currentIndexInOld++;
            } else {
                $this->diffAndAppendRows(null, $row, $appliedRowSpans);
            }
        }

        if (count($oldRows) > count($newRows)) {
            foreach (array_slice($oldRows, count($newRows)) as $row) {
                $this->diffAndAppendRows($row, null, $appliedRowSpans);
            }
        }
    }

    /**
     * @param TableRow|null $oldRow
     * @param TableRow|null $newRow
     * @param array         $appliedRowSpans
     * @param bool          $forceExpansion
     *
     * @return \DOMNode
     */
    protected function diffRows($oldRow, $newRow, array &$appliedRowSpans, $forceExpansion = false)
    {
        // create tr dom element
        $rowToClone = $newRow ?: $oldRow;
        $diffRow = $this->diffDom->importNode($rowToClone->getDomNode()->cloneNode(false), false);

        $oldCells = $oldRow ? $oldRow->getCells() : array();
        $newCells = $newRow ? $newRow->getCells() : array();

        $position = new DiffRowPosition();

        $extraRow = null;

        $expandCells = array();
        $cellsWithMultipleRows = array();

        // @todo: Do cell matching

        $newCellCount = count($newCells);
        while ($position->getIndexInNew() < $newCellCount) {
            if (!$position->areColumnsEqual()) {
                $type = $position->getLesserColumnType();
                if ($type === 'new') {
                    $row = $newRow;
                    $targetRow = $extraRow;
                } else {
                    $row = $oldRow;
                    $targetRow = $diffRow;
                }
                if ($row && (!$type === 'old' || isset($oldCells[$position->getIndexInOld()]))) {
                    $this->syncVirtualColumns($row, $position, $cellsWithMultipleRows, $targetRow, $type, true);

                    continue;
                }
            }

            /* @var $newCell TableCell */
            $newCell = $newCells[$position->getIndexInNew()];
            /* @var $oldCell TableCell */
            $oldCell = isset($oldCells[$position->getIndexInOld()]) ? $oldCells[$position->getIndexInOld()] : null;

            if ($oldCell && $newCell->getColspan() != $oldCell->getColspan()) {
                if (null === $extraRow) {
                    $extraRow = $this->diffDom->importNode($rowToClone->getDomNode()->cloneNode(false), false);
                }

                // @todo: How do we handle cells that have both rowspan and colspan?

                if ($oldCell->getColspan() > $newCell->getColspan()) {
                    $this->diffCellsAndIncrementCounters(
                        $oldCell,
                        null,
                        $cellsWithMultipleRows,
                        $diffRow,
                        $position,
                        true
                    );
                    $this->syncVirtualColumns($newRow, $position, $cellsWithMultipleRows, $extraRow, 'new', true);
                } else {
                    $this->diffCellsAndIncrementCounters(
                        null,
                        $newCell,
                        $cellsWithMultipleRows,
                        $extraRow,
                        $position,
                        true
                    );
                    $this->syncVirtualColumns($oldRow, $position, $cellsWithMultipleRows, $diffRow, 'old', true);
                }
            } else {
                $diffCell = $this->diffCellsAndIncrementCounters(
                    $oldCell,
                    $newCell,
                    $cellsWithMultipleRows,
                    $diffRow,
                    $position
                );
                $expandCells[] = $diffCell;
            }
        }

        $oldCellCount = count($oldCells);
        while ($position->getIndexInOld() < $oldCellCount) {
            $diffCell = $this->diffCellsAndIncrementCounters(
                $oldCells[$position->getIndexInOld()],
                null,
                $cellsWithMultipleRows,
                $diffRow,
                $position
            );
            $expandCells[] = $diffCell;
        }

        if ($extraRow) {
            foreach ($expandCells as $expandCell) {
                $expandCell->setAttribute('rowspan', $expandCell->getAttribute('rowspan') + 1);
            }
        }

        if ($extraRow || $forceExpansion) {
            foreach ($appliedRowSpans as $rowSpanCells) {
                foreach ($rowSpanCells as $extendCell) {
                    $extendCell->setAttribute('rowspan', $extendCell->getAttribute('rowspan') + 1);
                }
            }
        }

        if (!$forceExpansion) {
            array_shift($appliedRowSpans);
            $appliedRowSpans = array_values($appliedRowSpans);
        }
        $appliedRowSpans = array_merge($appliedRowSpans, array_values($cellsWithMultipleRows));

        return array($diffRow, $extraRow);
    }

    /**
     * @param TableCell|null $oldCell
     * @param TableCell|null $newCell
     *
     * @return \DOMElement
     */
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

    protected function diffCells($oldCell, $newCell, $usingExtraRow = false)
    {
        $diffCell = $this->getNewCellNode($oldCell, $newCell);

        $oldContent = $oldCell ? $this->getInnerHtml($oldCell->getDomNode()) : '';
        $newContent = $newCell ? $this->getInnerHtml($newCell->getDomNode()) : '';

        $htmlDiff = new HtmlDiff(
            mb_convert_encoding($oldContent, 'UTF-8', 'HTML-ENTITIES'),
            mb_convert_encoding($newContent, 'UTF-8', 'HTML-ENTITIES'),
            $this->encoding,
            $this->specialCaseTags,
            $this->groupDiffs
        );
        $htmlDiff->setMatchThreshold($this->matchThreshold);
        $diff = $htmlDiff->build();

        $this->setInnerHtml($diffCell, $diff);

        if (null === $newCell) {
            $diffCell->setAttribute('class', trim($diffCell->getAttribute('class').' del'));
        }

        if (null === $oldCell) {
            $diffCell->setAttribute('class', trim($diffCell->getAttribute('class').' ins'));
        }

        if ($usingExtraRow) {
            $diffCell->setAttribute('class', trim($diffCell->getAttribute('class').' extra-row'));
        }

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
        $endInOld = new TablePosition(
            $oldRowCount - 1, count($this->oldTable->getRow($oldRowCount - 1)->getCells()) - 1
        );
        $endInNew = new TablePosition(
            $newRowCount - 1, count($this->newTable->getRow($newRowCount - 1)->getCells()) - 1
        );

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
                $this->findMatches(
                    $startInOld,
                    $match->getStartInOld(),
                    $startInNew,
                    $match->getStartInNew(),
                    $matches
                );
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

    /**
     * @param        $tableRow
     * @param        $currentColumn
     * @param        $targetColumn
     * @param        $currentCell
     * @param        $cellsWithMultipleRows
     * @param        $diffRow
     * @param        $currentIndex
     * @param string $diffType
     */
    protected function syncVirtualColumns(
        $tableRow,
        DiffRowPosition $position,
        &$cellsWithMultipleRows,
        $diffRow,
        $diffType,
        $usingExtraRow = false
    ) {
        $currentCell = $tableRow->getCell($position->getIndex($diffType));
        while ($position->isColumnLessThanOther($diffType) && $currentCell) {
            $diffCell = $diffType === 'new' ? $this->diffCells(null, $currentCell, $usingExtraRow) : $this->diffCells(
                $currentCell,
                null,
                $usingExtraRow
            );
            // Store cell in appliedRowSpans if spans multiple rows
            if ($diffCell->getAttribute('rowspan') > 1) {
                $cellsWithMultipleRows[$diffCell->getAttribute('rowspan')][] = $diffCell;
            }
            $diffRow->appendChild($diffCell);
            $position->incrementColumn($diffType, $currentCell->getColspan());
            $currentCell = $tableRow->getCell($position->incrementIndex($diffType));
        }
    }

    /**
     * @param null|TableCell  $oldCell
     * @param null|TableCell  $newCell
     * @param array           $cellsWithMultipleRows
     * @param \DOMElement     $diffRow
     * @param DiffRowPosition $position
     * @param bool            $usingExtraRow
     *
     * @return \DOMElement
     */
    protected function diffCellsAndIncrementCounters(
        $oldCell,
        $newCell,
        &$cellsWithMultipleRows,
        $diffRow,
        DiffRowPosition $position,
        $usingExtraRow = false
    ) {
        $diffCell = $this->diffCells($oldCell, $newCell, $usingExtraRow);
        // Store cell in appliedRowSpans if spans multiple rows
        if ($diffCell->getAttribute('rowspan') > 1) {
            $cellsWithMultipleRows[$diffCell->getAttribute('rowspan')][] = $diffCell;
        }
        $diffRow->appendChild($diffCell);

        if ($newCell !== null) {
            $position->incrementIndexInNew();
            $position->incrementColumnInNew($newCell->getColspan());
        }

        if ($oldCell !== null) {
            $position->incrementIndexInOld();
            $position->incrementColumnInOld($oldCell->getColspan());
        }

        return $diffCell;
    }

    /**
     * @param      $oldRow
     * @param      $newRow
     * @param      $appliedRowSpans
     * @param bool $forceExpansion
     */
    protected function diffAndAppendRows($oldRow, $newRow, &$appliedRowSpans, $forceExpansion = false)
    {
        list($rowDom, $extraRow) = $this->diffRows(
            $oldRow,
            $newRow,
            $appliedRowSpans,
            $forceExpansion
        );

        $this->diffTable->appendChild($rowDom);

        if ($extraRow) {
            $this->diffTable->appendChild($extraRow);
        }
    }

    protected function getMatchPercentage(TableRow $oldRow, TableRow $newRow)
    {
        $firstCellWeight = 3;
        $thresholdCount = 0;
        $totalCount = (min(count($newRow->getCells()), count($oldRow->getCells())) + $firstCellWeight) * 100;
        foreach ($newRow->getCells() as $newIndex => $newCell) {
            $oldCell = $oldRow->getCell($newIndex);

            if ($oldCell) {
                $percentage = null;
                similar_text($oldCell->getInnerHtml(), $newCell->getInnerHtml(), $percentage);

                if ($percentage > ($this->matchThreshold * 0.50)) {
                    $increment = $percentage;
                    if ($newIndex === 0 && $percentage > 95) {
                        $increment = $increment * $firstCellWeight;
                    }
                    $thresholdCount += $increment;
                }
            }
        }

        $matchPercentage = ($totalCount > 0) ? ($thresholdCount / $totalCount) : 0;

        return $matchPercentage;
    }
}
