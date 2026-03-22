<?php

declare(strict_types=1);

namespace News\Core\Service\Cache;

use DateTimeImmutable;
use News\Core\Service\News\Dto\NewsItemDto;
use Override;
use Psr\Log\LoggerInterface;

final class CacheService implements CacheServiceInterface
{
    private const CACHE_DIR = 'data/news-rss/cache';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    public function getSourceBaseDir(string $source): string
    {
        return $this->getCacheDir() . '/' . $source;
    }

    public function store(array $news): int
    {
        $stored = 0;

        foreach ($news as $item) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $item->pubDate);
            if ($date === false) {
                $date = new DateTimeImmutable();
            }

            $sourceDateDir = $this->getSourceDateDir($item->source, $date);
            if (!is_dir($sourceDateDir)) {
                mkdir($sourceDateDir, 0755, true);
            }

            $id = $this->generateId($item);
            $baseFilename = $date->format('Ymd-His') . '-' . $id;

            $metaPath = $sourceDateDir . '/' . $baseFilename . '.json';
            $textPath = $sourceDateDir . '/' . $baseFilename . '.txt';

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

        $this->logger->info('News cached', ['stored' => $stored]);

        return $stored;
    }

    public function getByDate(DateTimeImmutable $date, array $sources = []): array
    {
        $news = [];
        $cacheDir = $this->getCacheDir();

        if (!is_dir($cacheDir)) {
            return [];
        }

        $sourceDirs = $sources === []
            ? (glob($cacheDir . '/*', GLOB_ONLYDIR) ?: [])
            : [];

        if ($sources !== []) {
            foreach ($sources as $source) {
                $dir = $this->getSourceBaseDir($source);
                if (is_dir($dir)) {
                    $sourceDirs[] = $dir;
                }
            }
        }

        $dateStr = $date->format('Y/m/d');

        foreach ($sourceDirs as $sourceDir) {
            $dateDir = $sourceDir . '/' . $dateStr;
            if (!is_dir($dateDir)) {
                continue;
            }

            $metaFiles = glob($dateDir . '/*.json');
            if ($metaFiles === false) {
                continue;
            }

            foreach ($metaFiles as $metaFile) {
                $content = file_get_contents($metaFile);
                if ($content === false) {
                    continue;
                }

                /** @var array{title: string, link: string, pubDate: string, source: string, categories?: list<string>, tags?: list<string>, simhash?: string, title_norm?: string} $meta */
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
                    simhash: isset($meta['simhash']) && is_string($meta['simhash']) ? $meta['simhash'] : '',
                    titleNorm: isset($meta['title_norm']) && is_string($meta['title_norm']) ? $meta['title_norm'] : '',
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

        return $this->deduplicate($results);
    }

    public function getCacheStats(): array
    {
        $stats = [];
        $cacheDir = $this->getCacheDir();

        if (!is_dir($cacheDir)) {
            return $stats;
        }

        $sourceDirs = glob($cacheDir . '/*', GLOB_ONLYDIR);
        if ($sourceDirs === false) {
            return $stats;
        }

        foreach ($sourceDirs as $sourceDir) {
            $source = basename($sourceDir);
            $yearDirs = glob($sourceDir . '/*', GLOB_ONLYDIR);
            if ($yearDirs === false) {
                continue;
            }

            foreach ($yearDirs as $yearDir) {
                $monthDirs = glob($yearDir . '/*', GLOB_ONLYDIR);
                if ($monthDirs === false) {
                    continue;
                }

                foreach ($monthDirs as $monthDir) {
                    $dayDirs = glob($monthDir . '/*', GLOB_ONLYDIR);
                    if ($dayDirs === false) {
                        continue;
                    }

                    foreach ($dayDirs as $dayDir) {
                        $files = glob($dayDir . '/*.json');
                        $count = $files !== false ? count($files) : 0;

                        $day = basename($dayDir);
                        $month = basename($monthDir);
                        $year = basename($yearDir);
                        $key = $year . '-' . $month . '-' . $day . '/' . $source;

                        $stats[$key] = [
                            'date' => $year . '-' . $month . '-' . $day,
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

        $sourceDirs = glob($cacheDir . '/*', GLOB_ONLYDIR);
        if ($sourceDirs === false) {
            return 0;
        }

        foreach ($sourceDirs as $sourceDir) {
            $yearDirs = glob($sourceDir . '/*', GLOB_ONLYDIR);
            if ($yearDirs === false) {
                continue;
            }

            foreach ($yearDirs as $yearDir) {
                $monthDirs = glob($yearDir . '/*', GLOB_ONLYDIR);
                if ($monthDirs === false) {
                    continue;
                }

                foreach ($monthDirs as $monthDir) {
                    $dayDirs = glob($monthDir . '/*', GLOB_ONLYDIR);
                    if ($dayDirs === false) {
                        continue;
                    }

                    foreach ($dayDirs as $dayDir) {
                        $dirDate = $this->extractDateFromPath($dayDir);

                        if ($dirDate !== null && $dirDate < $cutoffDate) {
                            $deleted += $this->removeDirectory($dayDir);
                        }
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

    private function getSourceDateDir(string $source, DateTimeImmutable $date): string
    {
        return $this->getSourceBaseDir($source) . '/' . $date->format('Y/m/d');
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
                } elseif (basename($file) !== 'fetch-meta.json') {
                    unlink($file);
                    $count++;
                }
            }
        }

        if ($count > 0) {
            rmdir($dir);
        }

        return $count;
    }

    /**
     * @param list<NewsItemDto> $news
     * @return list<NewsItemDto>
     */
    private function deduplicate(array $news): array
    {
        if ($news === []) {
            return [];
        }

        $deduplicated = [];
        $seenHashes = [];
        $seenTitles = [];

        foreach ($news as $item) {
            $titleNorm = $item->titleNorm;
            if ($titleNorm === '') {
                $titleNorm = $this->normalizeText($item->title);
            }

            if (isset($seenTitles[$titleNorm])) {
                continue;
            }

            $simhash = $item->simhash;
            if ($simhash === '') {
                $simhash = $this->calculateSimhash($item->title . ' ' . $item->description);
            }

            $isDuplicate = false;
            foreach ($seenHashes as $seenHash) {
                if ($this->hammingDistance($simhash, $seenHash) <= 10) {
                    $isDuplicate = true;
                    break;
                }
            }

            if ($isDuplicate) {
                continue;
            }

            $seenTitles[$titleNorm] = true;
            $seenHashes[] = $simhash;
            $deduplicated[] = $item;
        }

        return $deduplicated;
    }
}
