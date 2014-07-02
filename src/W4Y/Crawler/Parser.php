<?php
namespace W4Y\Crawler;

use W4Y\Dom\Selector;
use Zend\Uri\Uri;

class Parser
{
    private $domain;
    private $uri;

    public function __construct($url = null)
    {
        if (!empty($url)) {
            $this->setDomain($url);
        }
    }

    /**
     * Get URL's in a html document
     *
     * @param type $body
     * @return array
     */
    public function getUrls($body = null)
    {
        if (empty($body)) {
            return array();
        }

        $selector = new Selector();
        $selector->setBody($body);
        $res = $selector->query('a');

        $domain = $this->getDomain();

        $urls = array();
        foreach ($res as $result) {

            $a = $result->getAttribute('href');
            $title = $result->getAttribute('title');
            $text = $result->getText();


            $imageObj = null;

            if (empty($text)) {

                $nodeElement = $result->getDomElement();

                $imageTags	= $nodeElement->getElementsByTagName("img");//get img tag
                $image		= $imageTags->item(0);

                if (!empty($image) && 'img' == $image->localName) {

                    $src = $image->getAttribute('src');
                    $alt = $image->getAttribute('alt');

                    $imageObj = (object) array(
                        'src' => trim($this->absoluteUrl($src, $domain)),
                        'alt' => trim($alt)
                    );
                } else {
                    // Anchor tag but not a nested imgage tag
                }
            }

            $a = $this->absoluteUrl($a, $domain);

            $urls[md5($a)] = (object) array(
                'url' => trim($a),
                'image' => $imageObj,
                'text' => trim($text),
                'title' => trim($title)
            );
        }

        return $urls;
    }

    /**
     * Get images on the current crawled page.
     *
     * @param string $body
     * @return array
     */
    public function getImages($body = null)
    {
        if (null === $body) {
            return array();
        }

        $selector = new Selector();
        $selector->setBody($body);
        $res = $selector->query('img');

        $domain = $this->getDomain();

        $imgs = array();
        foreach ($res as $result) {
            $iSrc = $result->getAttribute('src');
            $iSrc = $this->absoluteUrl($iSrc, $domain);
            $imgs[md5($iSrc)] = $iSrc;
        }

        return $imgs;
    }

    /**
     * Create an absolute URL from a relative one.
     *
     * @param string $url
     * @param string $domain
     * @return string
     */
    private function absoluteUrl($url, $domain = null)
    {
        $domain = (null !== $domain) ? $domain : $this->getDomain();

        //Check if link has a protocol
        if ((strpos($url, '//') === false)) {

            $uri  = $this->getUri();
            $path = $uri->getPath();

            // Check if the url PATH is ending with a file extension and/or possible slash.
            if (preg_match('#\.[a-zA-Z]{2,4}\/?$#', $path)) {
                $oPath = $path;
                if ($path[strlen($path)-1] == '/') {
                    $path = substr($path, 0, -1);
                }
                $tmp = explode('/', $path);
                array_pop($tmp);
                $path = implode('/', $tmp) . '/';
                //echo 'FILE PATH --- ' . $oPath . ' : : ' . $path . '<br>';
            }

            // If URL begins with slash, then it should link from the
            // domain root.
            if (isset($url[0]) && $url[0] == '/') {
                $url = $uri->getScheme() . '://' . $uri->getHost() . $url;
            } else {

                // Check for path in url
                $tmp  		= explode('/', trim($url, '/'));
                $urlPrefix 	= array_shift($tmp);
                $tmpP 		= trim($path, '/');

                if (!empty($urlPrefix) && strpos($path, $urlPrefix) !== false) {
                    $path = '';
                    if ($url[0] != '/') {
                        $path = '/';
                    }
                }

                $url = $domain . $path . $url;
            }
        }

        return $url;
    }

    public function setDomain($url)
    {
        $uri = new Uri($url);
        $this->uri = $uri;

        $d = $uri->getScheme() . '://' . $uri->getHost();
        $this->domain = $d;
    }

    /**
     * Get domain
     *
     * @return mixed
     */
    public function getDomain()
    {
        return $this->domain;
    }
}
