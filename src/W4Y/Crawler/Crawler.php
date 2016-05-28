<?php
namespace W4Y\Crawler;

use W4Y\Crawler\Parser\Parser;
use W4Y\Crawler\Parser\ParserInterface;
use W4Y\Crawler\Plugin\PluginInterface;
use W4Y\Crawler\Client\ClientInterface;
use W4Y\Crawler\Storage\StorageInterface;
use W4Y\Crawler\Storage\Memory as StorageMemory;
use W4Y\Crawler\Plugin;

/**
 * Crawler
 *
 * @author Ilan Rivers <ilan@webb4you.com>
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

    /** @var StorageInterface $storage */
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

    const STATS_SUCCESS                 = 'success';
    const STATS_FAIL                    = 'fails';
    const STATS_ERROR                   = 'errors';
    const STATS_CRAWL                   = 'crawls';
    const STATS_ATTEMPT                 = 'attempts';
    const STATS_ID                      = 'id';
    const STATS_SEQUENCE                = 'sequence';

    const DATA_TYPE_PENDING             = 'pendingUrls';
    const DATA_TYPE_EXCLUDED            = 'excludedUrls';
    const DATA_TYPE_FAILED              = 'failedUrls';
    const DATA_TYPE_CRAWLED             = 'crawledUrls';
    const DATA_TYPE_CRAWLER_FOUND       = 'crawlerFoundUrls';
    const DATA_TYPE_CRAWLER_FOUND_RAW   = 'crawlerFound';
    const DATA_TYPE_CRAWLED_EXTERNAL    = 'externalFollows';
    const DATA_TYPE_EXTERNAL_URL        = 'externalUrls';

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

        $this->storage = new StorageMemory();
    }

    /**
     * Add data to the storage.
     *
     * @param $listType
     * @param $data
     */
    private function addToStorage($listType, $data)
    {
        $this->getStorage()->add($listType, $data);
    }

    /**
     * Fetch data from the storage.
     *
     * @param $listType
     * @return mixed
     */
    private function getFromStorage($listType)
    {
        return $this->getStorage()->get($listType);
    }

    /**
     * Set data to the storage.
     * Will overwrite existing data.
     *
     * @param $listType
     * @param $data
     */
    private function setToStorage($listType, $data)
    {
        $this->getStorage()->set($listType, $data);
    }

    /**
     * Reset the storage
     */
    private function resetStorage()
    {
        $this->getStorage()->reset();
    }

    /**
     * Add to a data list.
     *
     * @param $listType
     * @param $value
     * @param null $key
     * @throws \Exception
     */
    public function addToList($listType, $value, $key = null)
    {
        $currentStorageData = $this->getFromStorage($listType);
        if (!empty($key)) {
            $data = array_merge($currentStorageData, array($key => $value));
            $this->setToStorage($listType, $data);
        } else {
            $value = $this->formatUrl($value);
            $this->addToStorage($listType, array($this->hashString($value) => $value));
        }
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToPending($url)
    {
        $this->addToList(self::DATA_TYPE_PENDING, $url);

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
        $data = $this->getFromStorage($listType);

        return $data;
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToPendingBacklog($url)
    {
        $this->addToList(self::DATA_TYPE_PENDING_BACKLOG, $url);

        return $this;
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToFailed($url)
    {
        $this->addToList(self::DATA_TYPE_FAILED, $url);

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
        $this->addToList(self::DATA_TYPE_CRAWLER_FOUND, $links, $key = $this->hashString($url));

        return $this;
    }

    private function addToExternalUrls($url)
    {
        $this->addToList(self::DATA_TYPE_EXTERNAL_URL, $url);

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

        $this->addToList(self::DATA_TYPE_CRAWLED, $dt, $key = $dt['id']);

        return $this;
    }

    /**
     * Return all the URL's actually crawled.
     *
     * @return array
     */
    public function getCrawledUrls()
    {
        return $this->getList(self::DATA_TYPE_CRAWLED);
    }

    /**
     * Return all the URL's that were found during the crawl.
     *
     * @return array
     */
    public function getFoundUrls()
    {
        return $this->getList(self::DATA_TYPE_CRAWLER_FOUND);
    }

    /**
     * Get URL's that were pending to be crawled but were not.
     *
     * @return array
     */
    public function getPending()
    {
        return $this->getList(self::DATA_TYPE_PENDING);
    }

    public function getPendingUrl()
    {
        $pendingUrls = $this->getPending();
        $url = array_shift($pendingUrls);

        // Populate list.
        $this->setToStorage(self::DATA_TYPE_PENDING, $pendingUrls);
//        $this->pendingUrls = $pendingUrls;

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
        $this->storage = $storage;
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
        $this->resetStorage();
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
            $this->clientStats[$clientActive][self::STATS_ID] = $clientName;
            $this->clientStats[$clientActive][self::STATS_SEQUENCE] = $clientActive;
            $this->clientStats[$clientActive][self::STATS_SUCCESS] = 0;
            $this->clientStats[$clientActive][self::STATS_FAIL] = 0;
            $this->clientStats[$clientActive][self::STATS_CRAWL] = 0;
            $this->clientStats[$clientActive][self::STATS_ATTEMPT] = 0;
            $this->clientStats[$clientActive][self::STATS_ERROR] = 0;
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
    private function filterUrlList(array $filters, array $urls, $filterStrategy = 'AND')
    {
        $toRemove = array();

        if ('or' == strtolower($filterStrategy)) {

            foreach ($urls as $key => $u) {

                // If one filter returns true url will not be excluded.
                $isExcluded = true;
                foreach ($filters as $filter) {
                    if ($this->filterUrl(array($filter), $u->url)) {
                        $isExcluded = false;
                        break;
                    }
                }

                if ($isExcluded) {
                    $toRemove[] = $key;
                }

            }

        } else {
            foreach ($urls as $key => $u) {
                if (!$this->filterUrl($filters, $u->url)) {
                    $toRemove[] = $key;
                }
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
        // Check if URL has been already crawled.
        $crawledUrls = $this->getList(self::DATA_TYPE_CRAWLED);
        if (array_key_exists($this->hashString($url), $crawledUrls)) {
            return false;
        }

        // Check if url has been excluded
        $excludedUrls = $this->getList(self::DATA_TYPE_EXCLUDED);
        if (array_key_exists($this->hashString($url), $excludedUrls)) {
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
        // Check for url's
        if (!empty($options['url'])) {
            foreach ((array) $options['url'] as $u) {
                $this->addToPending($u);
            }
        }

        $pendingUrls = $this->getPending();

        // Check if we have anything to crawl
        if (empty($pendingUrls)) {
            throw new \Exception('You have to add atleast one URL to the queue.');
        }

        // First URL in the queue is the original host, save
        // it so we know if we are crawling external host url's
        $firstUrl = array_shift($pendingUrls);
        array_unshift($pendingUrls, $firstUrl);

        $uri = new \Zend\Uri\Http($firstUrl);
        $this->originalHost = $uri->getHost();

        // Execute preCrawlLoop
        $this->executePlugin('preCrawl');

        $maxUrlQue = $this->getOption('maxUrlQue');
        $maxConsecutiveFails = 500;
        $failedIterations = 0;
        $cntFollows = 0;
        $this->setCrawlerStatus(true);

        while (!empty($pendingUrls) && $this->getCrawlerStatus()) {

            // Set the client
            $this->roundRobinClient();

            $failedIterations++;
            if (($failedIterations > $maxConsecutiveFails) || ($cntFollows > $maxUrlQue))  {
                $this->setCrawlerStatus(false);
            }

            // Check for max follows
            if ($this->getOption('maxUrlFollows') <= $cntFollows) {
                $this->setCrawlerStatus(false);
                continue;
            }

            // Get first pending URL.
            $followUrl = $this->getPendingUrl();
            if (!$this->canBeCrawled($followUrl)) {
                continue;
            }

            // Check for external URL
            if (!$this->getOption('externalFollows')) {
                if (strpos($followUrl, $this->originalHost) === false) {
                    $this->addToExternalUrls($followUrl);
                    continue;
                }
            }

            try {

                $this->getClient()->setUrl($followUrl);

            } catch (\Exception $e) {

                $this->addToFailed($followUrl);

                // Set crawl error
                $this->setClientStats(self::STATS_ERROR);

                continue;
            }

            // Execute preRequest
            $this->executePlugin('preRequest');

            try {

                $result = $this->_doRequest();

                if (false === $result) {
                    $this->addToFailed($followUrl);

                    // Set crawl fail
                    $this->setClientStats(self::STATS_FAIL);

                    // Execute onFailure
                    $this->executePlugin('onFailure');
                }

            } catch (\Exception $e) {

                // Failed
                $this->addToFailed($followUrl);

                // Set crawl fail
                $this->setClientStats(self::STATS_ERROR);

                $dt = array(
                    'id' => $this->hashString($followUrl),
                    'url' => $followUrl,
                    'status' => 'FAILED',
                    'error' => $e->getMessage()
                );

                $this->addToCrawled($dt);
            }

            // Execute postRequest
            $this->executePlugin('postRequest');

            // Set crawl attempts
            $this->setClientStats(self::STATS_ATTEMPT);

            // Increment follows
            $cntFollows++;

            // Reset failed iterations
            $failedIterations = 0;

            $sleepInterval = $this->getOption('sleepInterval');
            if ($sleepInterval) {
                sleep($sleepInterval);
            }

            $pendingUrls = $this->getPending();
        }

        // Execute postCrawlLoop
        $this->executePlugin('postCrawl');
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
        $this->setClientStats(self::STATS_CRAWL);

        // Verify response
        if (!$this->getClient()->isResponseSuccess()) {
            return false;
        }

        // Set crawl success
        $this->setClientStats(self::STATS_SUCCESS);

        // Execute onSuccess
        $this->executePlugin('onSuccess');

        // Fetch found url's
        $this->parser->setDomain($finalUrl);
        $links = $this->parser->getUrls($this->getClient()->getBody());

        // If we are doing a recursive crawl then add all the found URL's to the queue.
        $isRecursiveCrawl = $this->getOption('recursiveCrawl');
        if (!empty($isRecursiveCrawl)) {

            // Filter URL's based on request filter
            $requestUrlFilter = $this->getRequestFilter();
            $filteredLinks = $this->filterUrlList($requestUrlFilter, $links, 'OR');
            $foundUrls = $this->getList(self::DATA_TYPE_CRAWLER_FOUND_RAW);
            foreach ($filteredLinks as $l) {
                // Do not add URL's already crawled
                if (array_key_exists($this->hashString($l->url), $foundUrls)) {
                    continue;
                }
                $this->addToList(self::DATA_TYPE_CRAWLER_FOUND_RAW, $l->url);
                $this->addToPending($l->url);
            }
        }

        // Add url's to found list
        $this->addToFoundUrls($currentUrl, $links);
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