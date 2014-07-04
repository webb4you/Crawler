<?php
namespace W4Y\Crawler\Plugin;

use W4Y\Crawler\Harvester;
use W4Y\Crawler\Crawler;
use W4Y\Crawler\Filter;

/**
 * Harvester data and implement plugin interface so that hooks can
 * be implemented during the crawling process.
 *
 */
class Scraper extends Harvester implements PluginInterface
{
    /** @var Filter $filters */
    private $filters = array();

    /*
     * Set Filter
     *
     * @param Filter $filter
     */
    public function setFilter(Filter $filter)
    {
        $this->filters[] = $filter;
    }

    /**
     * Get Filters
     *
     * @return boolean|array
     */
    public function getFilters()
    {
        if (empty($this->filters)) {
            return false;
        }

        return $this->filters;
    }

    /**
     * Filter URL
     *
     * @param string $url
     * @return boolean
     */
    private function filterUrl($url)
    {
        $isValid = true;

        if (empty($this->filters)) {
            return $isValid;
        }

        // Filter strings
        foreach ($this->filters as $filter) {
            if (!$filter->isValid($url)) {
                $isValid = false;
                break;
            }
        }

        return $isValid;
    }

    /**
     * Check if the url can be harvested
     *
     * @param string $url
     * @return boolean
     */
    private function canBeHarvested($url)
    {
        return $this->filterUrl($url);
    }

    /**
     * Pre Crawl Hook
     *
     * @param \W4Y\Crawler\Crawler $crawler
     */
    public function preCrawl(Crawler $crawler) {}

    /**
     * Pre Request Hook
     *
     * @param \W4Y\Crawler\Crawler $crawler
     */
    public function preRequest(Crawler $crawler) {}

    /**
     * On Success Hook
     *
     * @param \W4Y\Crawler\Crawler $crawler
     */
    public function onSuccess(Crawler $crawler)
    {
        $url = $crawler->getClient()->getUrl();

        if ($this->canBeHarvested($url)) {

            $this->harvest(
                $crawler->hashString($url), // Key
                $crawler->getClient()->getBody(), // Html
                $crawler->getLastRequestData() // Custom Data
            );

        }
    }

    /**
     * On Failure Hook
     *
     * @param \W4Y\Crawler\Crawler $crawler
     */
    public function onFailure(Crawler $crawler) {}

    /**
     * Post Request Hook
     *
     * @param \W4Y\Crawler\Crawler $crawler
     */
    public function postRequest(Crawler $crawler) {}

    /**
     * Post Crawl Hook
     *
     * @param \W4Y\Crawler\Crawler $crawler
     */
    public function postCrawl(Crawler $crawler) {}
}