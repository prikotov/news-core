<?php

declare(strict_types=1);

namespace News\Core\Service\Cache;

use News\Core\Service\News\Dto\NewsItemDto;

interface DeduplicationServiceInterface
{
    /**
     * @param list<NewsItemDto> $news
     * @return list<NewsItemDto>
     */
    public function deduplicate(array $news): array;
}
