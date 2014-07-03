<?php
namespace W4Y\Crawler;

use W4Y\Dom\Selector;

abstract class AbstractHarvester
{
    private $harvestedData = null;

    private $harvestRules = array();

    private $harvestFile = null;

    private $renderType = self::HARVEST_AS_OBJECT;

    const HARVEST_AS_OBJECT = 0;

    const HARVEST_AS_ARRAY = 1;

    public function setRenderType($type)
    {
        if (!in_array($type, array(self::HARVEST_AS_ARRAY, self::HARVEST_AS_OBJECT))) {
            throw new \Exception('Invalid render type supplied');
        }

        $this->renderType = $type;
    }

    public function setHarvestRule($name, $selector)
    {
        $this->harvestRules[$name] = $selector;
    }

    public function setHarvestFile($fileName, $appendToFile = false)
    {
        if (is_file($fileName) && !is_writable($fileName)) {
            throw new \Exception(sprintf('The file [%s] already exists and is not writable.', $fileName));
        }

        $dir = dirname($fileName);
        if (!is_writable($dir) || empty($fileName)) {
            throw new \Exception(sprintf('Directory [%s] is not writable.', $dir));
        }

        // Erase file
        if (is_file($fileName) && !$appendToFile) {
            unlink($fileName);
        }

        $this->harvestFile = $fileName;
    }

    public function getHarvestFile()
    {
        if (empty($this->harvestFile)) {
            return false;
        }

        return $this->harvestFile;
    }

    public function getHarvestRules()
    {
        return $this->harvestRules;
    }

    public function clearRules()
    {
        $this->harvestRules = null;
    }

    public function clearData()
    {
        $this->harvestedData = null;
    }

    final public function processData()
    {
        $isCallback = false;

        $args = func_get_args();
        if (isset($args[0]) && is_callable($args[0])) {
            $isCallback = true;
            $callback = $args[0];
        }

        $harvestData = array();
        $harvestFile = $this->getHarvestFile();
        if ($harvestFile) {

            if (!is_file($harvestFile)) {
                return false;
            }

            $file = new \SplFileObject($harvestFile);
            foreach ($file as $lineNr => $line) {
                $_data = unserialize(trim($line));

                if (empty($_data)) {
                    continue;
                }

                if (!$isCallback) {
                    $harvestData[] = $_data;
                } else {
                    call_user_func($callback, $_data);
                }

            }

        } else {
            $harvestedData = (array) $this->harvestedData;
            foreach ($harvestedData as $data) {

                if (!$isCallback) {
                    $harvestData[] = $data;
                } else {
                    call_user_func($callback, $data);
                }
            }
        }

        if (!$isCallback) {
            return $harvestData;
        }
    }

    private function organizeHarvestRules($harvestRules)
    {
        $newData = array();

        foreach ($harvestRules as $harvestName => $harvestData) {
            $newData[$harvestName] = $this->organizeHarvestRule($harvestData);
        }

        return $newData;
    }

    private function organizeHarvestRule($data)
    {
        $tmpD = array();
        $renderType = $this->renderType;
        $data = (array) $data;
        foreach ($data as $hdKey => $hd) {

            $dt = $hd;
            if (self::HARVEST_AS_ARRAY == $renderType) {
                if (is_object($hd)) {
                    $dt = $hd->toArray();
                }
            }
            $tmpD[$hdKey] = $dt;
        }

        if (self::HARVEST_AS_OBJECT === $renderType) {
            $tmpD = (object) $tmpD;
        }

        return $tmpD;
    }


    public function harvest($key, $body, $customData = array())
    {
        $data = array();
        $data['_custom'] = $customData;

        $sel = new Selector();
        $sel->setBody($body);

        foreach ($this->getHarvestRules() as $rule => $selector) {

            try {
                $res = $sel->query($selector)->result();
            } catch (Exception $e) {
            }

            $data[$rule] = $res;
        }

        $harvestFile = $this->getHarvestFile();
        if ($harvestFile) {

            // When saving to file render type must be an array to
            // easily serialize the data.
            $this->setRenderType(self::HARVEST_AS_ARRAY);
            $data = $this->organizeHarvestRules($data);

            // Append to file
            file_put_contents(
                $harvestFile,
                serialize($data) . PHP_EOL,
                FILE_APPEND
            );

        } else {
            $data = $this->organizeHarvestRules($data);
            $this->harvestedData[$key] = $data;
        }
    }
}