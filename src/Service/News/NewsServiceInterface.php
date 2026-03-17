<?php

declare(strict_types=1);

namespace News\Skill\Service\News;

use News\Skill\Service\News\Dto\NewsItemDto;

interface NewsServiceInterface
{
    /**
     * @param list<string> $sources
     * @return list<NewsItemDto>
     */
    public function fetchNews(array $sources): array;

    /**
     * @param list<NewsItemDto> $news
     * @param list<string> $searchTerms
     * @return list<NewsItemDto>
     */
    public function filterNews(array $news, array $searchTerms): array;

    /**
     * @param list<NewsItemDto> $news
     * @param list<string> $categories
     * @return list<NewsItemDto>
     */
    public function filterByCategories(array $news, array $categories): array;

    /**
     * @return array<string, string>
     */
    public function getAvailableSources(): array;
}
