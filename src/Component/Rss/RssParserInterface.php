<?php

declare(strict_types=1);

namespace News\Core\Component\Rss;

use News\Core\Component\Rss\Dto\RssFeedDto;

interface RssParserInterface
{
    public function fetchFeed(string $url, string $source): RssFeedDto;
}
