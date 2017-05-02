<?php

namespace Caxy\HtmlDiff;

use Caxy\HtmlDiff\Strategy\ListItemMatchStrategy;
use Sunra\PhpSimple\HtmlDomParser;

class ListDiffLines extends AbstractDiff
{
    const CLASS_LIST_ITEM_ADDED = 'normal new';
    const CLASS_LIST_ITEM_DELETED = 'removed';
    const CLASS_LIST_ITEM_CHANGED = 'replacement';
    const CLASS_LIST_ITEM_NONE = 'normal';

    protected static $listTypes = array('ul', 'ol', 'dl');

    /**
     * List of tags that should be included when retrieving
     * text from a single list item that will be used in
     * matching logic (and only in matching logic).
     *
     * @see getRelevantNodeText()
     *
     * @var array
     */
    protected static $listContentTags = array(
        'h1','h2','h3','h4','h5','pre','div','br','hr','code',
        'input','form','img','span','a','i','b','strong','em',
        'font','big','del','tt','sub','sup','strike',
    );

    /**
     * @var LcsService
     */
    protected $lcsService;

    /**
     * @param string              $oldText
     * @param string              $newText
     * @param HtmlDiffConfig|null $config
     *
     * @return ListDiffLines
     */
    public static function create($oldText, $newText, HtmlDiffConfig $config = null)
    {
        $diff = new self($oldText, $newText);

        if (null !== $config) {
            $diff->setConfig($config);
        }

        return $diff;
    }

    /**
     * {@inheritDoc}
     */
    public function build()
    {
        $this->prepare();

        if ($this->hasDiffCache() && $this->getDiffCache()->contains($this->oldText, $this->newText)) {
            $this->content = $this->getDiffCache()->fetch($this->oldText, $this->newText);

            return $this->content;
        }

        $matchStrategy = new ListItemMatchStrategy($this->config->getMatchThreshold());
        $this->lcsService = new LcsService($matchStrategy);

        return $this->listByLines($this->oldText, $this->newText);
    }

    /**
     * @param string $old
     * @param string $new
     *
     * @return string
     */
    protected function listByLines($old, $new)
    {
        /* @var $newDom \simple_html_dom */
        $newDom = HtmlDomParser::str_get_html($new);
        /* @var $oldDom \simple_html_dom */
        $oldDom = HtmlDomParser::str_get_html($old);

        $newListNode = $this->findListNode($newDom);
        $oldListNode = $this->findListNode($oldDom);

        $operations = $this->getListItemOperations($oldListNode, $newListNode);

        return $this->processOperations($operations, $oldListNode, $newListNode);
    }

    /**
     * @param \simple_html_dom|\simple_html_dom_node $dom
     *
     * @return \simple_html_dom_node[]|\simple_html_dom_node|null
     */
    protected function findListNode($dom)
    {
        return $dom->find(implode(', ', static::$listTypes), 0);
    }

    /**
     * @param \simple_html_dom_node $oldListNode
     * @param \simple_html_dom_node $newListNode
     *
     * @return array|Operation[]
     */
    protected function getListItemOperations($oldListNode, $newListNode)
    {
        // Prepare arrays of list item content to use in LCS algorithm
        $oldListText = $this->getListTextArray($oldListNode);
        $newListText = $this->getListTextArray($newListNode);

        $lcsMatches = $this->lcsService->longestCommonSubsequence($oldListText, $newListText);

        $oldLength = count($oldListText);
        $newLength = count($newListText);

        $operations = array();
        $currentLineInOld = 0;
        $currentLineInNew = 0;
        $lcsMatches[$oldLength + 1] = $newLength + 1;
        foreach ($lcsMatches as $matchInOld => $matchInNew) {
            // No matching line in new list
            if ($matchInNew === 0) {
                continue;
            }

            $nextLineInOld = $currentLineInOld + 1;
            $nextLineInNew = $currentLineInNew + 1;

            if ($matchInNew > $nextLineInNew && $matchInOld > $nextLineInOld) {
                // Change
                $operations[] = new Operation(
                    Operation::CHANGED,
                    $nextLineInOld,
                    $matchInOld - 1,
                    $nextLineInNew,
                    $matchInNew - 1
                );
            } elseif ($matchInNew > $nextLineInNew && $matchInOld === $nextLineInOld) {
                // Add items before this
                $operations[] = new Operation(
                    Operation::ADDED,
                    $currentLineInOld,
                    $currentLineInOld,
                    $nextLineInNew,
                    $matchInNew - 1
                );
            } elseif ($matchInNew === $nextLineInNew && $matchInOld > $nextLineInOld) {
                // Delete items before this
                $operations[] = new Operation(
                    Operation::DELETED,
                    $nextLineInOld,
                    $matchInOld - 1,
                    $currentLineInNew,
                    $currentLineInNew
                );
            }

            $currentLineInNew = $matchInNew;
            $currentLineInOld = $matchInOld;
        }

        return $operations;
    }

    /**
     * @param \simple_html_dom_node $listNode
     *
     * @return array
     */
    protected function getListTextArray($listNode)
    {
        $output = array();
        foreach ($listNode->children() as $listItem) {
            $output[] = $this->getRelevantNodeText($listItem);
        }

        return $output;
    }

    /**
     * @param \simple_html_dom_node $node
     *
     * @return string
     */
    protected function getRelevantNodeText($node)
    {
        if (!$node->hasChildNodes()) {
            return $node->innertext();
        }

        $output = '';
        foreach ($node->nodes as $child) {
            /* @var $child \simple_html_dom_node */
            if (!$child->hasChildNodes()) {
                $output .= $child->outertext();
            } elseif (in_array($child->nodeName(), static::$listContentTags, true)) {
                $output .= sprintf('<%1$s>%2$s</%1$s>', $child->nodeName(), $this->getRelevantNodeText($child));
            }
        }

        return $output;
    }

    /**
     * @param \simple_html_dom_node $li
     *
     * @return string
     */
    protected function deleteListItem($li)
    {
        $this->addClassToNode($li, self::CLASS_LIST_ITEM_DELETED);
        $li->innertext = sprintf('<del>%s</del>', $li->innertext);

        return $li->outertext;
    }

    /**
     * @param \simple_html_dom_node $li
     * @param bool                  $replacement
     *
     * @return string
     */
    protected function addListItem($li, $replacement = false)
    {
        $this->addClassToNode($li, $replacement ? self::CLASS_LIST_ITEM_CHANGED : self::CLASS_LIST_ITEM_ADDED);
        $li->innertext = sprintf('<ins>%s</ins>', $li->innertext);

        return $li->outertext;
    }

    /**
     * @param Operation[]|array     $operations
     * @param \simple_html_dom_node $oldListNode
     * @param \simple_html_dom_node $newListNode
     *
     * @return string
     */
    protected function processOperations($operations, $oldListNode, $newListNode)
    {
        $output = '';

        $indexInOld = 0;
        $indexInNew = 0;
        $lastOperation = null;

        foreach ($operations as $operation) {
            $replaced = false;
            while ($operation->startInOld > ($operation->action === Operation::ADDED ? $indexInOld : $indexInOld + 1)) {
                $li = $oldListNode->children($indexInOld);
                $matchingLi = null;
                if ($operation->startInNew > ($operation->action === Operation::DELETED ? $indexInNew
                        : $indexInNew + 1)
                ) {
                    $matchingLi = $newListNode->children($indexInNew);
                }
                if (null !== $matchingLi) {
                    $htmlDiff = HtmlDiff::create($li->innertext, $matchingLi->innertext, $this->config);
                    $li->innertext = $htmlDiff->build();
                    $indexInNew++;
                }
                $class = self::CLASS_LIST_ITEM_NONE;

                if ($lastOperation === Operation::DELETED && !$replaced) {
                    $class = self::CLASS_LIST_ITEM_CHANGED;
                    $replaced = true;
                }
                $li->setAttribute('class', trim($li->getAttribute('class').' '.$class));

                $output .= $li->outertext;
                $indexInOld++;
            }

            switch ($operation->action) {
                case Operation::ADDED:
                    for ($i = $operation->startInNew; $i <= $operation->endInNew; $i++) {
                        $output .= $this->addListItem($newListNode->children($i - 1));
                    }
                    $indexInNew = $operation->endInNew;
                    break;

                case Operation::DELETED:
                    for ($i = $operation->startInOld; $i <= $operation->endInOld; $i++) {
                        $output .= $this->deleteListItem($oldListNode->children($i - 1));
                    }
                    $indexInOld = $operation->endInOld;
                    break;

                case Operation::CHANGED:
                    $changeDelta = 0;
                    for ($i = $operation->startInOld; $i <= $operation->endInOld; $i++) {
                        $output .= $this->deleteListItem($oldListNode->children($i - 1));
                        $changeDelta--;
                    }
                    for ($i = $operation->startInNew; $i <= $operation->endInNew; $i++) {
                        $output .= $this->addListItem($newListNode->children($i - 1), $changeDelta < 0);
                        $changeDelta++;
                    }
                    $indexInOld = $operation->endInOld;
                    $indexInNew = $operation->endInNew;
                    break;
            }

            $lastOperation = $operation->action;
        }

        $oldCount = count($oldListNode->children());
        $newCount = count($newListNode->children());
        while ($indexInOld < $oldCount) {
            $li = $oldListNode->children($indexInOld);
            $matchingLi = null;
            if ($indexInNew < $newCount) {
                $matchingLi = $newListNode->children($indexInNew);
            }
            if (null !== $matchingLi) {
                $htmlDiff = HtmlDiff::create($li->innertext(), $matchingLi->innertext(), $this->config);
                $li->innertext = $htmlDiff->build();
                $indexInNew++;
            }
            $class = self::CLASS_LIST_ITEM_NONE;

            if ($lastOperation === Operation::DELETED) {
                $class = self::CLASS_LIST_ITEM_CHANGED;
            }
            $li->setAttribute('class', trim($li->getAttribute('class').' '.$class));

            $output .= $li->outertext;
            $indexInOld++;
        }

        $newListNode->innertext = $output;
        $newListNode->setAttribute('class', trim($newListNode->getAttribute('class').' diff-list'));

        return $newListNode->outertext;
    }

    /**
     * @param \simple_html_dom_node $node
     * @param string                $class
     */
    protected function addClassToNode($node, $class)
    {
        $node->setAttribute('class', trim(sprintf('%s %s', $node->getAttribute('class'), $class)));
    }
}
