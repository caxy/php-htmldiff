<?php

namespace Caxy\HtmlDiff\Demo\Model;

use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Persistable;
use MongoDB\BSON\UTCDatetime;

/**
 * Class DiffArchive
 * @package Caxy\HtmlDiff\Demo\Model
 */
class DiffArchive implements Persistable
{
    /**
     * @var ObjectID|string
     */
    private $id;
    /**
     * @var string
     */
    private $content;
    /**
     * @var string
     */
    private $prev;
    /**
     * @var int
     */
    private $proposalObjectId;
    /**
     * @var \DateTime
     */
    private $timestamp;
    /**
     * @var string
     */
    private $status;

    public function __construct()
    {
        $this->id = new ObjectID;

        // Get current time in milliseconds since the epoch
        $msec = floor(microtime(true) * 1000);
        $this->timestamp = new UTCDateTime($msec);
    }

    /**
     * @return ObjectID|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param ObjectID|string $id
     *
     * @return DiffArchive
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     *
     * @return DiffArchive
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getPrev()
    {
        return $this->prev;
    }

    /**
     * @param string $prev
     *
     * @return DiffArchive
     */
    public function setPrev($prev)
    {
        $this->prev = $prev;

        return $this;
    }

    /**
     * @return int
     */
    public function getProposalObjectId()
    {
        return $this->proposalObjectId;
    }

    /**
     * @param int $proposalObjectId
     *
     * @return DiffArchive
     */
    public function setProposalObjectId($proposalObjectId)
    {
        $this->proposalObjectId = $proposalObjectId;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param \DateTime $timestamp
     *
     * @return DiffArchive
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     *
     * @return DiffArchive
     */
    public function setStatus($status)
    {
        $this->status = $status;

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
            'content' => $this->content,
            'prev' => $this->prev,
            'proposalObjectId' => $this->proposalObjectId,
            'timestamp' => $this->timestamp,
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
        $this->content = $data['content'];
        $this->prev = $data['prev'];
        $this->proposalObjectId = $data['proposalObjectId'];
        $this->timestamp = $data['timestamp'];
    }
}
