<?php

declare(strict_types=1);

namespace News\Core\Component\Rss;

use DateTimeImmutable;
use GuzzleHttp\ClientInterface;
use News\Core\Component\Rss\Dto\RssFeedDto;
use News\Core\Component\Rss\Dto\RssItemDto;
use News\Core\Exception\InfrastructureException;
use Override;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

final class RssParser implements RssParserInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function fetchFeed(string $url, string $source): RssFeedDto
    {
        $this->logger->debug('Fetching RSS feed', ['url' => $url, 'source' => $source]);

        try {
            $response = $this->httpClient->request('GET', $url);
            $body = $response->getBody()->getContents();
        } catch (\Throwable $e) {
            throw InfrastructureException::rssFetchFailed($url, $e->getMessage());
        }

        try {
            $xml = new SimpleXMLElement($body);
        } catch (\Throwable $e) {
            throw InfrastructureException::rssParseFailed($url, $e->getMessage());
        }

        return $this->parseFeed($xml, $source);
    }

    private function parseFeed(SimpleXMLElement $xml, string $source): RssFeedDto
    {
        $channel = $xml->channel;

        $feedTitle = (string)($channel->title ?? $source);
        $feedLink = (string)($channel->link ?? '');
        $feedDescription = (string)($channel->description ?? '');

        $items = [];
        foreach ($channel->item as $item) {
            $items[] = $this->parseItem($item, $source);
        }

        return new RssFeedDto(
            title: $feedTitle,
            link: $feedLink,
            description: $feedDescription,
            source: $source,
            items: $items,
        );
    }

    private function parseItem(SimpleXMLElement $item, string $source): RssItemDto
    {
        $pubDate = $this->parseDate((string)$item->pubDate);
        $categories = $this->getCategories($item);
        $tags = $this->getTags($item, $source);

        return new RssItemDto(
            title: $this->cleanText((string)$item->title),
            link: (string)$item->link,
            description: $this->cleanText((string)$item->description),
            pubDate: $pubDate,
            source: $source,
            categories: $categories,
            tags: $tags,
            author: (string)($item->author ?? $item->children('dc', true)->creator ?? null),
            guid: (string)($item->guid ?? null),
        );
    }

    private function parseDate(string $dateString): DateTimeImmutable
    {
        $formats = [
            DateTimeImmutable::RFC2822,
            DateTimeImmutable::ATOM,
            'D, d M Y H:i:s O',
            'D, d M Y H:i:s P',
            'Y-m-d\TH:i:sP',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date;
            }
        }

        try {
            return new DateTimeImmutable($dateString);
        } catch (\Throwable) {
            return new DateTimeImmutable();
        }
    }

    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text ?? '');
    }

    /**
     * @return list<string>
     */
    private function getCategories(SimpleXMLElement $item): array
    {
        $categories = [];
        if (isset($item->category)) {
            foreach ($item->category as $cat) {
                $category = trim((string)$cat);
                if ($category !== '') {
                    $categories[] = $category;
                }
            }
        }
        return $categories;
    }

    /**
     * @return list<string>
     */
    private function getTags(SimpleXMLElement $item, string $source): array
    {
        $tags = [];

        // RBC-specific tags in rbc_news namespace
        if ($source === 'RBC') {
            $rbcNews = $item->children('rbc_news', true);
            if (isset($rbcNews->tag)) {
                foreach ($rbcNews->tag as $tag) {
                    $tagText = trim((string)$tag);
                    if ($tagText !== '') {
                        $tags[] = $tagText;
                    }
                }
            }
        }

        // Also check dc:subject for other providers
        $dcSubject = $item->children('dc', true)->subject ?? null;
        if ($dcSubject !== null) {
            $subject = trim((string)$dcSubject);
            if ($subject !== '') {
                $tags[] = $subject;
            }
        }

        return $tags;
    }
}
