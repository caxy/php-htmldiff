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
                similar_text($thisList, $thatList, $percentage);
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
                        //$this->dump("Absolute found");
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
            $this->content .= $this->processPlaceholders($this->diffElements($oldText, $newText), $matches);
            $this->content .= "</li>";
        }
        
        // Add the closing parent node from listType. So if ol, </ol>, etc.
        $this->content .= $this->addListTypeWrapper(false);
    }
    
    /**
     * Return the contents of each list node.
     * Process any placeholders for nested lists.
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
            $content = $word;
            if (in_array($word, $this->isolatedDiffTags)) {
                $oldText = implode('', $contentVault['old'][$count]);
                $newText = implode('', $contentVault['new'][$count]);
                $content = $this->diffList($oldText, $newText, true);
                $count++;
            }
            
            $returnText[] = $content;
        }
        // Return the result.
        return implode(' ', $returnText);
    }
    
    /**
     * Grab the list content using the listsIndex array.
     */
    protected function getListContent($indexKey = 'new', array $matches)
    {
        $bucket = array();
        $start = $this->listsIndex[$indexKey][$matches[$indexKey]];
        $stop = $this->listsIndex[$indexKey][$matches[$indexKey] + 1];
        for ($x = $start; $x < $stop; $x++) {
            if (in_array($this->list[$indexKey][$x], $this->isolatedDiffTags)) {
                $bucket[] = $this->listIsolatedDiffTags[$indexKey][$x]; 
            }
        }
        return $bucket;
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
        $lookingFor = "<li>";
        
        foreach ($this->list as $type => $list) {
            $this->listsIndex[$type] = array();
            
            foreach ($list as $key => $listItem) {
                if ($listItem == $lookingFor) {
                    $this->listsIndex[$type][] = $key;
                }
            }
        }
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
        preg_match_all('/<li>(.*?)<\/li>/s', implode('', $contentArray), $matches);
        return $matches[intval($stripTags)];
    }
}
