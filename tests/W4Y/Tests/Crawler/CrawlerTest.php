<?php
namespace W4Y\Tests\Crawler;
use W4Y\Crawler\Crawler;
use W4Y\Tests\Crawler\Client\MockClient;

class CrawlerTest extends \PHPUnit_Framework_TestCase
{
    /** @var Crawler filter */
    private $crawler;

    public function setUp()
    {
        $this->crawler = new Crawler();

        $client = new MockClient();
        $this->crawler->setClient($client);
    }

    public function testCanAddPendingUrls()
    {
        $url = 'http://www.example.com';
        $url2 = 'http://www.example.com/about.html';
        $this->crawler->addToPending($url);
        $this->crawler->addToPending($url2);

        $pendingUrls = $this->crawler->getPending();

        $this->assertCount(2, $pendingUrls);
    }

    public function testCannotAddDuplicateUrls()
    {
        $url = 'http://www.example.com';
        $url2 = 'http://www.example.com';
        $this->crawler->addToPending($url);
        $this->crawler->addToPending($url2);

        $pendingUrls = $this->crawler->getPending();

        $this->assertCount(1, $pendingUrls);
    }

    public function testCanRoundRobinClients()
    {
        $client1 = new MockClient();
        $client1->setName('Client 1');
        $this->crawler->setClient($client1);

        $client2 = new MockClient();
        $client2->setName('Client 2');
        $this->crawler->setClient($client2);

        $url = 'http://www.example.com';
        $url2 = 'http://www.example.com/1.html';
        $this->crawler->addToPending($url);
        $this->crawler->addToPending($url2);

        $this->crawler->crawl();
    }

}