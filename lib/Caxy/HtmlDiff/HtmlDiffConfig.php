<?php

namespace Caxy\HtmlDiff;

/**
 * Class HtmlDiffConfig.
 */
class HtmlDiffConfig
{
    /**
     * @var array
     */
    protected $specialCaseTags = array('strong', 'b', 'i', 'big', 'small', 'u', 'sub', 'sup', 'strike', 's', 'p');

    /**
     * @var array
     */
    protected $specialCaseChars = array('.', ',', '(', ')', '\'');

    /**
     * @var bool
     */
    protected $groupDiffs = true;

    /**
     * @var bool
     */
    protected $insertSpaceInReplace = false;

    /**
     * @var string
     */
    protected $encoding = 'UTF-8';

    /**
     * @var array
     */
    protected $isolatedDiffTags = array(
        'ol' => '[[REPLACE_ORDERED_LIST]]',
        'ul' => '[[REPLACE_UNORDERED_LIST]]',
        'sub' => '[[REPLACE_SUB_SCRIPT]]',
        'sup' => '[[REPLACE_SUPER_SCRIPT]]',
        'dl' => '[[REPLACE_DEFINITION_LIST]]',
        'table' => '[[REPLACE_TABLE]]',
        'strong' => '[[REPLACE_STRONG]]',
        'b' => '[[REPLACE_STRONG]]',
        'em' => '[[REPLACE_EM]]',
        'i' => '[[REPLACE_EM]]',
        'a' => '[[REPLACE_A]]',
        'img' => '[[REPLACE_IMG]]',
    );

    /**
     * @var int
     */
    protected $matchThreshold = 80;
    /**
     * @var array
     */
    protected $specialCaseOpeningTags = array();
    /**
     * @var array
     */
    protected $specialCaseClosingTags = array();

    /**
     * @var bool
     */
    protected $useTableDiffing = true;

    /**
     * @var null|\Doctrine\Common\Cache\Cache
     */
    protected $cacheProvider;

    /**
     * @var null|string
     */
    protected $purifierCacheLocation = null;

    /**
     * @return HtmlDiffConfig
     */
    public static function create()
    {
        return new self();
    }

    /**
     * HtmlDiffConfig constructor.
     */
    public function __construct()
    {
        $this->setSpecialCaseTags($this->specialCaseTags);
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
     *
     * @return $this
     */
    public function addSpecialCaseChar($char)
    {
        if (!in_array($char, $this->specialCaseChars)) {
            $this->specialCaseChars[] = $char;
        }

        return $this;
    }

    /**
     * @param string $char
     *
     * @return $this
     */
    public function removeSpecialCaseChar($char)
    {
        $key = array_search($char, $this->specialCaseChars);
        if ($key !== false) {
            unset($this->specialCaseChars[$key]);
        }

        return $this;
    }

    /**
     * @param array $tags
     *
     * @return $this
     */
    public function setSpecialCaseTags(array $tags = array())
    {
        $this->specialCaseTags = $tags;
        $this->specialCaseOpeningTags = array();
        $this->specialCaseClosingTags = array();

        foreach ($this->specialCaseTags as $tag) {
            $this->addSpecialCaseTag($tag);
        }

        return $this;
    }

    /**
     * @param string $tag
     *
     * @return $this
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

        return $this;
    }

    /**
     * @param string $tag
     *
     * @return $this
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

        return $this;
    }

    /**
     * @return array|null
     */
    public function getSpecialCaseTags()
    {
        return $this->specialCaseTags;
    }

    /**
     * @return bool
     */
    public function isGroupDiffs()
    {
        return $this->groupDiffs;
    }

    /**
     * @param bool $groupDiffs
     *
     * @return HtmlDiffConfig
     */
    public function setGroupDiffs($groupDiffs)
    {
        $this->groupDiffs = $groupDiffs;

        return $this;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param string $encoding
     *
     * @return HtmlDiffConfig
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * @return bool
     */
    public function isInsertSpaceInReplace()
    {
        return $this->insertSpaceInReplace;
    }

    /**
     * @param bool $insertSpaceInReplace
     *
     * @return HtmlDiffConfig
     */
    public function setInsertSpaceInReplace($insertSpaceInReplace)
    {
        $this->insertSpaceInReplace = $insertSpaceInReplace;

        return $this;
    }

    /**
     * @return array
     */
    public function getIsolatedDiffTags()
    {
        return $this->isolatedDiffTags;
    }

    /**
     * @param array $isolatedDiffTags
     *
     * @return HtmlDiffConfig
     */
    public function setIsolatedDiffTags($isolatedDiffTags)
    {
        $this->isolatedDiffTags = $isolatedDiffTags;

        return $this;
    }

    /**
     * @param string      $tag
     * @param null|string $placeholder
     *
     * @return $this
     */
    public function addIsolatedDiffTag($tag, $placeholder = null)
    {
        if (null === $placeholder) {
            $placeholder = sprintf('[[REPLACE_%s]]', strtoupper($tag));
        }

        if ($this->isIsolatedDiffTag($tag) && $this->isolatedDiffTags[$tag] !== $placeholder) {
            throw new \InvalidArgumentException(
                sprintf('Isolated diff tag "%s" already exists using a different placeholder', $tag)
            );
        }

        $matchingKey = array_search($placeholder, $this->isolatedDiffTags, true);
        if (false !== $matchingKey && $matchingKey !== $tag) {
            throw new \InvalidArgumentException(
                sprintf('Placeholder already being used for a different tag "%s"', $tag)
            );
        }

        if (!array_key_exists($tag, $this->isolatedDiffTags)) {
            $this->isolatedDiffTags[$tag] = $placeholder;
        }

        return $this;
    }

    /**
     * @param string $tag
     *
     * @return $this
     */
    public function removeIsolatedDiffTag($tag)
    {
        if ($this->isIsolatedDiffTag($tag)) {
            unset($this->isolatedDiffTags[$tag]);
        }

        return $this;
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function isIsolatedDiffTag($tag)
    {
        return array_key_exists($tag, $this->isolatedDiffTags);
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    public function isIsolatedDiffTagPlaceholder($text)
    {
        return in_array($text, $this->isolatedDiffTags, true);
    }

    /**
     * @param string $tag
     *
     * @return null|string
     */
    public function getIsolatedDiffTagPlaceholder($tag)
    {
        return $this->isIsolatedDiffTag($tag) ? $this->isolatedDiffTags[$tag] : null;
    }

    /**
     * @return array
     */
    public function getSpecialCaseOpeningTags()
    {
        return $this->specialCaseOpeningTags;
    }

    /**
     * @return array
     */
    public function getSpecialCaseClosingTags()
    {
        return $this->specialCaseClosingTags;
    }

    /**
     * @return bool
     */
    public function isUseTableDiffing()
    {
        return $this->useTableDiffing;
    }

    /**
     * @param bool $useTableDiffing
     *
     * @return HtmlDiffConfig
     */
    public function setUseTableDiffing($useTableDiffing)
    {
        $this->useTableDiffing = $useTableDiffing;

        return $this;
    }

    /**
     * @param null|\Doctrine\Common\Cache\Cache $cacheProvider
     *
     * @return $this
     */
    public function setCacheProvider(\Doctrine\Common\Cache\Cache $cacheProvider = null)
    {
        $this->cacheProvider = $cacheProvider;

        return $this;
    }

    /**
     * @return null|\Doctrine\Common\Cache\Cache
     */
    public function getCacheProvider()
    {
        return $this->cacheProvider;
    }

    /**
     * @param null|string
     *
     * @return $this
     */
    public function setPurifierCacheLocation($purifierCacheLocation = null)
    {
        $this->purifierCacheLocation = $purifierCacheLocation;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getPurifierCacheLocation()
    {
        return $this->purifierCacheLocation;
    }

    /**
     * @param string $tag
     *
     * @return string
     */
    protected function getOpeningTag($tag)
    {
        return '/<'.$tag.'[^>]*/i';
    }

    /**
     * @param string $tag
     *
     * @return string
     */
    protected function getClosingTag($tag)
    {
        return '</'.$tag.'>';
    }
}
