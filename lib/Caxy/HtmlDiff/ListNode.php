<?php

namespace Caxy\HtmlDiff;

class ListNode extends HtmlDiff
{
    protected $oldText;
    protected $newText;
    
    public function __construct($oldText, $newText)
    {
        $this->oldText = $oldText;
        $this->newText = $newText;
    }
}
