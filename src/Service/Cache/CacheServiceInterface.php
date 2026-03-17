<?php

declare(strict_types=1);

namespace News\Skill\Service\Cache;

use DateTimeImmutable;
use News\Skill\Service\News\Dto\NewsItemDto;

interface CacheServiceInterface
{
    /**
     * @param list<NewsItemDto> $news
     */
    public function store(array $news): int;

    /**
     * @param DateTimeImmutable $date
     * @param list<string> $sources
     * @return list<NewsItemDto>
     */
    public function getByDate(DateTimeImmutable $date, array $sources = []): array;

    /**
     * @param list<string> $searchTerms
     * @param list<string> $sources
     * @param int $daysBack
     * @return list<NewsItemDto>
     */
    public function search(
        array $searchTerms,
        array $sources = [],
        int $daysBack = 7,
    ): array;

    /**
     * @return array<string, array{date: string, count: int}>
     */
    public function getCacheStats(): array;

    public function clear(int $daysToKeep = 30): int;
}
