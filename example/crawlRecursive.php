<?php
require dirname(__DIR__) . '/vendor/autoload.php';

set_time_limit(0);

use W4Y\Crawler\Client\Client;
use W4Y\Crawler\Crawler;


$crawler = new Crawler;
$crawler->setClient(new Client(), 'CrawlerClient 1');

// Set options
$crawler->setOption('recursiveCrawl', true);
$crawler->setOption('maxUrlFollows', 25);

// Add URL to crawl
$crawler->addToPending('http://en.wikipedia.org/wiki/Web_crawler');
$crawler->crawl();

$stats = $crawler->getClientStats();
echo 'Crawl Stats<pre>' . print_r($stats, 1);

$crawledUrls = $crawler->getCrawledUrls();
echo 'Crawled URL\'s<pre>' . print_r($crawledUrls, 1);