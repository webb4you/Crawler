<?php
namespace W4Y\Crawler\Client;

/**
 * Client
 *
 */
class Client implements ClientInterface
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

        $status = @file_get_contents($this->url);
        if (($status)) {
            $this->setBody($status);
            $this->setResponseCode(200);
        } else {
            $this->setBody('Error');
            $this->setResponseCode(404);
        }

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