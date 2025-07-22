<?php

namespace App\Command;

use Carbon\Carbon;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'app:import',
    description: 'Import Products!'
)]
class ImportCommand extends AbstractCommand
{
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = PIMCORE_PROJECT_ROOT . '/tmp/exportProduct.json';

        if (!file_exists($filePath)) {
            $output->writeln('<error>File not found: ' . $filePath . '</error>');
            return Command::FAILURE;
        }

        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('<error>JSON decode error: ' . json_last_error_msg() . '</error>');
            return Command::FAILURE;
        }

        $prettyJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $output->writeln('<info>Imported Data (JSON):</info>');
        $output->writeln($prettyJson);

        return Command::SUCCESS;
    }


}
