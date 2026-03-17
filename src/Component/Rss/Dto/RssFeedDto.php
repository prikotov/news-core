<?php

declare(strict_types=1);

namespace News\Skill\Component\Rss\Dto;

final readonly class RssFeedDto
{
    /**
     * @param string $title
     * @param string $link
     * @param string $description
     * @param string $source
     * @param list<RssItemDto> $items
     */
    public function __construct(
        public string $title,
        public string $link,
        public string $description,
        public string $source,
        public array $items,
    ) {
    }
}
