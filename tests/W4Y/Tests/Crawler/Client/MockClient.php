<?php
namespace W4Y\Tests\Crawler\Client;

use W4Y\Crawler\Client\ClientInterface;

/**
 * MockClient
 *
 */
class MockClient implements ClientInterface
{
    private $url;
    private $body;
    private $responseCode;

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getFinalUrl()
    {
        return $this->url;
    }

    public function isResponseSuccess()
    {
        if (200 === $this->getResponseCode()) {
            return true;
        }

        return false;
    }

    public function request()
    {
        if (empty($this->url)) {
            throw new \Exception('You must first set a URL to request.');
        }

        usleep(100000); // 0.1 seconds
        $this->setBody('MockBody');
        $this->setResponseCode(200);

        return $this;
    }

    public function setResponseCode($code)
    {
        $this->responseCode = $code;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        if (null === $this->body) {
            throw new \Exception('You must first do a request.');
        }

        if (empty($this->body)) {
            return '';
        }

        return $this->body;
    }
}