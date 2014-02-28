<?php
namespace W4Y\Crawler\Plugin;

use W4Y\Crawler\Crawler;

/**
 * Plugin
 * 
 * Crawler plugin interface
 * 
 * @author Ilan Rivers <ilan@webb4you.com>
 */
interface PluginInterface
{
    public function preCrawl(Crawler $crawler);
    public function preRequest(Crawler $crawler);
    public function onSuccess(Crawler $crawler);
    public function onFailure(Crawler $crawler);
    public function postRequest(Crawler $crawler);
    public function postCrawl(Crawler $crawler);
}