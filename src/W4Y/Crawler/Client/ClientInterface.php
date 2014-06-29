<?php
namespace W4Y\Crawler\Client;

/**
 * ClientInterface
 *
 */
interface ClientInterface
{
    public function setUrl($url);
    public function request();
}