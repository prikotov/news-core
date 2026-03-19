<?php

declare(strict_types=1);

namespace News\Core\Component\Rss\Dto;

final readonly class RssItemDto
{
    /**
     * @param list<string> $categories
     * @param list<string> $tags
     */
    public function __construct(
        public string $title,
        public string $link,
        public string $description,
        public \DateTimeImmutable $pubDate,
        public string $source,
        public array $categories = [],
        public array $tags = [],
        public ?string $author = null,
        public ?string $guid = null,
    ) {
    }
}
