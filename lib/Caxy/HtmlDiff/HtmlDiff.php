<?php

namespace Caxy\HtmlDiff;

use Caxy\HtmlDiff\Table\TableDiff;

class HtmlDiff extends AbstractDiff
{
    protected $wordIndices;
    protected $newSpecialScript;
    protected $oldSpecialScript;
    protected $specialElements = array ('ol' => '[[REPLACE_ORDERED_LIST]]', 'ul' => '[[REPLACE_UNORDERED_LIST]]', 'sub' => '[[REPLACE_SUB_SCRIPT]]' , 'sup' => '[[REPLACE_SUPER_SCRIPT]]', 'dl' => '[[REPLACE_DEFINITION_LIST]]', 'table' => '[[REPLACE_TABLE]]');

    public function build()
    {
        $this->splitInputsToWords();
        $this->replaceSpecialScripts();
        $this->indexNewWords();
        
        $operations = $this->operations();
        foreach ($operations as $item) {
            $this->performOperation( $item );
        }

        return $this->content;
    }

    private function indexNewWords()
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

    private function replaceSpecialScripts()
    {
        $this->oldSpecialScript = $this->createSpecialPlaceholders($this->oldWords);
        $this->newSpecialScript = $this->createSpecialPlaceholders($this->newWords);
    }

    private function createSpecialPlaceholders(&$words)
    {
        $openSpecialScripts = 0;
        $specialScriptIndicies = array();
        $specialScriptStart = 0;
        $currentSpecialTag = null;
        foreach ($words as $index => $word) {
            $openSpecialTag = $this->isOpeningSpecialScript($word, $currentSpecialTag);
            if ($openSpecialTag) {
                if ($openSpecialScripts === 0) {
                    $specialScriptStart = $index;
                }
                $openSpecialScripts++;
                $currentSpecialTag = $openSpecialTag;
            } elseif($openSpecialScripts > 0 && $this->isClosingSpecialScript($word, $currentSpecialTag)) {
                $openSpecialScripts--;
                if($openSpecialScripts == 0){
                    $specialScriptIndicies[] = array ('start' => $specialScriptStart, 'length' => $index - $specialScriptStart + 1, 'tagType' => $currentSpecialTag);
                    $currentSpecialTag = null;
                }
            }
        }
        $specialScripts = array();
        $offset = 0;
        foreach ($specialScriptIndicies as $specialScriptIndex) {
            $start = $specialScriptIndex['start'] - $offset;
            $placeholderString = $this->specialElements[$specialScriptIndex['tagType']];
            $specialScripts[$start] = array_splice($words, $start, $specialScriptIndex['length'], $placeholderString);
            $offset += $specialScriptIndex['length'] - 1;
        }

        return $specialScripts;

    }

    private function isOpeningSpecialScript($item, $currentSpecialTag = null)
    {
        $tagsToMatch = $currentSpecialTag !== null ? array($currentSpecialTag => $this->specialElements[$currentSpecialTag]) : $this->specialElements;
        foreach ($tagsToMatch as $key => $value) {
            if (preg_match("#<".$key."[^>]*>\\s*#iU", $item)) {
                return $key;
            }
        }
        return false;
    }

    private function isClosingSpecialScript($item, $currentSpecialTag = null)
    {
        $tagsToMatch = $currentSpecialTag !== null ? array($currentSpecialTag => $this->specialElements[$currentSpecialTag]) : $this->specialElements;
        foreach ($tagsToMatch as $key => $value) {
            if (preg_match("#</".$key."[^>]*>\\s*#iU", $item))  {
                return $key;
            }
        }
        return false;
    }

    private function performOperation($operation)
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

    private function processReplaceOperation($operation)
    {
        $this->processDeleteOperation( $operation, "diffmod" );
        $this->processInsertOperation( $operation, "diffmod" );
    }

    private function processInsertOperation($operation, $cssClass)
    {
        $text = array();
        foreach ($this->newWords as $pos => $s) {
            $matchFound = false;
            if ($pos >= $operation->startInNew && $pos < $operation->endInNew) {
                foreach ($this->specialElements as $specialElement) {
                    if($s === $specialElement && isset($this->newSpecialScript[$pos]) && $matchFound === false) {
                        foreach ($this->newSpecialScript[$pos] as $word) {
                            $text[] = $word;
                        }
                        $matchFound = true;
                    }
                }
                if($matchFound === false){
                    $text[] = $s;
                }
            }
        }
        $this->insertTag( "ins", $cssClass, $text );
    }

    private function processDeleteOperation($operation, $cssClass)
    {
        $text = array();
        foreach ($this->oldWords as $pos => $s) {
            $matchFound = false;
            if ($pos >= $operation->startInOld && $pos < $operation->endInOld) {
                foreach ($this->specialElements as $specialElement) 
                if ($s === $specialElement && isset($this->oldSpecialScript[$pos]) && $matchFound === false) {
                    foreach ($this->oldSpecialScript[$pos] as $word) {
                        $text[] = $word;
                    }
                    $matchFound = true;
                } 
                if($matchFound === false){             
                    $text[] = $s;
                }
            }
        }
        $this->insertTag( "del", $cssClass, $text );
    }

    private function diffElements($oldText, $newText)
    {
        $pattern = '/(^<[^>]+>)|(<\/[^>]+>$)/i';
        $matches = array();
        $wrapStart = '';
        $wrapEnd = '';
        if (preg_match_all($pattern, $newText, $matches)) {
            $wrapStart = $matches[0][0];
            $wrapEnd = $matches[0][1];
        }
        $oldText = preg_replace($pattern, '', $oldText);
        $newText = preg_replace($pattern, '', $newText);

        $diff = new HtmlDiff($oldText, $newText, $this->encoding, $this->specialCaseTags, $this->groupDiffs);
        return $wrapStart . $diff->build() . $wrapEnd;
    }

    private function processEqualOperation($operation)
    {
        $result = array();
        foreach ($this->newWords as $pos => $s) {
            $matchFound = false;
            if ($pos >= $operation->startInNew && $pos < $operation->endInNew) {
                foreach ($this->specialElements as $specialElement) {
                    if ($s === $specialElement && isset($this->newSpecialScript[$pos]) && $matchFound === false) {
                        $oldText = implode("", $this->findMatchingScriptsInOld($operation, $pos));
                        $newText = implode("", $this->newSpecialScript[$pos]);
                        $result[] = $this->diffElements($oldText, $newText);
                        $matchFound = true;
                    } 
                }
                if($matchFound === false){
                    $result[] = $s;
                }
            }
        }
        $this->content .= implode( "", $result );
    }

    private function findMatchingScriptsInOld($operation, $posInNew)
    {
        $offset = $posInNew - $operation->startInNew;

        return $this->oldSpecialScript[$operation->startInOld + $offset];
    }

    private function insertTag($tag, $cssClass, &$words)
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

    private function checkCondition($word, $condition)
    {
        return $condition == 'tag' ? $this->isTag( $word ) : !$this->isTag( $word );
    }

    private function wrapText($text, $tagName, $cssClass)
    {
        return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tagName, $cssClass, $text );
    }

    private function extractConsecutiveWords(&$words, $condition)
    {
        $indexOfFirstTag = null;
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

    private function isTag($item)
    {
        return $this->isOpeningTag( $item ) || $this->isClosingTag( $item );
    }

    private function isOpeningTag($item)
    {
        return preg_match( "#<[^>]+>\\s*#iU", $item );
    }

    private function isClosingTag($item)
    {
        return preg_match( "#</[^>]+>\\s*#iU", $item );
    }

    private function operations()
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

    private function matchingBlocks()
    {
        $matchingBlocks = array();
        $this->findMatchingBlocks( 0, count( $this->oldWords ), 0, count( $this->newWords ), $matchingBlocks );

        return $matchingBlocks;
    }

    private function findMatchingBlocks($startInOld, $endInOld, $startInNew, $endInNew, &$matchingBlocks)
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

    private function stripTagAttributes($word)
    {
        $word = explode( ' ', trim( $word, '<>' ) );

        return '<' . $word[ 0 ] . '>';
    }

    private function findMatch($startInOld, $endInOld, $startInNew, $endInNew)
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
                if ($newMatchLength > $bestMatchSize) {
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
