<?php
namespace W4Y\Tests\Crawler;

use W4Y\Crawler\Crawler;
use W4Y\Crawler\Filter;
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
        $client3Cnt = $clientStats[3][Crawler::STATS_CRAWL];
        $this->assertEquals(2, $client3Cnt);
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

    public function testOptionRecursiveCrawl()
    {
        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');

        $url = 'http://www.example.com';
        $this->crawler->addToPending($url);

        // Set Mock Parser Interface to return URL's
        $parser = $this->getMock('W4Y\Crawler\Parser\ParserInterface');
        $parser->expects($this->exactly(4))
            ->method('getUrls')
            ->will($this->returnValue($this->getUrlSetOne()));

        $parser->expects($this->any())
            ->method('formatUrl')
            ->will($this->returnCallback(function() {
                $args = func_get_args();
                return trim($args[0]);
            }));

        // Set parser that will return a fixed set of URL's.
        $this->crawler->setParser($parser);

        // Only crawl 1 page
        $this->crawler->setOption('maxUrlFollows', 4);

        // Set recursive crawl so that found URL's are added to pending queue
        $this->crawler->setOption('recursiveCrawl', true);

        // Crawl
        $this->crawler->crawl();

        // Parser returned 5 url's for each page request that are the same. URL's should not be
        // added to the queue if they were already found. So 5 url's + the original = 6 - the external domain is 5.
        // We should have pending 5 - maxUrlFollows = 1
        $pendingUrls = $this->crawler->getPending();
        $this->assertCount(1, $pendingUrls);
    }

    public function testOptionMaxUrlFollows()
    {
        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');

        $url = 'http://www.example.com';
        $this->crawler->addToPending($url);

        $url = 'http://www.example.com/page1.html';
        $this->crawler->addToPending($url);

        $url = 'http://www.example.com/page2.html';
        $this->crawler->addToPending($url);

        $this->crawler->setOption('maxUrlFollows', 2);

        // Crawl
        $this->crawler->crawl();

        // Max set at 2 so we have 1 pending url
        $pendingUrls = $this->crawler->getPending();
        $this->assertCount(1, $pendingUrls);

        // Max set at 2 so we crawled 2 url's
        $crawledUrls = $this->crawler->getCrawledUrls();
        $this->assertCount(2, $crawledUrls);
    }

    public function testOptionExternalFollows()
    {
        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');

        $url = 'http://www.example.com';
        $this->crawler->addToPending($url);

        $url = 'http://www.example.com/page1.html';
        $this->crawler->addToPending($url);

        $url = 'http://www.example2.com/';
        $this->crawler->addToPending($url);

        $url = 'http://www.example2.com/page1.html';
        $this->crawler->addToPending($url);

        $this->crawler->setOption('externalFollows', true);

        // Crawl
        $this->crawler->crawl();
        $stats = $this->crawler->getClientStats();
        $crawlCnt = $stats[1][Crawler::STATS_CRAWL];
        $this->assertEquals(4, $crawlCnt);

        $crawled = $this->crawler->getCrawledUrls();
        $this->assertCount(4, $crawled);
    }

    public function testOptionSleepInterval()
    {
        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');

        $url = 'http://www.example.com';
        $this->crawler->addToPending($url);

        $this->crawler->setOption('sleepInterval', 1);

        // Crawl
        $start = microtime(true);
        $this->crawler->crawl();
        $total = (microtime(true) - $start);

        $this->assertGreaterThan(1, $total);
    }

    public function testRequestFilter()
    {
        $filter = new Filter('TestFilter', array(
            array('match' => '#contact#', 'type' => Filter::MUST_MATCH),
            array('match' => 'help', 'type' => Filter::MUST_NOT_CONTAIN)
        ));

        // Set filter
        $this->crawler->setRequestFilter($filter);

        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');

        $url = 'http://www.example.com';
        $this->crawler->addToPending($url);

        // Set Mock Parser Interface to return URL's
        $parser = $this->getMock('W4Y\Crawler\Parser\ParserInterface');
        $parser->expects($this->any())
            ->method('getUrls')
            ->will($this->returnValue($this->getUrlSetOne()));

        $parser->expects($this->any())
            ->method('formatUrl')
            ->will($this->returnCallback(function() {
                $args = func_get_args();
                return trim($args[0]);
            }));

        // Set parser that will return a fixed set of URL's.
        $this->crawler->setParser($parser);

        // Set recursive crawl so that found URL's are added to pending queue
        $this->crawler->setOption('recursiveCrawl', true);

        // Crawl
        $this->crawler->crawl();

        $crawledUrls = $this->crawler->getCrawledUrls();

        // Second URL crawled should be contact.html
        $url = current(array_slice($crawledUrls, 1, 1));


        $this->assertEquals('http://www.example.com/contact.html', $url['url']);

        // Should have only crawled 2 urls.
        $this->assertCount(2, $crawledUrls);

        // ---------------------------------
        // Reset all data
        $this->crawler->reset();

        $filter = new Filter('TestFilter', array(
            array('match' => 'crawl', 'type' => Filter::MUST_CONTAIN),
            array('match' => '#page#', 'type' => Filter::MUST_NOT_MATCH)
        ));

        // Set filter
        $this->crawler->setRequestFilter($filter);

        // Set Mock Parser Interface to return URL's
        $parser = $this->getMock('W4Y\Crawler\Parser\ParserInterface');
        $parser->expects($this->any())
            ->method('getUrls')
            ->will($this->returnValue($this->getUrlSetOne()));

        $parser->expects($this->any())
            ->method('formatUrl')
            ->will($this->returnCallback(function() {
                $args = func_get_args();
                return trim($args[0]);
            }));

        // Set parser that will return a fixed set of URL's.
        $this->crawler->setParser($parser);

        // Set crawler clients
        $this->crawler->setClient(new MockClient(), 'Client 1');

        $url = 'http://www.example.com';
        $this->crawler->addToPending($url);

        // Set recursive crawl so that found URL's are added to pending queue
        $this->crawler->setOption('recursiveCrawl', true);

        // Crawl
        $this->crawler->crawl();

        $crawledUrls = $this->crawler->getCrawledUrls();

        // Second URL crawled should be aboutCrawling.html based on our filters
        $url = current(array_slice($crawledUrls, 1, 1));
        $this->assertEquals('http://www.example.com/aboutCrawling.html', $url['url']);

        // Should have only crawled 2 urls.
        $this->assertCount(2, $crawledUrls);
    }

    private function getUrlSetOne()
    {
        $url1 = array('url' => 'http://www.example.com/pageCrawl.html');
        $url2 = array('url' => 'http://www.example.com/aboutCrawling.html');
        $url3 = array('url' => 'http://www.example2.com/contactExternalDomain.html');
        $url4 = array('url' => 'http://www.example.com/contact-help.html');
        $url5 = array('url' => 'http://www.example.com/contact.html');

        return array(
            (object) $url1,
            (object) $url2,
            (object) $url3,
            (object) $url4,
            (object) $url5
        );
    }

}