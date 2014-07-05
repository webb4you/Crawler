<?php
namespace W4Y\Crawler\Storage;

/**
 * StorageInterface
 *
 */
interface StorageInterface
{
    public function add($key, $data);
    public function get($key);
    public function set($key, $data);
    public function reset();
}