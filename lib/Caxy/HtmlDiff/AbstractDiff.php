<?php

namespace Caxy\HtmlDiff;

/**
 * Class AbstractDiff
 * @package Caxy\HtmlDiff
 */
abstract class AbstractDiff
{
    /**
     * @var array
     */
    public static $defaultSpecialCaseTags = array('strong', 'b', 'i', 'big', 'small', 'u', 'sub', 'sup', 'strike', 's', 'p');
    /**
     * @var array
     */
    public static $defaultSpecialCaseChars = array('.', ',', '(', ')', '\'');
    /**
     * @var bool
     */
    public static $defaultGroupDiffs = true;

    /**
     * @var string
     */
    protected $content;
    /**
     * @var string
     */
    protected $oldText;
    /**
     * @var string
     */
    protected $newText;
    /**
     * @var array
     */
    protected $oldWords = array();
    /**
     * @var array
     */
    protected $newWords = array();
    /**
     * @var string
     */
    protected $encoding;
    /**
     * @var array
     */
    protected $specialCaseOpeningTags = array();
    /**
     * @var array
     */
    protected $specialCaseClosingTags = array();
    /**
     * @var array|null
     */
    protected $specialCaseTags;
    /**
     * @var array|null
     */
    protected $specialCaseChars;
    /**
     * @var bool|null
     */
    protected $groupDiffs;
    /**
     * @var int
     */
    protected $matchThreshold = 80;

    /**
     * AbstractDiff constructor.
     *
     * @param string     $oldText
     * @param string     $newText
     * @param string     $encoding
     * @param null|array $specialCaseTags
     * @param null|bool  $groupDiffs
     */
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

    /**
     * @return int
     */
    public function getMatchThreshold()
    {
        return $this->matchThreshold;
    }

    /**
     * @param int $matchThreshold
     *
     * @return AbstractDiff
     */
    public function setMatchThreshold($matchThreshold)
    {
        $this->matchThreshold = $matchThreshold;

        return $this;
    }

    /**
     * @param array $chars
     */
    public function setSpecialCaseChars(array $chars)
    {
        $this->specialCaseChars = $chars;
    }

    /**
     * @return array|null
     */
    public function getSpecialCaseChars()
    {
        return $this->specialCaseChars;
    }

    /**
     * @param string $char
     */
    public function addSpecialCaseChar($char)
    {
        if (!in_array($char, $this->specialCaseChars)) {
            $this->specialCaseChars[] = $char;
        }
    }

    /**
     * @param string $char
     */
    public function removeSpecialCaseChar($char)
    {
        $key = array_search($char, $this->specialCaseChars);
        if ($key !== false) {
            unset($this->specialCaseChars[$key]);
        }
    }

    /**
     * @param array $tags
     */
    public function setSpecialCaseTags(array $tags = array())
    {
        $this->specialCaseTags = $tags;

        foreach ($this->specialCaseTags as $tag) {
            $this->addSpecialCaseTag($tag);
        }
    }

    /**
     * @param string $tag
     */
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

    /**
     * @param string $tag
     */
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

    /**
     * @return array|null
     */
    public function getSpecialCaseTags()
    {
        return $this->specialCaseTags;
    }

    /**
     * @return string
     */
    public function getOldHtml()
    {
        return $this->oldText;
    }

    /**
     * @return string
     */
    public function getNewHtml()
    {
        return $this->newText;
    }

    /**
     * @return string
     */
    public function getDifference()
    {
        return $this->content;
    }

    /**
     * @param bool $boolean
     *
     * @return $this
     */
    public function setGroupDiffs($boolean)
    {
        $this->groupDiffs = $boolean;

        return $this;
    }

    /**
     * @return bool
     */
    public function isGroupDiffs()
    {
        return $this->groupDiffs;
    }

    /**
     * @param string $tag
     *
     * @return string
     */
    protected function getOpeningTag($tag)
    {
        return "/<".$tag."[^>]*/i";
    }

    /**
     * @param string $tag
     *
     * @return string
     */
    protected function getClosingTag($tag)
    {
        return "</".$tag.">";
    }

    /**
     * @param string $str
     * @param string $start
     * @param string $end
     *
     * @return string
     */
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

    /**
     * @param string $html
     *
     * @return string
     */
    protected function purifyHtml($html)
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

    /**
     * @param string $text
     *
     * @return bool
     */
    protected function isPartOfWord($text)
    {
        return ctype_alnum(str_replace($this->specialCaseChars, '', $text));
    }

    /**
     * @param array $characterString
     *
     * @return array
     */
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
                } elseif (preg_match("/\s/", $character)) {
                    if ($current_word !== '') {
                        $words[] = $current_word;
                    }
                    $current_word = preg_replace('/\s+/S', ' ', $character);
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
                    if ($current_word !== '') {
                        $words[] = $current_word;
                    }
                    $current_word = "<";
                    $mode = 'tag';
                } elseif ( preg_match( "/\s/", $character ) ) {
                    $current_word .= $character;
                    $current_word = preg_replace('/\s+/S', ' ', $current_word);
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

    /**
     * @param string $val
     *
     * @return bool
     */
    protected function isStartOfTag($val)
    {
        return $val == "<";
    }

    /**
     * @param string $val
     *
     * @return bool
     */
    protected function isEndOfTag($val)
    {
        return $val == ">";
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function isWhiteSpace($value)
    {
        return !preg_match( '[^\s]', $value );
    }

    /**
     * @param string $value
     *
     * @return array
     */
    protected function explode($value)
    {
        // as suggested by @onassar
        return preg_split( '//u', $value );
    }
}
