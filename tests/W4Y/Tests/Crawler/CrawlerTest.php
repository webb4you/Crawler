<?php
namespace W4Y\Tests\Crawler;
use W4Y\Crawler\Crawler;

class CrawlerTest extends \PHPUnit_Framework_TestCase
{
    /** @var Crawler filter */
    private $crawler;

    public function setUp()
    {
        $this->crawler = new Crawler();
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

}