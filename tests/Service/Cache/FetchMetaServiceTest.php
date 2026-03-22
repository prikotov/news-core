<?php

declare(strict_types=1);

namespace News\Core\Tests\Service\Cache;

use News\Core\Service\Cache\FetchMetaService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FetchMetaServiceTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private string $tempDir;
    private FetchMetaService $service;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tempDir = sys_get_temp_dir() . '/news-core-test-' . uniqid();
        $this->service = new FetchMetaService($this->logger, $this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testNeedsFetchReturnsTrueWhenNoMetaExists(): void
    {
        $this->assertTrue($this->service->needsFetch('TASS'));
    }

    public function testNeedsFetchReturnsTrueWhenTtlExpired(): void
    {
        $source = 'TASS';
        $metaDir = $this->tempDir . '/data/news-rss/cache/' . $source;
        mkdir($metaDir, 0755, true);

        $oldTime = (new \DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s');
        file_put_contents($metaDir . '/fetch-meta.json', json_encode(['lastFetchAt' => $oldTime]));

        $this->assertTrue($this->service->needsFetch($source));
    }

    public function testNeedsFetchReturnsFalseWhenTtlNotExpired(): void
    {
        $source = 'TASS';
        $metaDir = $this->tempDir . '/data/news-rss/cache/' . $source;
        mkdir($metaDir, 0755, true);

        $recentTime = (new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s');
        file_put_contents($metaDir . '/fetch-meta.json', json_encode(['lastFetchAt' => $recentTime]));

        $this->assertFalse($this->service->needsFetch($source));
    }

    public function testMarkFetchedCreatesMetaFile(): void
    {
        $source = 'Interfax';

        $this->service->markFetched($source);

        $metaPath = $this->tempDir . '/data/news-rss/cache/' . $source . '/fetch-meta.json';
        $this->assertFileExists($metaPath);

        $meta = json_decode(file_get_contents($metaPath), true);
        $this->assertArrayHasKey('lastFetchAt', $meta);
    }

    public function testMarkFetchedUpdatesExistingMeta(): void
    {
        $source = 'RBC';
        $metaDir = $this->tempDir . '/data/news-rss/cache/' . $source;
        mkdir($metaDir, 0755, true);

        $oldTime = '2024-01-01 00:00:00';
        file_put_contents($metaDir . '/fetch-meta.json', json_encode(['lastFetchAt' => $oldTime]));

        $this->service->markFetched($source);

        $meta = json_decode(file_get_contents($metaDir . '/fetch-meta.json'), true);
        $this->assertNotEquals($oldTime, $meta['lastFetchAt']);
    }

    public function testNeedsFetchReturnsTrueForInvalidMetaFormat(): void
    {
        $source = 'PRIME';
        $metaDir = $this->tempDir . '/data/news-rss/cache/' . $source;
        mkdir($metaDir, 0755, true);

        file_put_contents($metaDir . '/fetch-meta.json', json_encode(['invalid' => 'data']));

        $this->assertTrue($this->service->needsFetch($source));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                is_dir($file) ? $this->removeDirectory($file) : unlink($file);
            }
        }

        rmdir($dir);
    }
}
