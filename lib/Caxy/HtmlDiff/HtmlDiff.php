<?php

namespace Caxy\HtmlDiff;

class HtmlDiff
{
    private $content;
    private $oldText;
    private $newText;
    private $oldWords = array();
    private $newWords = array();
    private $wordIndices;
    private $encoding;
    private $specialCaseOpeningTags = array();
    private $specialCaseClosingTags = array();
    private $specialCaseTags = array('strong', 'b', 'i', 'big', 'small', 'u', 'sub', 'sup', 'strike', 's', 'p');
    private $specialCaseChars = array('.', ',', '(', ')', '\'');
    private $groupDiffs = true;

    public function __construct($oldText, $newText, $encoding = 'UTF-8', $specialCaseTags = array(), $groupDiffs = true)
    {
        $this->oldText = $this->purifyHtml(trim($oldText));
        $this->newText = $this->purifyHtml(trim($newText));
        $this->encoding = $encoding;
        $this->content = '';
        $this->groupDiffs = $groupDiffs;

        $this->setSpecialCaseTags($specialCaseTags);
    }
    
    public function setSpecialCaseChars(array $chars)
    {
        $this->specialCaseChars = $chars;
    }
    
    public function getSpecialCaseChars()
    {
        return $this->specialCaseChars;
    }
    
    public function addSpecialCaseChar($char)
    {
        if (!in_array($char, $this->specialCaseChars)) {
            $this->specialCaseChars[] = $char;
        }
    }
    
    public function removeSpecialCaseChar($char)
    {
        $key = array_search($char, $this->specialCaseChars);
        if ($key !== false) {
            unset($this->specialCaseChars[$key]);
        }
    }

    public function setSpecialCaseTags(array $tags = array())
    {
        if (!empty($tags)) {
            $this->specialCaseTags = $tags;
        }

        foreach ($this->specialCaseTags as $tag) {
            $this->addSpecialCaseTag($tag);
        }
    }

    public function addSpecialCaseTag($tag)
    {
        if (!in_array($tag, $this->specialCaseTags)) {
            $this->specialCaseTags[] = $tag;
        }

        $opening = $this->getOpeningTag($tag);
        $closing = $this->getClosingTag($tag);

        if (!in_array($opening, $this->specialCaseOpeningTags)) {
            $this->specialCaseOpeningTags[] = $opening;
        }
        if (!in_array($closing, $this->specialCaseClosingTags)) {
            $this->specialCaseClosingTags[] = $closing;
        }
    }

    public function removeSpecialCaseTag($tag)
    {
        if (($key = array_search($tag, $this->specialCaseTags)) !== false) {
            unset($this->specialCaseTags[$key]);

            $opening = $this->getOpeningTag($tag);
            $closing = $this->getClosingTag($tag);

            if (($key = array_search($opening, $this->specialCaseOpeningTags)) !== false) {
                unset($this->specialCaseOpeningTags[$key]);
            }
            if (($key = array_search($closing, $this->specialCaseClosingTags)) !== false) {
                unset($this->specialCaseClosingTags[$key]);
            }
        }
    }

    public function getSpecialCaseTags()
    {
        return $this->specialCaseTags;
    }

    public function getOldHtml()
    {
        return $this->oldText;
    }

    public function getNewHtml()
    {
        return $this->newText;
    }

    public function getDifference()
    {
        return $this->content;
    }
    
    public function setGroupDiffs($boolean)
    {
        $this->groupDiffs = $boolean;
    }
    
    public function isGroupDiffs()
    {
        return $this->groupDiffs;
    }

    private function getOpeningTag($tag)
    {
        return "/<".$tag."[^>]*/i";
    }

    private function getClosingTag($tag)
    {
        return "</".$tag.">";
    }

    private function getStringBetween($str, $start, $end)
    {
        $expStr = explode( $start, $str, 2 );
        if ( count( $expStr ) > 1 ) {
            $expStr = explode( $end, $expStr[ 1 ] );
            if ( count( $expStr ) > 1 ) {
                array_pop( $expStr );

                return implode( $end, $expStr );
            }
        }

        return '';
    }

    private function purifyHtml($html, $tags = null)
    {
        if ( class_exists( 'Tidy' ) && false ) {
            $config = array( 'output-xhtml'   => true, 'indent' => false );
            $tidy = new tidy;
            $tidy->parseString( $html, $config, 'utf8' );
            $html = (string) $tidy;

            return $this->getStringBetween( $html, '<body>' );
        }

        return $html;
    }

    public function build()
    {
        $this->splitInputsToWords();
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

    private function splitInputsToWords()
    {
        $this->oldWords = $this->convertHtmlToListOfWords( $this->explode( $this->oldText ) );
        $this->newWords = $this->convertHtmlToListOfWords( $this->explode( $this->newText ) );
    }
    
    private function isPartOfWord($text)
    {
        return ctype_alnum(str_replace($this->specialCaseChars, '', $text));
    }

    private function convertHtmlToListOfWords($characterString)
    {
        $mode = 'character';
        $current_word = '';
        $words = array();
        foreach ($characterString as $i => $character) {
            switch ($mode) {
                case 'character':
                if ( $this->isStartOfTag( $character ) ) {
                    if ($current_word != '') {
                        $words[] = $current_word;
                    }
                    $current_word = "<";
                    $mode = 'tag';
                } elseif ( preg_match( "[^\s]", $character ) > 0 ) {
                    if ($current_word != '') {
                        $words[] = $current_word;
                    }
                    $current_word = $character;
                    $mode = 'whitespace';
                } else {
                    if (
                        (ctype_alnum($character) && (strlen($current_word) == 0 || $this->isPartOfWord($current_word))) ||
                        (in_array($character, $this->specialCaseChars) && isset($characterString[$i+1]) && $this->isPartOfWord($characterString[$i+1]))
                    ) {
                        $current_word .= $character;
                    } else {
                        $words[] = $current_word;
                        $current_word = $character;
                    }
                }
                break;
                case 'tag' :
                if ( $this->isEndOfTag( $character ) ) {
                    $current_word .= ">";
                    $words[] = $current_word;
                    $current_word = "";

                    if ( !preg_match('[^\s]', $character ) ) {
                        $mode = 'whitespace';
                    } else {
                        $mode = 'character';
                    }
                } else {
                    $current_word .= $character;
                }
                break;
                case 'whitespace':
                if ( $this->isStartOfTag( $character ) ) {
                    if ($current_word != '') {
                        $words[] = $current_word;
                    }
                    $current_word = "<";
                    $mode = 'tag';
                } elseif ( preg_match( "[^\s]", $character ) ) {
                    $current_word .= $character;
                } else {
                    if ($current_word != '') {
                        $words[] = $current_word;
                    }
                    $current_word = $character;
                    $mode = 'character';
                }
                break;
                default:
                break;
            }
        }
        if ($current_word != '') {
            $words[] = $current_word;
        }

        return $words;
    }

    private function isStartOfTag($val)
    {
        return $val == "<";
    }

    private function isEndOfTag($val)
    {
        return $val == ">";
    }

    private function isWhiteSpace($value)
    {
        return !preg_match( '[^\s]', $value );
    }

    private function explode($value)
    {
        // as suggested by @onassar
        return preg_split( '//u', $value );
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
            if ($pos >= $operation->startInNew && $pos < $operation->endInNew) {
                $text[] = $s;
            }
        }
        $this->insertTag( "ins", $cssClass, $text );
    }

    private function processDeleteOperation($operation, $cssClass)
    {
        $text = array();
        foreach ($this->oldWords as $pos => $s) {
            if ($pos >= $operation->startInOld && $pos < $operation->endInOld) {
                $text[] = $s;
            }
        }
        $this->insertTag( "del", $cssClass, $text );
    }

    private function processEqualOperation($operation)
    {
        $result = array();
        foreach ($this->newWords as $pos => $s) {
            if ($pos >= $operation->startInNew && $pos < $operation->endInNew) {
                $result[] = $s;
            }
        }
        $this->content .= implode( "", $result );
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
