<?php
require dirname(__DIR__) . '/vendor/autoload.php';

set_time_limit(0);

use W4Y\Crawler\Client\Client;
use W4Y\Crawler\Crawler;
use W4Y\Crawler\Storage\Apc as Storage;


$crawler = new Crawler;

$storage = new Storage('root', 'myDB10293847', 'testDB');
$storage->reset();
$crawler->setStorage($storage);

$crawler->setClient(new Client(), 'CrawlerClient 1');

// Set options
$crawler->setOption('recursiveCrawl', true);
$crawler->setOption('maxUrlFollows', 1000);

// Add URL to crawl
$crawler->addToPending('http://www.caribworkforce.com');
$crawler->crawl();

$stats = $crawler->getClientStats();
echo 'Crawl Stats<pre>' . print_r($stats, 1);

$crawledUrls = $crawler->getCrawledUrls();
//echo 'Crawled URL\'s<pre>' . print_r($crawledUrls, 1);