<?php
namespace W4Y\Crawler\Parser;

/**
 * ParserInterface
 *
 */
interface ParserInterface
{
    /**
     * Return array of objects.
     * Object must contain a url property.
     *
     * @param $html
     * @return mixed
     */
    public function getUrls($html);

    /**
     * Format a URL to a consisten one.
     *
     * Format url is expected to change relative urls to absolute ones.
     * Possibly remove hash tags and query strings etc.
     *
     * @param $url
     * @return string
     */
    public function formatUrl($url);

    /**
     * Set the domain of the URL.
     *
     * Domain will be needed for pages with relative links.
     * Always best to crawl with absolute urls
     *
     * @param $domain
     * @return void
     */
    public function setDomain($domain);

    /**
     * Return the set domain
     *
     * @return string|null
     */
    public function getDomain();
}