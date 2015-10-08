<?php

namespace Caxy\HtmlDiff;

class ListDiff extends HtmlDiff
{
    protected $listWords = array();
    protected $listTags = array();
    protected $listIsolatedDiffTags = array();
    protected $isolatedDiffTags = array (
        'ol' => '[[REPLACE_ORDERED_LIST]]',
        'ul' => '[[REPLACE_UNORDERED_LIST]]',
        'dl' => '[[REPLACE_DEFINITION_LIST]]',
    );
    
    protected $listType; // holds the type of list this is ol, ul, dl
    protected $list; // hold the old/new content of the content of the list
    protected $childLists; // contains the old/new child lists content within this list
    protected $textMatches; // contains the old/new text strings that match
    protected $listsIndex; // contains the indexed start positions of each list within word string.
    
    /**
     * We're using the same functions as the parent in build() to get us to the point of 
     * manipulating the data within this class.
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
        //var_dump($this->oldIsolatedDiffTags);
        foreach ($this->oldIsolatedDiffTags as $key => $diffTagArray) {
            $openingTag = preg_replace("/[^A-Za-z0-9 ]/", '', $diffTagArray[0]);
            $closingTag = preg_replace("/[^A-Za-z0-9 ]/", '', $diffTagArray[count($diffTagArray) - 1]);
            
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
        //var_dump($this->list);
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
        //$this->dump($this->childLists['new'], '=========NEW comparechildlists');
        //$this->dump($this->childLists['old'], '=========OLD comparechildlists');
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
                        $columns = array_column($bestMatchPercentages, $key);
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
        $matchColumns = array_column($matches, 'old');
        foreach ($this->childLists['old'] as $thisKey => $thisList) {
            if (!in_array($thisKey, $matchColumns)) {
                $matches[] = array('new' => null, 'old' => $thisKey);
            }
        }
        
        // Save the matches.
        $this->textMatches = $matches;
        //$this->dump($this->textMatches);
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
        //$this->dump($this->textMatches, "============ THE TEXT MATCHES");
        //$this->dump($this->childLists, " XXX CHILD LISTS XXX");
        foreach ($this->textMatches as $key => $matches) {
            //$this->dump($matches, " ////////// matches prep");
            $oldText = $matches['old'] !== null ? $this->childLists['old'][$matches['old']] : '';
            $newText = $matches['new'] !== null ? $this->childLists['new'][$matches['new']] : '';
            //$this->dump($oldText, " Old Text");
            //$this->dump($newText, " New Text");
            //$this->dump($this->convertListContentArrayToString($oldText), " +++++++++++ CONTENT TO STRING OLD");
            //$this->dump($this->convertListContentArrayToString($newText), " +++++++++++ CONTENT TO STRING NEW");
            /*$this->dump($this->diffElements(
                $this->convertListContentArrayToString($oldText),
                $this->convertListContentArrayToString($newText)
            ), " ====== DIFF ELEMENTS");*/
            
            // Add the opened and closed the list
            $this->content .= "<li>";
            // Process any placeholders, if they exist.
            // Placeholders would be nested lists (a nested ol, ul, dl for example).
            $this->content .= $this->processPlaceholders(
                $this->diffElements(
                    $this->convertListContentArrayToString($oldText),
                    $this->convertListContentArrayToString($newText)
                ), 
                $matches
            );
            $this->content .= "</li>";
        }
        
        // Add the closing parent node from listType. So if ol, </ol>, etc.
        $this->content .= $this->addListTypeWrapper(false);
    }
    
    protected function convertListContentArrayToString($listContentArray)
    {
        if (!is_array($listContentArray)) {
            return $listContentArray;
        }
        
        $content = array();
        $listString = '[[REPLACE_LIST_ITEM]]';
        ////$this->dump($listContentArray, "=================++++++WHAT IS IT");
        $words = explode(" ", $listContentArray['content']);
        $nestedListCount = 0;
        foreach ($words as $word) {
            $match = $word == $listString;
            
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
     */
    protected function processPlaceholders($text, array $matches)
    {
        //$this->dump(func_get_args(), "================= processPlaceholders function");
        // Prepare return
        $returnText = array();
        // Save the contents of all list nodes, new and old.
        $contentVault = array(
            'old' => $this->getListContent('old', $matches),
            'new' => $this->getListContent('new', $matches)
        );
        
        //$this->dump($contentVault, "==============CONTENT VAULT"); //die;
        
        $count = 0;
        // Loop through the text checking for placeholders. If a nested list is found, create a new ListDiff object for it.
        //$this->dump($text, " ----------- PRECONTENT PLACEHOLDER TEXT ");
        foreach (explode(' ', $text) as $word) {
            $preContent = $this->checkWordForDiffTag($this->stripNewLine($word));
            
            if (in_array(
                    is_array($preContent) ? $preContent[1] : $preContent, 
                    $this->isolatedDiffTags
                )
            ) {
                //$this->dump($word, " ----------- WORD ");
                //$this->dump($preContent, " ----------- PRECONTENT PLACEHOLDER ");
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
     */
    protected function stripNewLine($text)
    {
        return trim(preg_replace('/\s\s+/', ' ', $text));
    }
    
    /**
     * Grab the list content using the listsIndex array.
     */
    protected function getListContent($indexKey = 'new', array $matches)
    {
        //$this->dump(func_get_args(), " ------------  GET LIST CONTENT FUNCTION -------------- ");
        /*if ($matches == array('new' => 3, 'old' => 3)) {
            //$this->dump($this->listsIndex, '---------- FULL LIST INDEX --------');
            //$this->dump($this->list, "---------------------- FULL LIST");
            //$this->dump($this->listsIndex[$indexKey][$matches[$indexKey]], '---------- START --------');
            //$this->dump(array_key_exists(($matches[$indexKey] + 1), $this->listsIndex[$indexKey])
            ? $this->listsIndex[$indexKey][$matches[$indexKey] + 1]
            : $this->findEndForIndex($this->list[$indexKey], $this->listsIndex[$indexKey][$matches[$indexKey]], true), '---------- STOP --------');
            //$this->dump($this->isolatedDiffTags, "==== ISOLATED DIFF TAGS ==== ");
        }*/
        $bucket = array();
        $start = $this->listsIndex[$indexKey][$matches[$indexKey]];
        $stop = array_key_exists(($matches[$indexKey] + 1), $this->listsIndex[$indexKey])
            ? $this->listsIndex[$indexKey][$matches[$indexKey] + 1]
            : $this->findEndForIndex($this->list[$indexKey], $start);
        
        //$this->dump(array('type' => $indexKey, 'start' => $start, 'stop' => $stop), " AHHHHHHHHHHHHHHHHHHHHHHHHH");
        //$this->dump($this->list[$indexKey]);
        for ($x = $start; $x < $stop; $x++) {
            if (in_array($this->list[$indexKey][$x], $this->isolatedDiffTags)) {
                $bucket[] = $this->listIsolatedDiffTags[$indexKey][$x]; 
            }
        }
        
         //$this->dump($bucket, " ------------  GET LIST BUCKET -------------- ");
        return $bucket;
    }
    
    protected function findEndForIndex($index, $start, $debug = false)
    {
        $array = array_splice($index, $start);
        //if ($debug) {//$this->dump($array, "SPLICE ------------");}
        $count = 0;
        foreach ($array as $key => $item) {
            if ($this->isOpeningListTag($item)) {
                $count++;
            }
            
            if ($this->isClosingListTag($item)) {
                $count--;
                if ($debug) {
                    //$this->dump($start ." + ". $key, "FOUND FOR END OF INDEX");
                    //$this->dump($item, "ITEM");
                }
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
        //$this->dump($this->list, " INDEX LISTS ACTUAL LIST HELPER");
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
                
                ////$this->dump(array('c' => $count, 'i' => $listItem), "RESULT");
            }
        }
        
        ////$this->dump($this->listsIndex, " = ================ END OF INDEX LISTS FUNCTION"); die;
    }
    
    /**
     * Adds the opening or closing list html element, based on listType.
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
     */
    protected function getListsContent(array $contentArray, $stripTags = true)
    {
        $lematches = array();
        $arrayDepth = 0;
        $status = "//////////////////// STATUS \\\\\\\\\\\\\\\\\\\\\\";
        $nestedCount = array();
        foreach ($contentArray as $index => $word) {
            
            if ($this->isOpeningListTag($word)) {
                $arrayDepth++;
                if (!array_key_exists($arrayDepth, $nestedCount)) {
                    $nestedCount[$arrayDepth] = 1;
                } else {
                    $nestedCount[$arrayDepth]++;
                }
                ////$this->dump(array('arrayDepth' => $arrayDepth, 'prev' => $previousDepth, 'action' => '++', 'word' => $word, 'changed' => $changed), $status);
                continue;
            }
            
            if ($this->isClosingListTag($word)) {
                $arrayDepth--;
                ////$this->dump(array('arrayDepth' => $arrayDepth, 'prev' => $previousDepth, 'action' => '--', 'word' => $word, 'changed' => $changed), $status);
                continue;
            } 
            
            if ($arrayDepth > 0) {
                ////$this->dump(array('arrayDepth' => $arrayDepth, 'prev' => $previousDepth, 'action' => '==', 'word' => $word, 'changed' => $changed), $status);
                $this->addStringToArrayByDepth($word, $lematches, $arrayDepth, 1, $nestedCount);
                ////$this->dump($lematches, '---------- total array at end of this loop ---------');
            }
        }
        
        ////$this->dump($lematches);
        //die;
        //var_dump($contentArray);
        //var_dump(implode('', $contentArray));
        preg_match_all('/<li>(.*?)<\/li>/s', implode('', $contentArray), $matches);
        //$this->dump($matches, "================== OLD LIST CONTENT");
        //$this->dump($lematches, "================== NEW LIST CONTENT");
        ////$this->dump($matches[intval($stripTags)], 'XXXXXXXXXXXXXXXXx - matches dump');
        return $lematches;
    }
    
    protected function addStringToArrayByDepth($word, &$array, $targetDepth, $thisDepth, $nestedCount)
    {
        ////$this->dump(func_get_args(), '============ addstringfunction vars');
        ////$this->dump($array); 
        
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
            
            ////$this->dump($array, '========= ADDED CONTENT TO THIS ARRAY ==========');
        } else {
            
            // create first kid if not exist
            $newArray = array('content' => '', 'kids' => array());
            
            if (array_key_exists('kids', $array)) {
                if ($nestedCount[$targetDepth] > count($array['kids'])) {
                    $array['kids'][] = $newArray;
                    $array['content'] .= "[[REPLACE_LIST_ITEM]]";
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
                ////$this->dump($array, "================PREP");
                if ($nestedCount[$targetDepth] > count($array[count($array) - 1]['kids'])) {
                    $array[count($array) - 1]['kids'][] = $newArray;
                    $array[count($array) - 1]['content'] .= "[[REPLACE_LIST_ITEM]]";
                }
                ////$this->dump($array, "================POSTPREP");
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
        /* Structure
         * $matches = array(
         *      0 = array(
         *          content => string,
         *          kids => array(
         *              content => string,
         *              kids => array(...)
         *          )
         *      )
         * )
         * 
         
        ////$this->dump(func_get_args(), "======func args========");
        
        if ($depth === 1) {
            if ($changed && $previousDepth > $depth) {
                
            } else {
                $array[] = array('content' => '', 'kids' => array());
            }
                $array[count($array) - 1]['content'] .= $word;
            
        } else {
            $depth--;
            //$this->dump($array, "---------------DOWN");
            $this->addStringToArrayByDepth($word, $array, $depth, $changed, true);
        }*/
    }
    
    protected function dump($content, $text = null)
    {
        ini_set('xdebug.var_display_max_depth', '10');
        ini_set('xdebug.var_display_max_data', '4096');
        ini_set('xdebug.max_nesting_level', '200');
        ini_set('xdebug.var_display_max_children', 256);
        if ($text) {
            var_dump($text);
        }
        
        var_dump($content);
    }
    
    protected function isOpeningListTag($item)
    {
        if (preg_match("#<li[^>]*>\\s*#iU", $item)) {
            return true;
        }

        return false;
    }

    protected function isClosingListTag($item)
    {
        if (preg_match("#</li[^>]*>\\s*#iU", $item)) {
            return true;
        }

        return false;
    }
}
