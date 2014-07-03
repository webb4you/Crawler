<?php
namespace W4Y\Crawler;

use W4Y\Crawler\Parser\Parser;
use W4Y\Crawler\Parser\ParserInterface;
use W4Y\Crawler\Plugin\PluginInterface;
use W4Y\Crawler\Client\ClientInterface;
use W4Y\Crawler\Plugin;

/**
 * Crawler
 *
 * @author Ilan Rivers <ilan@webb4you.com>
 */
class Crawler
{
    private $options = array();

    private $pendingUrls = array();
    private $pendingBacklogUrls = array();
    private $crawledUrls = array();
    private $failedUrls = array();
    private $excludeUrls = array();
    private $externalFollows = array();
    private $externalUrls = array();
    private $crawlerFoundUrls = array();

    private $originalHost;

    private $lastRequestData = array();

    /** @var array Filter[] */
    private $requestUrlFilter = array();

    /** @var array PluginInterface[] */
    private $plugins = array();

    /** @var array ClientInterface[] */
    private $clients = array();

    /** @var ClientInterface $client */
    private $client;

    private $activeClientQueue;

    private $clientStats = array();

    private $crawledIndex = 0;

    private $crawlerRunning;

    private $parser;

    const STATS_SUCCESS = 'success';
    const STATS_FAIL = 'fails';
    const STATS_ERROR = 'errors';
    const STATS_CRAWL = 'crawls';
    const STATS_ATTEMPT = 'attempts';
    const STATS_ID = 'id';
    const STATS_SEQUENCE = 'sequence';

    const LIST_TYPE_PENDING = 'pendingUrls';
    const LIST_TYPE_PENDING_BACKLOG = 'pendingBacklogUrls';
    const LIST_TYPE_EXCLUDED = 'excludeUrls';
    const LIST_TYPE_FAILED = 'failedUrls';
    const LIST_TYPE_CRAWLED = 'crawledUrls';
    const LIST_TYPE_CRAWLER_FOUND = 'crawlerFoundUrls';
    const LIST_TYPE_CRAWLED_EXTERNAL = 'externalFollows';
    const LIST_TYPE_EXTERNAL_URL = 'externalUrls';

    public function __construct(array $options = array(), ClientInterface $client = null, ParserInterface $parser = null)
    {
        $defaults = array(
            'maxUrlFollows' => 100,
            'maxUrlQue'     => 1000,
            'externalFollows' => false,
            'recursiveCrawl' => false,
            'sleepInterval' => 0,
            'debug' => false
        );

        $this->options = array_merge($defaults, $options);

        // Set html parser
        if (null !== $parser) {
            $this->setParser($parser);
        } else {
            // Default parser
            $this->setParser(new Parser());
        }

        // Set client
        if (null !== $client) {
            $this->setClient($client);
        }
    }

    /**
     * @param $listType
     * @param $value
     * @param null $key
     * @throws \Exception
     */
    public function addToList($listType, $value, $key = null)
    {
        if (!property_exists($this, $listType)) {
            throw new \Exception(sprintf('Unrecognized URL Type %s.', $listType));
        }

        if (!empty($key)) {
            $this->$listType = array_merge($this->$listType, array($key => $value));
        } else {
            $value = $this->formatUrl($value);
            $this->$listType = array_merge($this->$listType, array(md5($value) => $value));
        }
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToPending($url)
    {
        $this->addToList(self::LIST_TYPE_PENDING, $url);

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
        if (!property_exists($this, $listType)) {
            throw new \Exception(sprintf('Unrecognized List Type %s.', $listType));
        }

        return $this->$listType;
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToPendingBacklog($url)
    {
        $this->addToList(self::LIST_TYPE_PENDING_BACKLOG, $url);

        return $this;
    }

    /**
     * @param $url
     * @return $this
     */
    public function addToFailed($url)
    {
        $this->addToList(self::LIST_TYPE_FAILED, $url);

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
        $this->addToList(self::LIST_TYPE_CRAWLER_FOUND, $links, $key = md5($url));

        return $this;
    }

    private function addToExternalUrls($url)
    {
        $this->addToList(self::LIST_TYPE_EXTERNAL_URL, $url);

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

        $this->addToList(self::LIST_TYPE_CRAWLED, $dt, $key = $dt['id']);

        return $this;
    }

    /**
     * Return all the URL's actually crawled.
     *
     * @return array
     */
    public function getCrawledUrls()
    {
        return $this->crawledUrls;
    }

    /**
     * Return all the URL's that were found during the crawl.
     *
     * @return array
     */
    public function getFoundUrls()
    {
        return $this->crawlerFoundUrls;
    }

    /**
     * Get URL's that were pending to be crawled but were not.
     *
     * @return array
     */
    public function getPending()
    {
        return $this->pendingUrls;
    }

    public function getPendingUrl()
    {
        $pendingUrls = $this->getList(self::LIST_TYPE_PENDING);
        $url = array_shift($pendingUrls);

        // Populate list.
        $this->pendingUrls = $pendingUrls;

        return $this->formatUrl($url);
    }

    /**
     * Get URL's that were pending to be crawled but were not.
     *
     * @return array
     */
    public function getPendingBacklog()
    {
        return $this->pendingBacklog;
    }

    /**
     * Set request filter
     *
     * @param Filter $filter
     * @return $this
     */
    public function setRequestFilter(Filter $filter)
    {
        $this->requestUrlFilter[] = $filter;

        return $this;
    }

    /**
     * Get request filters that were set.
     *
     * @return array
     */
    public function getRequestFilter()
    {
        return $this->requestUrlFilter;
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
     * @return ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Remove all clients from array.
     * All crawls after this will be performed via localhost.
     *
     * @return \W4Y\Crawler\Crawler
     */
    public function clearClients()
    {
        $this->clients = array();
        $this->client = null;

        return $this;
    }

    /**
     * Set last request data
     *
     * @param array $dt
     */
    private function setLastRequestData($dt)
    {
        $this->lastRequestData = $dt;
    }

    /**
     * Return last request data
     *
     * @return array
     */
    public function getLastRequestData()
    {
        return $this->lastRequestData;
    }

    /**
     * Set the state of the crawler
     *
     * @param boolean $status
     */
    public function setCrawlerRunning($status)
    {
        $this->crawlerRunning = $status;
    }

    /**
     * Return the array of the currently active client
     *
     * @return array
     */
    private function getActiveClientDetails()
    {
        $clients = $this->getClients();
        $client = array_slice($clients, ($this->activeClientQueue - 1), 1);

        return $client;
    }

    private function getActiveClientName()
    {
        $client = $this->getActiveClientDetails();
        $name = current(array_keys($client));

        return $name;
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
        if ((null === $this->activeClientQueue) || ($maxClients == $this->activeClientQueue) ) {
            $this->activeClientQueue = 1;
        } else {
            $this->activeClientQueue++;
        }

        $client = $this->getActiveClientDetails();
        $this->client = current($client);
    }

    private function setClientStats($statsType)
    {
        $clientName = $this->getActiveClientName();
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

        $maxConsecutiveFails = 500;
        $failedIterations = 0;
        $cntFollows = 0;
        $this->crawlerRunning = true;

        while (!empty($pendingUrls) && $this->crawlerRunning) {

            // Set the client
            $this->roundRobinClient();

            $failedIterations++;
            if ($failedIterations > $maxConsecutiveFails) {
                $this->crawlerRunning = false;
            }

            // Check for max follows
            if ($this->getOption('maxUrlFollows') <= $cntFollows) {
                $this->crawlerRunning = false;
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
                    'id' => md5($followUrl),
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
     * Check if a URL can be crawled based.
     *
     * @param string $url
     * @return boolean
     */
    private function canBeCrawled($url)
    {
        // Check if URL has been already crawled.
        $crawledUrls = $this->getList(self::LIST_TYPE_CRAWLED);
        if (array_key_exists(md5($url), $crawledUrls)) {
            return false;
        }

        // Check if url has been excluded
        $excludedUrls = $this->getList(self::LIST_TYPE_EXCLUDED);
        if (array_key_exists(md5($url), $excludedUrls)) {
            return false;
        }

        return true;
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
            'id' => md5($currentUrl),
            'url' => $currentUrl,
            'finalUrl' => $finalUrl,
            'status' => $responseCode,
        );
        $this->setLastRequestData($oUrl);

        // If original url was redirected, reset the original host variable.
        if (0 === $this->crawledIndex && $oUrl['url'] != $oUrl['finalUrl']) {
            // Reset original host.
            $uri = new \Zend\Uri\Http($oUrl['finalUrl']);
            $this->originalHost = $uri->getHost();
        }

        // Add to crawled
        $this->addToCrawled($oUrl);

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

        // TODO Dont add urls that are already in the crawled URL array. array_intersect_key

        $this->parser->setDomain($finalUrl);
        $links = $this->parser->getUrls($this->getClient()->getBody());

        // Add to found list
        $this->addToFoundUrls($currentUrl, $links);


        // If we are doing a recursive crawl then add all the found URL's to the queue.
        $isRecursiveCrawl = $this->getOption('recursiveCrawl');
        if (!empty($isRecursiveCrawl)) {

            // Filter URL's
            $requestUrlFilter = $this->getRequestFilter();
            $links = $this->filterUrlList($requestUrlFilter, $links);

            foreach ($links as $l) {

                // Add to pending queue only if limit was not reached else
                // add url to backlog so we know what was not crawled.
                // @todo why a max url que, should just keep track of how many urls were crawled.
                $pendingUrls = $this->getPending();
                $maxUrlQue = $this->getOption('maxUrlQue');
                if (count($pendingUrls) < $maxUrlQue) {
                    $this->addToPending($l->url);
                } else {
                    $this->addToPendingBacklog($l->url);
                }
            }
        }
    }

    public function getOption($option)
    {
        if (!isset($this->options[$option])) {
            throw new \Exception('The specified option does not exist.');
        }

        return $this->options[$option];
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
        $postSlash 	= '';

        // Remove the port from the url
        $url = preg_replace('#\:[0-9]{2,4}#', '', $url);

        // Check for a file extension in URL or query string.
        if (preg_match('#\/?.*\.[a-zA-Z]{2,4}(?!\/)$|\?.*#', $url)) {

            // Remove the slash if the url ends with one.
            $url = rtrim($url, '/');

        }

        return $url .= $postSlash;
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
        $isValid = true;

        // Filter strings
        foreach ($filters as $filter) {
            if (!$filter->isValid($url)) {
                $isValid = false;
                break;
            }
        }

        return $isValid;
    }

    /**
     * Removed URLs (strings) from array based on previously set filters.
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