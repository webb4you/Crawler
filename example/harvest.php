<?php
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/HarvestPlugin.php';

use Zend\Http\Client\Adapter\Proxy;
use W4Y\Crawler\Client;
use W4Y\Crawler\Crawler;
use W4Y\Crawler\Filter;

// Initialize Crawler
$crawler = new Crawler(array(
    'recursiveCrawl' => false, // Should the crawler follow other URL's on the page.
    'maxUrlFollows' => 10, // Maximum amount of URL's to follow.
    'externalFollows' => false // Follow URL's that lead to an external resource.
));

// Add URL's to crawl
$crawler->addToPending('http://en.wikipedia.org/wiki/Web_crawler');
$crawler->addToPending('http://en.wikipedia.org/wiki/DataparkSearch');

$filter = new Filter('MyRequestFilter', array(
    array('match' => '#movies\/[0-9]+#', 'type' => Filter::MUST_MATCH)
));
$crawler->setRequestFilter($filter);

// Initialize basic harvest Plugin and set some harvest rules.
$harvester = new HarvestPlugin();

// Get the header title
$harvester->setHarvestRule('title', 'h1');

// I know there is a section with open source crawlers.
$harvester->setHarvestRule('OpenSourceCrawler', 'h3:nth-last-of-type(1) + ul i a');

// How do we want to return this harvested data.
$harvester->setRenderType(HarvestPlugin::HARVEST_AS_ARRAY);

// Only harvest from specific URL's. Only the Web_crawler page will be harvested.
$filter = new Filter('MyHarvestFilter', array(
    array('match' => '#crawler#', 'type' => Filter::MUST_MATCH)
));
$harvester->setFilter($filter);

// Optionally save the harvested data directly to a file to reduce memory usage or
// to process the data at a later time.
// By default the harvested data is saved in memory.
// $harvester->setHarvestFile(__DIR__ . '/log/harvest.log', false);

// Set crawler plugin / harvester
// Other plugins can also be assigned to.
$crawler->setPlugin($harvester);

// Start crawling
$crawler->crawl();

echo $harvester->getNewLine() . 'CRAWLED LAST::<pre>' . print_r($crawler->getLastRequestData(), 1);