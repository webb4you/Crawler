<?php
namespace W4Y\Crawler\Client;

/**
 * Client
 *
 */
class Client implements ClientInterface
{
    private $curl;
    private $url;
    private $body;
    private $responseCode;

    public function __construct()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $this->curl = $ch;
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

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

        curl_setopt($this->curl, CURLOPT_URL, $this->url);

        $response = curl_exec($this->curl);
        $responseInfo = curl_getinfo($this->curl);

        $this->setResponseCode($responseInfo['http_code']);

        $this->setBody($response);
        $this->url = $responseInfo['url'];

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