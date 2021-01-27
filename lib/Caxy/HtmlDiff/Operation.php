<?php

namespace Caxy\HtmlDiff;

class Operation
{
    const ADDED   = 'a';
    const DELETED = 'd';
    const CHANGED = 'c';

    public $action;
    public $startInOld;
    public $endInOld;
    public $startInNew;
    public $endInNew;

    public function __construct(string $action, int $startInOld, int $endInOld, int $startInNew, int $endInNew)
    {
        $this->action     = $action;
        $this->startInOld = $startInOld;
        $this->endInOld   = $endInOld;
        $this->startInNew = $startInNew;
        $this->endInNew   = $endInNew;
    }
}
