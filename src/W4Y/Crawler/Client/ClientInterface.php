<?php
namespace W4Y\Crawler\Client;

/**
 * ClientInterface
 *
 */
interface ClientInterface
{
    public function setUrl($url);
    public function getUrl();
    public function request();
    public function getFinalUrl();
    public function getResponseCode();
    public function isResponseSuccess();
}