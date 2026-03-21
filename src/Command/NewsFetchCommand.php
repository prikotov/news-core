<?php

declare(strict_types=1);

namespace News\Core\Command;

use News\Core\Helper\OutputFormatTrait;
use News\Core\Service\News\Dto\NewsItemDto;
use News\Core\Service\News\NewsServiceInterface;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'news:fetch',
    description: 'Fetch news from RSS sources',
)]
final class NewsFetchCommand extends Command
{
    use OutputFormatTrait;

    public function __construct(
        private readonly NewsServiceInterface $newsService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'source',
                's',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Specific sources to fetch (interfax, tass, ria, prime, rbc, kommerzant)',
            )
            ->addOption(
                'search',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Search terms to filter news (e.g., --search "Сбербанк" --search "нефть")',
            )
            ->addOption(
                'category',
                'c',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Filter by categories (e.g., --category "Экономика" --category "Бизнес")',
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Limit number of news items',
                50,
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format: text, json, csv, md',
                'md',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $sources */
        $sources = $input->getOption('source');
        /** @var list<string> $searchTerms */
        $searchTerms = $input->getOption('search');
        /** @var list<string> $categories */
        $categories = $input->getOption('category');
        /** @var mixed $limitValue */
        $limitValue = $input->getOption('limit');
        $limit = is_numeric($limitValue) ? (int)$limitValue : 50;
        $format = $this->getFormat($input);

        $hasFilters = !empty($searchTerms) || !empty($categories);

        $news = $this->newsService->fetchNews($sources);

        if ($news === []) {
            $this->logger->warning('No news fetched from sources', ['sources' => $sources ?: 'all']);
            if ($format === 'json') {
                $json = json_encode([
                    'items' => [],
                    'total' => 0,
                    'reason' => 'no_data_from_sources',
                    'message' => 'Failed to fetch news from RSS sources',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $output->writeln($json ?: '{}');
                return Command::SUCCESS;
            }
            $output->writeln('<error>No news fetched from RSS sources. Check network connection or source availability.</error>');
            return Command::SUCCESS;
        }

        $allNewsCount = count($news);

        if (!empty($searchTerms)) {
            $news = $this->newsService->filterNews($news, $searchTerms);
            $output->writeln(sprintf('<comment>Filtering by: %s</comment>', implode(', ', $searchTerms)));
            $output->writeln('');
        }

        if (!empty($categories)) {
            $news = $this->newsService->filterByCategories($news, $categories);
            $output->writeln(sprintf('<comment>Filtering by categories: %s</comment>', implode(', ', $categories)));
            $output->writeln('');
        }

        if ($news === [] && $hasFilters) {
            $this->logger->info('No news matched filters', [
                'searchTerms' => $searchTerms,
                'categories' => $categories,
                'totalFetched' => $allNewsCount,
            ]);

            if ($format === 'json') {
                $json = json_encode([
                    'items' => [],
                    'total' => 0,
                    'reason' => 'no_matches',
                    'message' => 'No news matched the specified filters',
                    'filters' => [
                        'searchTerms' => $searchTerms,
                        'categories' => $categories,
                    ],
                    'totalFetched' => $allNewsCount,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $output->writeln($json ?: '{}');
                return Command::SUCCESS;
            }

            $output->writeln(sprintf(
                '<comment>No news matched filters (searched %d items)</comment>',
                $allNewsCount
            ));
            return Command::SUCCESS;
        }

        if ($limit > 0) {
            $news = array_slice($news, 0, $limit);
        }

        if ($format === 'csv' || $format === 'md') {
            $rows = array_map(fn(NewsItemDto $item) => [
                $item->pubDate,
                $item->source,
                implode(', ', $item->categories) ?: '-',
                $this->truncate($item->title, 100),
                $item->link,
            ], $news);

            return $this->outputFormat(
                $output,
                $format,
                ['Date', 'Source', 'Category', 'Title', 'Link'],
                $rows,
                'News'
            );
        }

        return match ($format) {
            'json' => $this->outputJson($output, $news),
            default => $this->outputTable($output, $news),
        };
    }

    /**
     * @param list<NewsItemDto> $news
     */
    private function outputTable(OutputInterface $output, array $news): int
    {
        $table = new Table($output);
        $table->setHeaders(['Date', 'Source', 'Category', 'Title']);
        $table->setColumnMaxWidth(3, 80);

        foreach ($news as $item) {
            $table->addRow([
                $item->pubDate,
                $item->source,
                implode(', ', $item->categories) ?: '-',
                $this->truncate($item->title, 77),
            ]);
        }

        $table->render();

        $output->writeln(sprintf('<info>Total: %d news items</info>', count($news)));

        return Command::SUCCESS;
    }

    /**
     * @param list<NewsItemDto> $news
     */
    private function outputJson(OutputInterface $output, array $news): int
    {
        $data = array_map(fn(NewsItemDto $item): array => [
            'title' => $item->title,
            'link' => $item->link,
            'description' => $item->description,
            'pubDate' => $item->pubDate,
            'source' => $item->source,
            'categories' => $item->categories,
            'tags' => $item->tags,
        ], $news);

        $json = json_encode([
            'total' => count($news),
            'items' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $output->writeln($json !== false ? $json : '[]');

        return Command::SUCCESS;
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }
}
