<?php

namespace Caxy\HtmlDiff;

class HtmlDiff extends AbstractDiff
{
    protected $wordIndices;
    protected $oldTables;
    protected $newTables;
    protected $insertSpaceInReplace = false;
    protected $newIsolatedDiffTags;
    protected $oldIsolatedDiffTags;
    protected $isolatedDiffTags = array (
        'ol' => '[[REPLACE_ORDERED_LIST]]',
        'ul' => '[[REPLACE_UNORDERED_LIST]]',
        'sub' => '[[REPLACE_SUB_SCRIPT]]',
        'sup' => '[[REPLACE_SUPER_SCRIPT]]',
        'dl' => '[[REPLACE_DEFINITION_LIST]]',
        'table' => '[[REPLACE_TABLE]]',
        'a' => '[[REPLACE_A]]',
    );

    /**
     * @param  boolean  $boolean
     * @return HtmlDiff
     */
    public function setInsertSpaceInReplace($boolean)
    {
        $this->insertSpaceInReplace = $boolean;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getInsertSpaceInReplace()
    {
        return $this->insertSpaceInReplace;
    }

    public function build()
    {
        $this->splitInputsToWords();
        $this->replaceIsolatedDiffTags();
        $this->indexNewWords();

        $operations = $this->operations();
        foreach ($operations as $item) {
            $this->performOperation( $item );
        }

        return $this->content;
    }

    protected function indexNewWords()
    {
        $this->wordIndices = array();
        foreach ($this->newWords as $i => $word) {
            if ( $this->isTag( $word ) ) {
                $word = $this->stripTagAttributes( $word );
            }
            if ( isset( $this->wordIndices[ $word ] ) ) {
                $this->wordIndices[ $word ][] = $i;
            } else {
                $this->wordIndices[ $word ] = array( $i );
            }
        }
    }

    protected function replaceIsolatedDiffTags()
    {
        $this->oldIsolatedDiffTags = $this->createIsolatedDiffTagPlaceholders($this->oldWords);
        $this->newIsolatedDiffTags = $this->createIsolatedDiffTagPlaceholders($this->newWords);
    }

    protected function createIsolatedDiffTagPlaceholders(&$words)
    {
        $openIsolatedDiffTags = 0;
        $isolatedDiffTagIndicies = array();
        $isolatedDiffTagStart = 0;
        $currentIsolatedDiffTag = null;
        foreach ($words as $index => $word) {
            $openIsolatedDiffTag = $this->isOpeningIsolatedDiffTag($word, $currentIsolatedDiffTag);
            if ($openIsolatedDiffTag) {
                if ($openIsolatedDiffTags === 0) {
                    $isolatedDiffTagStart = $index;
                }
                $openIsolatedDiffTags++;
                $currentIsolatedDiffTag = $openIsolatedDiffTag;
            } elseif ($openIsolatedDiffTags > 0 && $this->isClosingIsolatedDiffTag($word, $currentIsolatedDiffTag)) {
                $openIsolatedDiffTags--;
                if ($openIsolatedDiffTags == 0) {
                    $isolatedDiffTagIndicies[] = array ('start' => $isolatedDiffTagStart, 'length' => $index - $isolatedDiffTagStart + 1, 'tagType' => $currentIsolatedDiffTag);
                    $currentIsolatedDiffTag = null;
                }
            }
        }
        $isolatedDiffTagScript = array();
        $offset = 0;
        foreach ($isolatedDiffTagIndicies as $isolatedDiffTagIndex) {
            $start = $isolatedDiffTagIndex['start'] - $offset;
            $placeholderString = $this->isolatedDiffTags[$isolatedDiffTagIndex['tagType']];
            $isolatedDiffTagScript[$start] = array_splice($words, $start, $isolatedDiffTagIndex['length'], $placeholderString);
            $offset += $isolatedDiffTagIndex['length'] - 1;
        }

        return $isolatedDiffTagScript;

    }

    private function replaceTables()
    {
        $this->oldTables = $this->createTablePlaceholders($this->oldWords);
        $this->newTables = $this->createTablePlaceholders($this->newWords);
    }

    private function createTablePlaceholders(&$words)
    {
        $openTables = 0;
        $tableIndices = array();
        $tableStart = 0;
        foreach ($words as $index => $word) {
            if ($this->isOpeningTable($word)) {
                if ($openTables === 0) {
                    $tableStart = $index;
                }
                $openTables++;
            } elseif ($openTables > 0 && $this->isClosingTable($word)) {
                $openTables--;
                if ($openTables === 0) {
                    $tableIndices[] = array('start' => $tableStart, 'length' => $index - $tableStart + 1);
                }
            }
        }

        $tables = array();
        $offset = 0;
        foreach ($tableIndices as $tableIndex) {
            $start = $tableIndex['start'] - $offset;
            $tables[$start] = array_splice($words, $start, $tableIndex['length'], '[[REPLACE_TABLE]]');
            $offset += $tableIndex['length'] - 1;
        }

        return $tables;
    }

    private function isOpeningTable($item)
    {
        return preg_match("#<table[^>]*>\\s*#iU", $item);
    }

    private function isClosingTable($item)
    {
        return preg_match("#</table[^>]*>\\s*#iU", $item);
    }

    protected function isOpeningIsolatedDiffTag($item, $currentIsolatedDiffTag = null)
    {
        $tagsToMatch = $currentIsolatedDiffTag !== null ? array($currentIsolatedDiffTag => $this->isolatedDiffTags[$currentIsolatedDiffTag]) : $this->isolatedDiffTags;
        foreach ($tagsToMatch as $key => $value) {
            if (preg_match("#<".$key."[^>]*>\\s*#iU", $item)) {
                return $key;
            }
        }

        return false;
    }

    protected function isClosingIsolatedDiffTag($item, $currentIsolatedDiffTag = null)
    {
        $tagsToMatch = $currentIsolatedDiffTag !== null ? array($currentIsolatedDiffTag => $this->isolatedDiffTags[$currentIsolatedDiffTag]) : $this->isolatedDiffTags;
        foreach ($tagsToMatch as $key => $value) {
            if (preg_match("#</".$key."[^>]*>\\s*#iU", $item)) {
                return $key;
            }
        }

        return false;
    }

    protected function performOperation($operation)
    {
        switch ($operation->action) {
            case 'equal' :
            $this->processEqualOperation( $operation );
            break;
            case 'delete' :
            $this->processDeleteOperation( $operation, "diffdel" );
            break;
            case 'insert' :
            $this->processInsertOperation( $operation, "diffins");
            break;
            case 'replace':
            $this->processReplaceOperation( $operation );
            break;
            default:
            break;
        }
    }

    protected function processReplaceOperation($operation)
    {
        $this->processDeleteOperation( $operation, "diffmod" );
        $this->processInsertOperation( $operation, "diffmod" );
    }

    protected function processInsertOperation($operation, $cssClass)
    {
        $text = array();
        foreach ($this->newWords as $pos => $s) {
            if ($pos >= $operation->startInNew && $pos < $operation->endInNew) {
                if (in_array($s, $this->isolatedDiffTags) && isset($this->newIsolatedDiffTags[$pos])) {
                    foreach ($this->newIsolatedDiffTags[$pos] as $word) {
                        $text[] = $word;
                    }
                } else {
                    $text[] = $s;
                }
            }
        }
        $this->insertTag( "ins", $cssClass, $text );
    }

    protected function processDeleteOperation($operation, $cssClass)
    {
        $text = array();
        foreach ($this->oldWords as $pos => $s) {
            if ($pos >= $operation->startInOld && $pos < $operation->endInOld) {
                if (in_array($s, $this->isolatedDiffTags) && isset($this->oldIsolatedDiffTags[$pos])) {
                    foreach ($this->oldIsolatedDiffTags[$pos] as $word) {
                        $text[] = $word;
                    }
                } else {
                    $text[] = $s;
                }
            }
        }
        $this->insertTag( "del", $cssClass, $text );
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
        $oldText = implode("", $this->findIsolatedDiffTagsInOld($operation, $pos));
        $newText = implode("", $this->newIsolatedDiffTags[$pos]);

        if ($this->isListPlaceholder($placeholder)) {
            return $this->diffList($oldText, $newText);
        } elseif ($this->isLinkPlaceholder($placeholder)) {
            return $this->diffLinks($oldText, $newText);
        }

        return $this->diffElements($oldText, $newText, $stripWrappingTags);
    }

    protected function diffElements($oldText, $newText, $stripWrappingTags = true)
    {
        $wrapStart = '';
        $wrapEnd = '';

        if ($stripWrappingTags) {
            $pattern = '/(^<[^>]+>)|(<\/[^>]+>$)/i';
            $matches = array();

            if (preg_match_all($pattern, $newText, $matches)) {
                $wrapStart = isset($matches[0][0]) ? $matches[0][0] : '';
                $wrapEnd = isset($matches[0][1]) ? $matches[0][1] : '';
            }
            $oldText = preg_replace($pattern, '', $oldText);
            $newText = preg_replace($pattern, '', $newText);
        }

        $diff = new HtmlDiff($oldText, $newText, $this->encoding, $this->specialCaseTags, $this->groupDiffs);

        return $wrapStart . $diff->build() . $wrapEnd;
    }

    protected function diffList($oldText, $newText)
    {
        $diff = new ListDiffNew($oldText, $newText, $this->encoding, $this->specialCaseTags, $this->groupDiffs);
        $diff->setMatchThreshold($this->matchThreshold);

        return $diff->build();
    }

    private function diffTables($oldText, $newText)
    {
        $diff = new TableDiff($oldText, $newText, $this->encoding, $this->specialCaseTags, $this->groupDiffs);
        return $diff->build();
    }

    /**
     * @param string $oldText
     * @param string $newText
     *
     * @return string
     */
    protected function diffLinks($oldText, $newText)
    {
        $oldHref = $this->getAttributeFromTag($oldText, 'href');
        $newHref = $this->getAttributeFromTag($newText, 'href');

        if ($oldHref != $newHref) {
            return sprintf(
                '%s%s',
                $this->wrapText($oldText, 'del', 'diffmod diff-href'),
                $this->wrapText($newText, 'ins', 'diffmod diff-href')
            );
        }

        return $this->diffElements($oldText, $newText);
    }

    protected function processEqualOperation($operation)
    {
        $result = array();
        foreach ($this->newWords as $pos => $s) {

            if ($pos >= $operation->startInNew && $pos < $operation->endInNew) {
                if (in_array($s, $this->isolatedDiffTags) && isset($this->newIsolatedDiffTags[$pos])) {

                    $result[] = $this->diffIsolatedPlaceholder($operation, $pos, $s);
                } else {
                    $result[] = $s;
                }
            }
        }
        $this->content .= implode( "", $result );
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
        if (preg_match(sprintf('/<a\s+[^>]*%s=([\'"])(.*)\1[^>]*>/i', $attribute), $text, $matches)) {
            return $matches[2];
        }

        return null;
    }

    protected function isListPlaceholder($text)
    {
        return $this->isPlaceholderType($text, array('ol', 'dl', 'ul'));
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
     * @param string       $text
     * @param array|string $types
     * @param bool         $strict
     *
     * @return bool
     */
    protected function isPlaceholderType($text, $types, $strict = true)
    {
        if (!is_array($types)) {
            $types = array($types);
        }

        $criteria = array();
        foreach ($types as $type) {
            if (isset($this->isolatedDiffTags[$type])) {
                $criteria[] = $this->isolatedDiffTags[$type];
            } else {
                $criteria[] = $type;
            }
        }

        return in_array($text, $criteria, $strict);
    }

    protected function findIsolatedDiffTagsInOld($operation, $posInNew)
    {
        $offset = $posInNew - $operation->startInNew;

        return $this->oldIsolatedDiffTags[$operation->startInOld + $offset];
    }

    protected function insertTag($tag, $cssClass, &$words)
    {
        while (true) {
            if ( count( $words ) == 0 ) {
                break;
            }

            $nonTags = $this->extractConsecutiveWords( $words, 'noTag' );

            $specialCaseTagInjection = '';
            $specialCaseTagInjectionIsBefore = false;

            if ( count( $nonTags ) != 0 ) {
                $text = $this->wrapText( implode( "", $nonTags ), $tag, $cssClass );
                $this->content .= $text;
            } else {
                $firstOrDefault = false;
                foreach ($this->specialCaseOpeningTags as $x) {
                    if ( preg_match( $x, $words[ 0 ] ) ) {
                        $firstOrDefault = $x;
                        break;
                    }
                }
                if ($firstOrDefault) {
                    $specialCaseTagInjection = '<ins class="mod">';
                    if ($tag == "del") {
                        unset( $words[ 0 ] );
                    }
                } elseif ( array_search( $words[ 0 ], $this->specialCaseClosingTags ) !== false ) {
                    $specialCaseTagInjection = "</ins>";
                    $specialCaseTagInjectionIsBefore = true;
                    if ($tag == "del") {
                        unset( $words[ 0 ] );
                    }
                }
            }
            if ( count( $words ) == 0 && count( $specialCaseTagInjection ) == 0 ) {
                break;
            }
            if ($specialCaseTagInjectionIsBefore) {
                $this->content .= $specialCaseTagInjection . implode( "", $this->extractConsecutiveWords( $words, 'tag' ) );
            } else {
                $workTag = $this->extractConsecutiveWords( $words, 'tag' );
                if ( isset( $workTag[ 0 ] ) && $this->isOpeningTag( $workTag[ 0 ] ) && !$this->isClosingTag( $workTag[ 0 ] ) ) {
                    if ( strpos( $workTag[ 0 ], 'class=' ) ) {
                        $workTag[ 0 ] = str_replace( 'class="', 'class="diffmod ', $workTag[ 0 ] );
                        $workTag[ 0 ] = str_replace( "class='", 'class="diffmod ', $workTag[ 0 ] );
                    } else {
                        $workTag[ 0 ] = str_replace( ">", ' class="diffmod">', $workTag[ 0 ] );
                    }
                }
                $this->content .= implode( "", $workTag ) . $specialCaseTagInjection;
            }
        }
    }

    protected function checkCondition($word, $condition)
    {
        return $condition == 'tag' ? $this->isTag( $word ) : !$this->isTag( $word );
    }

    protected function wrapText($text, $tagName, $cssClass)
    {
        return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tagName, $cssClass, $text );
    }

    protected function extractConsecutiveWords(&$words, $condition)
    {
        $indexOfFirstTag = null;
        $words = array_values($words);
        foreach ($words as $i => $word) {
            if ( !$this->checkCondition( $word, $condition ) ) {
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
                array_splice( $words, 0, $indexOfFirstTag );
            }

            return $items;
        } else {
            $items = array();
            foreach ($words as $pos => $s) {
                if ( $pos >= 0 && $pos <= count( $words ) ) {
                    $items[] = $s;
                }
            }
            array_splice( $words, 0, count( $words ) );

            return $items;
        }
    }

    protected function isTag($item)
    {
        return $this->isOpeningTag( $item ) || $this->isClosingTag( $item );
    }

    protected function isOpeningTag($item)
    {
        return preg_match( "#<[^>]+>\\s*#iU", $item );
    }

    protected function isClosingTag($item)
    {
        return preg_match( "#</[^>]+>\\s*#iU", $item );
    }

    protected function operations()
    {
        $positionInOld = 0;
        $positionInNew = 0;
        $operations = array();
        $matches = $this->matchingBlocks();
        $matches[] = new Match( count( $this->oldWords ), count( $this->newWords ), 0 );
        foreach ($matches as $i => $match) {
            $matchStartsAtCurrentPositionInOld = ( $positionInOld == $match->startInOld );
            $matchStartsAtCurrentPositionInNew = ( $positionInNew == $match->startInNew );
            $action = 'none';

            if ($matchStartsAtCurrentPositionInOld == false && $matchStartsAtCurrentPositionInNew == false) {
                $action = 'replace';
            } elseif ($matchStartsAtCurrentPositionInOld == true && $matchStartsAtCurrentPositionInNew == false) {
                $action = 'insert';
            } elseif ($matchStartsAtCurrentPositionInOld == false && $matchStartsAtCurrentPositionInNew == true) {
                $action = 'delete';
            } else { // This occurs if the first few words are the same in both versions
                $action = 'none';
            }
            if ($action != 'none') {
                $operations[] = new Operation( $action, $positionInOld, $match->startInOld, $positionInNew, $match->startInNew );
            }
            if ( count( $match ) != 0 ) {
                $operations[] = new Operation( 'equal', $match->startInOld, $match->endInOld(), $match->startInNew, $match->endInNew() );
            }
            $positionInOld = $match->endInOld();
            $positionInNew = $match->endInNew();
        }

        return $operations;
    }

    protected function matchingBlocks()
    {
        $matchingBlocks = array();
        $this->findMatchingBlocks( 0, count( $this->oldWords ), 0, count( $this->newWords ), $matchingBlocks );

        return $matchingBlocks;
    }

    protected function findMatchingBlocks($startInOld, $endInOld, $startInNew, $endInNew, &$matchingBlocks)
    {
        $match = $this->findMatch( $startInOld, $endInOld, $startInNew, $endInNew );
        if ($match !== null) {
            if ($startInOld < $match->startInOld && $startInNew < $match->startInNew) {
                $this->findMatchingBlocks( $startInOld, $match->startInOld, $startInNew, $match->startInNew, $matchingBlocks );
            }
            $matchingBlocks[] = $match;
            if ( $match->endInOld() < $endInOld && $match->endInNew() < $endInNew ) {
                $this->findMatchingBlocks( $match->endInOld(), $endInOld, $match->endInNew(), $endInNew, $matchingBlocks );
            }
        }
    }

    protected function stripTagAttributes($word)
    {
        $word = explode( ' ', trim( $word, '<>' ) );

        return '<' . $word[ 0 ] . '>';
    }

    protected function findMatch($startInOld, $endInOld, $startInNew, $endInNew)
    {
        $bestMatchInOld = $startInOld;
        $bestMatchInNew = $startInNew;
        $bestMatchSize = 0;
        $matchLengthAt = array();
        for ($indexInOld = $startInOld; $indexInOld < $endInOld; $indexInOld++) {
            $newMatchLengthAt = array();
            $index = $this->oldWords[ $indexInOld ];
            if ( $this->isTag( $index ) ) {
                $index = $this->stripTagAttributes( $index );
            }
            if ( !isset( $this->wordIndices[ $index ] ) ) {
                $matchLengthAt = $newMatchLengthAt;
                continue;
            }
            foreach ($this->wordIndices[ $index ] as $indexInNew) {
                if ($indexInNew < $startInNew) {
                    continue;
                }
                if ($indexInNew >= $endInNew) {
                    break;
                }
                $newMatchLength = ( isset( $matchLengthAt[ $indexInNew - 1 ] ) ? $matchLengthAt[ $indexInNew - 1 ] : 0 ) + 1;
                $newMatchLengthAt[ $indexInNew ] = $newMatchLength;
                if ($newMatchLength > $bestMatchSize ||
                    (
                        $this->isGroupDiffs() &&
                        $bestMatchSize > 0 &&
                        preg_match(
                            '/^\s+$/',
                            implode('', array_slice($this->oldWords, $bestMatchInOld, $bestMatchSize))
                        )
                    )
                ) {
                    $bestMatchInOld = $indexInOld - $newMatchLength + 1;
                    $bestMatchInNew = $indexInNew - $newMatchLength + 1;
                    $bestMatchSize = $newMatchLength;
                }
            }
            $matchLengthAt = $newMatchLengthAt;
        }

        // Skip match if none found or match consists only of whitespace
        if ($bestMatchSize != 0 &&
            (
                !$this->isGroupDiffs() ||
                !preg_match('/^\s+$/', implode('', array_slice($this->oldWords, $bestMatchInOld, $bestMatchSize)))
            )
        ) {
            return new Match($bestMatchInOld, $bestMatchInNew, $bestMatchSize);
        }

        return null;
    }
}
