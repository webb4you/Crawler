<?php
require dirname(__DIR__) . '/vendor/autoload.php';
use W4Y\Crawler\Crawler;
use W4Y\Dom\Element;

/**
 * HarvestPlugin
 *
 * Basic Harvest Plugin
 *
 * Plugins extends the main Crawler\Plugin\Harvester
 * and extends the postCrawlLoop to process the data
 * that was harvest based on the harvest rules that were
 * set. Harvested data can be processed with a callback.
 *
 */
class HarvestPlugin extends W4Y\Crawler\Plugin\Scraper
{
    static private $dataCnt = 0;

    private $filePath;

    private $index = 0;

    public function __construct($filePath)
    {
        $fileName = 'scrape_' . date('Y-m-d_H-i-s') . '.crawl';
        $fullName = rtrim($filePath, '/') . '/' . $fileName;
        if (file_exists($fullName)) {
            throw new Exception('File exists :' . $fullName);
        }

        if (!is_writable(dirname($fullName))) {
            throw new Exception('Directory not writable :' . dirname($fullName));
        }

        $this->filePath = $fullName;
    }

    public function postCrawl(Crawler $crawler)
    {
        $callback = array($this, 'process');
        $data = $this->fetchData();
        echo '<pre>' . print_r($this->filterData($data), 1);
        echo $this->getNewLine() . 'Harvested: ' . self::$dataCnt;
    }

    public function postRequest(Crawler $crawler)
    {
        $this->index++;

        // Data will be processed using a callback.
        $now = date('d-m-s H:i:s');

        $data = $crawler->getLastRequestData();
        $crawlString =  sprintf('%d [%s] - Crawled [%d] : %s', $this->index, $now, $data['status'], $data['url']) . PHP_EOL;
        // echo print_r($crawlString, 1);
        file_put_contents($this->filePath, $crawlString, FILE_APPEND);
    }

    public function preRequest(Crawler $crawler)
    {
        $client = $crawler->getClient();
        //echo 'Crawling From : ' . get_class($client) . ' - ' . $crawler->getClientName() . PHP_EOL;
        //echo self::getNewLine();
    }

    static public function process($dt)
    {
        self::$dataCnt++;
        echo self::getNewLine() . self::$dataCnt . '<pre>' . print_r($dt, 1);
    }

    static public function getNewLine()
    {
        $newLine = '<br>';
        if ('cli' == strtolower(PHP_SAPI)) {

            $newLine = PHP_EOL;
        }

        return $newLine;
    }
}