<?php
namespace W4Y\Crawler\Client;

use W4Y\Crawler\Response\Response;

/**
 * Response
 *
 */
class Client implements ClientInterface
{
    private $url;
    private $statusCode;
    private $body;

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function request()
    {
        if (empty($this->url)) {
            throw new \Exception('You must first set a URL to request.');
        }

        $this->setStatusCode(200);
        $this->setBody('Body');

        return new Response($this);
    }

    private function setStatusCode($code)
    {
        $this->statusCode = $code;
    }

    private function setBody($body)
    {
        $this->body = $body;
    }

    public function getStatusCode()
    {
        return $this->getStatusCode();
    }

    public function getBody()
    {
        return $this->body;
    }
}