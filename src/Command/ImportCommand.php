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
        if (!is_array($data)) {
            $output->writeln('<error>Decoded JSON is not an array.</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>Products:</info>');
        foreach ($data as $index => $product) {
            $output->writeln("Product #$index");
            $output->writeln("ID: " . ($product['id'] ?? 'N/A'));
            $output->writeln("Name: " . ($product['name'] ?? 'N/A'));
            $output->writeln("Price: " . ($product['price'] ?? 'N/A'));
            $output->writeln('---------------------------');
        }
        return Command::SUCCESS;
    }


}
