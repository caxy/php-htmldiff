<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use Caxy\HtmlDiff\Demo\Model\DiffArchive;
use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\HtmlDiffConfig;
use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use MongoDB\Collection;

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

$diffArchive = $mongodb->tracker->diffArchive;

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

        $updates = processDiff($diff, $diffArchive);

        $serialized = $diff->bsonSerialize();
        $serialized['_id'] = (string) $serialized['_id'];

        if (!empty($updates)) {
            try {
                $collection->updateOne(['_id' => $diff->getId()], ['$set' => $updates]);
            } catch (\Exception $e) {
                var_dump($e->getMessage());
                var_dump($e->getTraceAsString());
                throw $e;
            }
        }

        $response = $serialized;
        break;

    case 'cget':
        $filterStatuses = !empty($requestJson['status_filter']);
        $criteria = [];
        if ($filterStatuses) {
            $statuses = $requestJson['status_filter'];
            if (!is_array($statuses)) {
                $statuses = array($statuses);
            }
            if (in_array(Diff::STATUS_NONE, $statuses, true)) {
                $statuses[] = null;
            }
            $statusCriteria = [];
            foreach ($statuses as $status) {
                $statusCriteria[] = ['status' => $status];
            }
            $criteria['$or'] = $statusCriteria;
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

        $response = ['result' => [], 'totalCount' => $collection->count($criteria)];
        foreach ($diffs as $diff) {
            $serialized = $diff->bsonSerialize();
            $serialized['_id'] = (string) $serialized['_id'];

            $response['result'][] = $serialized;
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

        if ($cursor instanceof Traversable) {
            $response = [];
            foreach ($cursor as $stat) {
                if (!is_array($stat)) {
                    $stat = (array) $stat;
                }
                $status = $stat['_id'] ?: 'none';
                if (array_key_exists($status, $response)) {
                    $response[$status] += $stat['count'];
                } else {
                    $response[$status] = $stat['count'];
                }
            }
        } else {
            $response = (array) $cursor;
        }
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

function processDiff(Diff $diff, Collection $archive)
{
    $updates = [];
    $oldText = $diff->getOldContent();
    $newText = $diff->getNewContent();

    $config = HtmlDiffConfig::create();
    $config->setMatchThreshold(75);
    $htmldiff = HtmlDiff::create($oldText, $newText, $config);
    $diffContent = $htmldiff->build();
    $diffContent = iconv('UTF-8', 'UTF-8//IGNORE', $diffContent);

    $diffHash = md5($diffContent);

    // check if diff changed
    if ($diffHash !== $diff->getDiffHash()) {
        if (null !== $diff->getDiffContent()) {
            $prevDiffArchive = new DiffArchive();
            $prevDiffArchive->setContent($diff->getDiffContent());
            $prevDiffArchive->setProposalObjectId($diff->getProposalObjectId());
            $prevDiffArchive->setStatus($diff->getStatus());

            if (null !== $diff->getPrevDiffArchiveId()) {
                $prevDiffArchive->setPrev($diff->getPrevDiffArchiveId());
            }

            $result = $archive->insertOne($prevDiffArchive);
            $archiveId = $result->getInsertedId();

            $diff->setPrevDiffArchiveId($archiveId);
            $updates['prevDiffArchiveId'] = $archiveId;
        }

        $diff->setDiffContent($diffContent);
        $updates['diffContent'] = $diffContent;
        if ($diff->getStatus() !== null && $diff->getStatus() !== Diff::STATUS_NONE) {
            if ($diff->getPrevStatus() !== Diff::STATUS_CHANGED) {
                $diff->setPrevStatus($diff->getStatus());
                $updates['prevStatus'] = $diff->getStatus();
            }
            $diff->setStatus(Diff::STATUS_CHANGED);
            $updates['status'] = Diff::STATUS_CHANGED;
        }
        $diff->setDiffHash($diffHash);
        $updates['diffHash'] = $diffHash;
    }

    return $updates;
}
