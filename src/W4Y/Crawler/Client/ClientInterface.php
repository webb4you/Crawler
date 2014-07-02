<?php
namespace W4Y\Crawler\Client;

use W4Y\Crawler\Response\ResponseInterface;

/**
 * ClientInterface
 *
 */
interface ClientInterface
{
    public function setUrl($url);
    public function getUrl();
    public function request();
    public function getResponseCode();
}