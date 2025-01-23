<?php

namespace Caxy\HtmlDiff;

use Caxy\HtmlDiff\Strategy\ListItemMatchStrategy;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use LogicException;

class ListDiffLines extends AbstractDiff
{
    private const CLASS_LIST_ITEM_ADDED   = 'normal new';
    private const CLASS_LIST_ITEM_DELETED = 'removed';
    private const CLASS_LIST_ITEM_CHANGED = 'replacement';
    private const CLASS_LIST_ITEM_NONE    = 'normal';

    protected const LIST_TAG_NAMES = ['ul', 'ol', 'dl'];

    /**
     * List of tags that should be included when retrieving
     * text from a single list item that will be used in
     * matching logic (and only in matching logic).
     *
     * @see getRelevantNodeText()
     *
     * @var array
     */
    protected static $listContentTags = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'pre', 'div', 'br', 'hr', 'code',
        'input', 'form', 'img', 'span', 'a', 'i', 'b', 'strong', 'em',
        'font', 'big', 'del', 'tt', 'sub', 'sup', 'strike',
    ];

    /**
     * @var LcsService
     */
    protected $lcsService;

    /**
     * @var array<string, DOMElement>
     */
    private $nodeCache = [];

    /**
     * @param string              $oldText
     * @param string              $newText
     * @param HtmlDiffConfig|null $config
     *
     * @return ListDiffLines
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
     * {@inheritDoc}
     */
    public function build()
    {
        $this->prepare();

        if ($this->hasDiffCache() && $this->getDiffCache()->contains($this->oldText, $this->newText)) {
            $this->content = $this->getDiffCache()->fetch($this->oldText, $this->newText);

            return $this->content;
        }

        $this->lcsService = new LcsService(
            new ListItemMatchStrategy($this->stringUtil, $this->config->getMatchThreshold())
        );

        return $this->listByLines($this->oldText, $this->newText);
    }

    protected function listByLines(string $old, string $new) : string
    {
        $new = mb_encode_numericentity($new, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');
        $old = mb_encode_numericentity($old, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');

        $newDom = new DOMDocument();
        $newDom->loadHTML($new);

        $oldDom = new DOMDocument();
        $oldDom->loadHTML($old);

        $newListNode = $this->findListNode($newDom);
        $oldListNode = $this->findListNode($oldDom);

        $operations = $this->getListItemOperations($oldListNode, $newListNode);

        return $this->processOperations($operations, $oldListNode, $newListNode);
    }

    protected function findListNode(DOMDocument $dom) : DOMNode
    {
        $xPathQuery = '//' . implode('|//', self::LIST_TAG_NAMES);
        $xPath      = new DOMXPath($dom);
        $listNodes  = $xPath->query($xPathQuery);

        if ($listNodes->length > 0) {
            return $listNodes->item(0);
        }

        throw new LogicException('Unable to diff list; missing list node');
    }

    /**
     * @return Operation[]
     */
    protected function getListItemOperations(DOMElement $oldListNode, DOMElement $newListNode) : array
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
     * @return string[]
     */
    protected function getListTextArray(DOMElement $listNode) : array
    {
        $output = [];

        foreach ($listNode->childNodes as $listItem) {
            if ($listItem instanceof DOMText) {
                continue;
            }

            $output[] = $this->getRelevantNodeText($listItem);
        }

        return $output;
    }

    protected function getRelevantNodeText(DOMNode $node) : string
    {
        if ($node->hasChildNodes() === false) {
            return $node->textContent;
        }

        $output = '';

        /** @var DOMElement $child */
        foreach ($node->childNodes as $child) {
            if ($child->hasChildNodes() === false) {
                $output .= $this->getOuterText($child);

                continue;
            }

            if (in_array($child->tagName, static::$listContentTags, true) === true) {
                $output .= sprintf(
                    '<%1$s>%2$s</%1$s>',
                    $child->tagName,
                    $this->getRelevantNodeText($child)
                );
            }
        }

        return $output;
    }

    protected function deleteListItem(DOMElement $li) : string
    {
        $this->wrapNodeContent($li, 'del');

        $this->appendClassToNode($li, self::CLASS_LIST_ITEM_DELETED);

        return $this->getOuterText($li);
    }

    protected function addListItem(DOMElement $li, bool $replacement = false) : string
    {
        $this->wrapNodeContent($li, 'ins');

        $this->appendClassToNode(
            $li,
            $replacement === true ? self::CLASS_LIST_ITEM_CHANGED : self::CLASS_LIST_ITEM_ADDED
        );

        return $this->getOuterText($li);
    }

    /**
     * @param Operation[] $operations
     */
    protected function processOperations(array $operations, DOMElement $oldListNode, DOMElement $newListNode) : string
    {
        $output = '';

        $indexInOld = 0;
        $indexInNew = 0;
        $lastOperation = null;

        foreach ($operations as $operation) {
            $replaced = false;
            while ($operation->startInOld > ($operation->action === Operation::ADDED ? $indexInOld : $indexInOld + 1)) {
                $li = $this->getChildNodeByIndex($oldListNode, $indexInOld);
                $matchingLi = null;
                if ($operation->startInNew > ($operation->action === Operation::DELETED ? $indexInNew
                        : $indexInNew + 1)
                ) {
                    $matchingLi = $this->getChildNodeByIndex($newListNode, $indexInNew);
                }

                if (null !== $matchingLi) {
                    $htmlDiff = HtmlDiff::create(
                        $this->getInnerHtml($li),
                        $this->getInnerHtml($matchingLi),
                        $this->config
                    );

                    $this->setInnerHtml($li, $htmlDiff->build());

                    $indexInNew++;
                }

                $class = self::CLASS_LIST_ITEM_NONE;

                if ($lastOperation === Operation::DELETED && !$replaced) {
                    $class = self::CLASS_LIST_ITEM_CHANGED;
                    $replaced = true;
                }

                $this->appendClassToNode($li, $class);

                $output .= $this->getOuterText($li);
                $indexInOld++;
            }

            switch ($operation->action) {
                case Operation::ADDED:
                    for ($i = $operation->startInNew; $i <= $operation->endInNew; $i++) {
                        $output .= $this->addListItem(
                            $this->getChildNodeByIndex($newListNode, $i - 1)
                        );
                    }
                    $indexInNew = $operation->endInNew;
                    break;

                case Operation::DELETED:
                    for ($i = $operation->startInOld; $i <= $operation->endInOld; $i++) {
                        $output .= $this->deleteListItem(
                            $this->getChildNodeByIndex($oldListNode, $i - 1)
                        );
                    }
                    $indexInOld = $operation->endInOld;
                    break;

                case Operation::CHANGED:
                    $changeDelta = 0;
                    for ($i = $operation->startInOld; $i <= $operation->endInOld; $i++) {
                        $output .= $this->deleteListItem(
                            $this->getChildNodeByIndex($oldListNode, $i - 1)
                        );
                        $changeDelta--;
                    }
                    for ($i = $operation->startInNew; $i <= $operation->endInNew; $i++) {
                        $output .= $this->addListItem(
                            $this->getChildNodeByIndex($newListNode, $i - 1),
                            ($changeDelta < 0)
                        );
                        $changeDelta++;
                    }
                    $indexInOld = $operation->endInOld;
                    $indexInNew = $operation->endInNew;
                    break;
            }

            $lastOperation = $operation->action;
        }

        $oldCount = $this->childCountWithoutTextNode($oldListNode);
        $newCount = $this->childCountWithoutTextNode($newListNode);

        while ($indexInOld < $oldCount) {
            $li = $this->getChildNodeByIndex($oldListNode, $indexInOld);
            $matchingLi = null;
            if ($indexInNew < $newCount) {
                $matchingLi = $this->getChildNodeByIndex($newListNode, $indexInNew);
            }

            if (null !== $matchingLi) {
                $htmlDiff = HtmlDiff::create(
                    $this->getInnerHtml($li),
                    $this->getInnerHtml($matchingLi),
                    $this->config
                );

                $this->setInnerHtml($li, $htmlDiff->build());

                $indexInNew++;
            }

            $class = self::CLASS_LIST_ITEM_NONE;

            if ($lastOperation === Operation::DELETED) {
                $class = self::CLASS_LIST_ITEM_CHANGED;
            }

            $this->appendClassToNode($li, $class);

            $output .= $this->getOuterText($li);
            $indexInOld++;
        }

        $this->setInnerHtml($newListNode, $output);
        $this->appendClassToNode($newListNode, 'diff-list');

        return $newListNode->ownerDocument->saveHTML($newListNode);
    }

    protected function appendClassToNode(DOMElement $node, string $class)
    {
        $node->setAttribute(
            'class',
            trim(sprintf('%s %s', $node->getAttribute('class'), $class))
        );
    }

    private function getOuterText(DOMNode $node) : string
    {
        return $node->ownerDocument->saveHTML($node);
    }

    private function getInnerHtml(DOMNode $node) : string
    {
        $bufferDom = new DOMDocument('1.0', 'UTF-8');

        foreach($node->childNodes as $childNode)
        {
            $bufferDom->appendChild($bufferDom->importNode($childNode, true));
        }

        return trim($bufferDom->saveHTML());
    }

    private function setInnerHtml(DOMNode $node, string $html) : void
    {
        $html = sprintf('<%s>%s</%s>', 'body', $html, 'body');
        $html = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');

        $node->nodeValue = '';

        $bufferDom = new DOMDocument('1.0', 'UTF-8');
        $bufferDom->loadHTML($html);

        $bodyNode = $bufferDom->getElementsByTagName('body')->item(0);

        foreach ($bodyNode->childNodes as $childNode) {
            $node->appendChild($node->ownerDocument->importNode($childNode, true));
        }

        $this->nodeCache = [];
    }

    private function wrapNodeContent(DOMElement $node, string $tagName) : void
    {
        $childNodes = [];

        foreach ($node->childNodes as $childNode) {
            $childNodes[] = $childNode;
        }

        $wrapNode = $node->ownerDocument->createElement($tagName);

        $node->appendChild($wrapNode);

        foreach ($childNodes as $childNode) {
            $wrapNode->appendChild($childNode);
        }
    }

    private function childCountWithoutTextNode(DOMNode $node) : int
    {
        $counter = 0;

        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMText) {
                continue;
            }

            $counter++;
        }

        return $counter;
    }

    private function getChildNodeByIndex(DOMNode $node, int $index) : DOMElement
    {
        $nodeHash = spl_object_hash($node);

        if (isset($this->nodeCache[$nodeHash]) === true) {
            return $this->nodeCache[$nodeHash][$index];
        }

        $listCache[$nodeHash] = [];

        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMText === false) {
                $this->nodeCache[$nodeHash][] = $childNode;
            }
        }

        return $this->nodeCache[$nodeHash][$index];
    }
}
