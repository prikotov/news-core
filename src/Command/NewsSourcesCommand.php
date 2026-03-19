<?php

declare(strict_types=1);

namespace News\Core\Command;

use News\Core\Service\News\NewsServiceInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'news:sources',
    description: 'List available RSS sources',
)]
final class NewsSourcesCommand extends Command
{
    public function __construct(
        private readonly NewsServiceInterface $newsService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sources = $this->newsService->getAvailableSources();

        $table = new Table($output);
        $table->setHeaders(['Source ID', 'Name']);

        foreach ($sources as $id => $name) {
            $table->addRow([$id, $name]);
        }

        $table->render();

        $output->writeln('');
        $output->writeln('Usage: <comment>./bin/news news:fetch --source=interfax --source=tass</comment>');

        return Command::SUCCESS;
    }
}
