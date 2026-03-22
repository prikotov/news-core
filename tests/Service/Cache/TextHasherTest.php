<?php

declare(strict_types=1);

namespace News\Core\Tests\Service\Cache;

use News\Core\Service\Cache\TextHasher;
use PHPUnit\Framework\TestCase;

final class TextHasherTest extends TestCase
{
    private TextHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new TextHasher();
    }

    public function testNormalizeTextToLowercase(): void
    {
        $result = $this->hasher->normalizeText('HELLO World');

        $this->assertSame('hello world', $result);
    }

    public function testNormalizeTextRemovesPunctuation(): void
    {
        $result = $this->hasher->normalizeText('Hello, World! How are you?');

        $this->assertSame('hello world how are you', $result);
    }

    public function testNormalizeTextCollapsesWhitespace(): void
    {
        $result = $this->hasher->normalizeText('Hello    World');

        $this->assertSame('hello world', $result);
    }

    public function testNormalizeTextTrimsWhitespace(): void
    {
        $result = $this->hasher->normalizeText('  Hello World  ');

        $this->assertSame('hello world', $result);
    }

    public function testNormalizeTextKeepsCyrillic(): void
    {
        $result = $this->hasher->normalizeText('Привет, Мир!');

        $this->assertSame('привет мир', $result);
    }

    public function testNormalizeTextKeepsNumbers(): void
    {
        $result = $this->hasher->normalizeText('Price: 100$');

        $this->assertSame('price 100', $result);
    }

    public function testCalculateSimhashReturnsString(): void
    {
        $result = $this->hasher->calculateSimhash('Some text');

        $this->assertIsString($result);
    }

    public function testCalculateSimhashIsDeterministic(): void
    {
        $text = 'Deterministic test';

        $result1 = $this->hasher->calculateSimhash($text);
        $result2 = $this->hasher->calculateSimhash($text);

        $this->assertSame($result1, $result2);
    }

    public function testCalculateSimhashDifferentForDifferentTexts(): void
    {
        $hash1 = $this->hasher->calculateSimhash('First text');
        $hash2 = $this->hasher->calculateSimhash('Second text');

        $this->assertNotSame($hash1, $hash2);
    }

    public function testCalculateSimhashSimilarForSimilarTexts(): void
    {
        $hash1 = $this->hasher->calculateSimhash('Сбербанк увеличил прибыль на 20%');
        $hash2 = $this->hasher->calculateSimhash('Сбербанк увеличил прибыль на 25%');

        $distance = $this->hasher->hammingDistance($hash1, $hash2);

        $this->assertLessThan(15, $distance);
    }

    public function testCalculateSimhashReturnsZeroForEmptyString(): void
    {
        $result = $this->hasher->calculateSimhash('');

        $this->assertSame(str_repeat('0', 64), $result);
    }

    public function testHammingDistanceZeroForIdenticalHashes(): void
    {
        $hash = str_repeat('01', 32);

        $distance = $this->hasher->hammingDistance($hash, $hash);

        $this->assertSame(0, $distance);
    }

    public function testHammingDistancePositiveForDifferentHashes(): void
    {
        $hash1 = str_repeat('0', 64);
        $hash2 = str_repeat('0', 63) . '1';

        $distance = $this->hasher->hammingDistance($hash1, $hash2);

        $this->assertGreaterThan(0, $distance);
    }

    public function testHammingDistanceSymmetric(): void
    {
        $hash1 = str_repeat('01', 32);
        $hash2 = str_repeat('10', 32);

        $distance12 = $this->hasher->hammingDistance($hash1, $hash2);
        $distance21 = $this->hasher->hammingDistance($hash2, $hash1);

        $this->assertSame($distance12, $distance21);
    }
}
