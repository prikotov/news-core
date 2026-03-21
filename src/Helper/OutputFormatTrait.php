<?php

declare(strict_types=1);

namespace News\Core\Helper;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait OutputFormatTrait
{
    private function addFormatOption(): void
    {
        $this->addOption(
            'format',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Output format: text, json, csv, md',
            'md'
        );
    }

    private function getFormat(InputInterface $input): string
    {
        $format = $input->getOption('format');
        return in_array($format, ['text', 'json', 'csv', 'md'], true) ? $format : 'md';
    }

    /**
     * @param array<string> $headers
     * @param array<array<int, string|null>> $rows
     */
    private function outputFormat(
        OutputInterface $output,
        string $format,
        array $headers,
        array $rows,
        ?string $title = null
    ): int {
        return match ($format) {
            'json' => $this->outputJsonFormat($output, $headers, $rows),
            'csv' => $this->outputCsvFormat($output, $headers, $rows),
            'md' => $this->outputMdFormat($output, $headers, $rows, $title),
            default => $this->outputTextFormat($output, $headers, $rows, $title),
        };
    }

    /**
     * @param array<string> $headers
     * @param array<array<int, string|null>> $rows
     */
    private function outputTextFormat(OutputInterface $output, array $headers, array $rows, ?string $title): int
    {
        if ($title !== null) {
            $output->writeln(sprintf('<info>%s</info>', $title));
            $output->writeln('');
        }

        $table = new Table($output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->render();

        return 0;
    }

    /**
     * @param array<string> $headers
     * @param array<array<int, string|null>> $rows
     */
    private function outputJsonFormat(OutputInterface $output, array $headers, array $rows): int
    {
        $data = array_map(function (array $row) use ($headers): array {
            $item = [];
            foreach ($headers as $i => $header) {
                $item[$header] = $row[$i] ?? null;
            }
            return $item;
        }, $rows);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $output->writeln($json ?: '[]');

        return 0;
    }

    /**
     * @param array<string> $headers
     * @param array<array<int, string|null>> $rows
     */
    private function outputCsvFormat(OutputInterface $output, array $headers, array $rows): int
    {
        $output->writeln($this->csvRow($headers));

        foreach ($rows as $row) {
            $output->writeln($this->csvRow($row));
        }

        return 0;
    }

    /**
     * @param array<string|null> $row
     */
    private function csvRow(array $row): string
    {
        return implode(',', array_map(function ($cell): string {
            if ($cell === null) {
                return '';
            }
            $cell = (string)$cell;
            if (str_contains($cell, ',') || str_contains($cell, '"') || str_contains($cell, "\n")) {
                return '"' . str_replace('"', '""', $cell) . '"';
            }
            return $cell;
        }, $row));
    }

    /**
     * @param array<string> $headers
     * @param array<array<int, string|null>> $rows
     */
    private function outputMdFormat(OutputInterface $output, array $headers, array $rows, ?string $title): int
    {
        if ($title !== null) {
            $output->writeln(sprintf('## %s', $title));
            $output->writeln('');
        }

        $output->writeln('| ' . implode(' | ', $headers) . ' |');
        $output->writeln('| ' . implode(' | ', array_fill(0, count($headers), '---')) . ' |');

        foreach ($rows as $row) {
            $cells = array_map(function ($v): string {
                return $v === null ? '' : (string)$v;
            }, $row);
            $output->writeln('| ' . implode(' | ', $cells) . ' |');
        }

        return 0;
    }
}
