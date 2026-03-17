<?php

declare(strict_types=1);

namespace News\Skill\Service\Cache;

use DateTimeImmutable;
use News\Skill\Service\News\Dto\NewsItemDto;
use Override;
use Psr\Log\LoggerInterface;

final class CacheService implements CacheServiceInterface
{
    private const CACHE_DIR = 'cache';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    public function store(array $news): int
    {
        $stored = 0;
        $duplicates = 0;

        foreach ($news as $item) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $item->pubDate);
            if ($date === false) {
                $date = new DateTimeImmutable();
            }

            $sourceDir = $this->getSourceDir($date, $item->source);
            if (!is_dir($sourceDir)) {
                mkdir($sourceDir, 0755, true);
            }

            $id = $this->generateId($item);
            $baseFilename = $date->format('Ymd-His') . '-' . $id;

            $existingFiles = glob($sourceDir . '/*-' . $id . '.json');
            if ($existingFiles !== [] && $this->isDuplicate($item, $sourceDir, $id)) {
                $duplicates++;
                continue;
            }

            if ($this->isSimilarExists($item, $sourceDir)) {
                $duplicates++;
                continue;
            }

            $metaPath = $sourceDir . '/' . $baseFilename . '.json';
            $textPath = $sourceDir . '/' . $baseFilename . '.txt';

            $meta = [
                'id' => $id,
                'title' => $item->title,
                'title_norm' => $this->normalizeText($item->title),
                'simhash' => $this->calculateSimhash($item->title . ' ' . $item->description),
                'link' => $item->link,
                'source' => $item->source,
                'pubDate' => $item->pubDate,
                'categories' => $item->categories,
                'tags' => $item->tags,
            ];

            file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            file_put_contents($textPath, $item->description);

            $stored++;
        }

        $this->logger->info('News cached', ['stored' => $stored, 'duplicates' => $duplicates]);

        return $stored;
    }

    public function getByDate(DateTimeImmutable $date, array $sources = []): array
    {
        $news = [];
        $baseDir = $this->getCacheDir() . '/' . $date->format('Y/m/d');

        if (!is_dir($baseDir)) {
            return [];
        }

        $sourceDirs = $sources === [] ? (glob($baseDir . '/*', GLOB_ONLYDIR) ?: []) : [];

        if ($sources !== []) {
            foreach ($sources as $source) {
                $dir = $baseDir . '/' . $source;
                if (is_dir($dir)) {
                    $sourceDirs[] = $dir;
                }
            }
        }

        foreach ($sourceDirs as $sourceDir) {
            $metaFiles = glob($sourceDir . '/*.json');
            if ($metaFiles === false) {
                continue;
            }

            foreach ($metaFiles as $metaFile) {
                $content = file_get_contents($metaFile);
                if ($content === false) {
                    continue;
                }

                /** @var array{title: string, link: string, pubDate: string, source: string, categories?: list<string>, tags?: list<string>} $meta */
                $meta = json_decode($content, true);
                if (!is_array($meta)) {
                    continue;
                }

                $title = $meta['title'] ?? null;
                $link = $meta['link'] ?? null;
                $pubDate = $meta['pubDate'] ?? null;
                $source = $meta['source'] ?? null;

                if ($title === null || $link === null || $pubDate === null || $source === null) {
                    continue;
                }

                $textFile = substr($metaFile, 0, -4) . 'txt';
                $description = file_exists($textFile) ? file_get_contents($textFile) : '';

                $news[] = new NewsItemDto(
                    title: $title,
                    link: $link,
                    description: $description !== false ? $description : '',
                    pubDate: $pubDate,
                    source: $source,
                    categories: isset($meta['categories']) && is_array($meta['categories']) ? $meta['categories'] : [],
                    tags: isset($meta['tags']) && is_array($meta['tags']) ? $meta['tags'] : [],
                );
            }
        }

        usort($news, fn($a, $b) => strcmp($b->pubDate, $a->pubDate));

        return $news;
    }

    public function search(array $searchTerms, array $sources = [], int $daysBack = 7): array
    {
        $results = [];
        $today = new DateTimeImmutable();

        for ($i = 0; $i <= $daysBack; $i++) {
            $date = $today->modify('-' . $i . ' days');
            $dayNews = $this->getByDate($date, $sources);

            foreach ($dayNews as $item) {
                if ($this->matchesSearch($item, $searchTerms)) {
                    $results[] = $item;
                }
            }
        }

        usort($results, fn($a, $b) => strcmp($b->pubDate, $a->pubDate));

        return $results;
    }

    public function getCacheStats(): array
    {
        $stats = [];
        $cacheDir = $this->getCacheDir();

        if (!is_dir($cacheDir)) {
            return $stats;
        }

        $years = glob($cacheDir . '/*', GLOB_ONLYDIR);
        if ($years === false) {
            return $stats;
        }

        foreach ($years as $yearDir) {
            $months = glob($yearDir . '/*', GLOB_ONLYDIR);
            if ($months === false) {
                continue;
            }

            foreach ($months as $monthDir) {
                $days = glob($monthDir . '/*', GLOB_ONLYDIR);
                if ($days === false) {
                    continue;
                }

                foreach ($days as $dayDir) {
                    $sources = glob($dayDir . '/*', GLOB_ONLYDIR);
                    if ($sources === false) {
                        continue;
                    }

                    foreach ($sources as $sourceDir) {
                        $files = glob($sourceDir . '/*.json');
                        $count = $files !== false ? count($files) : 0;

                        $date = basename($dayDir);
                        $month = basename($monthDir);
                        $year = basename($yearDir);
                        $source = basename($sourceDir);
                        $key = $year . '-' . $month . '-' . $date . '/' . $source;

                        $stats[$key] = [
                            'date' => $year . '-' . $month . '-' . $date,
                            'source' => $source,
                            'count' => $count,
                        ];
                    }
                }
            }
        }

        krsort($stats);

        return $stats;
    }

    public function clear(int $daysToKeep = 30): int
    {
        $deleted = 0;
        $cacheDir = $this->getCacheDir();
        $cutoffDate = new DateTimeImmutable('-' . $daysToKeep . ' days');

        if (!is_dir($cacheDir)) {
            return 0;
        }

        $years = glob($cacheDir . '/*', GLOB_ONLYDIR);
        if ($years === false) {
            return 0;
        }

        foreach ($years as $yearDir) {
            $months = glob($yearDir . '/*', GLOB_ONLYDIR);
            if ($months === false) {
                continue;
            }

            foreach ($months as $monthDir) {
                $days = glob($monthDir . '/*', GLOB_ONLYDIR);
                if ($days === false) {
                    continue;
                }

                foreach ($days as $dayDir) {
                    $dirDate = $this->extractDateFromPath($dayDir);

                    if ($dirDate !== null && $dirDate < $cutoffDate) {
                        $deleted += $this->removeDirectory($dayDir);
                    }
                }
            }
        }

        $this->logger->info('Cache cleared', ['deleted' => $deleted, 'daysToKeep' => $daysToKeep]);

        return $deleted;
    }

    private function getCacheDir(): string
    {
        return $this->projectDir . '/' . self::CACHE_DIR;
    }

    private function getSourceDir(DateTimeImmutable $date, string $source): string
    {
        return $this->getCacheDir() . '/' . $date->format('Y/m/d') . '/' . $source;
    }

    private function generateId(NewsItemDto $item): string
    {
        return substr(md5($item->link), 0, 8);
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '';
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        return trim($text);
    }

    private function calculateSimhash(string $text): string
    {
        $normalized = $this->normalizeText($text);
        $shingles = $this->getShingles($normalized, 3);

        if ($shingles === []) {
            return '0';
        }

        $v = array_fill(0, 64, 0);

        foreach ($shingles as $shingle) {
            $hash = md5($shingle, true);
            $bits = unpack('N*', $hash);

            for ($i = 0; $i < 64; $i++) {
                $byteIndex = (int)floor($i / 8);
                $bitIndex = $i % 8;
                $byte = $bits[$byteIndex + 1] ?? 0;
                $bit = ($byte >> (7 - $bitIndex)) & 1;
                $v[$i] += $bit === 1 ? 1 : -1;
            }
        }

        $simhash = 0;
        for ($i = 0; $i < 64; $i++) {
            if ($v[$i] > 0) {
                $simhash |= (1 << (63 - $i));
            }
        }

        return (string)$simhash;
    }

    /**
     * @return list<string>
     */
    private function getShingles(string $text, int $k): array
    {
        $words = preg_split('/\s+/', $text);
        if ($words === false || count($words) < $k) {
            return [$text];
        }

        $shingles = [];
        $count = count($words);

        for ($i = 0; $i <= $count - $k; $i++) {
            $shingle = implode(' ', array_slice($words, $i, $k));
            $shingles[] = $shingle;
        }

        return $shingles;
    }

    private function hammingDistance(string $hash1, string $hash2): int
    {
        $h1 = (int)$hash1;
        $h2 = (int)$hash2;

        $xor = $h1 ^ $h2;
        $distance = 0;

        while ($xor !== 0) {
            $distance += $xor & 1;
            $xor >>= 1;
        }

        return $distance;
    }

    private function isDuplicate(NewsItemDto $item, string $sourceDir, string $id): bool
    {
        $existingFiles = glob($sourceDir . '/*-' . $id . '.json');
        return $existingFiles !== [];
    }

    private function isSimilarExists(NewsItemDto $item, string $sourceDir): bool
    {
        $newSimhash = $this->calculateSimhash($item->title . ' ' . $item->description);
        $newTitleNorm = $this->normalizeText($item->title);

        $metaFiles = glob($sourceDir . '/*.json');
        if ($metaFiles === false) {
            return false;
        }

        foreach ($metaFiles as $metaFile) {
            $content = file_get_contents($metaFile);
            if ($content === false) {
                continue;
            }

            /** @var array{title_norm?: string, simhash?: string}|null $meta */
            $meta = json_decode($content, true);
            if (!is_array($meta)) {
                continue;
            }

            $existingTitleNorm = $meta['title_norm'] ?? '';
            if ($existingTitleNorm === $newTitleNorm) {
                return true;
            }

            $existingSimhash = $meta['simhash'] ?? '0';
            if (!is_string($existingSimhash)) {
                continue;
            }

            $distance = $this->hammingDistance($newSimhash, $existingSimhash);

            if ($distance <= 10) {
                return true;
            }
        }

        return false;
    }

    private function matchesSearch(NewsItemDto $item, array $searchTerms): bool
    {
        if ($searchTerms === []) {
            return true;
        }

        $searchableContent = mb_strtolower(sprintf(
            '%s %s %s %s',
            $item->title,
            $item->description,
            implode(' ', $item->categories),
            implode(' ', $item->tags),
        ));

        foreach ($searchTerms as $term) {
            if (mb_strpos($searchableContent, mb_strtolower($term)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extractDateFromPath(string $path): ?DateTimeImmutable
    {
        $parts = explode('/', $path);
        $count = count($parts);

        if ($count < 3) {
            return null;
        }

        $day = $parts[$count - 1];
        $month = $parts[$count - 2];
        $year = $parts[$count - 3];

        $dateStr = $year . '-' . $month . '-' . $day;

        try {
            return new DateTimeImmutable($dateStr);
        } catch (\Throwable) {
            return null;
        }
    }

    private function removeDirectory(string $dir): int
    {
        $count = 0;

        $files = glob($dir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $count += $this->removeDirectory($file);
                } else {
                    unlink($file);
                    $count++;
                }
            }
        }

        rmdir($dir);

        return $count;
    }
}
