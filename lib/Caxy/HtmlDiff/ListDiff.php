<?php

namespace Caxy\HtmlDiff;

class ListDiff extends HtmlDiff
{
    /**
     * This is the minimum percentage a list item can match its counterpart in order to be considered a match.
     * @var integer
     */
    protected static $listMatchThreshold = 35;
    
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
     * Used to hold what type of list the old list is.
     * @var string
     */
    protected $oldListType;
    
    /**
     * Used to hold what type of list the new list is.
     * @var string
     */
    protected $newListType;

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
     * Array that holds the index of all content outside of the array. Format is array(index => content).
     * @var array
     */
    protected $contentIndex = array();
    
    /** 
     * Holds the order and data on each list/content block within this list.
     * @var array
     */
    protected $diffOrderIndex = array();

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
        
        /* Build an index of content outside of list tags.
         */
        $this->indexContent();
        
        /* In cases where we're dealing with nested lists,
         * make sure we use placeholders to replace the nested lists
         */
        $this->replaceListIsolatedDiffTags();
        
        /* Build a list of matches we can reference when we diff the contents of the lists.
         * This is needed so that we each NEW list node is matched against the best possible OLD list node/
         * It helps us determine whether the list was added, removed, or changed.
         */
        $this->matchAndCompareLists();
        
        /* Go through the list of matches, content, and diff each.
         * Any nested lists would be sent to parent's diffList function, which creates a new listDiff class.
         */
        $this->diff();
    }
    
    /**
     * This function is used to populate both contentIndex and diffOrderIndex arrays for use in the diff function.
     */
    protected function indexContent()
    {
        $this->contentIndex = array();
        $this->diffOrderIndex = array('new' => array(), 'old' => array());
        foreach ($this->list as $type => $list) {
            
            $this->contentIndex[$type] = array();
            $depth = 0;
            $parentList = 0;
            $position = 0;
            $newBlock = true;
            $listCount = 0;
            $contentCount = 0;
            foreach ($list as $key => $word) {
                if (!$parentList && $this->isOpeningListTag($word)) {
                    $depth++;
                    
                    $this->diffOrderIndex[$type][] = array('type' => 'list', 'position' => $listCount, 'index' => $key);
                    $listCount++;
                    continue;
                }
                
                if (!$parentList && $this->isClosingListTag($word)) {
                    $depth--;
                    
                    if ($depth == 0) {
                        $newBlock = true;
                    }
                    continue;
                }
                
                if ($this->isOpeningIsolatedDiffTag($word)) {
                    $parentList++;
                }
                
                if ($this->isClosingIsolatedDiffTag($word)) {
                    $parentList--;
                }
                
                if ($depth == 0) {
                    if ($newBlock && !array_key_exists($contentCount, $this->contentIndex[$type])) {
                        $this->diffOrderIndex[$type][] = array('type' => 'content', 'position' => $contentCount, 'index' => $key);
                        
                        $position = $contentCount;
                        $this->contentIndex[$type][$position] = '';
                        $contentCount++;
                    }
                    
                    $this->contentIndex[$type][$position] .= $word;
                }
                
                $newBlock = false;
            }
        }
    }

    /*
     * This function is used to remove the wrapped ul, ol, or dl characters from this list
     * and sets the listType as ul, ol, or dl, so that we can use it later.
     * $list is being set here as well, as an array with the old and new version of this list content.
     */
    protected function formatThisListContent()
    {
        $formatArray = array(
            array('type' => 'old', 'array' => $this->oldIsolatedDiffTags),
            array('type' => 'new', 'array' => $this->newIsolatedDiffTags)
        );
        
        foreach ($formatArray as $item) {
            $values = array_values($item['array']);
            $this->list[$item['type']] = count($values)
                ? $this->formatList($values[0], $item['type'])
                : array();
        }
        
        $this->listType = $this->newListType ?: $this->oldListType;
    }
    
    /**
     * 
     * @param array $arrayData
     * @param string $index
     * @return array
     */
    protected function formatList(array $arrayData, $index = 'old')
    {
        $openingTag = $this->getAndStripTag($arrayData[0]);
        $closingTag = $this->getAndStripTag($arrayData[count($arrayData) - 1]);
        
        if (array_key_exists($openingTag, $this->isolatedDiffTags) &&
            array_key_exists($closingTag, $this->isolatedDiffTags)
        ) {
            if ($index == 'new' && $this->isOpeningTag($arrayData[0])) {
                $this->newListType = $this->getAndStripTag($arrayData[0]);
            }
            
            if ($index == 'old' && $this->isOpeningTag($arrayData[0])) {
                $this->oldListType = $this->getAndStripTag($arrayData[0]);
            }
            
            array_shift($arrayData);
            array_pop($arrayData);
        }
        
        return $arrayData;
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
    
    /**
     * Creates matches for lists.
     */
    protected function compareChildLists()
    {
        $this->createNewOldMatches($this->childLists, $this->textMatches, 'content');
    }
    
    /**
     * Abstracted function used to match items in an array.
     * This is used primarily for populating lists matches.
     * 
     * @param array $listArray
     * @param array $resultArray
     * @param string|null $column
     */
    protected function createNewOldMatches(&$listArray, &$resultArray, $column = null)
    {
        // Always compare the new against the old.
        // Compare each new string against each old string.
        $bestMatchPercentages = array();
        
        foreach ($listArray['new'] as $thisKey => $thisList) {
            $bestMatchPercentages[$thisKey] = array();
            foreach ($listArray['old'] as $thatKey => $thatList) {
                // Save the percent amount each new list content compares against the old list content.
                similar_text(
                    $column ? $thisList[$column] : $thisList,
                    $column ? $thatList[$column] : $thatList,
                    $percentage
                );
                
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
                        
                        /**
                         * If the list item does not meet the threshold, it will not be considered a match.
                         */
                        if ($percent >= self::$listMatchThreshold) {
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
        foreach ($listArray['old'] as $thisKey => $thisList) {
            if (!in_array($thisKey, $matchColumns)) {
                $matches[] = array('new' => null, 'old' => $thisKey);
            }
        }
        
        // Save the matches.
        $resultArray = $matches;
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
        
        $oldIndexCount = 0;
        $diffOrderNewKeys = array_keys($this->diffOrderIndex['new']);
        foreach ($this->diffOrderIndex['new'] as $key => $index) {
            
            if ($index['type'] == "list") {
                
                // Check to see if an old list was deleted.
                $oldMatch = $this->getArrayByColumnValue($this->textMatches, 'old', $index['position']);
                if ($oldMatch && $oldMatch['new'] === null) {
                    $newList = '';
                    $oldList = $this->getListByMatch($oldMatch, 'old');
                    $this->content .= $this->addListElementToContent($newList, $oldList, $oldMatch);
                }
                
                $match = $this->getArrayByColumnValue($this->textMatches, 'new', $index['position']);
                $newList = $this->childLists['new'][$match['new']];
                $oldList = $this->getListByMatch($match, 'old');
                $this->content .= $this->addListElementToContent($newList, $oldList, $match);
            }
            
            if ($index['type'] == 'content') {
                $this->content .= $this->addContentElementsToContent($oldIndexCount, $index['position']);
            }
            
            $oldIndexCount++;
            
            if ($key == $diffOrderNewKeys[count($diffOrderNewKeys) - 1]) {
                foreach ($this->diffOrderIndex['old'] as $oldKey => $oldIndex) {
                    if ($oldKey > $key) {
                        if ($oldIndex['type'] == 'list') {
                            $oldMatch = $this->getArrayByColumnValue($this->textMatches, 'old', $oldIndex['position']);
                            if ($oldMatch && $oldMatch['new'] === null) {
                                $newList = '';
                                $oldList = $this->getListByMatch($oldMatch, 'old');
                                $this->content .= $this->addListElementToContent($newList, $oldList, $oldMatch);
                            }
                        } else {
                            $this->content .= $this->addContentElementsToContent($oldKey);
                        }
                    }
                }
            }
        }

        // Add the closing parent node from listType. So if ol, </ol>, etc.
        $this->content .= $this->addListTypeWrapper(false);
    }
    
    /**
     * 
     * @param string $newList
     * @param string $oldList
     * @param array $match
     * @return string
     */
    protected function addListElementToContent($newList, $oldList, array $match)
    {
        $content = "<li>";
        $content .= $this->processPlaceholders(
            $this->diffElements(
                $this->convertListContentArrayToString($oldList),
                $this->convertListContentArrayToString($newList),
                false
            ),
            $match
        );
        $content .= "</li>";
        return $content;
    }
    
    /**
     * 
     * @param integer $oldIndexCount
     * @param null|integer $newPosition
     * @return string
     */
    protected function addContentElementsToContent($oldIndexCount, $newPosition = null)
    {
        $newContent = $newPosition && array_key_exists($newPosition, $this->contentIndex['new'])
            ? $this->contentIndex['new'][$newPosition]
            : '';

        $oldDiffOrderIndexMatch = array_key_exists($oldIndexCount, $this->diffOrderIndex['old'])
            ? $this->diffOrderIndex['old'][$oldIndexCount]
            : '';

        $oldContent = $oldDiffOrderIndexMatch && array_key_exists($oldDiffOrderIndexMatch['position'], $this->contentIndex['old'])
            ? $this->contentIndex['old'][$oldDiffOrderIndexMatch['position']]
            : '';

        $diffObject = new HtmlDiff($oldContent, $newContent);
        $content = $diffObject->build();
        return $content;
    }
    
    /**
     * 
     * @param array $match
     * @param string $type
     * @return array|string
     */
    protected function getListByMatch(array $match, $type = 'new')
    {
        return array_key_exists($match[$type], $this->childLists[$type])
            ? $this->childLists[$type][$match[$type]]
            : '';
    }
    
    /**
     * This function replaces array_column function in PHP for older versions of php.
     * 
     * @param array $parentArray
     * @param string $column
     * @param mixed $value
     * @param boolean $allMatches
     * @return array|boolean
     */
    protected function getArrayByColumnValue($parentArray, $column, $value, $allMatches = false)
    {
        $returnArray = array();
        foreach ($parentArray as $array) {
            if (array_key_exists($column, $array) && $array[$column] == $value) {
                if ($allMatches) {
                    $returnArray[] = $array;
                } else {
                    return $array;
                }
            }
        }
        
        return $allMatches ? $returnArray : false;
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
                $oldText = array_key_exists($count, $contentVault['old']) ? implode('', $contentVault['old'][$count]) : '';
                $newText = array_key_exists($count, $contentVault['new']) ? implode('', $contentVault['new'][$count]) : '';
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
            $stop = $this->findEndForIndex($this->list[$indexKey], $start);
            
            for ($x = $start; $x <= $stop; $x++) {
                
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
