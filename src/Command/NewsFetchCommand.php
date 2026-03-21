<?php

declare(strict_types=1);

namespace News\Core\Command;

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
                'ticker',
                't',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Stock tickers to search (e.g., --ticker SBER --ticker GAZP)',
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
                'Output format: table, json, simple',
                'table',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $sources */
        $sources = $input->getOption('source');
        /** @var list<string> $searchTerms */
        $searchTerms = $input->getOption('search');
        /** @var list<string> $tickers */
        $tickers = $input->getOption('ticker');
        /** @var list<string> $categories */
        $categories = $input->getOption('category');
        /** @var mixed $limitValue */
        $limitValue = $input->getOption('limit');
        $limit = is_numeric($limitValue) ? (int)$limitValue : 50;
        /** @var mixed $formatValue */
        $formatValue = $input->getOption('format');
        $format = is_string($formatValue) ? $formatValue : 'table';

        $hasFilters = !empty($searchTerms) || !empty($tickers) || !empty($categories);

        $news = $this->newsService->fetchNews($sources);

        if ($news === []) {
            $this->logger->warning('No news fetched from sources', ['sources' => $sources ?: 'all']);
            if ($format === 'json') {
                $output->writeln(json_encode([
                    'items' => [],
                    'total' => 0,
                    'reason' => 'no_data_from_sources',
                    'message' => 'Failed to fetch news from RSS sources',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return Command::SUCCESS;
            }
            $output->writeln('<error>No news fetched from RSS sources. Check network connection or source availability.</error>');
            return Command::SUCCESS;
        }

        $allNewsCount = count($news);
        $allSearchTerms = array_merge($searchTerms, $this->expandTickers($tickers));

        if (!empty($allSearchTerms)) {
            $news = $this->newsService->filterNews($news, $allSearchTerms);
            $output->writeln(sprintf('<comment>Filtering by: %s</comment>', implode(', ', $allSearchTerms)));
            $output->writeln('');
        }

        if (!empty($categories)) {
            $news = $this->newsService->filterByCategories($news, $categories);
            $output->writeln(sprintf('<comment>Filtering by categories: %s</comment>', implode(', ', $categories)));
            $output->writeln('');
        }

        if ($news === [] && $hasFilters) {
            $this->logger->info('No news matched filters', [
                'searchTerms' => $allSearchTerms,
                'categories' => $categories,
                'totalFetched' => $allNewsCount,
            ]);

            if ($format === 'json') {
                $output->writeln(json_encode([
                    'items' => [],
                    'total' => 0,
                    'reason' => 'no_matches',
                    'message' => 'No news matched the specified filters',
                    'filters' => [
                        'searchTerms' => $allSearchTerms,
                        'categories' => $categories,
                    ],
                    'totalFetched' => $allNewsCount,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

        return match ($format) {
            'json' => $this->outputJson($output, $news),
            'simple' => $this->outputSimple($output, $news),
            default => $this->outputTable($output, $news),
        };
    }

    /**
     * @param list<string> $tickers
     * @return list<string>
     */
    private function expandTickers(array $tickers): array
    {
        $tickerNames = [
            'SBER' => ['Сбербанк', 'Sber', 'SBER'],
            'GAZP' => ['Газпром', 'Gazprom', 'GAZP'],
            'LKOH' => ['Лукойл', 'Lukoil', 'LKOH'],
            'NVTK' => ['Новатэк', 'Novatek', 'NVTK'],
            'ROSN' => ['Роснефть', 'Rosneft', 'ROSN'],
            'GMKN' => ['Норникель', 'Nornickel', 'GMKN', 'Норильский никель'],
            'MGNT' => ['Магнит', 'Magnit', 'MGNT'],
            'YNDX' => ['Яндекс', 'Yandex', 'YNDX'],
            'AFKS' => ['АФК Система', 'Sistema', 'AFKS'],
            'AFLT' => ['Аэрофлот', 'Aeroflot', 'AFLT'],
            'ALRS' => ['Алроса', 'Alrosa', 'ALRS'],
            'CHMF' => ['Северсталь', 'Severstal', 'CHMF'],
            'FEES' => ['ФСК ЕЭС', 'Federal Grid', 'FEES'],
            'MOEX' => ['Мосбиржа', 'Moscow Exchange', 'MOEX'],
            'MTSS' => ['МТС', 'MTS', 'MTSS'],
            'PLZL' => ['Полюс', 'Polyus', 'PLZL'],
            'TATN' => ['Татнефть', 'Tatneft', 'TATN'],
            'VTBR' => ['ВТБ', 'VTB', 'VTBR'],
            'POLY' => ['Polymetal', 'Polymetal', 'POLY'],
            'NLMK' => ['НЛМК', 'NLMK', 'Новолипецкий металлургический'],
            'TCSG' => ['Т-Банк', 'Тинькофф', 'Tinkoff', 'TCSG'],
            'PHOR' => ['ФосАгро', 'PhosAgro', 'PHOR'],
            'MAGN' => ['ММК', 'Magnitogorsk', 'MAGN'],
            'PIKK' => ['ПИК', 'PIK', 'PIKK'],
            'RTKM' => ['Ростелеком', 'Rostelecom', 'RTKM'],
        ];

        $expanded = [];
        foreach ($tickers as $ticker) {
            $upperTicker = strtoupper($ticker);
            if (isset($tickerNames[$upperTicker])) {
                $expanded = array_merge($expanded, $tickerNames[$upperTicker]);
            } else {
                $expanded[] = $ticker;
            }
        }

        return array_values(array_unique($expanded));
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

    /**
     * @param list<NewsItemDto> $news
     */
    private function outputSimple(OutputInterface $output, array $news): int
    {
        foreach ($news as $item) {
            $output->writeln(sprintf(
                '<info>[%s]</info> <comment>%s</comment> %s',
                $item->pubDate,
                $item->source,
                $item->title,
            ));
            $output->writeln(sprintf('  %s', $item->link));
            $output->writeln('');
        }

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
