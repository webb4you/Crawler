<?php
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/HarvestPlugin.php';

use W4Y\Crawler\Client\Client;
use W4Y\Crawler\Crawler;
use W4Y\Crawler\Filter;

$client1 = new Client();
$client2 = new Client();

// Initialize Crawler
$crawler = new Crawler(array(
    'recursiveCrawl' => true, // Should the crawler follow other URL's on the page.
    'maxUrlFollows' => 25, // Maximum amount of URL's to follow.
    'externalFollows' => false // Follow URL's that lead to an external resource.
));

$crawler->setClient($client1, 'Client 1');
$crawler->setClient($client2, 'Client 2');


// Add URL's to crawl
//$crawler->addToPending('http://en.wikipedia.org/wiki/Web_crawler');
//$crawler->addToPending('http://en.wikipedia.org/wiki/DataparkSearch');
$crawler->addToPending('http://dev.webb4you.com/sandbox/');

$filter = new Filter('MyRequestFilter', array(
    array('match' => 'german', 'type' => Filter::MUST_CONTAIN)
));
$crawler->setRequestFilter($filter);

// Initialize basic harvest Plugin and set some harvest rules.
$harvester = new HarvestPlugin();

// Get the header title
$harvester->setHarvestRule('Provinces', 'table.wikitable tr td:nth-last-of-type(2)');

// I know there is a section with open source crawlers.
//$harvester->setHarvestRule('OpenSourceCrawler', 'h3:nth-last-of-type(1) + ul i a');

// How do we want to return this harvested data.
$harvester->setRenderType(HarvestPlugin::HARVEST_AS_ARRAY);

// Only harvest from specific URL's. Only the Web_crawler page will be harvested.
$filter = new Filter('MyHarvestFilter', array(
    array('match' => '#crawler#', 'type' => Filter::MUST_MATCH)
));
//$harvester->setFilter($filter);

// Optionally save the harvested data directly to a file to reduce memory usage or
// to process the data at a later time.
// By default the harvested data is saved in memory.
// $harvester->setHarvestFile(__DIR__ . '/log/harvest.log', false);

// Set crawler plugin / harvester
// Other plugins can also be assigned to.
 $crawler->setPlugin($harvester);

// Start crawling
$crawler->crawl();

$stats = $crawler->getClientStats();


echo $harvester->getNewLine() . 'CRAWLED LAST::<pre>' . print_r($crawler->getLastRequestData(), 1);

echo '<pre>' . print_r($stats, 1);

echo '<pre>' . print_r($crawler->getCrawledUrls(), 1);