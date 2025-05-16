<?php

namespace Caxy\HtmlDiff\Table;

use Caxy\HtmlDiff\AbstractDiff;
use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\HtmlDiffConfig;
use Caxy\HtmlDiff\Operation;

/**
 * Class TableDiff.
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
     * @var null|\DOMElement
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
     * @param string              $oldText
     * @param string              $newText
     * @param HtmlDiffConfig|null $config
     *
     * @return self
     */
    public static function create($oldText, $newText, ?HtmlDiffConfig $config = null)
    {
        $diff = new self($oldText, $newText);

        if (null !== $config) {
            $diff->setConfig($config);
        }

        return $diff;
    }

    /**
     * TableDiff constructor.
     *
     * @param string     $oldText
     * @param string     $newText
     * @param string     $encoding
     * @param array|null $specialCaseTags
     * @param bool|null  $groupDiffs
     */
    public function __construct(
        $oldText,
        $newText,
        $encoding = 'UTF-8',
        $specialCaseTags = null,
        $groupDiffs = null
    ) {
        parent::__construct($oldText, $newText, $encoding, $specialCaseTags, $groupDiffs);
    }

    /**
     * @return string
     */
    public function build()
    {
        $this->prepare();

        if ($this->hasDiffCache() && $this->getDiffCache()->contains($this->oldText, $this->newText)) {
            $this->content = $this->getDiffCache()->fetch($this->oldText, $this->newText);

            return $this->content;
        }

        $this->buildTableDoms();

        $this->diffDom = new \DOMDocument();

        $this->indexCellValues($this->newTable);

        $this->diffTableContent();

        if ($this->hasDiffCache()) {
            $this->getDiffCache()->save($this->oldText, $this->newText, $this->content);
        }

        return $this->content;
    }

    protected function diffTableContent()
    {
        $this->diffDom = new \DOMDocument();
        $this->diffTable = $this->newTable->cloneNode($this->diffDom);
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
                $percentage = $this->getMatchPercentage($oldRow, $newRow, $oldIndex, $newIndex);

                $oldMatchData[$oldIndex][$newIndex] = $percentage;
                $newMatchData[$newIndex][$oldIndex] = $percentage;
            }
        }

        $matches = $this->getRowMatches($oldMatchData, $newMatchData);
        $this->diffTableRowsWithMatches($oldRows, $newRows, $matches);

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
                $operations[] = new Operation(
                    $action,
                    $indexInOld,
                    $match->getStartInOld(),
                    $indexInNew,
                    $match->getStartInNew()
                );
            }

            $operations[] = new Operation(
                'equal',
                $match->getStartInOld(),
                $match->getEndInOld(),
                $match->getStartInNew(),
                $match->getEndInNew()
            );

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
                    $this->processDeleteOperation($operation, $oldRows, $appliedRowSpans);
                    break;

                case 'insert':
                    $this->processInsertOperation($operation, $newRows, $appliedRowSpans);
                    break;

                case 'replace':
                    $this->processReplaceOperation($operation, $oldRows, $newRows, $appliedRowSpans);
                    break;
            }
        }
    }

    /**
     * @param Operation $operation
     * @param array     $newRows
     * @param array     $appliedRowSpans
     * @param bool      $forceExpansion
     */
    protected function processInsertOperation(
        Operation $operation,
        $newRows,
        &$appliedRowSpans,
        $forceExpansion = false
    ) {
        $targetRows = array_slice($newRows, $operation->startInNew, $operation->endInNew - $operation->startInNew);
        foreach ($targetRows as $row) {
            $this->diffAndAppendRows(null, $row, $appliedRowSpans, $forceExpansion);
        }
    }

    /**
     * @param Operation $operation
     * @param array     $oldRows
     * @param array     $appliedRowSpans
     * @param bool      $forceExpansion
     */
    protected function processDeleteOperation(
        Operation $operation,
        $oldRows,
        &$appliedRowSpans,
        $forceExpansion = false
    ) {
        $targetRows = array_slice($oldRows, $operation->startInOld, $operation->endInOld - $operation->startInOld);
        foreach ($targetRows as $row) {
            $this->diffAndAppendRows($row, null, $appliedRowSpans, $forceExpansion);
        }
    }

    /**
     * @param Operation $operation
     * @param array     $oldRows
     * @param array     $newRows
     * @param array     $appliedRowSpans
     */
    protected function processEqualOperation(Operation $operation, $oldRows, $newRows, &$appliedRowSpans)
    {
        $targetOldRows = array_values(
            array_slice($oldRows, $operation->startInOld, $operation->endInOld - $operation->startInOld)
        );
        $targetNewRows = array_values(
            array_slice($newRows, $operation->startInNew, $operation->endInNew - $operation->startInNew)
        );

        foreach ($targetNewRows as $index => $newRow) {
            if (!isset($targetOldRows[$index])) {
                continue;
            }

            $this->diffAndAppendRows($targetOldRows[$index], $newRow, $appliedRowSpans);
        }
    }

    /**
     * @param Operation $operation
     * @param array     $oldRows
     * @param array     $newRows
     * @param array     $appliedRowSpans
     */
    protected function processReplaceOperation(Operation $operation, $oldRows, $newRows, &$appliedRowSpans)
    {
        $this->processDeleteOperation($operation, $oldRows, $appliedRowSpans, true);
        $this->processInsertOperation($operation, $newRows, $appliedRowSpans, true);
    }

    /**
     * @param array $oldMatchData
     * @param array $newMatchData
     *
     * @return array
     */
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

    /**
     * @param array $newMatchData
     * @param int   $startInOld
     * @param int   $endInOld
     * @param int   $startInNew
     * @param int   $endInNew
     * @param array $matches
     */
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
                $this->findRowMatches(
                    $newMatchData,
                    $match->getEndInOld(),
                    $endInOld,
                    $match->getEndInNew(),
                    $endInNew,
                    $matches
                );
            }
        }
    }

    /**
     * @param array $newMatchData
     * @param int   $startInOld
     * @param int   $endInOld
     * @param int   $startInNew
     * @param int   $endInNew
     *
     * @return RowMatch|null
     */
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
            return new RowMatch(
                $bestMatch['newIndex'],
                $bestMatch['oldIndex'],
                $bestMatch['newIndex'] + 1,
                $bestMatch['oldIndex'] + 1,
                $bestMatch['percentage']
            );
        }

        return;
    }

    /**
     * @param TableRow|null $oldRow
     * @param TableRow|null $newRow
     * @param array         $appliedRowSpans
     * @param bool          $forceExpansion
     *
     * @return array
     */
    protected function diffRows($oldRow, $newRow, array &$appliedRowSpans, $forceExpansion = false)
    {
        // create tr dom element
        $rowToClone = $newRow ?: $oldRow;
        /* @var $diffRow \DOMElement */
        $diffRow = $this->diffDom->importNode($rowToClone->getDomNode()->cloneNode(false), false);

        $oldCells = $oldRow ? $oldRow->getCells() : array();
        $newCells = $newRow ? $newRow->getCells() : array();

        $position = new DiffRowPosition();

        $extraRow = null;

        /* @var $expandCells \DOMElement[] */
        $expandCells = array();
        /* @var $cellsWithMultipleRows \DOMElement[] */
        $cellsWithMultipleRows = array();

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
                if ($row && $targetRow && (!$type === 'old' || isset($oldCells[$position->getIndexInOld()]))) {
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
                    /* @var $extraRow \DOMElement */
                    $extraRow = $this->diffDom->importNode($rowToClone->getDomNode()->cloneNode(false), false);
                }

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
                $rowspan = $expandCell->getAttribute('rowspan') ?: 1;
                $expandCell->setAttribute('rowspan', 1 + $rowspan);
            }
        }

        if ($extraRow || $forceExpansion) {
            foreach ($appliedRowSpans as $rowSpanCells) {
                /* @var $rowSpanCells \DOMElement[] */
                foreach ($rowSpanCells as $extendCell) {
                    $rowspan = $extendCell->getAttribute('rowspan') ?: 1;
                    $extendCell->setAttribute('rowspan', 1 + $rowspan);
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
    protected function getNewCellNode(?TableCell $oldCell = null, ?TableCell $newCell = null)
    {
        // If only one cell exists, use it
        if (!$oldCell || !$newCell) {
            $clone = $newCell
                ? $newCell->getDomNode()->cloneNode(false)
                : $oldCell->getDomNode()->cloneNode(false);
        } else {
            $oldNode = $oldCell->getDomNode();
            $newNode = $newCell->getDomNode();

            /* @var $clone \DOMElement */
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

    /**
     * @param TableCell|null $oldCell
     * @param TableCell|null $newCell
     * @param bool           $usingExtraRow
     *
     * @return \DOMElement
     */
    protected function diffCells($oldCell, $newCell, $usingExtraRow = false)
    {
        $diffCell = $this->getNewCellNode($oldCell, $newCell);

        $oldContent = $oldCell ? $this->getInnerHtml($oldCell->getDomNode()) : '';
        $newContent = $newCell ? $this->getInnerHtml($newCell->getDomNode()) : '';

        $htmlDiff = HtmlDiff::create(
            mb_convert_encoding($oldContent, 'UTF-8', 'HTML-ENTITIES'),
            mb_convert_encoding($newContent, 'UTF-8', 'HTML-ENTITIES'),
            $this->config
        );
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
        $this->oldTable = $this->parseTableStructure($this->oldText);
        $this->newTable = $this->parseTableStructure($this->newText);
    }

    /**
     * @param string $text
     *
     * @return \DOMDocument
     */
    protected function createDocumentWithHtml($text)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML(htmlspecialchars_decode(iconv('UTF-8', 'ISO-8859-1//IGNORE', htmlentities($text, ENT_COMPAT, 'UTF-8')), ENT_QUOTES));

        return $dom;
    }

    /**
     * @param string $text
     *
     * @return Table
     */
    protected function parseTableStructure($text)
    {
        $dom = $this->createDocumentWithHtml($text);

        $tableNode = $dom->getElementsByTagName('table')->item(0);

        $table = new Table($tableNode);

        $this->parseTable($table);

        return $table;
    }

    /**
     * @param Table         $table
     * @param \DOMNode|null $node
     */
    protected function parseTable(Table $table, ?\DOMNode $node = null)
    {
        if ($node === null) {
            $node = $table->getDomNode();
        }

        if (!$node->childNodes) {
            return;
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

    /**
     * @param TableRow $row
     */
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

    /**
     * @param \DOMNode $node
     *
     * @return string
     */
    protected function getInnerHtml($node)
    {
        $innerHtml = '';
        $children = $node->childNodes;

        foreach ($children as $child) {
            $innerHtml .= $this->htmlFromNode($child);
        }

        return $innerHtml;
    }

    /**
     * @param \DOMNode $node
     *
     * @return string
     */
    protected function htmlFromNode($node)
    {
        $domDocument = new \DOMDocument();
        $newNode = $domDocument->importNode($node, true);
        $domDocument->appendChild($newNode);

        return $domDocument->saveHTML();
    }

    /**
     * @param \DOMNode $node
     * @param string   $html
     */
    protected function setInnerHtml($node, $html)
    {
        // DOMDocument::loadHTML does not allow empty strings.
        if ($this->stringUtil->strlen(trim($html)) === 0) {
            $html = '<span class="empty"></span>';
        }

        $doc = $this->createDocumentWithHtml($html);
        $fragment = $node->ownerDocument->createDocumentFragment();
        $root = $doc->getElementsByTagName('body')->item(0);
        foreach ($root->childNodes as $child) {
            $fragment->appendChild($node->ownerDocument->importNode($child, true));
        }

        $node->appendChild($fragment);
    }

    /**
     * @param Table $table
     */
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

    /**
     * @param TableRow        $tableRow
     * @param DiffRowPosition $position
     * @param array           $cellsWithMultipleRows
     * @param \DOMNode        $diffRow
     * @param string          $diffType
     * @param bool            $usingExtraRow
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
     * @param TableRow|null $oldRow
     * @param TableRow|null $newRow
     * @param array         $appliedRowSpans
     * @param bool          $forceExpansion
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

    /**
     * @param TableRow $oldRow
     * @param TableRow $newRow
     * @param int      $oldIndex
     * @param int      $newIndex
     *
     * @return float|int
     */
    protected function getMatchPercentage(TableRow $oldRow, TableRow $newRow, $oldIndex, $newIndex)
    {
        $firstCellWeight = 1.5;
        $indexDeltaWeight = 0.25 * (abs($oldIndex - $newIndex));
        $thresholdCount = 0;
        $minCells = min(count($newRow->getCells()), count($oldRow->getCells()));
        $totalCount = ($minCells + $firstCellWeight + $indexDeltaWeight) * 100;
        foreach ($newRow->getCells() as $newIndex => $newCell) {
            $oldCell = $oldRow->getCell($newIndex);

            if ($oldCell) {
                $percentage = null;
                similar_text($oldCell->getInnerHtml(), $newCell->getInnerHtml(), $percentage);

                if ($percentage > ($this->config->getMatchThreshold() * 0.50)) {
                    $increment = $percentage;
                    if ($newIndex === 0 && $percentage > 95) {
                        $increment = $increment * $firstCellWeight;
                    }
                    $thresholdCount += $increment;
                }
            }
        }

        return ($totalCount > 0) ? ($thresholdCount / $totalCount) : 0;
    }
}
