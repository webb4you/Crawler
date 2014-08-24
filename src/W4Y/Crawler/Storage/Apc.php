<?php
namespace W4Y\Crawler\Storage;
use SebastianBergmann\Exporter\Exception;

/**
 * Apc
 *
 * Save data in memory.
 *
 */
class Apc implements StorageInterface
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

        $this->saveStorage($dataType);
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
        $this->verifyStorage($dataType);
       // echo print_r($this->storage[$dataType]);
        unset($this->storage[$dataType][$id]);
       // echo print_r($this->storage[$dataType]);
        $this->saveStorage($dataType);
    }

    public function reset()
    {
        $this->storage = array();
    }

    private function saveStorage($dataType, $apcData = null)
    {
        if (null !== $apcData) {
            $status = apc_add($dataType, $apcData, 86400);
        } else {
            $status = apc_store($dataType, $this->storage[$dataType]);
        }

        if (empty($status)) {

            throw new \Exception('Error saving to APC');
        }
    }

    private function verifyStorage($dataType)
    {
        $apcData = apc_fetch($dataType);
        //var_dump('DATA::', $apcData);

        if (empty($apcData)) {
            $apcData = array();
            $this->saveStorage($dataType, $apcData);
        }

        //var_dump($apcData);
        $this->storage[$dataType] = $apcData;
        //die;


//        if (!isset($this->storage[$dataType])) {
//            $this->storage[$dataType] = array();
//        }
    }
}