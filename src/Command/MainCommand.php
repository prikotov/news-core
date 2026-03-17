<?php

declare(strict_types=1);

namespace News\Skill\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'main',
    description: 'Main command - show available sources',
)]
final class MainCommand extends Command
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>News Skill - RSS News Aggregator</info>');
        $output->writeln('');
        $output->writeln('Available commands:');
        $output->writeln('  <comment>news:fetch</comment>  Fetch news from all sources');
        $output->writeln('  <comment>news:sources</comment> List available sources');
        $output->writeln('');
        $output->writeln('Run <comment>./bin/news list</comment> for all commands.');

        return Command::SUCCESS;
    }
}
