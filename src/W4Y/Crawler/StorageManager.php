<?php
namespace W4Y\Crawler;

use W4Y\Crawler\Storage\StorageInterface;

class StorageManager
{
    /** @var  StorageInterface $storage */
    private $storage;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * Add data to the storage.
     *
     * @param $listType
     * @param $data
     * @param $parentKey
     */
    public function addToStorage($listType, $data, $parentKey = null)
    {
        $this->getStorage()->add($listType, $data, $parentKey);
    }

    /**
     * @param $listType
     * @param $key
     * @return bool
     */
    public function hasInStorage($listType, $key)
    {
        return $this->getStorage()->has($listType, $key);
    }

    /**
     * Fetch one data object from the storage.
     *
     * @param $listType
     * @param $key
     */
    public function removeFromStorage($listType, $key)
    {
        $this->getStorage()->remove($listType, $key);
    }

    /**
     * Fetch data from the storage.
     *
     * @param $listType
     * @param bool $fetchSingleResult
     * @return array
     */
    public function getFromStorage($listType, $fetchSingleResult = false)
    {
        return $this->getStorage()->get($listType, $fetchSingleResult);
    }

    /**
     * Reset the storage
     */
    public function resetStorage()
    {
        $this->getStorage()->reset();
    }
}