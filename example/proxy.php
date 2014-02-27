<?php
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/HarvestPlugin.php';

use Zend\Http\Client\Adapter\Proxy;
use W4Y\Crawler\Client;
use W4Y\Crawler\Crawler;

// Initialize Crawler
$crawler = new Crawler(array(
    'recursiveCrawl' => false, // Should the crawler follow other URL's on the page.
    'maxUrlFollows' => 10, // Maximum amount of URL's to follow.
    'externalFollows' => false // Follow URL's that lead to an external resource.
));

// Add URL's to crawl
$crawler->addToPending('http://en.wikipedia.org/wiki/Web_crawler');
$crawler->addToPending('http://en.wikipedia.org/wiki/DataparkSearch');

// Get Harvester / Plugin
$harvester = new HarvestPlugin();
$harvester->setHarvestRule('title', 'h1');

// Assign the harvester plugin to the crawler.
// Other plugins can also be assigned to.
$crawler->setPlugin($harvester);

// Assuming the proxy is working correct you can crawl using a proxy.
$proxy = new Proxy();
$proxy->setOptions(array(
    'proxy_host' => '212.156.86.242',
    'proxy_port' => 80,
));

// Set a client
$crawler->setClient(new Client());

// Set a proxy client
$client2 = new Client;
$client2->setAdapter($proxy);
$crawler->setClient($client2);

// Start crawling
$crawler->crawl();

echo $harvester->getNewLine() . 'CRAWLED LAST::<pre>' . print_r($crawler->getCrawledUrls(), 1);