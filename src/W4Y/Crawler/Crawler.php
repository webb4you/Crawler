<?php
namespace W4Y\Crawler;

use SebastianBergmann\Exporter\Exception;
use W4Y\Crawler\Plugin;
use Zend\Http\Request;

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

    private $originalHost = null;

    private $requestUrlFilter = array();

    private $lastRequestData = array();

    private $plugins = array();

    private $clients = array();

    private $crawledIndex = 0;

    private $client = null;

    private $defaultClient = null;

    private $crawlerRunning = null;

    const LIST_TYPE_PENDING = 'pendingUrls';
    const LIST_TYPE_PENDING_BACKLOG = 'pendingBacklogUrls';
    const LIST_TYPE_EXCLUDED = 'excludeUrls';
    const LIST_TYPE_FAILED = 'failedUrls';
    const LIST_TYPE_CRAWLED = 'crawledUrls';
    const LIST_TYPE_CRAWLER_FOUND = 'crawlerFoundUrls';
    const LIST_TYPE_CRAWLED_EXTERNAL = 'externalFollows';
    const LIST_TYPE_EXTERNAL_URL = 'externalUrls';

    public function __construct(array $options = array())
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

        $request = new Request();
        $client = new Client();

        $client->setRequest($request);

        $this->defaultClient = $client;
    }

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

    public function getList($listType)
    {
        if (!property_exists($this, $listType)) {
            throw new \Exception(sprintf('Unrecognized URL Type %s.', $listType));
        }

        return $this->$listType;
    }

    /**
     * Add a url to the queue.
     *
     * @param string $url
     */
    public function addToPending($url)
    {
        $this->addToList(self::LIST_TYPE_PENDING, $url);
    }

    public function getPendingUrl()
    {
        $pendingUrls = self::LIST_TYPE_PENDING;
        $url = array_shift($this->$pendingUrls);

        return $this->formatUrl($url);
    }


    public function getClient()
    {
        return $this->client;
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
     * Add a url to the found array.
     *
     * @param string $url
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
    }

    /**
     * Add a url to the crawled array.
     *
     * @param string $url
     */
    public function addToCrawled($dt)
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
    public function setPlugin(Plugin\PluginInterface $plugin)
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
        if (empty($this->plugins)) {
            return false;
        }

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
     * Set a client to use to crawl with.
     *
     * @param \W4Y\Crawler\Client $client
     * @return \W4Y\Crawler\Crawler
     */
    public function setClient(Client $client)
    {
        $this->clients[] = $client;
        return $this;
    }

    /**
     * Get clients
     *
     * @return boolean
     */
    public function getClients()
    {
        if (empty($this->clients)) {
            return false;
        }

        return $this->clients;
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
     * When multiple clients are set, we want to alternate the
     * crawling process between them.
     *
     * @param array $clients
     */
    private function roundRobinClient($clients)
    {
        if (!empty($clients) && count($clients) > 1) {

            if (empty($this->clients)) {
                $this->clients = $clients;
            }

            $client = array_shift($this->clients);
            $this->client = $client;

        } else if (null === $this->client) {
            $this->client = (!empty($this->clients)
                ? $this->clients[0]
                : $this->defaultClient);
        }
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

        $cntFollows = 0;
        $this->crawlerRunning = true;

        // Set current client, if none was set with setClient then
        // we use the default.
        $clients = $this->getClients();
        $this->roundRobinClient($clients);

        // Execute preCrawlLoop
        $this->executePlugin('preCrawl');

        while (!empty($pendingUrls) && $this->crawlerRunning) {

            $followUrl = $this->getPendingUrl();
            if (!$this->canBeCrawled($followUrl)) {
                continue;
            }

            // Check for external URL
			if (!$this->options['externalFollows']) {
                if (strpos($followUrl, $this->originalHost) === false) {
                    $this->addToExternalUrls($followUrl);
                    continue;
                }
            }

            // Check for max follows
            if ($this->options['maxUrlFollows'] <= $cntFollows) {
                $this->crawlerRunning = false;
                continue;
            }

            try {

                //$this->getClient()->resetParameters(true);
                //$this->getClient()->clearCookies();

                $this->getClient()->setUri($followUrl);

            } catch (\Exception $e) {

                $this->addToFailed($followUrl);
                continue;
            }


            try {

                $result = $this->_doRequest();
                if (!$result) {
                    $this->addToFailed($followUrl);
                }

            } catch (\Exception $e) {

                // Failed
                $this->addToFailed($followUrl);

                $dt = array(
                    'id' => md5($followUrl),
                    'url' => $followUrl,
                    'status' => 'FAILED',
                    'error' => $e->getMessage()
                );

                $this->addToCrawled($dt);
            }

            // Change the client
            $this->roundRobinClient($clients);

			// Increment follows
            $cntFollows++;

            if ($this->options['sleepInterval']) {
                sleep($this->options['sleepInterval']);
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
        $crawledUrls = self::LIST_TYPE_CRAWLED;
        if (array_key_exists(md5($url), $this->$crawledUrls)) {
            return false;
        }

        // Check if url has been excluded
        $excludedUrls = self::LIST_TYPE_EXCLUDED;
        if (array_key_exists(md5($url), $this->$excludedUrls)) {
            return false;
        }

        // Check filters

        return true;
    }

    /**
     * Perform the actual request
     */
    private function _doRequest()
    {
        $currentUrl = $this->formatUrl($this->getClient()->getUri()->__toString());

        // Execute preRequest
        $this->executePlugin('preRequest');

        // Do the request
        $response = $this->getClient()->request();

        // Uri will change if request was redirected so check
        // the uri after the request.
        $lastUrl = $this->formatUrl($this->getClient()->getUri()->__toString());

        // Response code
        $responseCode = $response->getStatusCode();

        // Add to crawled url's
        $oUrl = array(
            'id' => md5($currentUrl),
            'url' => $currentUrl,
            'finalUrl' => $lastUrl,
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

        // Verify response
        if ($response->isSuccess()) {

            // TODO Dont add urls that are already in the crawled URL array. array_intersect_key
            $links = $this->getClient()->getUrls();

            $this->addToFoundUrls($currentUrl, $links);

            if (!empty($this->options['recursiveCrawl'])) {

                // Filter URL's
                $requestUrlFilter = $this->getRequestFilter();
                $links = $this->filterUrlList($requestUrlFilter, $links);

                foreach ($links as $l) {

                    // Add to pending queue only if limit was not reached else
                    // add url to backlog so we know what was not crawled.
                    // @todo why a max url que, should just keep track of how many urls were crawled.
                    $pendingUrls = $this->getPending();
                    if (count($pendingUrls) < $this->options['maxUrlQue']) {
                        $this->addToPending($l->url);
                    } else {
                        $this->addToPendingBacklog($l->url);
                    }
                }
            }

            // Execute onSuccess
            $this->executePlugin('onSuccess');

        } else {
            // Execute onFailure
            $this->executePlugin('onFailure');
        }

        // Execute postRequest
        $this->executePlugin('postRequest');
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
     * Call a plugin method that is based on the
     * crawler plugin interface.
     *
     * @param string $hook
     */
    private function executePlugin($hook)
    {
        if ($this->getPlugins()) {
            foreach ($this->getPlugins() as $plugin) {
                if (method_exists($plugin, $hook)) {
                    $plugin->$hook($this);
                }
            }
        }
    }
}