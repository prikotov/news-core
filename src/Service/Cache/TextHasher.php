<?php

declare(strict_types=1);

namespace News\Core\Service\Cache;

final class TextHasher
{
    public function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '';
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        return trim($text);
    }

    public function calculateSimhash(string $text): string
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === '') {
            return str_repeat('0', 64);
        }

        $shingles = $this->getShingles($normalized, 3);

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

        $binary = '';
        for ($i = 0; $i < 64; $i++) {
            $binary .= $v[$i] > 0 ? '1' : '0';
        }

        return $binary;
    }

    public function hammingDistance(string $hash1, string $hash2): int
    {
        $distance = 0;
        $len = min(strlen($hash1), strlen($hash2));

        for ($i = 0; $i < $len; $i++) {
            if ($hash1[$i] !== $hash2[$i]) {
                $distance++;
            }
        }

        return $distance;
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
}
