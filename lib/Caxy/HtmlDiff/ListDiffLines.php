<?php

namespace Caxy\HtmlDiff;

use Sunra\PhpSimple\HtmlDomParser;

class ListDiffLines extends ListDiff
{
    const CLASS_LIST_ITEM_ADDED = 'normal new';
    const CLASS_LIST_ITEM_DELETED = 'removed';
    const CLASS_LIST_ITEM_CHANGED = 'replacement';
    const CLASS_LIST_ITEM_NONE = 'normal';

    protected static $containerTags = array('html', 'body', 'p', 'blockquote',
        'h1', 'h2', 'h3', 'h4', 'h5', 'pre', 'div', 'ul', 'ol', 'li',
        'table', 'tbody', 'tr', 'td', 'th', 'br', 'hr', 'code', 'dl',
        'dt', 'dd', 'input', 'form', 'img', 'span', 'a');
    protected static $styleTags = array('i', 'b', 'strong', 'em', 'font',
        'big', 'del', 'tt', 'sub', 'sup', 'strike');

    protected static $listContentTags = array(
        'h1', 'h2', 'h3', 'h4', 'h5', 'pre', 'div', 'br', 'hr', 'code', 'input',
        'form', 'img', 'span', 'a', 'i', 'b', 'strong', 'em', 'font', 'big',
        'del', 'tt', 'sub', 'sup', 'strike',
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

    public function build()
    {
        $threshold = $this->config->getMatchThreshold();

        $comparator = function($a, $b) use ($threshold) {
            $percentage = null;

            // Strip tags and check similarity
            $aStripped = strip_tags($a);
            $bStripped = strip_tags($b);
            similar_text($aStripped, $bStripped, $percentage);

            if ($percentage >= $threshold) {
                return true;
            }

            // Check w/o stripped tags
            similar_text($a, $b, $percentage);
            if ($percentage >= $threshold) {
                return true;
            }

            // Check common prefix/ suffix length
            $aCleaned = trim($aStripped);
            $bCleaned = trim($bStripped);
            if (strlen($aCleaned) === 0 || strlen($bCleaned) === 0) {
                $aCleaned = $a;
                $bCleaned = $b;
            }
            if (strlen($aCleaned) === 0 || strlen($bCleaned) === 0) {
                return false;
            }
            $prefixIndex = Preprocessor::diffCommonPrefix($aCleaned, $bCleaned);
            $suffixIndex = Preprocessor::diffCommonSuffix($aCleaned, $bCleaned);

            // Use shorter string, and see how much of it is leftover
            $len = min(strlen($aCleaned), strlen($bCleaned));
            $remaining = $len - ($prefixIndex + $suffixIndex);
            $strLengthPercent = $len / max(strlen($a), strlen($b));

            if ($remaining === 0 && $strLengthPercent > 0.1) {
                return true;
            }

            $percentRemaining = $remaining / $len;

            if ($strLengthPercent > 0.1 && $percentRemaining < 0.4) {
                return true;
            }

            return false;
        };
        $this->lcsService = new LcsService($comparator);

        return $this->listByLines($this->oldText, $this->newText);
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

        $j = $this->lcsService->longestCommonSubsequence($oldListText, $newListText);


        $m = count($oldListText);
        $n = count($newListText);

        $operations = [];
        $lineInOld = 0;
        $lineInNew = 0;
        $j[$m + 1] = $n + 1;
        foreach ($j as $i => $match) {
            if ($match !== 0) {
                if ($match > ($lineInNew + 1) && $i === ($lineInOld + 1)) {
                    // Add items before this
                    $operations[] = new Operation(Operation::ADDED, $lineInOld, $lineInOld, $lineInNew + 1, $match - 1);
                } elseif ($i > ($lineInOld + 1) && $match === ($lineInNew + 1)) {
                    // Delete items before this
                    $operations[] = new Operation(Operation::DELETED, $lineInOld + 1, $i - 1, $lineInNew, $lineInNew);
                } elseif ($match !== ($lineInNew + 1) && $i !== ($lineInOld + 1)) {
                    // Change
                    $operations[] = new Operation(Operation::CHANGED, $lineInOld + 1, $i - 1, $lineInNew + 1, $match - 1);
                }

                $lineInNew = $match;
                $lineInOld = $i;
            }
        }

        return $operations;
    }

    protected function getListTextArray($listNode)
    {
        $output = array();
        foreach ($listNode->children() as $listItem) {
            $output[] = $this->getRelevantNodeText($listItem);
        }

        return $output;
    }

    protected function getRelevantNodeText(\simple_html_dom_node $node)
    {
        if (!$node->hasChildNodes()) {
            return $node->innertext();
        }

        $output = '';
        foreach ($node->nodes as $child) {
            /* @var $child \simple_html_dom_node */
            if (!$child->hasChildNodes()) {
                $output .= $child->outertext();
            } elseif (in_array($child->nodeName(), static::$listContentTags)) {
                $output .= sprintf('<%1$s>%2$s</%1$s>', $child->nodeName(), $this->getRelevantNodeText($child));
            }
        }

        return $output;
    }

    /**
     * @param $li
     */
    protected function deleteListItem($li)
    {
        $li->setAttribute('class', trim($li->getAttribute('class').' '.self::CLASS_LIST_ITEM_DELETED));
        $li->innertext = sprintf('<del>%s</del>', $li->innertext);

        return $li->outertext;
    }

    /**
     * @param $li
     *
     * @return string
     */
    protected function addListItem($li, $replacement = false)
    {
        $li->setAttribute('class', trim($li->getAttribute('class').' '.($replacement ? self::CLASS_LIST_ITEM_CHANGED : self::CLASS_LIST_ITEM_ADDED)));
        $li->innertext = sprintf('<ins>%s</ins>', $li->innertext);

        return $li->outertext;
    }

    /**
     * @param $operations
     * @param $oldListNode
     * @param $newListNode
     *
     * @return mixed
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

        $replaced = false;
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
}
