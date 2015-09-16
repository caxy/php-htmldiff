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
    protected $childListObjects;
    
    public function build()
    {
        ini_set('xdebug.var_display_max_depth', 5);
        ini_set('xdebug.var_display_max_children', 256);
        ini_set('xdebug.var_display_max_data', 1024);
        // get content from li's
        $this->splitInputsToWords();
        $this->replaceIsolatedDiffTags();
        $this->indexNewWords();
        $this->diffListContent();
        die;
    }
    
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
        // Set matches of lists.
        $this->matchAndCompareLists();
        // Create child lists objects
        $this->createChildListObjects();
        // Diff the child lists
        $this->diff();
    }
    
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
        // Build childLists array of old/new content of lists.
        $this->buildChildLists();
        // Compare the lists, saving total matches to textMatches array.
        $this->compareChildLists();
        // Create the child list objects from textMatches array
        $this->createChildListObjects();
    }
    
    protected function compareChildLists()
    {
        // Always compare the new against the old.
        // Compare each new string against each old string.
        $matchPercentages = array();
        foreach ($this->childLists['new'] as $thisKey => $thisList) {
            $matchPercentages[$thisKey] = array();
            foreach ($this->childLists['old'] as $thatKey => $thatList) {
                similar_text($thisList, $thatList, $percentage);
                $matchPercentages[$thisKey][] = $percentage;
            }
        }
        //var_dump($matchPercentages);
        
        $bestMatchPercentages = $matchPercentages;
        foreach ($bestMatchPercentages as &$thisMatch) {
            arsort($thisMatch);
        }
        var_dump($bestMatchPercentages);
        
        // Build matches.
        $matches = array();
        $taken = array();
        $takenItems = array(2,3);
        $absolute = 100;
        foreach ($bestMatchPercentages as $item => $percentages) {
            $highestMatch = -1;
            $highestMatchKey = -1;
            
            foreach ($percentages as $key => $percent) {
                $str = "key: ".$key." / percent: ".$percent;
                //var_dump($str);
                if (!in_array($key, $taken) && $percent > $highestMatch) {
                    // If matches 100%, set and move on.
                    /*
                     * if ($percent == $absolute) {
                        $highestMatch = $percent;
                        $highestMatchKey = $key;
                        break;
                    } else {
                        // If not an absolute match, loop through the other high results, checking if any are higher
                        foreach ($bestMatchPercentages as $otherItem => $otherPercentages) {
                            if ($otherPercentages[$key] > $percent) {
                                array_column($taken, $otherPercentages)
                            }
                        }
                    }
                    */
                    $str = "Key: ".$key." / percent: ".$percent; var_dump($str);
                    $columns = array_column($bestMatchPercentages, $key);
                    var_dump(
                        // Start to filter: GOAL is to get values higher than $percent and keys not included in $takenItems
                        array_filter(
                            // Build array we want the filter to use
                            $columns,
                            // return if value is higher than percent
                            function ($v) use ($percent, $columns) {
                                return $v > $percent && ();
                            }
                        )
                    );
                    /*var_dump(
                        (array_column($bestMatchPercentages, $key))
                    );*/
                }
                die;
            }
            
            $matches[] = array('new' => $item, 'old' => $highestMatchKey > -1 ? $highestMatchKey : null);
            if ($highestMatchKey > -1) {
                $taken[] = $highestMatchKey;
            }
        }
        
        // Save the matches.
        $this->textMatches = $matches;
        //var_dump($matches);
    }
    
    protected function buildChildLists()
    {
        $this->childLists['old'] = $this->getListsContent($this->list['old']);
        $this->childLists['new'] = $this->getListsContent($this->list['new']);
    }
    
    protected function createChildListObjects()
    {
        /*$this->childListObjects = array();
        foreach ($this->textMatches as $match) {
            $object = new ListNode($match['old'], $match['new']);
            $this->childListObjects[] = $object;
        }*/
    }
    
    protected function diff()
    {
        
    }
    
    public function replaceListIsolatedDiffTags()
    {
        $this->listIsolatedDiffTags['old'] = $this->createIsolatedDiffTagPlaceholders($this->list['old']);
        $this->listIsolatedDiffTags['new'] = $this->createIsolatedDiffTagPlaceholders($this->list['new']);
    }
    
    protected function getListsContent(array $contentArray, $stripTags = true)
    {
        preg_match_all('/<li>(.*?)<\/li>/s', implode('', $contentArray), $matches);
        return $matches[intval($stripTags)];
    }
}
