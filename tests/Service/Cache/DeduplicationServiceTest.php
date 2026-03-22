<?php

declare(strict_types=1);

namespace News\Core\Tests\Service\Cache;

use News\Core\Service\Cache\DeduplicationService;
use News\Core\Service\Cache\TextHasher;
use News\Core\Service\News\Dto\NewsItemDto;
use PHPUnit\Framework\TestCase;

final class DeduplicationServiceTest extends TestCase
{
    private DeduplicationService $service;

    protected function setUp(): void
    {
        $this->service = new DeduplicationService(new TextHasher());
    }

    public function testDeduplicateEmptyArrayReturnsEmpty(): void
    {
        $result = $this->service->deduplicate([]);

        $this->assertSame([], $result);
    }

    public function testDeduplicateSingleItemReturnsSameItem(): void
    {
        $news = [
            new NewsItemDto(
                title: 'Test Title',
                link: 'https://example.com/1',
                description: 'Test description',
                pubDate: '2026-03-22 10:00:00',
                source: 'TestSource',
            ),
        ];

        $result = $this->service->deduplicate($news);

        $this->assertCount(1, $result);
        $this->assertSame('Test Title', $result[0]->title);
    }

    public function testDeduplicateRemovesExactTitleDuplicates(): void
    {
        $news = [
            new NewsItemDto(
                title: 'Same Title',
                link: 'https://example.com/1',
                description: 'Description 1',
                pubDate: '2026-03-22 10:00:00',
                source: 'Source1',
            ),
            new NewsItemDto(
                title: 'Same Title',
                link: 'https://example.com/2',
                description: 'Description 2',
                pubDate: '2026-03-22 11:00:00',
                source: 'Source2',
            ),
        ];

        $result = $this->service->deduplicate($news);

        $this->assertCount(1, $result);
        $this->assertSame('https://example.com/1', $result[0]->link);
    }

    public function testDeduplicateRemovesCaseInsensitiveTitleDuplicates(): void
    {
        $news = [
            new NewsItemDto(
                title: 'Test Title',
                link: 'https://example.com/1',
                description: 'Description 1',
                pubDate: '2026-03-22 10:00:00',
                source: 'Source1',
            ),
            new NewsItemDto(
                title: 'TEST TITLE',
                link: 'https://example.com/2',
                description: 'Description 2',
                pubDate: '2026-03-22 11:00:00',
                source: 'Source2',
            ),
        ];

        $result = $this->service->deduplicate($news);

        $this->assertCount(1, $result);
    }

    public function testDeduplicateKeepsDifferentTitles(): void
    {
        $news = [
            new NewsItemDto(
                title: 'First Title',
                link: 'https://example.com/1',
                description: 'Description 1',
                pubDate: '2026-03-22 10:00:00',
                source: 'Source1',
            ),
            new NewsItemDto(
                title: 'Second Title',
                link: 'https://example.com/2',
                description: 'Description 2',
                pubDate: '2026-03-22 11:00:00',
                source: 'Source2',
            ),
        ];

        $result = $this->service->deduplicate($news);

        $this->assertCount(2, $result);
    }

    public function testDeduplicateRemovesSimilarContent(): void
    {
        $news = [
            new NewsItemDto(
                title: 'Сбербанк увеличил прибыль на 20%',
                link: 'https://example.com/1',
                description: 'Сбербанк сообщил об увеличении прибыли на 20% за квартал',
                pubDate: '2026-03-22 10:00:00',
                source: 'Source1',
            ),
            new NewsItemDto(
                title: 'Сбербанк увеличил прибыль на 20% за квартал',
                link: 'https://example.com/2',
                description: 'Сбербанк сообщил об увеличении прибыли на 20 процентов в отчетном периоде',
                pubDate: '2026-03-22 11:00:00',
                source: 'Source2',
            ),
        ];

        $result = $this->service->deduplicate($news);

        $this->assertCount(1, $result);
    }

    public function testDeduplicateUsesPrecomputedTitleNorm(): void
    {
        $news = [
            new NewsItemDto(
                title: 'Title A',
                link: 'https://example.com/1',
                description: 'Description',
                pubDate: '2026-03-22 10:00:00',
                source: 'Source',
                titleNorm: 'normalized_title',
            ),
            new NewsItemDto(
                title: 'Title B',
                link: 'https://example.com/2',
                description: 'Description',
                pubDate: '2026-03-22 11:00:00',
                source: 'Source',
                titleNorm: 'normalized_title',
            ),
        ];

        $result = $this->service->deduplicate($news);

        $this->assertCount(1, $result);
    }

    public function testDeduplicateUsesPrecomputedSimhash(): void
    {
        $textHasher = new TextHasher();
        $simhash = $textHasher->calculateSimhash('Same content here');

        $news = [
            new NewsItemDto(
                title: 'Title A',
                link: 'https://example.com/1',
                description: 'Different description A',
                pubDate: '2026-03-22 10:00:00',
                source: 'Source',
                simhash: $simhash,
            ),
            new NewsItemDto(
                title: 'Title B',
                link: 'https://example.com/2',
                description: 'Different description B',
                pubDate: '2026-03-22 11:00:00',
                source: 'Source',
                simhash: $simhash,
            ),
        ];

        $result = $this->service->deduplicate($news);

        $this->assertCount(1, $result);
    }

    public function testDeduplicatePreservesOrder(): void
    {
        $news = [
            new NewsItemDto(
                title: 'First',
                link: 'https://example.com/1',
                description: 'Description 1',
                pubDate: '2026-03-22 10:00:00',
                source: 'Source',
            ),
            new NewsItemDto(
                title: 'Second',
                link: 'https://example.com/2',
                description: 'Description 2',
                pubDate: '2026-03-22 11:00:00',
                source: 'Source',
            ),
            new NewsItemDto(
                title: 'Third',
                link: 'https://example.com/3',
                description: 'Description 3',
                pubDate: '2026-03-22 12:00:00',
                source: 'Source',
            ),
        ];

        $result = $this->service->deduplicate($news);

        $this->assertCount(3, $result);
        $this->assertSame('First', $result[0]->title);
        $this->assertSame('Second', $result[1]->title);
        $this->assertSame('Third', $result[2]->title);
    }

    public function testDeduplicateRemovesDuplicatesFromLargeSet(): void
    {
        $news = [];
        for ($i = 0; $i < 50; $i++) {
            $news[] = new NewsItemDto(
                title: 'Duplicate Title',
                link: "https://example.com/$i",
                description: 'Same description',
                pubDate: '2026-03-22 10:00:00',
                source: 'Source',
            );
        }
        for ($i = 0; $i < 50; $i++) {
            $news[] = new NewsItemDto(
                title: "Unique Title $i",
                link: "https://example.com/" . ($i + 50),
                description: "Unique description $i",
                pubDate: '2026-03-22 10:00:00',
                source: 'Source',
            );
        }

        $result = $this->service->deduplicate($news);

        $this->assertLessThanOrEqual(51, count($result));
        $this->assertGreaterThanOrEqual(1, count($result));
    }
}
