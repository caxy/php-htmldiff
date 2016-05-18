<?php

namespace Caxy\HtmlDiff\Demo\Model;

use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Persistable;

class Diff implements Persistable
{
    const STATUS_CHANGED = 'changed';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_IGNORED = 'ignored';
    const STATUS_NONE = 'none';

    public static $statuses = array(
        self::STATUS_CHANGED,
        self::STATUS_APPROVED,
        self::STATUS_DENIED,
        self::STATUS_SKIPPED,
        self::STATUS_IGNORED,
    );

    private $id;
    private $proposalObjectId;
    private $entityId;
    private $newContent;
    private $oldContent;
    private $legislativeOverride;
    private $diffContent;
    private $diffHash;
    private $status;
    private $prevStatus;
    private $favorite = false;
    private $notes;
    private $prevDiffArchiveId;

    public function __construct()
    {
        $this->id = new ObjectID;
    }

    /**
     * @return ObjectID
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param ObjectID $id
     *
     * @return Diff
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getProposalObjectId()
    {
        return $this->proposalObjectId;
    }

    /**
     * @param mixed $proposalObjectId
     *
     * @return Diff
     */
    public function setProposalObjectId($proposalObjectId)
    {
        $this->proposalObjectId = $proposalObjectId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEntityId()
    {
        return $this->entityId;
    }

    /**
     * @param mixed $entityId
     *
     * @return Diff
     */
    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getNewContent()
    {
        return $this->newContent;
    }

    /**
     * @param mixed $newContent
     *
     * @return Diff
     */
    public function setNewContent($newContent)
    {
        $this->newContent = $newContent;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOldContent()
    {
        return $this->oldContent;
    }

    /**
     * @param mixed $oldContent
     *
     * @return Diff
     */
    public function setOldContent($oldContent)
    {
        $this->oldContent = $oldContent;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLegislativeOverride()
    {
        return $this->legislativeOverride;
    }

    /**
     * @param mixed $legislativeOverride
     *
     * @return Diff
     */
    public function setLegislativeOverride($legislativeOverride)
    {
        $this->legislativeOverride = $legislativeOverride;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDiffHash()
    {
        return $this->diffHash;
    }

    /**
     * @param mixed $diffHash
     *
     * @return Diff
     */
    public function setDiffHash($diffHash)
    {
        $this->diffHash = $diffHash;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     *
     * @return Diff
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPrevStatus()
    {
        return $this->prevStatus;
    }

    /**
     * @param mixed $prevStatus
     *
     * @return Diff
     */
    public function setPrevStatus($prevStatus)
    {
        $this->prevStatus = $prevStatus;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDiffContent()
    {
        return $this->diffContent;
    }

    /**
     * @param mixed $diffContent
     *
     * @return Diff
     */
    public function setDiffContent($diffContent)
    {
        $this->diffContent = $diffContent;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isFavorite()
    {
        return $this->favorite;
    }

    /**
     * @param boolean $favorite
     *
     * @return Diff
     */
    public function setFavorite($favorite)
    {
        $this->favorite = $favorite;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * @param string $notes
     *
     * @return Diff
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPrevDiffArchiveId()
    {
        return $this->prevDiffArchiveId;
    }

    /**
     * @param mixed $prevDiffArchiveId
     *
     * @return Diff
     */
    public function setPrevDiffArchiveId($prevDiffArchiveId)
    {
        $this->prevDiffArchiveId = $prevDiffArchiveId;

        return $this;
    }

    /**
     * Provides an array or document to serialize as BSON
     * Called during serialization of the object to BSON. The method must return an array or stdClass.
     * Root documents (e.g. a MongoDB\BSON\Serializable passed to MongoDB\BSON\fromPHP()) will always be serialized as a BSON document.
     * For field values, associative arrays and stdClass instances will be serialized as a BSON document and sequential arrays (i.e. sequential, numeric indexes starting at 0) will be serialized as a BSON array.
     * @link http://php.net/manual/en/mongodb-bson-serializable.bsonserialize.php
     * @return array|object An array or stdClass to be serialized as a BSON array or document.
     */
    public function bsonSerialize()
    {
        return [
            '_id' => $this->id,
            'proposalObjectId' => $this->proposalObjectId,
            'entityId' => $this->entityId,
            'newContent' => $this->newContent,
            'oldContent' => $this->oldContent,
            'legislativeOverride' => $this->legislativeOverride,
            'diffHash' => $this->diffHash,
            'status' => $this->status ?: self::STATUS_NONE,
            'prevStatus' => $this->prevStatus,
            'diffContent' => $this->diffContent,
            'favorite' => $this->favorite,
            'notes' => $this->notes,
            'prevDiffArchiveId' => $this->prevDiffArchiveId,
        ];
    }

    /**
     * Constructs the object from a BSON array or document
     * Called during unserialization of the object from BSON.
     * The properties of the BSON array or document will be passed to the method as an array.
     * @link http://php.net/manual/en/mongodb-bson-unserializable.bsonunserialize.php
     *
     * @param array $data Properties within the BSON array or document.
     */
    public function bsonUnserialize(array $data)
    {
        $this->id = $data['_id'];
        $this->proposalObjectId = $data['proposalObjectId'];
        $this->entityId = $data['entityId'];
        $this->newContent = $data['newContent'];
        $this->oldContent = $data['oldContent'];
        $this->legislativeOverride = $data['legislativeOverride'];
        $this->diffHash = $data['diffHash'];
        $this->status = $data['status'];
        $this->prevStatus = $data['prevStatus'];
        $this->diffContent = !empty($data['diffContent']) ? $data['diffContent'] : null;
        $this->favorite = isset($data['favorite']) ? $data['favorite'] : false;
        $this->notes = isset($data['notes']) ? $data['notes'] : null;
        $this->prevDiffArchiveId = isset($data['prevDiffArchiveId']) ? $data['prevDiffArchiveId'] : null;
    }
}
