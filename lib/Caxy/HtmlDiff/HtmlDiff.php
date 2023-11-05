<?php

namespace Caxy\HtmlDiff;

use Caxy\HtmlDiff\Table\TableDiff;

/**
 * Class HtmlDiff.
 */
class HtmlDiff extends AbstractDiff
{
    /**
     * @var array
     */
    protected $wordIndices;

    /**
     * @var array
     */
    protected $newIsolatedDiffTags;

    /**
     * @var array
     */
    protected $oldIsolatedDiffTags;

    /**
     * @param string              $oldText
     * @param string              $newText
     * @param HtmlDiffConfig|null $config
     *
     * @return self
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
     * @param $bool
     *
     * @return $this
     *
     * @deprecated since 0.1.0
     */
    public function setUseTableDiffing($bool)
    {
        $this->config->setUseTableDiffing($bool);

        return $this;
    }

    /**
     * @param bool $boolean
     *
     * @return HtmlDiff
     *
     * @deprecated since 0.1.0
     */
    public function setInsertSpaceInReplace($boolean)
    {
        $this->config->setInsertSpaceInReplace($boolean);

        return $this;
    }

    /**
     * @return bool
     *
     * @deprecated since 0.1.0
     */
    public function getInsertSpaceInReplace()
    {
        return $this->config->isInsertSpaceInReplace();
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

        // Pre-processing Optimizations

        // 1. Equality
        if ($this->oldText == $this->newText) {
            return $this->newText;
        }

        $this->splitInputsToWords();
        $this->replaceIsolatedDiffTags();
        $this->indexNewWords();

        $operations = $this->operations();

        foreach ($operations as $item) {
            $this->performOperation($item);
        }

        if ($this->hasDiffCache()) {
            $this->getDiffCache()->save($this->oldText, $this->newText, $this->content);
        }

        return $this->content;
    }

    protected function indexNewWords() : void
    {
        $this->wordIndices = [];

        foreach ($this->newWords as $i => $word) {
            if ($this->isTag($word) === true) {
                $word = $this->stripTagAttributes($word);
            }

            if (isset($this->wordIndices[$word]) === false) {
                $this->wordIndices[$word] = [];
            }

            $this->wordIndices[$word][] = $i;
        }
    }

    protected function replaceIsolatedDiffTags()
    {
        $this->oldIsolatedDiffTags = $this->createIsolatedDiffTagPlaceholders($this->oldWords);
        $this->newIsolatedDiffTags = $this->createIsolatedDiffTagPlaceholders($this->newWords);
    }

    /**
     * @param array $words
     *
     * @return array
     */
    protected function createIsolatedDiffTagPlaceholders(&$words)
    {
        $openIsolatedDiffTags = 0;
        $isolatedDiffTagIndices = array();
        $isolatedDiffTagStart = 0;
        $currentIsolatedDiffTag = null;
        foreach ($words as $index => $word) {
            $openIsolatedDiffTag = $this->isOpeningIsolatedDiffTag($word, $currentIsolatedDiffTag);
            if ($openIsolatedDiffTag) {
                if ($this->isSelfClosingTag($word) || $this->stringUtil->stripos($word, '<img') !== false) {
                    if ($openIsolatedDiffTags === 0) {
                        $isolatedDiffTagIndices[] = array(
                            'start' => $index,
                            'length' => 1,
                            'tagType' => $openIsolatedDiffTag,
                        );
                        $currentIsolatedDiffTag = null;
                    }
                } else {
                    if ($openIsolatedDiffTags === 0) {
                        $isolatedDiffTagStart = $index;
                    }
                    ++$openIsolatedDiffTags;
                    $currentIsolatedDiffTag = $openIsolatedDiffTag;
                }
            } elseif ($openIsolatedDiffTags > 0 && $this->isClosingIsolatedDiffTag($word, $currentIsolatedDiffTag)) {
                --$openIsolatedDiffTags;
                if ($openIsolatedDiffTags == 0) {
                    $isolatedDiffTagIndices[] = array('start' => $isolatedDiffTagStart, 'length' => $index - $isolatedDiffTagStart + 1, 'tagType' => $currentIsolatedDiffTag);
                    $currentIsolatedDiffTag = null;
                }
            }
        }
        $isolatedDiffTagScript = array();
        $offset = 0;
        foreach ($isolatedDiffTagIndices as $isolatedDiffTagIndex) {
            $start = $isolatedDiffTagIndex['start'] - $offset;
            $placeholderString = $this->config->getIsolatedDiffTagPlaceholder($isolatedDiffTagIndex['tagType']);
            $isolatedDiffTagScript[$start] = array_splice($words, $start, $isolatedDiffTagIndex['length'], $placeholderString);
            $offset += $isolatedDiffTagIndex['length'] - 1;
        }

        return $isolatedDiffTagScript;
    }

    /**
     * @param string      $item
     * @param null|string $currentIsolatedDiffTag
     *
     * @return false|string
     */
    protected function isOpeningIsolatedDiffTag($item, $currentIsolatedDiffTag = null)
    {
        $tagsToMatch = $currentIsolatedDiffTag !== null
            ? array($currentIsolatedDiffTag => $this->config->getIsolatedDiffTagPlaceholder($currentIsolatedDiffTag))
            : $this->config->getIsolatedDiffTags();
        $pattern = '#<%s(\s+[^>]*)?>#iUu';
        foreach ($tagsToMatch as $key => $value) {
            if (preg_match(sprintf($pattern, $key), $item)) {
                return $key;
            }
        }

        return false;
    }

    protected function isSelfClosingTag($text)
    {
        return (bool) preg_match('/<[^>]+\/\s*>/u', $text);
    }

    /**
     * @param string      $item
     * @param null|string $currentIsolatedDiffTag
     *
     * @return false|string
     */
    protected function isClosingIsolatedDiffTag($item, $currentIsolatedDiffTag = null)
    {
        $tagsToMatch = $currentIsolatedDiffTag !== null
            ? array($currentIsolatedDiffTag => $this->config->getIsolatedDiffTagPlaceholder($currentIsolatedDiffTag))
            : $this->config->getIsolatedDiffTags();
        $pattern = '#</%s(\s+[^>]*)?>#iUu';
        foreach ($tagsToMatch as $key => $value) {
            if (preg_match(sprintf($pattern, $key), $item)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * @param Operation $operation
     */
    protected function performOperation($operation)
    {
        switch ($operation->action) {
            case 'equal' :
                $this->processEqualOperation($operation);
                break;
            case 'delete' :
                $this->processDeleteOperation($operation, 'diffdel');
                break;
            case 'insert' :
                $this->processInsertOperation($operation, 'diffins');
                break;
            case 'replace':
                $this->processReplaceOperation($operation);
                break;
            default:
                break;
        }
    }

    /**
     * @param Operation $operation
     */
    protected function processReplaceOperation($operation)
    {
        $this->processDeleteOperation($operation, 'diffmod');
        $this->processInsertOperation($operation, 'diffmod');
    }

    /**
     * @param Operation $operation
     * @param string    $cssClass
     */
    protected function processInsertOperation($operation, $cssClass)
    {
        $text = array();
        foreach ($this->newWords as $pos => $s) {
            if ($pos >= $operation->startInNew && $pos < $operation->endInNew) {
                if ($this->config->isIsolatedDiffTagPlaceholder($s) && isset($this->newIsolatedDiffTags[$pos])) {
                    foreach ($this->newIsolatedDiffTags[$pos] as $word) {
                        $text[] = $word;
                    }
                } else {
                    $text[] = $s;
                }
            }
        }

        $this->insertTag('ins', $cssClass, $text);
    }

    /**
     * @param Operation $operation
     * @param string    $cssClass
     */
    protected function processDeleteOperation($operation, $cssClass)
    {
        $text = array();
        foreach ($this->oldWords as $pos => $s) {
            if ($pos >= $operation->startInOld && $pos < $operation->endInOld) {
                if ($this->config->isIsolatedDiffTagPlaceholder($s) && isset($this->oldIsolatedDiffTags[$pos])) {
                    foreach ($this->oldIsolatedDiffTags[$pos] as $word) {
                        $text[] = $word;
                    }
                } else {
                    $text[] = $s;
                }
            }
        }
        $this->insertTag('del', $cssClass, $text);
    }

    /**
     * @param Operation $operation
     * @param int       $pos
     * @param string    $placeholder
     * @param bool      $stripWrappingTags
     *
     * @return string
     */
    protected function diffIsolatedPlaceholder($operation, $pos, $placeholder, $stripWrappingTags = true)
    {
        $oldText = implode('', $this->findIsolatedDiffTagsInOld($operation, $pos));
        $newText = implode('', $this->newIsolatedDiffTags[$pos]);

        if ($this->isListPlaceholder($placeholder)) {
            return $this->diffList($oldText, $newText);
        } elseif ($this->config->isUseTableDiffing() && $this->isTablePlaceholder($placeholder)) {
            return $this->diffTables($oldText, $newText);
        } elseif ($this->isLinkPlaceholder($placeholder)) {
            return $this->diffElementsByAttribute($oldText, $newText, 'href', 'a');
        } elseif ($this->isImagePlaceholder($placeholder)) {
            return $this->diffElementsByAttribute($oldText, $newText, 'src', 'img');
        } elseif ($this->isPicturePlaceholder($placeholder)) {
           return $this->diffPicture($oldText, $newText);
        }

        return $this->diffElements($oldText, $newText, $stripWrappingTags);
    }

    /**
     * @param string $oldText
     * @param string $newText
     * @param bool   $stripWrappingTags
     *
     * @return string
     */
    protected function diffElements($oldText, $newText, $stripWrappingTags = true)
    {
        $wrapStart = '';
        $wrapEnd = '';

        if ($stripWrappingTags) {
            $pattern = '/(^<[^>]+>)|(<\/[^>]+>$)/iu';
            $matches = array();

            if (preg_match_all($pattern, $newText, $matches)) {
                $wrapStart = isset($matches[0][0]) ? $matches[0][0] : '';
                $wrapEnd = isset($matches[0][1]) ? $matches[0][1] : '';
            }
            $oldText = preg_replace($pattern, '', $oldText);
            $newText = preg_replace($pattern, '', $newText);
        }

        $diff = self::create($oldText, $newText, $this->config);

        return $wrapStart.$diff->build().$wrapEnd;
    }

    /**
     * @param string $oldText
     * @param string $newText
     *
     * @return string
     */
    protected function diffList($oldText, $newText)
    {
        $diff = ListDiffLines::create($oldText, $newText, $this->config);

        return $diff->build();
    }

    /**
     * @param string $oldText
     * @param string $newText
     *
     * @return string
     */
    protected function diffTables($oldText, $newText)
    {
        $diff = TableDiff::create($oldText, $newText, $this->config);

        return $diff->build();
    }

    /**
     * @param string $oldText
     * @param string $newText
     *
     * @return string
     */
    protected function diffPicture($oldText, $newText) {
        if ($oldText !== $newText) {
            return sprintf(
                '%s%s',
                $this->wrapText($oldText, 'del', 'diffmod'),
                $this->wrapText($newText, 'ins', 'diffmod')
            );
        }
        return $this->diffElements($oldText, $newText);
  }

    protected function diffElementsByAttribute($oldText, $newText, $attribute, $element)
    {
        $oldAttribute = $this->getAttributeFromTag($oldText, $attribute);
        $newAttribute = $this->getAttributeFromTag($newText, $attribute);

        if ($oldAttribute !== $newAttribute) {
            $diffClass = sprintf('diffmod diff%s diff%s', $element, $attribute);

            return sprintf(
                '%s%s',
                $this->wrapText($oldText, 'del', $diffClass),
                $this->wrapText($newText, 'ins', $diffClass)
            );
        }

        return $this->diffElements($oldText, $newText);
    }

    /**
     * @param Operation $operation
     */
    protected function processEqualOperation($operation)
    {
        $result = array();
        foreach ($this->newWords as $pos => $s) {
            if ($pos >= $operation->startInNew && $pos < $operation->endInNew) {
                if ($this->config->isIsolatedDiffTagPlaceholder($s) && isset($this->newIsolatedDiffTags[$pos])) {
                    $result[] = $this->diffIsolatedPlaceholder($operation, $pos, $s);
                } else {
                    $result[] = $s;
                }
            }
        }
        $this->content .= implode('', $result);
    }

    /**
     * @param string $text
     * @param string $attribute
     *
     * @return null|string
     */
    protected function getAttributeFromTag($text, $attribute)
    {
        $matches = array();
        if (preg_match(sprintf('/<[^>]*\b%s\s*=\s*([\'"])(.*)\1[^>]*>/iu', $attribute), $text, $matches)) {
            return htmlspecialchars_decode($matches[2]);
        }

        return;
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    protected function isListPlaceholder($text)
    {
        return $this->isPlaceholderType($text, ['ol', 'dl', 'ul']);
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    public function isLinkPlaceholder($text)
    {
        return $this->isPlaceholderType($text, 'a');
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    public function isImagePlaceholder($text)
    {
        return $this->isPlaceholderType($text, 'img');
    }

    public function isPicturePlaceholder($text)
    {
        return $this->isPlaceholderType($text, 'picture');
    }

    /**
     * @param string       $text
     * @param array|string $types
     *
     * @return bool
     */
    protected function isPlaceholderType($text, $types)
    {
        if (is_array($types) === false) {
            $types = [$types];
        }

        $criteria = [];

        foreach ($types as $type) {
            if ($this->config->isIsolatedDiffTag($type) === true) {
                $criteria[] = $this->config->getIsolatedDiffTagPlaceholder($type);
            } else {
                $criteria[] = $type;
            }
        }

        return in_array($text, $criteria, true);
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    protected function isTablePlaceholder($text)
    {
        return $this->isPlaceholderType($text, 'table');
    }

    /**
     * @param Operation $operation
     * @param int       $posInNew
     *
     * @return array
     */
    protected function findIsolatedDiffTagsInOld($operation, $posInNew)
    {
        $offset = $posInNew - $operation->startInNew;

        return $this->oldIsolatedDiffTags[$operation->startInOld + $offset];
    }

    /**
     * @param string $tag
     * @param string $cssClass
     * @param array  $words
     */
    protected function insertTag($tag, $cssClass, &$words)
    {
        while (count($words) > 0) {
            $nonTags = $this->extractConsecutiveWords($words, 'noTag');

            if (count($nonTags) > 0) {
                $this->content .= $this->wrapText(implode('', $nonTags), $tag, $cssClass);
            }

            if (count($words) === 0) {
                break;
            }

            $workTag = $this->extractConsecutiveWords($words, 'tag');

            if (
                isset($workTag[0]) === true &&
                $this->isOpeningTag($workTag[0]) === true &&
                $this->isClosingTag($workTag[0]) === false
            ) {
                if ($this->stringUtil->strpos($workTag[0], 'class=')) {
                    $workTag[0] = str_replace('class="', 'class="diffmod ', $workTag[0]);
                } else {
                    $isSelfClosing = $this->stringUtil->strpos($workTag[0], '/>') !== false;

                    if ($isSelfClosing === true) {
                        $workTag[0] = str_replace('/>', ' class="diffmod" />', $workTag[0]);
                    } else {
                        $workTag[0] = str_replace('>', ' class="diffmod">', $workTag[0]);
                    }
                }
            }

            $appendContent = implode('', $workTag);

            if (isset($workTag[0]) === true && $this->stringUtil->stripos($workTag[0], '<img') !== false) {
                $appendContent = $this->wrapText($appendContent, $tag, $cssClass);
            }

            $this->content .= $appendContent;
        }
    }

    /**
     * @param string $word
     * @param string $condition
     *
     * @return bool
     */
    protected function checkCondition($word, $condition)
    {
        return $condition == 'tag' ? $this->isTag($word) : !$this->isTag($word);
    }

    protected function wrapText(string $text, string $tagName, string $cssClass) : string
    {
        if (!$this->config->isSpaceMatching() && trim($text) === '') {
            return '';
        }

        return sprintf('<%1$s class="%2$s">%3$s</%1$s>', $tagName, $cssClass, $text);
    }

    /**
     * @param array  $words
     * @param string $condition
     *
     * @return array
     */
    protected function extractConsecutiveWords(&$words, $condition)
    {
        $indexOfFirstTag = null;
        $words = array_values($words);
        foreach ($words as $i => $word) {
            if (!$this->checkCondition($word, $condition)) {
                $indexOfFirstTag = $i;
                break;
            }
        }
        if ($indexOfFirstTag !== null) {
            $items = array();
            foreach ($words as $pos => $s) {
                if ($pos >= 0 && $pos < $indexOfFirstTag) {
                    $items[] = $s;
                }
            }
            if ($indexOfFirstTag > 0) {
                array_splice($words, 0, $indexOfFirstTag);
            }

            return $items;
        } else {
            $items = array();
            foreach ($words as $pos => $s) {
                if ($pos >= 0 && $pos <= count($words)) {
                    $items[] = $s;
                }
            }
            array_splice($words, 0, count($words));

            return $items;
        }
    }

    /**
     * @param string $item
     *
     * @return bool
     */
    protected function isTag($item)
    {
        return $this->isOpeningTag($item) || $this->isClosingTag($item);
    }

    protected function isOpeningTag($item) : bool
    {
        return preg_match('#<[^>]+>\\s*#iUu', $item) === 1;
    }

    protected function isClosingTag($item) : bool
    {
        return preg_match('#</[^>]+>\\s*#iUu', $item) === 1;
    }

    /**
     * @return Operation[]
     */
    protected function operations()
    {
        $positionInOld = 0;
        $positionInNew = 0;
        $operations = array();

        $matches   = $this->matchingBlocks();
        $matches[] = new MatchingBlock(count($this->oldWords), count($this->newWords), 0);

        foreach ($matches as $match) {
            $matchStartsAtCurrentPositionInOld = ($positionInOld === $match->startInOld);
            $matchStartsAtCurrentPositionInNew = ($positionInNew === $match->startInNew);

            if ($matchStartsAtCurrentPositionInOld === false && $matchStartsAtCurrentPositionInNew === false) {
                $action = 'replace';
            } elseif ($matchStartsAtCurrentPositionInOld === true && $matchStartsAtCurrentPositionInNew === false) {
                $action = 'insert';
            } elseif ($matchStartsAtCurrentPositionInOld === false && $matchStartsAtCurrentPositionInNew === true) {
                $action = 'delete';
            } else { // This occurs if the first few words are the same in both versions
                $action = 'none';
            }

            if ($action !== 'none') {
                $operations[] = new Operation($action, $positionInOld, $match->startInOld, $positionInNew, $match->startInNew);
            }

            if (count($match) !== 0) {
                $operations[] = new Operation('equal', $match->startInOld, $match->endInOld(), $match->startInNew, $match->endInNew());
            }

            $positionInOld = $match->endInOld();
            $positionInNew = $match->endInNew();
        }

        return $operations;
    }

    /**
     * @return MatchingBlock[]
     */
    protected function matchingBlocks()
    {
        $matchingBlocks = array();
        $this->findMatchingBlocks(0, count($this->oldWords), 0, count($this->newWords), $matchingBlocks);

        return $matchingBlocks;
    }

    /**
     * @param MatchingBlock[] $matchingBlocks
     */
    protected function findMatchingBlocks(int $startInOld, int $endInOld, int $startInNew, int $endInNew, array &$matchingBlocks) : void
    {
        $match = $this->findMatch($startInOld, $endInOld, $startInNew, $endInNew);

        if ($match === null) {
            return;
        }

        if ($startInOld < $match->startInOld && $startInNew < $match->startInNew) {
            $this->findMatchingBlocks($startInOld, $match->startInOld, $startInNew, $match->startInNew, $matchingBlocks);
        }

        $matchingBlocks[] = $match;

        if ($match->endInOld() < $endInOld && $match->endInNew() < $endInNew) {
            $this->findMatchingBlocks($match->endInOld(), $endInOld, $match->endInNew(), $endInNew, $matchingBlocks);
        }
    }

    /**
     * @param string $word
     *
     * @return string
     */
    protected function stripTagAttributes($word)
    {
        $space = $this->stringUtil->strpos($word, ' ', 1);

        if ($space > 0) {
            return '<' . $this->stringUtil->substr($word, 1, $space) . '>';
        }

        return trim($word, '<>');
    }

    protected function findMatch(int $startInOld, int $endInOld, int $startInNew, int $endInNew) : ?MatchingBlock
    {
        $groupDiffs     = $this->isGroupDiffs();
        $bestMatchInOld = $startInOld;
        $bestMatchInNew = $startInNew;
        $bestMatchSize = 0;
        $matchLengthAt = [];

        for ($indexInOld = $startInOld; $indexInOld < $endInOld; ++$indexInOld) {
            $newMatchLengthAt = [];

            $index = $this->oldWords[ $indexInOld ];

            if ($this->isTag($index) === true) {
                $index = $this->stripTagAttributes($index);
            }

            if (isset($this->wordIndices[$index]) === false) {
                $matchLengthAt = $newMatchLengthAt;

                continue;
            }

            foreach ($this->wordIndices[$index] as $indexInNew) {
                if ($indexInNew < $startInNew) {
                    continue;
                }

                if ($indexInNew >= $endInNew) {
                    break;
                }

                $newMatchLength =
                    (isset($matchLengthAt[$indexInNew - 1]) === true ? ($matchLengthAt[$indexInNew - 1] + 1) : 1);

                $newMatchLengthAt[$indexInNew] = $newMatchLength;

                if ($newMatchLength > $bestMatchSize ||
                    (
                        $groupDiffs === true &&
                        $bestMatchSize > 0 &&
                        $this->oldTextIsOnlyWhitespace($bestMatchInOld, $bestMatchSize) === true
                    )
                ) {
                    $bestMatchInOld = $indexInOld - $newMatchLength + 1;
                    $bestMatchInNew = $indexInNew - $newMatchLength + 1;
                    $bestMatchSize  = $newMatchLength;
                }
            }

            $matchLengthAt = $newMatchLengthAt;
        }

        // Skip match if none found or match consists only of whitespace
        if ($bestMatchSize !== 0 &&
            (
                $groupDiffs === false ||
                $this->oldTextIsOnlyWhitespace($bestMatchInOld, $bestMatchSize) === false
            )
        ) {
            return new MatchingBlock($bestMatchInOld, $bestMatchInNew, $bestMatchSize);
        }

        return null;
    }

    protected function oldTextIsOnlyWhitespace(int $startingAtWord, int $wordCount) : bool
    {
        $isWhitespace = true;

        // oldTextIsWhitespace get called consecutively by findMatch, with the same parameters.
        // by caching the previous result, we speed up the algorithm by more then 50%
        static $lastStartingWordOffset = null;
        static $lastWordCount          = null;
        static $cache                  = null;

        if ($this->resetCache === true) {
            $cache = null;

            $this->resetCache = false;
        }

        if (
            $cache !== null &&
            $lastWordCount === $wordCount &&
            $lastStartingWordOffset === $startingAtWord
        ) { // Hit
            return $cache;
        } // Miss

        for ($index = $startingAtWord; $index < ($startingAtWord + $wordCount); $index++) {
            // Assigning the oldWord to a variable is slightly faster then searching by reference twice
            // in the if statement
            $oldWord = $this->oldWords[$index];

            if ($oldWord !== '' && trim($oldWord) !== '') {
                $isWhitespace = false;

                break;
            }
        }

        $lastWordCount          = $wordCount;
        $lastStartingWordOffset = $startingAtWord;

        $cache = $isWhitespace;

        return $cache;
    }
}
