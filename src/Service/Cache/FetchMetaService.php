<?php

declare(strict_types=1);

namespace News\Core\Service\Cache;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final class FetchMetaService
{
    private const META_FILENAME = 'fetch-meta.json';
    private const TTL_SECONDS = 600;
    private const CACHE_DIR = 'data/news-rss/cache';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    public function needsFetch(string $source): bool
    {
        $lastFetch = $this->loadMeta($source);

        if ($lastFetch === null) {
            return true;
        }

        $lastFetchTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastFetch);
        if ($lastFetchTime === false) {
            return true;
        }

        $now = new DateTimeImmutable();
        $diff = $now->getTimestamp() - $lastFetchTime->getTimestamp();

        return $diff >= self::TTL_SECONDS;
    }

    public function markFetched(string $source): void
    {
        $this->saveMeta($source, (new DateTimeImmutable())->format('Y-m-d H:i:s'));

        $this->logger->debug('Marked source as fetched', ['source' => $source]);
    }

    private function loadMeta(string $source): ?string
    {
        $filePath = $this->getMetaFilePath($source);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        /** @var array{lastFetchAt?: string} $meta */
        $meta = json_decode($content, true);

        return is_array($meta) && isset($meta['lastFetchAt']) ? $meta['lastFetchAt'] : null;
    }

    private function saveMeta(string $source, string $lastFetchAt): void
    {
        $filePath = $this->getMetaFilePath($source);
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $meta = ['lastFetchAt' => $lastFetchAt];

        file_put_contents($filePath, json_encode($meta, JSON_PRETTY_PRINT));
    }

    private function getMetaFilePath(string $source): string
    {
        return $this->projectDir . '/' . self::CACHE_DIR . '/' . $source . '/' . self::META_FILENAME;
    }
}
