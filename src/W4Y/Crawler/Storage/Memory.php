<?php
namespace W4Y\Crawler\Storage;

/**
 * Memory
 *
 * Save data in memory.
 *
 */
class Memory implements StorageInterface
{
    /** @var array $storage */
    private $storage = array();

    public function add($key, $data)
    {
        $this->verifyStorage($key);
        $this->storage[$key] = array_merge($this->storage[$key], $data);
    }

    public function get($key)
    {
        $this->verifyStorage($key);
        return $this->storage[$key];
    }

    public function set($key, $data)
    {
        $this->verifyStorage($key);
        $this->storage[$key] = $data;
    }

    public function reset()
    {
        $this->storage = array();
    }

    private function verifyStorage($key)
    {
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = array();
        }
    }
}