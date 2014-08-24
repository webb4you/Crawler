<?php
namespace W4Y\Crawler\Storage;

/**
 * StorageInterface
 *
 */
interface StorageInterface
{
    public function add($dataType, $data, $parentKey = null);
    public function get($dataType, $fetchSingleResult = false);
    public function has($dataType, $id);
    public function remove($dataType, $id);
    public function reset();
}