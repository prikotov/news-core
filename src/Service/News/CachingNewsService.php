<?php

declare(strict_types=1);

namespace News\Core\Service\News;

use DateTimeImmutable;
use News\Core\Service\Cache\CacheServiceInterface;
use News\Core\Service\News\Dto\NewsItemDto;
use Override;

final class CachingNewsService implements NewsServiceInterface
{
    public function __construct(
        private readonly NewsServiceInterface $newsService,
        private readonly CacheServiceInterface $cacheService,
    ) {
    }

    #[Override]
    public function fetchNews(array $sources): array
    {
        if ($sources !== []) {
            return $this->newsService->fetchNews($sources);
        }

        $today = new DateTimeImmutable();
        $todayNews = $this->cacheService->getByDate($today);
        $cachedSources = array_values(array_unique(array_map(
            fn(NewsItemDto $item) => $item->source,
            $todayNews,
        )));

        $allSources = array_keys($this->newsService->getAvailableSources());
        $sourcesToFetch = array_values(array_diff($allSources, $cachedSources));

        if ($sourcesToFetch === []) {
            return [];
        }

        return $this->newsService->fetchNews($sourcesToFetch);
    }

    #[Override]
    public function filterNews(array $news, array $searchTerms): array
    {
        return $this->newsService->filterNews($news, $searchTerms);
    }

    #[Override]
    public function filterByCategories(array $news, array $categories): array
    {
        return $this->newsService->filterByCategories($news, $categories);
    }

    #[Override]
    public function getAvailableSources(): array
    {
        return $this->newsService->getAvailableSources();
    }
}
