<?php

declare(strict_types=1);

namespace News\Core\Service\Cache;

use News\Core\Service\News\Dto\NewsItemDto;
use Override;

final class DeduplicationService implements DeduplicationServiceInterface
{
    private const SIMHASH_THRESHOLD = 10;

    public function __construct(
        private readonly TextHasher $textHasher,
    ) {
    }

    #[Override]
    public function deduplicate(array $news): array
    {
        if ($news === []) {
            return [];
        }

        $deduplicated = [];
        $seenHashes = [];
        $seenTitles = [];

        foreach ($news as $item) {
            $titleNorm = $this->getNormalizedTitle($item);

            if (isset($seenTitles[$titleNorm])) {
                continue;
            }

            $simhash = $this->getSimhash($item);

            if ($this->isSimilarExists($simhash, $seenHashes)) {
                continue;
            }

            $seenTitles[$titleNorm] = true;
            $seenHashes[] = $simhash;
            $deduplicated[] = $item;
        }

        return $deduplicated;
    }

    private function getNormalizedTitle(NewsItemDto $item): string
    {
        if ($item->titleNorm !== '') {
            return $item->titleNorm;
        }

        return $this->textHasher->normalizeText($item->title);
    }

    private function getSimhash(NewsItemDto $item): string
    {
        if ($item->simhash !== '') {
            return $item->simhash;
        }

        return $this->textHasher->calculateSimhash($item->title . ' ' . $item->description);
    }

    private function isSimilarExists(string $simhash, array $seenHashes): bool
    {
        foreach ($seenHashes as $seenHash) {
            if ($this->textHasher->hammingDistance($simhash, $seenHash) <= self::SIMHASH_THRESHOLD) {
                return true;
            }
        }

        return false;
    }
}
