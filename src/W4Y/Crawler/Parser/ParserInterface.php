<?php
namespace W4Y\Crawler\Parser;

/**
 * ParserInterface
 *
 */
interface ParserInterface
{
    public function setDomain($domain);
    public function getDomain();

    /**
     * Return array of objects.
     * Object must contain a url property.
     *
     * @param $html
     * @return mixed
     */
    public function getUrls($html);
    //public function formatUrl($url);
}