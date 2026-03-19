<?php

declare(strict_types=1);

namespace News\Core\Tests\Component\Rss;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use News\Core\Component\Rss\Dto\RssFeedDto;
use News\Core\Component\Rss\Dto\RssItemDto;
use News\Core\Component\Rss\RssParser;
use News\Core\Exception\InfrastructureException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RssParserTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private RssParser $parser;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parser = new RssParser($this->httpClient, $this->logger);
    }

    public function testFetchFeedReturnsFeedDto(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <link>https://example.com</link>
        <description>Test Description</description>
        <item>
            <title>Test Title</title>
            <link>https://example.com/article/1</link>
            <description>Test description content</description>
            <pubDate>Mon, 15 Jan 2024 10:30:00 +0000</pubDate>
            <category>Finance</category>
            <category>Economy</category>
            <author>Test Author</author>
            <guid>article-1</guid>
        </item>
        <item>
            <title>Second Article</title>
            <link>https://example.com/article/2</link>
            <description>Second description</description>
            <pubDate>Mon, 15 Jan 2024 12:00:00 +0000</pubDate>
        </item>
    </channel>
</rss>
XML;

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.com/rss')
            ->willReturn(new Response(200, [], $rssXml));

        $feed = $this->parser->fetchFeed('https://example.com/rss', 'TestSource');

        $this->assertInstanceOf(RssFeedDto::class, $feed);
        $this->assertSame('Test Feed', $feed->title);
        $this->assertSame('https://example.com', $feed->link);
        $this->assertSame('Test Description', $feed->description);
        $this->assertSame('TestSource', $feed->source);
        $this->assertCount(2, $feed->items);

        $firstItem = $feed->items[0];
        $this->assertInstanceOf(RssItemDto::class, $firstItem);
        $this->assertSame('Test Title', $firstItem->title);
        $this->assertSame('https://example.com/article/1', $firstItem->link);
        $this->assertSame('Test description content', $firstItem->description);
        $this->assertSame('TestSource', $firstItem->source);
        $this->assertSame(['Finance', 'Economy'], $firstItem->categories);
        $this->assertSame('Test Author', $firstItem->author);
        $this->assertSame('article-1', $firstItem->guid);

        $secondItem = $feed->items[1];
        $this->assertSame('Second Article', $secondItem->title);
        $this->assertEmpty($secondItem->categories);
        $this->assertSame('', $secondItem->author);
    }

    public function testFetchFeedCleansHtmlFromDescription(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test</title>
        <link>https://example.com</link>
        <item>
            <title>Title</title>
            <link>https://example.com/1</link>
            <description>&lt;p&gt;Hello &lt;b&gt;world&lt;/b&gt;!&lt;/p&gt;</description>
            <pubDate>Mon, 15 Jan 2024 10:30:00 +0000</pubDate>
        </item>
    </channel>
</rss>
XML;

        $this->httpClient
            ->method('request')
            ->willReturn(new Response(200, [], $rssXml));

        $feed = $this->parser->fetchFeed('https://example.com/rss', 'Test');

        $this->assertSame('Hello world!', $feed->items[0]->description);
    }

    public function testFetchFeedThrowsOnNetworkError(): void
    {
        $this->httpClient
            ->method('request')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->expectException(InfrastructureException::class);

        $this->parser->fetchFeed('https://example.com/rss', 'Test');
    }

    public function testFetchFeedThrowsOnInvalidXml(): void
    {
        $this->httpClient
            ->method('request')
            ->willReturn(new Response(200, [], 'Not valid XML'));

        $this->expectException(InfrastructureException::class);

        $this->parser->fetchFeed('https://example.com/rss', 'Test');
    }

    public function testFetchFeedHandlesEmptyFeed(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Empty Feed</title>
        <link>https://example.com</link>
    </channel>
</rss>
XML;

        $this->httpClient
            ->method('request')
            ->willReturn(new Response(200, [], $rssXml));

        $feed = $this->parser->fetchFeed('https://example.com/rss', 'Test');

        $this->assertCount(0, $feed->items);
    }

    public function testFetchFeedParsesVariousDateFormats(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test</title>
        <link>https://example.com</link>
        <item>
            <title>Item 1</title>
            <link>https://example.com/1</link>
            <description>Desc</description>
            <pubDate>2024-01-15T10:30:00+00:00</pubDate>
        </item>
    </channel>
</rss>
XML;

        $this->httpClient
            ->method('request')
            ->willReturn(new Response(200, [], $rssXml));

        $feed = $this->parser->fetchFeed('https://example.com/rss', 'Test');

        $this->assertInstanceOf(\DateTimeImmutable::class, $feed->items[0]->pubDate);
    }
}
