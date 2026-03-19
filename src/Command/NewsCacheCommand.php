<?php

declare(strict_types=1);

namespace News\Core\Command;

use News\Core\Service\Cache\CacheServiceInterface;
use News\Core\Service\News\NewsServiceInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'news:cache',
    description: 'Fetch news and store in local cache',
)]
final class NewsCacheCommand extends Command
{
    public function __construct(
        private readonly NewsServiceInterface $newsService,
        private readonly CacheServiceInterface $cacheService,
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
                'Specific sources to fetch',
            )
            ->addOption(
                'clear',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Clear cache older than N days (default: 30)',
                false,
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var mixed $clearValue */
        $clearValue = $input->getOption('clear');

        if ($clearValue !== false) {
            $daysToKeep = is_numeric($clearValue) ? (int)$clearValue : 30;
            $deleted = $this->cacheService->clear($daysToKeep);
            $output->writeln(sprintf('<info>Cleared %d cached items older than %d days</info>', $deleted, $daysToKeep));
        }

        /** @var list<string> $sources */
        $sources = $input->getOption('source');

        $output->writeln('<comment>Fetching news from RSS sources...</comment>');

        $news = $this->newsService->fetchNews($sources);
        $output->writeln(sprintf('Fetched %d news items', count($news)));

        $stored = $this->cacheService->store($news);

        $output->writeln(sprintf('<info>Stored %d new items in cache</info>', $stored));

        return Command::SUCCESS;
    }
}
