<?php

namespace Caxy\HtmlDiff;

class ListDiff extends HtmlDiff
{
    /** @var array */
    protected $listWords = array();

    /** @var array */
    protected $listTags = array();

    /** @var array */
    protected $listIsolatedDiffTags = array();

    /** @var array */
    protected $isolatedDiffTags = array (
        'ol' => '[[REPLACE_ORDERED_LIST]]',
        'ul' => '[[REPLACE_UNORDERED_LIST]]',
        'dl' => '[[REPLACE_DEFINITION_LIST]]',
    );

    /**
     * List (li) placeholder.
     * @var string
     */
    protected static $listPlaceHolder = "[[REPLACE_LIST_ITEM]]";

    /**
     * Holds the type of list this is ol, ul, dl.
     * @var string
     */
    protected $listType;

    /**
     * Hold the old/new content of the content of the list.
     * @var array
     */
    protected $list;

    /**
     * Contains the old/new child lists content within this list.
     * @var array
     */
    protected $childLists;

    /**
     * Contains the old/new text strings that match
     * @var array
     */
    protected $textMatches;

    /**
     * Contains the indexed start positions of each list within word string.
     * @var array
     */
    protected $listsIndex;

    /**
     * We're using the same functions as the parent in build() to get us to the point of
     * manipulating the data within this class.
     *
     * @return string
     */
    public function build()
    {
        // Use the parent functions to get the data we need organized.
        $this->splitInputsToWords();
        $this->replaceIsolatedDiffTags();
        $this->indexNewWords();
        // Now use the custom functions in this class to use the data and generate our diff.
        $this->diffListContent();

        return $this->content;
    }

    /**
     * Calls to the actual custom functions of this class, to diff list content.
     */
    protected function diffListContent()
    {
        /* Format the list we're focusing on.
         * There will always be one list, though passed as an array with one item.
         * Format this to only have the list contents, outside of the array.
         */
        $this->formatThisListContent();
        /* In cases where we're dealing with nested lists,
         * make sure we use placeholders to replace the nested lists
         */
        $this->replaceListIsolatedDiffTags();
        /* Build a list of matches we can reference when we diff the contents of the lists.
         * This is needed so that we each NEW list node is matched against the best possible OLD list node/
         * It helps us determine whether the list was added, removed, or changed.
         */
        $this->matchAndCompareLists();
        /* Go through the list of matches, and diff the contents of each.
         * Any nested lists would be sent to parent's diffList function, which creates a new listDiff class.
         */
        $this->diff();
    }

    /*
     * This function is used to remove the wrapped ul, ol, or dl characters from this list
     * and sets the listType as ul, ol, or dl, so that we can use it later.
     * $list is being set here as well, as an array with the old and new version of this list content.
     */
    protected function formatThisListContent()
    {
        foreach ($this->oldIsolatedDiffTags as $key => $diffTagArray) {
            $openingTag = $this->getAndStripTag($diffTagArray[0]);
            $closingTag = $this->getAndStripTag($diffTagArray[count($diffTagArray) - 1]);

            if (array_key_exists($openingTag, $this->isolatedDiffTags) &&
                array_key_exists($closingTag, $this->isolatedDiffTags)
            ) {
                $this->listType = $openingTag;
                array_shift($this->oldIsolatedDiffTags[$key]);
                array_pop($this->oldIsolatedDiffTags[$key]);
                array_shift($this->newIsolatedDiffTags[$key]);
                array_pop($this->newIsolatedDiffTags[$key]);
                $this->list['old'] = $this->oldIsolatedDiffTags[$key];
                $this->list['new'] = $this->newIsolatedDiffTags[$key];
            }
        }
    }

    /**
     * @param string $tag
     * @return string
     */
    protected function getAndStripTag($tag)
    {
        $content = explode(' ', preg_replace("/[^A-Za-z0-9 ]/", '', $tag));
        return $content[0];
    }

    protected function matchAndCompareLists()
    {
        /**
         * Build the an array (childLists) to hold the contents of the list nodes within this list.
         * This only holds the content of each list node.
         */
        $this->buildChildLists();

        /**
         * Index the list, starting positions, so that we can refer back to it later.
         * This is used to see where one list node starts and another ends.
         */
        $this->indexLists();

        /**
         * Compare the lists and build $textMatches array with the matches.
         * Each match is an array of "new" and "old" keys, with the id of the list it matches to.
         * Whenever there is no match (in cases where a new list item was added or removed), null is used instead of the id.
         */
        $this->compareChildLists();
    }

    protected function compareChildLists()
    {
        // Always compare the new against the old.
        // Compare each new string against each old string.
        $bestMatchPercentages = array();

        foreach ($this->childLists['new'] as $thisKey => $thisList) {
            $bestMatchPercentages[$thisKey] = array();
            foreach ($this->childLists['old'] as $thatKey => $thatList) {
                // Save the percent amount each new list content compares against the old list content.
                similar_text($thisList['content'], $thatList['content'], $percentage);
                $bestMatchPercentages[$thisKey][] = $percentage;
            }
        }

        // Sort each array by value, highest percent to lowest percent.
        foreach ($bestMatchPercentages as &$thisMatch) {
            arsort($thisMatch);
        }

        // Build matches.
        $matches = array();
        $taken = array();
        $takenItems = array();
        $absoluteMatch = 100;
        foreach ($bestMatchPercentages as $item => $percentages) {
            $highestMatch = -1;
            $highestMatchKey = -1;
            $takeItemKey = -1;

            foreach ($percentages as $key => $percent) {
                // Check that the key for the percentage is not already taken and the new percentage is higher.
                if (!in_array($key, $taken) && $percent > $highestMatch) {
                    // If an absolute match, choose this one.
                    if ($percent == $absoluteMatch) {
                        $highestMatch = $percent;
                        $highestMatchKey = $key;
                        $takenItemKey = $item;
                        break;
                    } else {
                        // Get all the other matces for the same $key
                        $columns = $this->getArrayColumn($bestMatchPercentages, $key);
                        $thisBestMatches = array_filter(
                            $columns,
                            function ($v) use ($percent) {
                                return $v > $percent;
                            }
                        );

                        arsort($thisBestMatches);

                        // If no greater amounts, use this one.
                        if (!count($thisBestMatches)) {
                            $highestMatch = $percent;
                            $highestMatchKey = $key;
                            $takenItemKey = $item;
                            break;
                        }

                        // Loop through, comparing only the items that have not already been added.
                        foreach ($thisBestMatches as $k => $v) {
                            if (in_array($k, $takenItems)) {
                                $highestMatch = $percent;
                                $highestMatchKey = $key;
                                $takenItemKey = $item;
                                break(2);
                            }
                        }
                    }
                }
            }

            $matches[] = array('new' => $item, 'old' => $highestMatchKey > -1 ? $highestMatchKey : null);
            if ($highestMatchKey > -1) {
                $taken[] = $highestMatchKey;
                $takenItems[] = $takenItemKey;
            }
        }

        /* Checking for removed items. Basically, if a list item from the old lists is removed
         * it will not be accounted for, and will disappear in the results altogether.
         * Loop through all the old lists, any that has not been added, will be added as:
         * array( new => null, old => oldItemId )
         */
        $matchColumns = $this->getArrayColumn($matches, 'old');
        foreach ($this->childLists['old'] as $thisKey => $thisList) {
            if (!in_array($thisKey, $matchColumns)) {
                $matches[] = array('new' => null, 'old' => $thisKey);
            }
        }

        // Save the matches.
        $this->textMatches = $matches;
    }
    
    /**
     * This fuction is exactly like array_column. This is added for PHP versions that do not support array_column.
     * @param array $targetArray
     * @param mixed $key
     * @return array
     */
    protected function getArrayColumn(array $targetArray, $key)
    {
        $data = array();
        foreach ($targetArray as $item) {
            if (array_key_exists($key, $item)) {
                $data[] = $item[$key];
            }
        }
        
        return $data;
    }

    /**
     * Build multidimensional array holding the contents of each list node, old and new.
     */
    protected function buildChildLists()
    {
        $this->childLists['old'] = $this->getListsContent($this->list['old']);
        $this->childLists['new'] = $this->getListsContent($this->list['new']);
    }

    /**
     * Diff the actual contents of the lists against their matched counterpart.
     * Build the content of the class.
     */
    protected function diff()
    {
        // Add the opening parent node from listType. So if ol, <ol>, etc.
        $this->content = $this->addListTypeWrapper();
        foreach ($this->textMatches as $key => $matches) {

            $oldText = $matches['old'] !== null ? $this->childLists['old'][$matches['old']] : '';
            $newText = $matches['new'] !== null ? $this->childLists['new'][$matches['new']] : '';

            // Add the opened and closed the list
            $this->content .= "<li>";
            // Process any placeholders, if they exist.
            // Placeholders would be nested lists (a nested ol, ul, dl for example).
            $this->content .= $this->processPlaceholders(
                $this->diffElements(
                    $this->convertListContentArrayToString($oldText),
                    $this->convertListContentArrayToString($newText),
                    false
                ),
                $matches
            );
            $this->content .= "</li>";
        }

        // Add the closing parent node from listType. So if ol, </ol>, etc.
        $this->content .= $this->addListTypeWrapper(false);
    }

    /**
     * Converts the list (li) content arrays to string.
     *
     * @param array $listContentArray
     * @return string
     */
    protected function convertListContentArrayToString($listContentArray)
    {
        if (!is_array($listContentArray)) {
            return $listContentArray;
        }

        $content = array();

        $words = explode(" ", $listContentArray['content']);
        $nestedListCount = 0;
        foreach ($words as $word) {
            $match = $word == self::$listPlaceHolder;

            $content[] = $match
                ? "<li>" . $this->convertListContentArrayToString($listContentArray['kids'][$nestedListCount]) . "</li>"
                : $word;

            if ($match) {
                $nestedListCount++;
            }
        }

        return implode(" ", $content);
    }

    /**
     * Return the contents of each list node.
     * Process any placeholders for nested lists.
     *
     * @param string $text
     * @param array $matches
     * @return string
     */
    protected function processPlaceholders($text, array $matches)
    {
        // Prepare return
        $returnText = array();
        // Save the contents of all list nodes, new and old.
        $contentVault = array(
            'old' => $this->getListContent('old', $matches),
            'new' => $this->getListContent('new', $matches)
        );

        $count = 0;
        // Loop through the text checking for placeholders. If a nested list is found, create a new ListDiff object for it.
        foreach (explode(' ', $text) as $word) {
            $preContent = $this->checkWordForDiffTag($this->stripNewLine($word));

            if (in_array(
                    is_array($preContent) ? $preContent[1] : $preContent,
                    $this->isolatedDiffTags
                )
            ) {
                $oldText = implode('', $contentVault['old'][$count]);
                $newText = implode('', $contentVault['new'][$count]);
                $content = $this->diffList($oldText, $newText);
                $count++;
            } else {
                $content = $preContent;
            }

            $returnText[] = is_array($preContent) ? $preContent[0] . $content . $preContent[2] : $content;
        }
        // Return the result.
        return implode(' ', $returnText);
    }

    /**
     * Checks to see if a diff tag is in string.
     *
     * @param string $word
     * @return string
     */
    protected function checkWordForDiffTag($word)
    {
        foreach ($this->isolatedDiffTags as $diffTag) {
            if (strpos($word, $diffTag) > -1) {
                $position = strpos($word, $diffTag);
                $length = strlen($diffTag);
                $result = array(
                    substr($word, 0, $position),
                    $diffTag,
                    substr($word, ($position + $length))
                );

                return $result;
            }
        }

        return $word;
    }

    /**
     * Used to remove new lines.
     *
     * @param string $text
     * @return string
     */
    protected function stripNewLine($text)
    {
        return trim(preg_replace('/\s\s+/', ' ', $text));
    }

    /**
     * Grab the list content using the listsIndex array.
     *
     * @param string $indexKey
     * @param array $matches
     * @return array
     */
    protected function getListContent($indexKey = 'new', array $matches)
    {
        $bucket = array();

        if (isset($matches[$indexKey]) && $matches[$indexKey] !== null) {
            $start = $this->listsIndex[$indexKey][$matches[$indexKey]];
            $stop = array_key_exists(($matches[$indexKey] + 1), $this->listsIndex[$indexKey])
                ? $this->listsIndex[$indexKey][$matches[$indexKey] + 1]
                : $this->findEndForIndex($this->list[$indexKey], $start);

            for ($x = $start; $x < $stop; $x++) {
                if (in_array($this->list[$indexKey][$x], $this->isolatedDiffTags)) {
                    $bucket[] = $this->listIsolatedDiffTags[$indexKey][$x];
                }
            }
        }

        return $bucket;
    }

    /**
     * Finds the end of list within its index.
     *
     * @param array $index
     * @param integer $start
     * @return integer
     */
    protected function findEndForIndex(array $index, $start)
    {
        $array = array_splice($index, $start);
        $count = 0;
        foreach ($array as $key => $item) {
            if ($this->isOpeningListTag($item)) {
                $count++;
            }

            if ($this->isClosingListTag($item)) {
                $count--;
                if ($count === 0) {
                    return $start + $key;
                }
            }
        }

        return $start + count($array);
    }

    /**
     * indexLists
     *
     * Index the list, starting positions, so that we can refer back to it later.
     * This is used to see where one list node starts and another ends.
     */
    protected function indexLists()
    {
        $this->listsIndex = array();
        $count = 0;
        foreach ($this->list as $type => $list) {
            $this->listsIndex[$type] = array();

            foreach ($list as $key => $listItem) {
                if ($this->isOpeningListTag($listItem)) {
                    $count++;
                    if ($count === 1) {
                        $this->listsIndex[$type][] = $key;
                    }
                }

                if ($this->isClosingListTag($listItem)) {
                    $count--;
                }
            }
        }
    }

    /**
     * Adds the opening or closing list html element, based on listType.
     *
     * @param boolean $opening
     * @return string
     */
    protected function addListTypeWrapper($opening = true)
    {
        return "<" . (!$opening ? "/" : '') . $this->listType . ">";
    }

    /**
     * Replace nested list with placeholders.
     */
    public function replaceListIsolatedDiffTags()
    {
        $this->listIsolatedDiffTags['old'] = $this->createIsolatedDiffTagPlaceholders($this->list['old']);
        $this->listIsolatedDiffTags['new'] = $this->createIsolatedDiffTagPlaceholders($this->list['new']);
    }

    /**
     * Grab the contents of a list node.
     *
     * @param array $contentArray
     * @param boolean $stripTags
     * @return array
     */
    protected function getListsContent(array $contentArray, $stripTags = true)
    {
        $lematches = array();
        $arrayDepth = 0;
        $nestedCount = array();
        foreach ($contentArray as $index => $word) {

            if ($this->isOpeningListTag($word)) {
                $arrayDepth++;
                if (!array_key_exists($arrayDepth, $nestedCount)) {
                    $nestedCount[$arrayDepth] = 1;
                } else {
                    $nestedCount[$arrayDepth]++;
                }
                continue;
            }

            if ($this->isClosingListTag($word)) {
                $arrayDepth--;
                continue;
            }

            if ($arrayDepth > 0) {
                $this->addStringToArrayByDepth($word, $lematches, $arrayDepth, 1, $nestedCount);
            }
        }

        return $lematches;
    }

    /**
     * This function helps build the list content array of a list.
     * If a list has another list within it, the inner list is replaced with the list placeholder and the inner list
     * content becomes a child of the parent list.
     * This goes recursively down.
     *
     * @param string $word
     * @param array $array
     * @param integer $targetDepth
     * @param integer $thisDepth
     * @param array $nestedCount
     */
    protected function addStringToArrayByDepth($word, array &$array, $targetDepth, $thisDepth, array $nestedCount)
    {
        // determine what depth we're at
        if ($targetDepth == $thisDepth) {
            // decide on what to do at this level

            if (array_key_exists('content', $array)) {
                $array['content'] .= $word;
            } else {
                // if we're on depth 1, add content
                if ($nestedCount[$targetDepth] > count($array)) {
                    $array[] = array('content' => '', 'kids' => array());
                }

                $array[count($array) - 1]['content'] .= $word;
            }

        } else {

            // create first kid if not exist
            $newArray = array('content' => '', 'kids' => array());

            if (array_key_exists('kids', $array)) {
                if ($nestedCount[$targetDepth] > count($array['kids'])) {
                    $array['kids'][] = $newArray;
                    $array['content'] .= self::$listPlaceHolder;
                }

                // continue to the next depth
                $thisDepth++;

                // get last kid and send to next depth

                $this->addStringToArrayByDepth(
                    $word,
                    $array['kids'][count($array['kids']) - 1],
                    $targetDepth,
                    $thisDepth,
                    $nestedCount
                );

            } else {

                if ($nestedCount[$targetDepth] > count($array[count($array) - 1]['kids'])) {
                    $array[count($array) - 1]['kids'][] = $newArray;
                    $array[count($array) - 1]['content'] .= self::$listPlaceHolder;
                }
                // continue to the next depth
                $thisDepth++;

                // get last kid and send to next depth

                $this->addStringToArrayByDepth(
                    $word,
                    $array[count($array) - 1]['kids'][count($array[count($array) - 1]['kids']) - 1],
                    $targetDepth,
                    $thisDepth,
                    $nestedCount
                );
            }
        }
    }

    /**
     * Checks if text is opening list tag.
     *
     * @param string $item
     * @return boolean
     */
    protected function isOpeningListTag($item)
    {
        if (preg_match("#<li[^>]*>\\s*#iU", $item)) {
            return true;
        }

        return false;
    }

    /**
     * Check if text is closing list tag.
     *
     * @param string $item
     * @return boolean
     */
    protected function isClosingListTag($item)
    {
        if (preg_match("#</li[^>]*>\\s*#iU", $item)) {
            return true;
        }

        return false;
    }
}
