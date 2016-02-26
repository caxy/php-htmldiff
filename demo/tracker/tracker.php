<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use Caxy\HtmlDiff\HtmlDiff;
use MongoDB\BSON\ObjectID;
use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../../vendor/autoload.php';

$requestBody = file_get_contents('php://input');
$requestJson = json_decode($requestBody, true);

if (!isset($requestJson['action'])) {
    throw new \Exception('action must be present in data.');
}

$mongodb = new Client();
$collection = $mongodb->tracker->diffs;

$response = null;
switch ($requestJson['action']) {
    case 'get':
        $keys = ['_id', 'proposalObjectId', 'entityId'];
        $criteria = array_intersect_key($requestJson, array_flip($keys));
        if (empty($criteria)) {
            throw new \Exception('No criteria passed to find by.');
        }
        if (isset($criteria['_id'])) {
            $criteria['_id'] = new ObjectID($criteria['_id']);
        }
        $diff = $collection->findOne($criteria);

        if (!$diff) {
            throw new \Exception('Not found.');
        }

        processDiff($diff);

        $serialized = $diff->bsonSerialize();
        $serialized['_id'] = (string) $serialized['_id'];
        $update = $serialized;
        unset($update['_id']);
        $collection->updateOne(['_id' => $diff->getId()], ['$set' => $update]);

        $response = $serialized;
        break;

    case 'cget':
        $filterStatuses = isset($requestJson['status_filter']) && $requestJson['status_filter'];
        $criteria = [];
        if ($filterStatuses) {
            $criteria['$or'] = [['status' => null], ['status' => Diff::STATUS_CHANGED]];
        }

        $options = [
            'sort' => ['proposalObjectId' => -1]
        ];
        if (isset($requestJson['limit'])) {
            $options['limit'] = $requestJson['limit'];

            if (isset($requestJson['offset'])) {
                $options['skip'] = $requestJson['offset'];
            }
        }
        $diffs = $collection->find($criteria, $options);

        $response = [];
        foreach ($diffs as $diff) {
//            processDiff($diff);

            $serialized = $diff->bsonSerialize();
            $serialized['_id'] = (string) $serialized['_id'];
//            $update = $serialized;
//            unset($update['_id']);
//            $collection->updateOne(['_id' => $diff->getId()], ['$set' => $update]);

            $response[] = $serialized;
        }
        break;

    case 'putStatus':
        $filter = ['_id' => new ObjectID($requestJson['_id'])];
        $diff = $collection->findOne($filter);

        if (!$diff) {
            throw new \Exception('Diff not found.');
        }

        $status = $requestJson['status'];

        if (!in_array($status, Diff::$statuses)) {
            throw new \Exception('Status not valid.');
        }

        $notes = isset($requestJson['notes']) ? $requestJson['notes'] : null;

        $collection->updateOne($filter, ['$set' => ['status' => $status, 'notes' => $notes]]);

        $response = $diff->bsonSerialize();
        break;

    case 'stats':
        $cursor = $collection->aggregate([
            ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]],
            ['$sort' => ['count' => -1]]
        ]);

        $response = (array) $cursor;
        break;

    case 'favorite':
        $filter = ['_id' => new ObjectID($requestJson['_id'])];
        $favorite = $requestJson['favorite'];

        $diff = $collection->findOne($filter);

        if (!$diff) {
            throw new \Exception('Diff not found.');
        }

        $collection->updateOne($filter, ['$set' => ['favorite' => (bool) $favorite]]);

        $response = ['success' => true];

        break;

    default:
        throw new \Exception(sprintf('Action "%s" is not valid.', $requestJson['action']));
}

header('Content-Type: application/json');

$output = json_encode($response);

if (false !== $output) {
    echo $output;
} else {
    throw new \Exception('Failed to encode to JSON: '.json_last_error_msg());
}

exit();

function processDiff(Diff $diff)
{
    $oldText = $diff->getOldContent();
    $newText = $diff->getNewContent();

    $htmldiff = new HtmlDiff($oldText, $newText, 'UTF-8', array());
    $diffContent = $htmldiff->build();

    $diffHash = md5($diffContent);

    // check if diff changed
    if ($diffHash !== $diff->getDiffHash()) {
        $diff->setDiffContent($diffContent);
        if ($diff->getStatus() !== null) {
            if ($diff->getPrevStatus() !== Diff::STATUS_CHANGED) {
                $diff->setPrevStatus($diff->getStatus());
            }
            $diff->setStatus(Diff::STATUS_CHANGED);
        }
        $diff->setDiffHash($diffHash);
    }
}
