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

    public function add($dataType, $data, $parentKey = null)
    {
        $this->verifyStorage($dataType);

        $dataKey = current(array_keys($data));
        $dataValue = current(array_values($data));

        if (!empty($parentKey)) {

            if (empty($this->storage[$dataType][$parentKey])) {
                $this->storage[$dataType][$parentKey] = array();
            }
            $this->storage[$dataType][$parentKey][$dataKey] = $dataValue;

        } else {
            $this->storage[$dataType][$dataKey] = $dataValue;
        }
    }

    public function get($dataType, $fetchSingleResult = false)
    {
        $this->verifyStorage($dataType);
        $data = $this->storage[$dataType];

        if ($fetchSingleResult) {

            $dataKey = current(array_keys($data));
            $dataValue = current(array_values($data));

            $data = array();
            $data[$dataKey] = $dataValue;
        }

        return $data;
    }

    public function has($dataType, $id)
    {
        $this->verifyStorage($dataType);

        return array_key_exists($id, $this->storage[$dataType]);
    }

    public function remove($dataType, $id)
    {
       // echo print_r($this->storage[$dataType]);
        unset($this->storage[$dataType][$id]);
       // echo print_r($this->storage[$dataType]);
    }

    public function reset()
    {
        $this->storage = array();
    }

    private function verifyStorage($dataType)
    {
        if (!isset($this->storage[$dataType])) {
            $this->storage[$dataType] = array();
        }
    }
}