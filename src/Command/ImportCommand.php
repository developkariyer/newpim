<?php

namespace App\Command;

use Carbon\Carbon;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Pimcore\Model\DataObject\Currency;
use Symfony\Component\Console\Input\InputOption;
use Pimcore\Model\DataObject\Folder;
use App\Logger\LoggerFactory;
use App\Service\DatabaseService;

#[AsCommand(
    name: 'app:import',
    description: 'Import Products!'
)]
class ImportCommand extends AbstractCommand
{
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $content = file_get_contents(PIMCORE_PROJECT_ROOT . '/tmp/exportProduct.json');
        $content = json_decode($content, true);
        print_r($content);

        return Command::SUCCESS;
    }


}
