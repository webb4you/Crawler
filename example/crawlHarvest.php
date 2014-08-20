<?php
require dirname(__DIR__) . '/vendor/autoload.php';

set_time_limit(0);

use W4Y\Crawler\Client\Client;
use W4Y\Crawler\Crawler;
use W4Y\Crawler\Plugin\Scraper;


$crawler = new Crawler;
$crawler->setClient(new Client(), 'CrawlerClient 1');

// Add a plugin to harvest data from a web page.
// The scraper plugin extends the Harvester which does the parsing of the html.
$scraper = new Scraper;

// Set harvest rules using CSS selectors to parse html
$scraper->setHarvestRule('Page Title', 'h1');
$scraper->setHarvestRule('Headlines', '.mw-headline');
$scraper->setHarvestRule('OpenSourceCrawler', 'h3:nth-last-of-type(1) + ul i a');

// You can also save the scraped data to a file to reduce memory usage or to process the data at a later time.
// By default the harvested data is saved in memory.
// $scraper->setHarvestFile(YOUR_FILE_LOCATION, false);

// Set plugin
$crawler->setPlugin($scraper);

// Add URL to crawl
$crawler->addToPending('http://en.wikipedia.org/wiki/Web_crawler');
$crawler->crawl();

$data = $scraper->fetchData();
echo 'Scraped Data<pre>' . print_r($scraper->filterRawData($data), 1);

