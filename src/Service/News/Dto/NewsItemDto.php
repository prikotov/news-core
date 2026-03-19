<?php

declare(strict_types=1);

namespace News\Core\Service\News\Dto;

final readonly class NewsItemDto
{
    /**
     * @param list<string> $categories
     * @param list<string> $tags
     */
    public function __construct(
        public string $title,
        public string $link,
        public string $description,
        public string $pubDate,
        public string $source,
        public array $categories = [],
        public array $tags = [],
    ) {
    }
}
