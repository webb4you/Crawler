<?php
namespace W4Y\Crawler\Response;

use W4Y\Crawler\Client\ClientInterface;

/**
 * Response
 *
 */
class Response implements ResponseInterface
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function getBody()
    {
        $this->client->getBody();
    }

    public function getStatusCode()
    {
        $this->client->getStatusCode();
    }
}