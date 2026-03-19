<?php

declare(strict_types=1);

namespace News\Core\Service\News;

use DateTimeImmutable;
use News\Core\Component\Rss\RssParserInterface;
use News\Core\Service\News\Dto\NewsItemDto;
use Override;
use Psr\Log\LoggerInterface;

final class NewsService implements NewsServiceInterface
{
    private const SOURCES = [
        'interfax' => 'Interfax',
        'tass' => 'TASS',
        'ria' => 'RIA Novosti',
        'prime' => 'PRIME',
        'rbc' => 'RBC',
        'kommerzant' => 'Kommersant',
    ];

    public function __construct(
        private readonly RssParserInterface $rssParser,
        private readonly LoggerInterface $logger,
        private readonly string $interfaxUrl,
        private readonly string $tassUrl,
        private readonly string $riaUrl,
        private readonly string $primeUrl,
        private readonly string $rbcUrl,
        private readonly string $kommerzantUrl,
    ) {
    }

    #[Override]
    public function getAvailableSources(): array
    {
        return self::SOURCES;
    }

    #[Override]
    public function fetchNews(array $sources): array
    {
        $urls = $this->getSourceUrls($sources);
        $allNews = [];

        foreach ($urls as $source => $url) {
            try {
                $feed = $this->rssParser->fetchFeed($url, self::SOURCES[$source] ?? $source);
                $this->logger->info('Fetched news from source', [
                    'source' => $source,
                    'count' => count($feed->items),
                ]);

                foreach ($feed->items as $item) {
                    $allNews[] = new NewsItemDto(
                        title: $item->title,
                        link: $item->link,
                        description: $item->description,
                        pubDate: $item->pubDate->format('Y-m-d H:i:s'),
                        source: $item->source,
                        categories: $item->categories,
                        tags: $item->tags,
                    );
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to fetch news from source', [
                    'source' => $source,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        usort($allNews, function (NewsItemDto $a, NewsItemDto $b): int {
            return strcmp($b->pubDate, $a->pubDate);
        });

        return $allNews;
    }

    #[Override]
    public function filterNews(array $news, array $searchTerms): array
    {
        if (empty($searchTerms)) {
            return $news;
        }

        return array_values(array_filter($news, function (NewsItemDto $item) use ($searchTerms): bool {
            $searchableContent = mb_strtolower(sprintf(
                '%s %s %s %s',
                $item->title,
                $item->description,
                implode(' ', $item->categories),
                implode(' ', $item->tags),
            ));

            foreach ($searchTerms as $term) {
                if (mb_strpos($searchableContent, mb_strtolower($term)) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param list<NewsItemDto> $news
     * @param list<string> $categories
     * @return list<NewsItemDto>
     */
    public function filterByCategories(array $news, array $categories): array
    {
        if (empty($categories)) {
            return $news;
        }

        $categoriesLower = array_map('mb_strtolower', $categories);

        return array_values(array_filter($news, function (NewsItemDto $item) use ($categoriesLower): bool {
            $itemCategoriesLower = array_map('mb_strtolower', $item->categories);

            foreach ($categoriesLower as $category) {
                foreach ($itemCategoriesLower as $itemCategory) {
                    if (mb_strpos($itemCategory, $category) !== false) {
                        return true;
                    }
                }
            }

            return false;
        }));
    }

    /**
     * @param list<string> $sources
     * @return array<string, string>
     */
    private function getSourceUrls(array $sources): array
    {
        $urlMap = [
            'interfax' => $this->interfaxUrl,
            'tass' => $this->tassUrl,
            'ria' => $this->riaUrl,
            'prime' => $this->primeUrl,
            'rbc' => $this->rbcUrl,
            'kommerzant' => $this->kommerzantUrl,
        ];

        if (empty($sources) || in_array('all', $sources, true)) {
            return $urlMap;
        }

        return array_intersect_key($urlMap, array_flip($sources));
    }
}
