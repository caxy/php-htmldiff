<?php

namespace Caxy\Tests\HtmlDiff;

class HtmlFileIterator implements \Iterator
{
    protected $files = array();
    protected $key = 0;
    protected $loadedDiffs = array();

    public function __construct($directory)
    {
        $this->files = glob($directory.DIRECTORY_SEPARATOR."*.html");
    }

    /**
     * Return the current element
     * @link  http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return $this->loadHtmlFile($this->key);
    }

    /**
     * Move forward to next element
     * @link  http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        $this->key++;
    }

    /**
     * Return the key of the current element
     * @link  http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return basename($this->files[$this->key]);
    }

    /**
     * Checks if current position is valid
     * @link  http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return isset($this->files[$this->key]);
    }

    /**
     * Rewind the Iterator to the first element
     * @link  http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        $this->key = 0;
    }

    protected function loadHtmlFile($key)
    {
        $filename = $this->files[$key];

        if (!isset($this->loadedDiffs[$filename])) {

            $html = file_get_contents($filename);

            $oldText = $this->parseTagContent('oldText', $html);
            $newText = $this->parseTagContent('newText', $html);
            $expected = $this->parseTagContent('expected', $html);

            if (null === $expected) {
                throw new \Exception('HTML fixture content should have an <expected> tag.');
            }

            $this->loadedDiffs[$filename] = array($oldText, $newText, $expected);
        }

        return $this->loadedDiffs[$filename];
    }

    protected function parseTagContent($tagName, $html)
    {
        $matches = array();
        if (preg_match(sprintf('/<%s\s*[^>]*>(.*)<\/%s\s*>/is', $tagName, $tagName), $html, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
