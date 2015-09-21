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
    //protected $childListObjects;
    protected $listsIndex;
    
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
        
        return $this->content;
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
        
        $this->indexLists();
        // Compare the lists, saving total matches to textMatches array.
        $this->compareChildLists();
        // Create the child list objects from textMatches array
        $this->createChildListObjects();
    }
    
    protected function compareChildLists()
    {
        // Always compare the new against the old.
        // Compare each new string against each old string.
        $bestMatchPercentages = array();
        foreach ($this->childLists['new'] as $thisKey => $thisList) {
            $bestMatchPercentages[$thisKey] = array();
            foreach ($this->childLists['old'] as $thatKey => $thatList) {
                similar_text($thisList, $thatList, $percentage);
                $bestMatchPercentages[$thisKey][] = $percentage;
            }
        }
        
        foreach ($bestMatchPercentages as &$thisMatch) {
            arsort($thisMatch);
        }
        //var_dump($bestMatchPercentages);
        
        // Build matches.
        $matches = array();
        $taken = array();
        $absoluteMatch = 100;
        foreach ($bestMatchPercentages as $item => $percentages) {
            $highestMatch = -1;
            $highestMatchKey = -1;
            
            foreach ($percentages as $key => $percent) {
                // Check that the key for the percentage is not already taken and the new percentage is higher.
                if (!in_array($key, $taken) && $percent > $highestMatch) {
                    // If an absolute match, choose this one.
                    if ($percent == $absoluteMatch) {
                        $highestMatch = $percent;
                        $highestMatchKey = $key;
                        break;
                    } else {
                        // Get all the other matces for the same $key
                        $columns = array_column($bestMatchPercentages, $key);
                        //$str = "All the other matches for this key:".$key; var_dump($str);
                        //var_dump($columns);
                        $thisBestMatches = array_filter(
                            $columns,
                            function ($v) use ($percent) {
                                return $v > $percent;
                            }
                        );
                        
                        //$str = "Best Matches Sorted, with lower matches filtered out: ".$percent; var_dump($str);
                        arsort($thisBestMatches);
                        //var_dump($thisBestMatches);
                        
                        // If no greater amounts, use this one.
                        if (!count($thisBestMatches)) {
                            $highestMatch = $percent;
                            $highestMatchKey = $key;
                            break;
                        }
                        
                        // Loop through, comparing only the items that have not already been added.
                        /*foreach ($thisBestMatches as $k => $v) {
                            if (!in_array($k, $takenItems)) {
                                $highestMatch = $percent;
                                $highestMatchKey = $key;
                                $takenItemKey = $item;
                                break(2);
                            }
                        }*/
                    }
                }
            }
            
            $matches[] = array('new' => $item, 'old' => $highestMatchKey > -1 ? $highestMatchKey : null);
            if ($highestMatchKey > -1) {
                $taken[] = $highestMatchKey;
            }
        }
        
        // Save the matches.
        $this->textMatches = $matches;
        $this->dump($matches);
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
        $this->content = $this->addListTypeWrapper();
        
        foreach ($this->textMatches as $key => $matches) {
            $oldText = $matches['old'] !== null ? $this->childLists['old'][$matches['old']] : '';
            $newText = $matches['new'] !== null ? $this->childLists['new'][$matches['new']] : '';
            $this->dump("OLD TEXT: ". $oldText);
            $this->dump("NEW TEXT: ".$newText);
            
            $this->content .= "<li>";
            if ($newText && !$oldText) {
                $this->content .= $newText;
            } elseif ($oldText && !$newText) {
                $this->content .= "THIS RIGHT HERE";
            } else {
                $thisDiff = $this->processPlaceholders($this->diffElements($oldText, $newText), $matches);
                $this->content .= $thisDiff;
            }
            $this->content .= "</li>";
        }
        
        $this->content .= $this->addListTypeWrapper(false);
    }
    
    protected function processPlaceholders($text, array $matches)
    {
        $returnText = array();
        $contentVault = array(
            'old' => $this->getListContent('old', $matches),
            'new' => $this->getListContent('new', $matches)
        );
        
        $count = 0;
        foreach (explode(' ', $text) as $word) {
            $content = $word;
            if (in_array($word, $this->isolatedDiffTags)) {
                $oldText = implode('', $contentVault['old'][$count]);
                $newText = implode('', $contentVault['new'][$count]);
                $content = $this->diffList($oldText, $newText);
                $count++;
            }
            
            $returnText[] = $content;
        }
        return implode(' ', $returnText);
    }
    
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
    
    protected function addListTypeWrapper($opening = true)
    {
        return "<" . (!$opening ? "/" : '') . $this->listType . ">";
    }
    
    protected function dump($content)
    {
        var_dump($content);
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
