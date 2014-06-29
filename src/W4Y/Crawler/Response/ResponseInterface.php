<?php
namespace W4Y\Crawler\Response;

/**
 * ResponseInterface
 *
 */
interface ResponseInterface
{
    public function getBody();
    public function getStatusCode();
}