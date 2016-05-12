<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

$jsonFile = __DIR__.'/listdiffs.json';
$demoStorage = json_decode(file_get_contents($jsonFile), true);

$mongodb = new Client();
$collection = $mongodb->tracker->diffs;

$diffs = [];
foreach ($demoStorage as $demoIndex => $tableDiff) {
    $existing = $collection->findOne(['proposalObjectId' => $tableDiff['id']]);

    if (!$existing) {
        $diff = new Diff();
        $diff->setProposalObjectId($tableDiff['id']);
        $diff->setEntityId(!empty($tableDiff['entity_id']) ? $tableDiff['entity_id'] : null);
        $diff->setNewContent($tableDiff['new']);
        $diff->setOldContent($tableDiff['old']);
        $diff->setLegislativeOverride(!empty($tableDiff['override']) ? $tableDiff['override'] : null);
        $diff->setStatus(Diff::STATUS_NONE);

        $diffs[] = $diff;
    }
}

$result = $collection->insertMany($diffs);

var_dump($result);

exit();

