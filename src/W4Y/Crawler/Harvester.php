<?php
namespace W4Y\Crawler;

use W4Y\Dom\Selector;

class Harvester
{
    private $harvestedData = null;
    private $harvestRules = array();
    private $harvestFile = null;
    private $renderType = self::HARVEST_AS_ARRAY;

    const HARVEST_AS_OBJECT = 0;
    const HARVEST_AS_ARRAY = 1;

    /**
     * @param $type
     * @throws \Exception
     */
    public function setRenderType($type)
    {
        if (!in_array($type, array(self::HARVEST_AS_ARRAY, self::HARVEST_AS_OBJECT))) {
            throw new \Exception('Invalid render type supplied');
        }

        $this->renderType = $type;
    }

    /**
     * Set harvest rule
     *
     * @param $name
     * @param $selector
     */
    public function setHarvestRule($name, $selector)
    {
        $this->harvestRules[$name] = $selector;
    }

    /**
     * Set a harvest file
     *
     * File will be used to save the harvested data.
     *
     * @param $fileName
     * @param bool $appendToFile
     * @throws \Exception
     */
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

    /**
     * Get the harvest file
     *
     * @return bool|string|null
     */
    public function getHarvestFile()
    {
        if (empty($this->harvestFile)) {
            return false;
        }

        return $this->harvestFile;
    }

    /**
     * Get the Harvest rules
     *
     * @return array
     */
    public function getHarvestRules()
    {
        return $this->harvestRules;
    }

    /**
     * Clear all harvest rules
     */
    public function clearRules()
    {
        $this->harvestRules = array();
    }

    /**
     * Clear any harvested data
     */
    public function clearData()
    {
        $this->harvestedData = null;
    }

    /**
     * Fetch data.
     *
     * Return the previously harvested data. If a callback is passed then
     * we iterate the harvested data and pass each item one by one to the callback.
     *
     * @return array|bool
     */
    final public function fetchData()
    {
        $isCallback = false;
        $callback = null;

        $args = func_get_args();
        if (isset($args[0]) && is_callable($args[0])) {
            $isCallback = true;
            $callback = $args[0];
        }

        $harvestData = array();
        $harvestFile = $this->getHarvestFile();
        if (is_string($harvestFile)) {

            if (!is_file($harvestFile)) {
                return false;
            }

            $file = new \SplFileObject($harvestFile);
            foreach ($file as $lineNr => $line) {

                if (!is_string($line)) {
                    continue;
                }

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

    /**
     * Organize harvest rules
     *
     * @param $harvestRules
     * @return array
     */
    private function organizeHarvestRules($harvestRules)
    {
        $newData = array();
        foreach ($harvestRules as $harvestName => $harvestData) {
            $newData[$harvestName] = $this->organizeHarvestRule($harvestData);
        }

        return $newData;
    }

    /**
     * Organize harvest rule
     *
     * @param $data
     * @return array|object
     */
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

    /**
     * Harvest
     *
     * Parse the string and harvest the data matched by
     * the harvest rules that were set.
     * If harvest file was set, save data serialized to the harvest file, otherwise
     * save in memory.
     *
     * @param $key
     * @param $body
     * @param array $customData
     */
    public function harvest($key, $body, $customData = array())
    {
        $data = array();
        $data['_custom'] = $customData;

        $sel = new Selector();
        $sel->setBody($body);

        foreach ($this->getHarvestRules() as $rule => $selector) {

            try {
                $res = $sel->query($selector)->result();
            } catch (\Exception $e) {
                // If we fail to harvest the data set a empty data collection.
                $res = 'ERROR';
            }

            $data[$rule] = $res;
        }

        $harvestFile = $this->getHarvestFile();
        if (!empty($harvestFile)) {

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

    /**
     * Filter the array of fetched data.
     *
     * @param $data
     * @return array
     */
    public function filterRawData($data)
    {
        $filteredData = array();
        foreach ($data as $key => $dataArray) {
            $filteredData[$key] = $this->filterDataArray($dataArray);
        }

        return $filteredData;
    }

    /**
     * Filter fetched data
     *
     * @param $data
     * @return array
     */
    private function filterDataArray($data)
    {
        $dataArray = array();
        foreach ($data as $dKey => $dVal) {

            if (is_array($dVal)) {

                foreach ($dVal as $dKey2 => $dVal2) {

                    if (is_array($dVal2)) {

                        if (!empty($dVal2['text'])) {
                            $dataArray[$dKey][] = trim($dVal2['text']);
                        }

                    } else {
                        $dataArray[$dKey][$dKey2] = trim($dVal2);
                    }
                }
            } else {
                $dataArray[$dKey] = trim($dVal);
            }
        }

        return $dataArray;
    }
}