<?php
namespace W4Y\Crawler;

use W4Y\Crawler\Parser\Parser;
use W4Y\Crawler\Parser\ParserInterface;
use W4Y\Crawler\Plugin\PluginInterface;
use W4Y\Crawler\Client\ClientInterface;
use W4Y\Crawler\Storage\StorageInterface;
use W4Y\Crawler\DataValue as CrawlerValue;
use W4Y\Crawler\Storage\Memory as StorageMemory;
use W4Y\Crawler\Plugin;

use W4Y\Crawler\StorageManager;

/**
 * Crawler
 *
 * @author Ilan R <ilan@webb4you.com>
 */
class Crawler
{
    private $defaultOptions = array(
        'maxUrlFollows' => 100,
        'maxUrlQue'     => 1000,
        'externalFollows' => false,
        'recursiveCrawl' => false,
        'sleepInterval' => 0,
    );

    private $options = array();
    private $originalHost;
    private $crawledIndex = 0;
    private $crawlerRunning = false;

    /** @var StorageManager $storage */
    public $storage;

    /** @var ParserInterface $parser */
    private $parser;

    /** @var Filter[] $requestFilter */
    private $requestFilter = array();

    /** @var PluginInterface[] $plugins */
    private $plugins = array();

    /** @var ClientInterface[] $clients */
    private $clients = array();
    private $clientStats = array();
    private $activeClientQueue = 1;

    public function __construct(array $options = array(), ClientInterface $client = null, ParserInterface $parser = null)
    {
        $defaults = $this->getDefaultOptions();
        $this->options = array_merge($defaults, $options);

        // Set html parser
        if (null === $parser) {
            $parser = new Parser();
        }
        $this->setParser($parser);

        // Set client
        if (null !== $client) {
            $this->setClient($client);
        }

        $this->storage = new StorageManager(new StorageMemory());
    }

    /**
     * Add to a data list.
     *
     * @param $listType
     * @param $value
     * @param null $parentKey
     * @throws \Exception
     */
    public function addToList($listType, $value, $parentKey = null)
    {
        if (is_string($value)) {
            $value = $this->formatUrl($value);
            $this->getStorage()->addToStorage($listType, array($this->hashString($value) => $value));
        } else {
            $saveData = array(
                $this->hashString($value['url']) => $value
            );
            $this->getStorage()->addToStorage($listType, $saveData, $parentKey);
        }
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToPending($url)
    {
        $this->addToList(CrawlerValue::DATA_TYPE_PENDING, $url);

        return $this;
    }

    public function setParser(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @param $listType
     * @return mixed
     * @throws \Exception
     */
    public function getList($listType)
    {
        $data = $this->getStorage()->getFromStorage($listType);

        return $data;
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToPendingBacklog($url)
    {
        $this->addToList(CrawlerValue::DATA_TYPE_PENDING_BACKLOG, $url);

        return $this;
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToFailed($url)
    {
        $this->addToList(CrawlerValue::DATA_TYPE_FAILED, $url);

        return $this;
    }

    /**
     * @param $url
     * @param $links
     * @return $this
     */
    public function addToFoundUrls($url, $links)
    {
        $url = $this->formatUrl($url);
        $parentKey = $this->hashString($url);
        foreach ($links as $link) {
            $link = (array) $link;
            $link['parentKey'] = $parentKey;
            $this->addToList(CrawlerValue::DATA_TYPE_CRAWLER_FOUND, $link, $parentKey);
        }

        return $this;
    }

    private function addToExternalUrls($url)
    {
        $this->addToList(CrawlerValue::DATA_TYPE_EXTERNAL_URL, $url);

        return $this;
    }

    private function addToExcludedUrls($url)
    {
        $this->addToList(CrawlerValue::DATA_TYPE_EXCLUDED, $url);

        return $this;
    }

    /**
     * @param $dt
     * @return $this
     * @throws \Exception
     */
    public function addToCrawled(array $dt)
    {
        if (empty($dt['id'])) {
            throw new \Exception('Crawled data missing ID.');
        }

        $this->crawledIndex++;
        $dt['sequence'] = $this->crawledIndex;

        $this->addToList(CrawlerValue::DATA_TYPE_CRAWLED, $dt);

        return $this;
    }

    /**
     * Return all the URL's actually crawled.
     *
     * @return array
     */
    public function getCrawledUrls()
    {
        return $this->getList(CrawlerValue::DATA_TYPE_CRAWLED);
    }

    /**
     * Return all the URL's that were found during the crawl.
     *
     * @return array
     */
    public function getFoundUrls()
    {
        return $this->getList(CrawlerValue::DATA_TYPE_CRAWLER_FOUND);
    }

    /**
     * Get URL's that were pending to be crawled but were not.
     *
     * @return array
     */
    public function getPending()
    {
        return $this->getList(CrawlerValue::DATA_TYPE_PENDING);
    }

    public function getPendingUrl($deleteAfterFetch = true)
    {
        $storage = $this->getStorage();

        $url = $storage->getFromStorage(CrawlerValue::DATA_TYPE_PENDING, $fetchSingleResult = true);

        $urlKey = current(array_keys($url));
        $url = current(array_values($url));

        if ($deleteAfterFetch) {
            $storage->removeFromStorage(CrawlerValue::DATA_TYPE_PENDING, $urlKey);
        }

        return $this->formatUrl($url);
    }

    /**
     * Set request filter
     *
     * @param Filter $filter
     * @return $this
     */
    public function setRequestFilter(Filter $filter)
    {
        $this->requestFilter[] = $filter;

        return $this;
    }

    /**
     * Get request filters that were set.
     *
     * @return array
     */
    public function getRequestFilter()
    {
        return $this->requestFilter;
    }

    /**
     * Set plugin
     *
     * @param \W4Y\Crawler\Plugin\PluginInterface $plugin
     * @return \W4Y\Crawler\Crawler
     */
    public function setPlugin(PluginInterface $plugin)
    {
        $this->plugins[] = $plugin;
        return $this;
    }

    /**
     * Get plugins
     *
     * @return boolean|array
     */
    public function getPlugins()
    {
        return $this->plugins;
    }

    /**
     * Remove all previously set plugins
     *
     * @return \W4Y\Crawler\Crawler
     */
    public function clearPlugins()
    {
        $this->plugins = array();

        return $this;
    }

    /**
     * @param ClientInterface $client
     * @param null $identifier
     * @return $this
     */
    public function setClient(ClientInterface $client, $identifier = null)
    {
        if (empty($identifier)) {
            $identifier = get_class($client) . '_' . uniqid() ;
        }

        $this->clients[$identifier] = $client;

        return $this;
    }

    public function setStorage(StorageInterface $storage)
    {
        $this->storage = new StorageManager($storage);
    }

    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * Get clients
     *
     * @return boolean
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * The current active client.
     *
     * When using round robin crawl the active client will be reset on each request.
     *
     * @return ClientInterface
     */
    public function getClient()
    {
        $client = $this->getClientDetails();

        return current($client);
    }

    /**
     * Remove all clients from array.
     *
     * @return \W4Y\Crawler\Crawler
     */
    public function clearClients()
    {
        $this->clients = array();

        return $this;
    }

    /**
     * Clear all request filters
     */
    public function clearRequestFilters()
    {
        $this->requestFilter = array();
    }

    /**
     * Reset the core crawler data as it was just as it was when
     * the Crawler was initialized.
     *
     */
    public function reset()
    {
        $this->clearClients();
        $this->clearPlugins();
        $this->clearRequestFilters();
        $this->getStorage()->resetStorage();
    }

    /**
     * Set the state of the crawler
     *
     * @param boolean $status
     */
    public function setCrawlerStatus($status)
    {
        $this->crawlerRunning = $status;
    }

    /**
     * Get the state of the crawler
     *
     * @return bool
     */
    public function getCrawlerStatus()
    {
        return $this->crawlerRunning;
    }

    /**
     * Return the array of the currently active client
     *
     * @return array
     */
    private function getClientDetails()
    {
        $clients = $this->getClients();
        $client = array_slice($clients, ($this->activeClientQueue - 1), 1);

        return $client;
    }

    public function getClientName()
    {
        $client = $this->getClientDetails();
        $name = current(array_keys($client));

        return $name;
    }

    private function setClientStats($statsType)
    {
        $clientName = $this->getClientName();
        $clientActive = $this->activeClientQueue;

        // Initialize all stats.
        if (!isset($this->clientStats[$clientActive])) {
            $this->clientStats[$clientActive][CrawlerValue::STATS_ID] = $clientName;
            $this->clientStats[$clientActive][CrawlerValue::STATS_SEQUENCE] = $clientActive;
            $this->clientStats[$clientActive][CrawlerValue::STATS_SUCCESS] = 0;
            $this->clientStats[$clientActive][CrawlerValue::STATS_FAIL] = 0;
            $this->clientStats[$clientActive][CrawlerValue::STATS_CRAWL] = 0;
            $this->clientStats[$clientActive][CrawlerValue::STATS_ATTEMPT] = 0;
            $this->clientStats[$clientActive][CrawlerValue::STATS_ERROR] = 0;
        }

        $this->clientStats[$clientActive][$statsType]++;
    }

    public function getClientStats()
    {
        return $this->clientStats;
    }

    public function getLastRequestData()
    {
        $crawled = $this->getCrawledUrls();

        return array_pop($crawled);
    }

    public function hashString($string)
    {
        return md5($string);
    }

    public function getOption($option)
    {
        if (!isset($this->options[$option])) {
            throw new \Exception('The specified option does not exist.');
        }

        return $this->options[$option];
    }

    public function setOption($option, $value)
    {
        if (!isset($this->options[$option])) {
            throw new \Exception('The specified option does not exist.');
        }
        $this->options[$option] = $value;

        return $this;
    }

    private function getDefaultOptions()
    {
        return $this->defaultOptions;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Format all URL's so that we have consistent URL's
     *
     * @param string $url
     * @return string
     */
    private function formatUrl($url)
    {
        return $this->parser->formatUrl($url);
    }

    /**
     * Removed URLs (strings) from array based on the set filters.
     *
     * @param array $filters
     * @param array $urls
     * @return array
     */
    private function filterUrlList(array $filters, array $urls)
    {
        $toRemove = array();

        foreach ($urls as $key => $u) {

            if (!$this->filterUrl($filters, $u->url)) {
                $toRemove[] = $key;
            }
        }

        // Filter strings
        foreach ($toRemove as $key) {
            unset($urls[$key]);
        }

        return $urls;
    }

    /**
     * Check if string matches filter.
     *
     * @param array $filters
     * @param string $url
     * @return boolean
     */
    private function filterUrl(array $filters, $url)
    {
        /** @var Filter $filter */
        foreach ($filters as $filter) {
            if (!$filter->isValid($url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine the client to use.
     *
     * @throws \Exception
     */
    private function roundRobinClient()
    {
        $clients = $this->getClients();

        if (empty($clients)) {
            throw new \Exception('You have to set a client.');
        }

        $maxClients = count($clients);
        if ((0 === $this->crawledIndex) || ($maxClients == $this->activeClientQueue) ) {
            $this->activeClientQueue = 1;
        } else {
            // Only while crawling is running will be round robin the queue
            $this->activeClientQueue++;
        }
    }

    /**
     * Check if a URL can be crawled based.
     *
     * @param string $url
     * @return boolean
     */
    private function canBeCrawled($url)
    {
        $storage = $this->getStorage();

        // Check for external URL
        if ((null !== $this->originalHost) && (!$this->getOption('externalFollows')) ) {

            // Check if URL contains the original domain.
            if (false === strpos($url, $this->originalHost) ) {
                $this->addToExternalUrls($url);
                $this->addToExcludedUrls($url);

                return false;
            }
        }

        if ($storage->hasInStorage(CrawlerValue::DATA_TYPE_CRAWLED, $this->hashString($url))) {
            return false;
        }

        // Check if url has been excluded
        if ($storage->hasInStorage(CrawlerValue::DATA_TYPE_EXCLUDED, $this->hashString($url))) {
            return false;
        }

        return true;
    }

    /**
     * Begin crawling url's in the queuee.
     *
     */
    public function crawl(array $options = array())
    {
        // $startTime = microtime(true);

        // Check for url's
        if (!empty($options['url'])) {
            foreach ((array) $options['url'] as $u) {
                $this->addToPending($u);
            }
        }

        $pendingUrl = $this->getPendingUrl(false);

        // Check if we have anything to crawl
        if (empty($pendingUrl)) {
            throw new \Exception('You have to add atleast one URL to the queue.');
        }

        // First URL in the queue is the original host, save
        // it so we know if we are crawling external host URL's.
        $uri = new \Zend\Uri\Http($pendingUrl);
        $this->originalHost = $uri->getHost();

        // Execute preCrawlLoop
        $this->executePlugin('preCrawl');

        $maxUrlQue = $this->getOption('maxUrlQue');
        $maxConsecutiveFails = 500;
        $failedIterations = 0;
        $numberOfSuccessfulUrlFollows = 0;
        $this->setCrawlerStatus(true);

        while ($this->getCrawlerStatus()) {

            $pendingUrl = $this->getPendingUrl();

            if (empty($pendingUrl)) {
                $this->setCrawlerStatus(false);
                continue;
            }

            // Do the Crawl Process
            $status = $this->handlePendingUrl($pendingUrl);

            // If we have failed.
            if (!$status) {

                $failedIterations++;

                if (($failedIterations > $maxConsecutiveFails) || ($numberOfSuccessfulUrlFollows > $maxUrlQue))  {
                    $this->setCrawlerStatus(false);
                }

                continue;
            }

            // Increment follows
            $numberOfSuccessfulUrlFollows++;

            // Reset failed iterations
            $failedIterations = 0;

            // Sleep
            $this->crawlerSleep();

            // Check for max follows
            if ($this->getOption('maxUrlFollows') <= $numberOfSuccessfulUrlFollows) {
                $this->setCrawlerStatus(false);
                continue;
            }
        }

        // Execute postCrawlLoop
        $this->executePlugin('postCrawl');

        // echo 'Total Time: ' . (microtime(true) - $startTime) . PHP_EOL;
    }

    private function handlePendingUrl($pendingUrl)
    {
        // Set the client to use for this URL
        $this->roundRobinClient();

        try {

            $this->getClient()->setUrl($pendingUrl);

        } catch (\Exception $e) {

            $this->addToFailed($pendingUrl);

            // Set crawl error
            $this->setClientStats(CrawlerValue::STATS_ERROR);

            return false;
        }

        // Execute preRequest
        $this->executePlugin('preRequest');

        try {

            $result = $this->_doRequest();

            if (false === $result) {
                $this->addToFailed($pendingUrl);

                // Set crawl fail
                $this->setClientStats(CrawlerValue::STATS_FAIL);

                // Execute onFailure
                $this->executePlugin('onFailure');
            }

        } catch (\Exception $e) {

            // Failed
            $this->addToFailed($pendingUrl);

            // Set crawl fail
            $this->setClientStats(CrawlerValue::STATS_ERROR);

            $dt = array(
                'id' => $this->hashString($pendingUrl),
                'url' => $pendingUrl,
                'status' => 'FAILED',
                'error' => $e->getMessage()
            );

            $this->addToCrawled($dt);
        }

        // Execute postRequest
        $this->executePlugin('postRequest');

        // Set crawl attempts
        $this->setClientStats(CrawlerValue::STATS_ATTEMPT);

        return true;
    }

    private function crawlerSleep()
    {
        $sleepInterval = $this->getOption('sleepInterval');
        if ($sleepInterval) {
            sleep($sleepInterval);
        }
    }

    /**
     * Perform the actual request
     */
    private function _doRequest()
    {
        $currentUrl = $this->formatUrl($this->getClient()->getUrl());

        // Do the request
        $this->getClient()->request();

        // Get the final URL, we might have gotten redirected during our request.
        $finalUrl = $this->formatUrl($this->getClient()->getFinalUrl());

        // Response code
        $responseCode = $this->getClient()->getResponseCode();

        // Add to crawled url's
        $oUrl = array(
            'id' => $this->hashString($currentUrl),
            'url' => $currentUrl,
            'finalUrl' => $finalUrl,
            'status' => $responseCode,
        );
        $this->addToCrawled($oUrl);

        // If original url was redirected, reset the original host variable.
        if (0 === $this->crawledIndex && $oUrl['url'] != $oUrl['finalUrl']) {
            // Reset original host.
            $uri = new \Zend\Uri\Http($oUrl['finalUrl']);
            $this->originalHost = $uri->getHost();
        }

        // Set crawl amount
        $this->setClientStats(CrawlerValue::STATS_CRAWL);

        // Verify response
        if (!$this->getClient()->isResponseSuccess()) {
            return false;
        }

        // Set crawl success
        $this->setClientStats(CrawlerValue::STATS_SUCCESS);

        // Execute onSuccess
        $this->executePlugin('onSuccess');

        // Fetch found url's
        $this->parser->setDomain($finalUrl);
        $links = $this->parser->getUrls($this->getClient()->getBody());

        // Add url's to found list
        $this->addToFoundUrls($currentUrl, $links);

        // If we are doing a recursive crawl then add all the found URL's to the queue.
        $isRecursiveCrawl = $this->getOption('recursiveCrawl');
        if (!empty($isRecursiveCrawl)) {

            // Filter URL's based on request filter
            $requestUrlFilter = $this->getRequestFilter();
            $filteredLinks = $this->filterUrlList($requestUrlFilter, $links);
            foreach ($filteredLinks as $l) {

                if ($this->canBeCrawled($l->url)) {
                    $this->addToPending($l->url);
                }
            }
        }

    }

    /**
     * Call a plugin method that is based on the crawler plugin interface.
     *
     * @param string $hook
     */
    private function executePlugin($hook)
    {
        $plugins = $this->getPlugins();
        if (empty($plugins)) {
            return;
        }

        foreach ($plugins as $plugin) {
            if (method_exists($plugin, $hook)) {
                $plugin->$hook($this);
            }
        }
    }


}