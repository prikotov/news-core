<?php

declare(strict_types=1);

namespace News\Skill\Component\Rss;

use News\Skill\Component\Rss\Dto\RssFeedDto;

interface RssParserInterface
{
    public function fetchFeed(string $url, string $source): RssFeedDto;
}
