<?php

namespace Caxy\HtmlDiff;

use Caxy\HtmlDiff\ListDiff\DiffList;
use Caxy\HtmlDiff\ListDiff\DiffListItem;

class ListDiff extends AbstractDiff
{
    protected static $listTypes = array('ul', 'ol', 'dl');

    /**
     * @param string              $oldText
     * @param string              $newText
     * @param HtmlDiffConfig|null $config
     *
     * @return ListDiff
     */
    public static function create($oldText, $newText, HtmlDiffConfig $config = null)
    {
        $diff = new self($oldText, $newText);

        if (null !== $config) {
            $diff->setConfig($config);
        }

        return $diff;
    }

    public function build()
    {
        $this->prepare();

        if ($this->hasDiffCache() && $this->getDiffCache()->contains($this->oldText, $this->newText)) {
            $this->content = $this->getDiffCache()->fetch($this->oldText, $this->newText);

            return $this->content;
        }

        $this->splitInputsToWords();

        $this->content = $this->diffLists(
            $this->buildDiffList($this->oldWords),
            $this->buildDiffList($this->newWords)
        );

        if ($this->hasDiffCache()) {
            $this->getDiffCache()->save($this->oldText, $this->newText, $this->content);
        }

        return $this->content;
    }

    protected function diffLists(DiffList $oldList, DiffList $newList)
    {
        $oldMatchData = array();
        $newMatchData = array();
        $oldListIndices = array();
        $newListIndices = array();
        $oldListItems = array();
        $newListItems = array();

        foreach ($oldList->getListItems() as $oldIndex => $oldListItem) {
            if ($oldListItem instanceof DiffListItem) {
                $oldListItems[$oldIndex] = $oldListItem;

                $oldListIndices[] = $oldIndex;
                $oldMatchData[$oldIndex] = array();

                // Get match percentages
                foreach ($newList->getListItems() as $newIndex => $newListItem) {
                    if ($newListItem instanceof DiffListItem) {
                        if (!in_array($newListItem, $newListItems)) {
                            $newListItems[$newIndex] = $newListItem;
                        }
                        if (!in_array($newIndex, $newListIndices)) {
                            $newListIndices[] = $newIndex;
                        }
                        if (!array_key_exists($newIndex, $newMatchData)) {
                            $newMatchData[$newIndex] = array();
                        }

                        $oldText = implode('', $oldListItem->getText());
                        $newText = implode('', $newListItem->getText());

                        // similar_text
                        $percentage = null;
                        similar_text($oldText, $newText, $percentage);

                        $oldMatchData[$oldIndex][$newIndex] = $percentage;
                        $newMatchData[$newIndex][$oldIndex] = $percentage;
                    }
                }
            }
        }

        $currentIndexInOld = 0;
        $currentIndexInNew = 0;
        $oldCount = count($oldListIndices);
        $newCount = count($newListIndices);
        $difference = max($oldCount, $newCount) - min($oldCount, $newCount);

        $diffOutput = '';

        foreach ($newList->getListItems() as $newIndex => $newListItem) {
            if ($newListItem instanceof DiffListItem) {
                $operation = null;

                $oldListIndex = array_key_exists($currentIndexInOld, $oldListIndices) ? $oldListIndices[$currentIndexInOld] : null;
                $class = 'normal';

                if (null !== $oldListIndex && array_key_exists($oldListIndex, $oldMatchData)) {
                    // Check percentage matches of upcoming list items in old.
                    $matchPercentage = $oldMatchData[$oldListIndex][$newIndex];

                    // does the old list item match better?
                    $otherMatchBetter = false;
                    foreach ($oldMatchData[$oldListIndex] as $index => $percentage) {
                        if ($index > $newIndex && $percentage > $matchPercentage) {
                            $otherMatchBetter = $index;
                        }
                    }

                    if (false !== $otherMatchBetter && $newCount > $oldCount && $difference > 0) {
                        $diffOutput .= sprintf('%s', $newListItem->getHtml('normal new', 'ins'));
                        ++$currentIndexInNew;
                        --$difference;

                        continue;
                    }

                    $replacement = false;

                    // is there a better old list item match coming up?
                    if ($oldCount > $newCount) {
                        while ($difference > 0 && $this->hasBetterMatch($newMatchData[$newIndex], $oldListIndex)) {
                            $diffOutput .= sprintf('%s', $oldListItems[$oldListIndex]->getHtml('removed', 'del'));

                            ++$currentIndexInOld;
                            --$difference;
                            $oldListIndex = array_key_exists($currentIndexInOld, $oldListIndices) ? $oldListIndices[$currentIndexInOld] : null;
                            $matchPercentage = $oldMatchData[$oldListIndex][$newIndex];
                            $replacement = true;
                        }
                    }

                    $nextOldListIndex = array_key_exists($currentIndexInOld + 1, $oldListIndices) ? $oldListIndices[$currentIndexInOld + 1] : null;

                    if ($nextOldListIndex !== null && $oldMatchData[$nextOldListIndex][$newIndex] > $matchPercentage && $oldMatchData[$nextOldListIndex][$newIndex] > $this->config->getMatchThreshold()) {
                        // Following list item in old is better match, use that.
                        $diffOutput .= sprintf('%s', $oldListItems[$oldListIndex]->getHtml('removed', 'del'));

                        ++$currentIndexInOld;
                        $oldListIndex = $nextOldListIndex;
                        $matchPercentage = $oldMatchData[$oldListIndex][$newIndex];
                        $replacement = true;
                    }

                    if ($matchPercentage > $this->config->getMatchThreshold() || $currentIndexInNew === $currentIndexInOld) {
                        // Diff the two lists.
                        $htmlDiff = HtmlDiff::create(
                            $oldListItems[$oldListIndex]->getInnerHtml(),
                            $newListItem->getInnerHtml(),
                            $this->config
                        );
                        $diffContent = $htmlDiff->build();

                        $diffOutput .= sprintf('%s%s%s', $newListItem->getStartTagWithDiffClass($replacement ? 'replacement' : 'normal'), $diffContent, $newListItem->getEndTag());
                    } else {
                        $diffOutput .= sprintf('%s', $oldListItems[$oldListIndex]->getHtml('removed', 'del'));
                        $diffOutput .= sprintf('%s', $newListItem->getHtml('replacement', 'ins'));
                    }
                    ++$currentIndexInOld;
                } else {
                    $diffOutput .= sprintf('%s', $newListItem->getHtml('normal new', 'ins'));
                }

                ++$currentIndexInNew;
            }
        }

        // Output any additional list items
        while (array_key_exists($currentIndexInOld, $oldListIndices)) {
            $oldListIndex = $oldListIndices[$currentIndexInOld];
            $diffOutput .= sprintf('%s', $oldListItems[$oldListIndex]->getHtml('removed', 'del'));
            ++$currentIndexInOld;
        }

        return sprintf('%s%s%s', $newList->getStartTagWithDiffClass(), $diffOutput, $newList->getEndTag());
    }

    /**
     * @param array $matchData
     * @param int   $currentIndex
     *
     * @return bool
     */
    protected function hasBetterMatch(array $matchData, $currentIndex)
    {
        $matchPercentage = $matchData[$currentIndex];
        foreach ($matchData as $index => $percentage) {
            if ($index > $currentIndex &&
                $percentage > $matchPercentage &&
                $percentage > $this->config->getMatchThreshold()
            ) {
                return true;
            }
        }

        return false;
    }

    protected function buildDiffList($words)
    {
        $listType = null;
        $listStartTag = null;
        $listEndTag = null;
        $attributes = array();
        $openLists = 0;
        $openListItems = 0;
        $list = array();
        $currentListItem = null;
        $listItemType = null;
        $listItemStart = null;
        $listItemEnd = null;

        foreach ($words as $i => $word) {
            if ($this->isOpeningListTag($word, $listType)) {
                if ($openLists > 0) {
                    if ($openListItems > 0) {
                        $currentListItem[] = $word;
                    } else {
                        $list[] = $word;
                    }
                } else {
                    $listType = mb_substr($word, 1, 2);
                    $listStartTag = $word;
                }

                ++$openLists;
            } elseif ($this->isClosingListTag($word, $listType)) {
                if ($openLists > 1) {
                    if ($openListItems > 0) {
                        $currentListItem[] = $word;
                    } else {
                        $list[] = $word;
                    }
                } else {
                    $listEndTag = $word;
                }

                --$openLists;
            } elseif ($this->isOpeningListItemTag($word, $listItemType)) {
                if ($openListItems === 0) {
                    // New top-level list item
                    $currentListItem = array();
                    $listItemType = mb_substr($word, 1, 2);
                    $listItemStart = $word;
                } else {
                    $currentListItem[] = $word;
                }

                ++$openListItems;
            } elseif ($this->isClosingListItemTag($word, $listItemType)) {
                if ($openListItems === 1) {
                    $listItemEnd = $word;
                    $listItem = new DiffListItem($currentListItem, array(), $listItemStart, $listItemEnd);
                    $list[] = $listItem;
                    $currentListItem = null;
                } else {
                    $currentListItem[] = $word;
                }

                --$openListItems;
            } else {
                if ($openListItems > 0) {
                    $currentListItem[] = $word;
                } else {
                    $list[] = $word;
                }
            }
        }

        $diffList = new DiffList($listType, $listStartTag, $listEndTag, $list, $attributes);

        return $diffList;
    }

    protected function isOpeningListTag($word, $type = null)
    {
        $filter = $type !== null ? array('<'.$type) : array('<ul', '<ol', '<dl');

        return in_array(mb_substr($word, 0, 3), $filter);
    }

    protected function isClosingListTag($word, $type = null)
    {
        $filter = $type !== null ? array('</'.$type) : array('</ul', '</ol', '</dl');

        return in_array(mb_substr($word, 0, 4), $filter);
    }

    protected function isOpeningListItemTag($word, $type = null)
    {
        $filter = $type !== null ? array('<'.$type) : array('<li', '<dd', '<dt');

        return in_array(mb_substr($word, 0, 3), $filter);
    }

    protected function isClosingListItemTag($word, $type = null)
    {
        $filter = $type !== null ? array('</'.$type) : array('</li', '</dd', '</dt');

        return in_array(mb_substr($word, 0, 4), $filter);
    }
}
