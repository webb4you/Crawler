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
class HarvestPlugin extends W4Y\Crawler\Plugin\Harvester
{
    static private $dataCnt = 0;

    public function postCrawl(Crawler $crawler)
    {
        // Data will be processed using a callback.
        $this->processData(array($this, 'process'));
        echo $this->getNewLine() . 'Harvested: ' . self::$dataCnt;
    }

    public function preRequest(Crawler $crawler)
    {
        $c = $crawler->getClient()->getAdapter()->getConfig();
        echo 'Crawling From : ' . (empty($c['proxy_host']) ? 'LocalHost' : $c['proxy_host']);
        echo self::getNewLine();
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