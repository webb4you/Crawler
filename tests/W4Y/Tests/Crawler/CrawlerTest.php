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
    }

    public function testCanAddPendingUrls()
    {
        $client = new MockClient();
        $this->crawler->setClient($client);

        $url = 'http://www.example.com';
        $url2 = 'http://www.example.com/about.html';
        $url3 = 'http://www.example.com/home.html';

        $this->crawler
            ->addToPending($url)
            ->addToPending($url2)
            ->addToPending($url3);

        $pendingUrls = $this->crawler->getPending();
        $this->assertCount(3, $pendingUrls);
    }

    public function testCannotAddDuplicateUrls()
    {
        $url = 'http://www.example.com';
        $url2 = 'http://www.example.com';
        $this->crawler
            ->addToPending($url)
            ->addToPending($url2);

        $pendingUrls = $this->crawler->getPending();

        $this->assertCount(1, $pendingUrls);
    }

    public function testCanCrawlWithSingleClient()
    {
        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');

        // Client 1
        $url = 'http://www.example.com/page1.html';
        $this->crawler->addToPending($url);

        // Client 1
        $url = 'http://www.example.com/page2.html';
        $this->crawler->addToPending($url);

        // Client 1
        $url = 'http://www.example.com/page3.html';
        $this->crawler->addToPending($url);

        // Client 1
        $url = 'http://www.example.com/page4.html';
        $this->crawler->addToPending($url);

        // Client 1
        $url = 'http://www.example.com/page5.html';
        $this->crawler->addToPending($url);

        // Client 1
        $url = 'http://www.example.com/page6.html';
        $this->crawler->addToPending($url);

        // Crawl URL's
        $this->crawler->crawl();

        // Fetch client stats
        $clientStats = $this->crawler->getClientStats();

        // Client 1 should have crawled 6 URL's.
        $client1Cnt = $clientStats[1][Crawler::STATS_CRAWL];
        $this->assertEquals(6, $client1Cnt);
    }

    public function testCanRoundRobinClients()
    {
        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');
        $this->crawler->setClient(new MockClient(), 'Client 2');
        $this->crawler->setClient(new MockClient(), 'Client 3');

        // Client 1
        $url = 'http://www.example.com/page1.html';
        $this->crawler->addToPending($url);

        // Client 2
        $url = 'http://www.example.com/page2.html';
        $this->crawler->addToPending($url);

        // Client 3
        $url = 'http://www.example.com/page3.html';
        $this->crawler->addToPending($url);

        // Client 1
        $url = 'http://www.example.com/page4.html';
        $this->crawler->addToPending($url);

        // Client 2
        $url = 'http://www.example.com/page5.html';
        $this->crawler->addToPending($url);

        // Client 3
        $url = 'http://www.example.com/page6.html';
        $this->crawler->addToPending($url);

        // Client 1
        $url = 'http://www.example.com/page7.html';
        $this->crawler->addToPending($url);

        // Client 2
        $url = 'http://www.example.com/page8.html';
        $this->crawler->addToPending($url);

        // Crawl URL's
        $this->crawler->crawl();

        // Fetch client stats
        $clientStats = $this->crawler->getClientStats();

        // Client 1 should have crawled 3 URL's.
        $client1Cnt = $clientStats[1][Crawler::STATS_CRAWL];
        $this->assertEquals(3, $client1Cnt);

        // Client 2 should have crawled 3 URL's.
        $client2Cnt = $clientStats[2][Crawler::STATS_CRAWL];
        $this->assertEquals(3, $client2Cnt);

        // Client 3 should have crawled 2 URL's.
        $client2Cnt = $clientStats[3][Crawler::STATS_CRAWL];
        $this->assertEquals(2, $client2Cnt);
    }

    public function testCanCrawlAndSaveClientStats()
    {
        // Client 1
        $url = 'http://www.example.com/page1.html';

        // Test a success response
        // -------------------------
        // Mock Client Interface
        $client = $this->getMock('W4Y\Crawler\Client\ClientInterface');

        $client->expects($this->once())
            ->method('getResponseCode')
            ->will($this->returnValue(200));

        $client->expects($this->any())
            ->method('isResponseSuccess')
            ->will($this->returnValue(true));

        // Crawl
        $this->crawler->setClient($client, 'Client 1');
        $this->crawler->addToPending($url);
        $this->crawler->crawl();

        // Fetch client stats
        $clientStats = $this->crawler->getClientStats();
        $stats = $clientStats[1];

        $this->assertEquals(1, $stats[Crawler::STATS_SUCCESS]);
        $this->assertEquals(1, $stats[Crawler::STATS_ATTEMPT]);


        // Test a failed response
        // -------------------------
        // Mock Client Interface
        $client = $this->getMock('W4Y\Crawler\Client\ClientInterface');

        $client->expects($this->once())
            ->method('getResponseCode')
            ->will($this->returnValue(200));

        $client->expects($this->any())
            ->method('isResponseSuccess')
            ->will($this->returnValue(false));

        // Crawl
        $this->crawler->setClient($client, 'Client 1');
        $this->crawler->addToPending($url);
        $this->crawler->crawl();

        // Fetch client stats
        $clientStats = $this->crawler->getClientStats();
        $stats = $clientStats[1];

        $this->assertEquals(1, $stats[Crawler::STATS_FAIL]);
        $this->assertEquals(2, $stats[Crawler::STATS_ATTEMPT]);


        // Test a exception
        // -------------------------
        // Mock Client Interface
        $client = $this->getMock('W4Y\Crawler\Client\ClientInterface');

        $client->expects($this->once())
            ->method('getResponseCode')
            ->will($this->returnValue(200));

        $client->expects($this->any())
            ->method('isResponseSuccess')
            ->will($this->throwException(new \Exception('Invalid response')));

        // Crawl
        $this->crawler->setClient($client, 'Client 1');
        $this->crawler->addToPending($url);
        $this->crawler->crawl();

        // Fetch client stats
        $clientStats = $this->crawler->getClientStats();
        $stats = $clientStats[1];

        $this->assertEquals(1, $stats[Crawler::STATS_ERROR]);
        $this->assertEquals(1, $stats[Crawler::STATS_FAIL]);
        $this->assertEquals(1, $stats[Crawler::STATS_SUCCESS]);
        $this->assertEquals(3, $stats[Crawler::STATS_CRAWL]);
    }

    public function testCanCallPluginSuccessHooks()
    {
        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');

        // Client 1
        $url = 'http://www.example.com/page1.html';
        $this->crawler->addToPending($url);

        // Mock Plugin Interface
        $plugin = $this->getMock('W4Y\Crawler\Plugin\PluginInterface');

        $plugin->expects($this->once())
            ->method('preCrawl');

        $plugin->expects($this->once())
            ->method('preRequest');

        $plugin->expects($this->once())
            ->method('onSuccess');

        $plugin->expects($this->once())
            ->method('postRequest');

        $plugin->expects($this->once())
            ->method('postCrawl');

        $this->crawler->setPlugin($plugin);

        $this->crawler->crawl();
    }

    public function testCanCallPluginFailHooks()
    {
        // Mock Client Interface
        $client = $this->getMock('W4Y\Crawler\Client\ClientInterface');
        $client->expects($this->once())
            ->method('isResponseSuccess')
            ->will($this->returnValue(false));

        // Mock Plugin Interface
        $plugin = $this->getMock('W4Y\Crawler\Plugin\PluginInterface');
        $plugin->expects($this->once())
            ->method('preCrawl');

        $plugin->expects($this->once())
            ->method('preRequest');

        $plugin->expects($this->once())
            ->method('onFailure');

        $plugin->expects($this->once())
            ->method('postRequest');

        $plugin->expects($this->once())
            ->method('postCrawl');

        // Set crawler clients
        $this->crawler->setClient($client, 'Client 1');

        // Client 1
        $url = 'http://www.example.com';
        $this->crawler->addToPending($url);

        $this->crawler->setPlugin($plugin);
        $this->crawler->crawl();
    }
}