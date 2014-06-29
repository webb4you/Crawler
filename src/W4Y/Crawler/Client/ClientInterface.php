<?php
namespace W4Y\Crawler\Client;

/**
 * ClientInterface
 *
 * Crawler client interface
 *
 */
interface ClientInterface
{
    public function setUrl();
    public function request();
    public function getBody();
    public function getResponseCode();
}