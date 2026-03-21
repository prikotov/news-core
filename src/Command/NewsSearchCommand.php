<?php

declare(strict_types=1);

namespace News\Core\Command;

use News\Core\Helper\OutputFormatTrait;
use News\Core\Service\Cache\CacheServiceInterface;
use News\Core\Service\News\Dto\NewsItemDto;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'news:search',
    description: 'Search news in local cache',
)]
final class NewsSearchCommand extends Command
{
    use OutputFormatTrait;

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'query',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Search terms',
            )
            ->addOption(
                'source',
                's',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Filter by sources',
            )
            ->addOption(
                'category',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Filter by categories',
            )
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Number of days to search back',
                7,
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Limit number of results',
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
        /** @var list<string> $queryTerms */
        $queryTerms = $input->getArgument('query');
        /** @var list<string> $sources */
        $sources = $input->getOption('source');
        /** @var list<string> $categories */
        $categories = $input->getOption('category');
        /** @var mixed $daysValue */
        $daysValue = $input->getOption('days');
        $daysBack = is_numeric($daysValue) ? (int)$daysValue : 7;
        /** @var mixed $limitValue */
        $limitValue = $input->getOption('limit');
        $limit = is_numeric($limitValue) ? (int)$limitValue : 50;
        $format = $this->getFormat($input);

        $results = $this->cacheService->search($queryTerms, $sources, $daysBack);

        if ($categories !== []) {
            $results = $this->filterByCategories($results, $categories);
        }

        if ($limit > 0) {
            $results = array_slice($results, 0, $limit);
        }

        if ($queryTerms !== []) {
            $output->writeln(sprintf('<comment>Searching for: %s</comment>', implode(', ', $queryTerms)));
            $output->writeln('');
        }

        if ($format === 'csv' || $format === 'md') {
            $rows = array_map(fn(NewsItemDto $item) => [
                $item->pubDate,
                $item->source,
                implode(', ', $item->categories) ?: '-',
                $this->truncate($item->title, 100),
                $item->link,
            ], $results);

            return $this->outputFormat(
                $output,
                $format,
                ['Date', 'Source', 'Category', 'Title', 'Link'],
                $rows,
                'Search Results'
            );
        }

        return match ($format) {
            'json' => $this->outputJson($output, $results),
            default => $this->outputTable($output, $results),
        };
    }

    /**
     * @param list<NewsItemDto> $news
     * @param list<string> $categories
     * @return list<NewsItemDto>
     */
    private function filterByCategories(array $news, array $categories): array
    {
        $categoriesLower = array_map('mb_strtolower', $categories);

        return array_values(array_filter($news, function (NewsItemDto $item) use ($categoriesLower): bool {
            $itemCategoriesLower = array_map('mb_strtolower', $item->categories);

            foreach ($categoriesLower as $category) {
                foreach ($itemCategoriesLower as $itemCategory) {
                    if (mb_strpos($itemCategory, $category) !== false) {
                        return true;
                    }
                }
            }

            return false;
        }));
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

        $output->writeln(sprintf('<info>Found: %d news items</info>', count($news)));

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

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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
