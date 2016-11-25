<?php

namespace tests\MediaMonks\Crawler;

use MediaMonks\Crawler\Crawler;
use MediaMonks\Crawler\Url\Matcher\PathRegexUrlMatcher;
use MediaMonks\Crawler\Url\Matcher\UrlMatcherInterface;
use Mockery as m;
use Psr\Log\NullLogger;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class CrawlerTest extends \PHPUnit_Framework_TestCase
{
    public function test_default_values()
    {
        $crawler = new Crawler();

        $this->assertInstanceOf(Client::class, $crawler->getClient());
        $this->assertEquals(0, $crawler->getLimit());
        $this->assertInstanceOf(NullLogger::class, $crawler->getLogger());
        $this->assertFalse($crawler->getStopOnError());
        $this->assertCount(0, $crawler->getUrlsCrawled());
        $this->assertCount(0, $crawler->getUrlsQueued());
        $this->assertCount(0, $crawler->getUrlsRejected());
        $this->assertCount(0, $crawler->getUrlsReturned());
        $this->assertCount(0, $crawler->getWhitelistUrlMatchers());
        $this->assertCount(0, $crawler->getBlacklistUrlMatchers());
    }

    public function test_getters_setters()
    {
        $crawler = new Crawler();

        $client = new \Goutte\Client();
        $crawler->setClient($client);
        $this->assertEquals($client, $crawler->getClient());

        $limit = 1;
        $crawler->setLimit($limit);
        $this->assertEquals($limit, $crawler->getLimit());

        $crawler->setStopOnError(true);
        $this->assertTrue($crawler->getStopOnError());

        $logger = m::mock(NullLogger::class);
        $crawler->setLogger($logger);
        $this->assertEquals($logger, $crawler->getLogger());
    }

    public function test_options()
    {
        $logger = m::mock(NullLogger::class);

        $crawler = new Crawler(
            null, [
            'limit'                  => 1,
            'stop_on_error'          => true,
            'logger'                 => $logger,
            'whitelist_url_matchers' => [
                m::mock(UrlMatcherInterface::class),
            ],
            'blacklist_url_matchers' => [
                m::mock(UrlMatcherInterface::class),
            ],
        ]
        );

        $this->assertEquals(1, $crawler->getLimit());
        $this->assertTrue($crawler->getStopOnError());
        $this->assertEquals($logger, $crawler->getLogger());
        $this->assertCount(1, $crawler->getWhitelistUrlMatchers());
        $this->assertCount(1, $crawler->getBlacklistUrlMatchers());
    }

    public function test_clear_matchers()
    {
        $matcher = m::mock(UrlMatcherInterface::class);

        $crawler = new Crawler();
        $crawler->addBlacklistUrlMatcher($matcher);
        $crawler->addWhitelistUrlMatcher($matcher);

        $this->assertCount(1, $crawler->getBlacklistUrlMatchers());
        $this->assertCount(1, $crawler->getWhitelistUrlMatchers());

        $crawler->clearBlacklistUrlMatchers();
        $crawler->clearWhitelistUrlMatchers();

        $this->assertCount(0, $crawler->getBlacklistUrlMatchers());
        $this->assertCount(0, $crawler->getWhitelistUrlMatchers());

        $crawler->setBlacklistUrlMatchers([$matcher]);
        $crawler->setWhitelistUrlMatchers([$matcher]);

        $this->assertCount(1, $crawler->getBlacklistUrlMatchers());
        $this->assertCount(1, $crawler->getWhitelistUrlMatchers());

        $crawler->clearBlacklistUrlMatchers();
        $crawler->clearWhitelistUrlMatchers();

        $this->assertCount(0, $crawler->getBlacklistUrlMatchers());
        $this->assertCount(0, $crawler->getWhitelistUrlMatchers());
    }

    public function test_crawl_single_page()
    {
        $domCrawler = new DomCrawler('<html></html>');

        $client = m::mock(Client::class);
        $client->shouldReceive('request')->once()->andReturn($domCrawler);

        $crawler = new Crawler($client);

        foreach ($crawler->crawl('http://my-test') as $page) {
        }

        $this->assertCount(1, $crawler->getUrlsCrawled());
    }

    public function test_crawl_multiple_pages()
    {
        $crawler = new Crawler($this->getDummyClient());

        foreach ($crawler->crawl('http://my-test') as $page) {
        }

        $this->assertCount(5, $crawler->getUrlsCrawled());
        $this->assertCount(5, $crawler->getUrlsReturned());
        $this->assertCount(2, $crawler->getUrlsRejected());
    }

    public function test_crawl_with_limit()
    {
        $crawler = new Crawler($this->getDummyClient());
        $crawler->setLimit(3);

        foreach ($crawler->crawl('http://my-test') as $page) {
        }

        $this->assertCount(3, $crawler->getUrlsCrawled());
        $this->assertCount(2, $crawler->getUrlsQueued());
    }

    public function test_crawl_with_whitelist()
    {
        $crawler = new Crawler($this->getDummyClient());
        $crawler->addWhitelistUrlMatcher(new PathRegexUrlMatcher('~^/page_1.html~'));

        foreach ($crawler->crawl('http://my-test') as $page) {
        }

        $this->assertCount(5, $crawler->getUrlsCrawled());
        $this->assertCount(1, $crawler->getUrlsReturned());
    }

    public function test_crawl_with_blacklist()
    {
        $crawler = new Crawler($this->getDummyClient());
        $crawler->addBlacklistUrlMatcher(new PathRegexUrlMatcher('~^/page_1.html~'));

        foreach ($crawler->crawl('http://my-test') as $page) {
        }

        $this->assertCount(5, $crawler->getUrlsCrawled());
        $this->assertCount(4, $crawler->getUrlsReturned());
    }

    /**
     * @return m\MockInterface
     */
    protected function getDummyClient()
    {
        $client = m::mock(Client::class);

        $i = 0;
        $client->shouldReceive('request')->andReturnUsing(function() use(&$i) {
            $i++;
            switch($i) {
                case 1:
                    $html = '<html><body><a href="/page_1.html">Page 1</a><a href="/page_2.html">Page 2</a></body></html>';
                    break;
                case 2:
                    $html = '<html><body><a href="/page_3.html">Page 3</a><a href="http://external/">External</a></body></html>';
                    break;
                case 3:
                    $html = '<html><body><a href="/page_4.html">Page 4</a><a href="mailto:foo@bar.com">Invalid</a></body></html>';
                    break;
                default:
                    $html = '<html><body><a href="/page_1.html">Page 1</a></body></html>';
                    break;
            }

            return new DomCrawler($html, 'http://my-test');
        });

        return $client;
    }

    protected function tearDown()
    {
        parent::tearDown();

        m::close();
    }
}