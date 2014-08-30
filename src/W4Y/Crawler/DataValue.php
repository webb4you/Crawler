<?php
namespace W4Y\Crawler;

class DataValue
{
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
    const DATA_TYPE_CRAWLER_FOUND       = 'crawlerFound';
    const DATA_TYPE_CRAWLED_EXTERNAL    = 'externalFollows';
    const DATA_TYPE_EXTERNAL_URL        = 'externalUrls';
}
