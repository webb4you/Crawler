<?php
require dirname(__DIR__) . '/vendor/autoload.php';

set_time_limit(0);

use W4Y\Crawler\Client\Client;
use W4Y\Crawler\Crawler;
use W4Y\Crawler\Filter;
use W4Y\Crawler\Plugin\Scraper;


$crawler = new Crawler;

// Add multiple clients to initialize round robin mode crawl.
// This is useful if you want to set up clients to crawl from different IP addresses.
$crawler->setClient(new Client(), 'CrawlerClient 1');
$crawler->setClient(new Client(), 'CrawlerClient 2');

// Add URL to crawl
$crawler->addToPending('http://en.wikipedia.org/wiki/Web_crawler');
$crawler->addToPending('http://en.wikipedia.org/wiki/Googlebot');
$crawler->addToPending('http://en.wikipedia.org/wiki/Bingbot');
$crawler->addToPending('http://en.wikipedia.org/wiki/TkWWW_Robot#The_TkWWW_Robot');
$crawler->addToPending('http://en.wikipedia.org/wiki/PHP-Crawler');

$crawler->crawl();

$crawledUrls = $crawler->getCrawledUrls();
echo 'Crawled URL\'s<pre>' . print_r($crawledUrls, 1);

$stats = $crawler->getClientStats();
echo 'Crawl Stats<pre>' . print_r($stats, 1);