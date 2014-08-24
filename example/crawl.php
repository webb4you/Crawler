<?php
require dirname(__DIR__) . '/vendor/autoload.php';

set_time_limit(0);

use W4Y\Crawler\Client\Client;
use W4Y\Crawler\Crawler;

use W4Y\Crawler\Storage\MySQL;

$dbFile = __DIR__ . '/sqllite.db';
$storage = new MySQL('root', 'myDB10293847', 'testDB');


$crawler = new Crawler;
$crawler->setClient(new Client(), 'CrawlerClient 1');

//$storage->reset();
//$crawler->setStorage($storage);

// Add URL to crawl
$crawler->addToPending('http://dev.webb4you.com/sandbox/link_1.php');
$crawler->addToPending('http://dev.webb4you.com/sandbox/link_6.php');

$crawler->crawl();

$stats = $crawler->getClientStats();
echo '<pre>' . print_r($stats, 1);

$foundUrls = $crawler->getFoundUrls();
echo '<pre>' . print_r($foundUrls, 1);

$foundUrls = $crawler->getLastRequestData();
echo '<pre>' . print_r($foundUrls, 1);