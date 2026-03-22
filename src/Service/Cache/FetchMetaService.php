<?php

declare(strict_types=1);

namespace News\Core\Service\Cache;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final class FetchMetaService
{
    private const META_FILE = 'data/news-rss/cache/fetch-meta.json';
    private const TTL_SECONDS = 600;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    public function needsFetch(string $source): bool
    {
        $meta = $this->loadMeta();
        $lastFetch = $meta[$source] ?? null;

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
        $meta = $this->loadMeta();
        $meta[$source] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->saveMeta($meta);

        $this->logger->debug('Marked source as fetched', ['source' => $source]);
    }

    /**
     * @return array<string, string>
     */
    private function loadMeta(): array
    {
        $filePath = $this->getMetaFilePath();

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        /** @var array<string, string> $meta */
        $meta = json_decode($content, true);

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param array<string, string> $meta
     */
    private function saveMeta(array $meta): void
    {
        $filePath = $this->getMetaFilePath();
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, json_encode($meta, JSON_PRETTY_PRINT));
    }

    private function getMetaFilePath(): string
    {
        return $this->projectDir . '/' . self::META_FILE;
    }
}
