<?php
require dirname(__DIR__) . '/vendor/autoload.php';

set_time_limit(0);

use W4Y\Crawler\Client\Client;
use W4Y\Crawler\Crawler;
use W4Y\Crawler\Filter;
use W4Y\Crawler\Plugin\Scraper;


$crawler = new Crawler;
$crawler->setClient(new Client(), 'CrawlerClient 1');

// Add a plugin to harvest data from a web page.
// The scraper plugin extends the Harvester which does the parsing of the html.
$scraper = new Scraper;

// Get the header title
$scraper->setHarvestRule('Page Title', 'h1');
$scraper->setHarvestRule('Headlines', '.mw-headline');

// Add filter to ignore certain URL's
$filter = new Filter;
$filter->setName('BasicFilter');

// Only harvest URL's that contain the text "bot".
$filter->setFilter('bot', Filter::MUST_CONTAIN);

// You can add multiple filters.
// Each filter must be valid for the URL to be valid to be scraped.
$filter->setFilter('bing', Filter::MUST_NOT_CONTAIN);

// The same filter could be initialized using this syntax also.
/*
$filter = new Filter('BasicFilter', array(
    array('match' => 'bot', 'type' => Filter::MUST_CONTAIN),
    array('match' => 'bing', 'type' => Filter::MUST_NOT_CONTAIN)
));
*/

// Set filter
$scraper->setFilter($filter);

// Set plugin
$crawler->setPlugin($scraper);

// Add URL to crawl
$crawler->addToPending('http://en.wikipedia.org/wiki/Web_crawler');
$crawler->addToPending('http://en.wikipedia.org/wiki/Googlebot');
$crawler->addToPending('http://en.wikipedia.org/wiki/Bingbot');
$crawler->addToPending('http://en.wikipedia.org/wiki/TkWWW_Robot#The_TkWWW_Robot');
$crawler->addToPending('http://en.wikipedia.org/wiki/PHP-Crawler');

$crawler->crawl();

$crawledUrls = $crawler->getCrawledUrls();
echo 'Crawled URL\'s<pre>' . print_r($crawledUrls, 1);

$data = $scraper->fetchData();
echo 'Scraped Data<pre>' . print_r($scraper->filterRawData($data), 1);

