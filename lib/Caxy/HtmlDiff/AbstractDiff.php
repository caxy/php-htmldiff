<?php

namespace Caxy\HtmlDiff;

abstract class AbstractDiff
{
    public static $defaultSpecialCaseTags = array('strong', 'b', 'i', 'big', 'small', 'u', 'sub', 'sup', 'strike', 's', 'p');
    public static $defaultSpecialCaseChars = array('.', ',', '(', ')', '\'');
    public static $defaultGroupDiffs = true;

    protected $content;
    protected $oldText;
    protected $newText;
    protected $oldWords = array();
    protected $newWords = array();
    protected $encoding;
    protected $specialCaseOpeningTags = array();
    protected $specialCaseClosingTags = array();
    protected $specialCaseTags;
    protected $specialCaseChars;
    protected $groupDiffs;

    public function __construct($oldText, $newText, $encoding = 'UTF-8', $specialCaseTags = null, $groupDiffs = null)
    {
        if ($specialCaseTags === null) {
            $specialCaseTags = static::$defaultSpecialCaseTags;
        }

        if ($groupDiffs === null) {
            $groupDiffs = static::$defaultGroupDiffs;
        }

        $this->oldText = $this->purifyHtml(trim($oldText));
        $this->newText = $this->purifyHtml(trim($newText));
        $this->encoding = $encoding;
        $this->content = '';
        $this->groupDiffs = $groupDiffs;
        $this->setSpecialCaseTags($specialCaseTags);
        $this->setSpecialCaseChars(static::$defaultSpecialCaseChars);
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
        $this->specialCaseTags = $tags;

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

    protected function getOpeningTag($tag)
    {
        return "/<".$tag."[^>]*/i";
    }

    protected function getClosingTag($tag)
    {
        return "</".$tag.">";
    }

    protected function getStringBetween($str, $start, $end)
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

    protected function purifyHtml($html, $tags = null)
    {
        if ( class_exists( 'Tidy' ) && false ) {
            $config = array( 'output-xhtml'   => true, 'indent' => false );
            $tidy = new tidy();
            $tidy->parseString( $html, $config, 'utf8' );
            $html = (string) $tidy;

            return $this->getStringBetween( $html, '<body>' );
        }

        return $html;
    }

    protected function splitInputsToWords()
    {
        $this->oldWords = $this->convertHtmlToListOfWords( $this->explode( $this->oldText ) );
        $this->newWords = $this->convertHtmlToListOfWords( $this->explode( $this->newText ) );
    }

    protected function isPartOfWord($text)
    {
        return ctype_alnum(str_replace($this->specialCaseChars, '', $text));
    }

    protected function convertHtmlToListOfWords($characterString)
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

    protected function isStartOfTag($val)
    {
        return $val == "<";
    }

    protected function isEndOfTag($val)
    {
        return $val == ">";
    }

    protected function isWhiteSpace($value)
    {
        return !preg_match( '[^\s]', $value );
    }

    protected function explode($value)
    {
        // as suggested by @onassar
        return preg_split( '//u', $value );
    }
}
