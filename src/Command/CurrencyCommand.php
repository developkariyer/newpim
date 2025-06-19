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

#[AsCommand(
    name: 'app:currency',
    description: 'Retrieve Currency!'
)]
class CurrencyCommand extends AbstractCommand
{
    
    protected function configure()
    {
        $this
            ->addArgument('marketplace', InputOption::VALUE_OPTIONAL, 'The marketplace to import from.')
            ->addOption('download', null, InputOption::VALUE_NONE, 'If set, Shopify listing data will always be downloaded.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $urlExtra = "https://www.tcmb.gov.tr/bilgiamackur/today.xml";
        $xmlExtra = simplexml_load_file($urlExtra);
        $jsonExtra = json_encode($xmlExtra  );
        $arrayExtra = json_decode($jsonExtra, TRUE);
        $url = "https://www.tcmb.gov.tr/kurlar/today.xml";
        $xml = simplexml_load_file($url);
        $json = json_encode($xml);
        $array = json_decode($json, TRUE);
        echo "Current Date: ".date('m/d/Y')."\n";
        echo "TCMP Date: ".$array['@attributes']['Date']."\n";
        list($month, $day, $year) = explode('/', $array['@attributes']['Date']);
        $date = sprintf('%4d-%02d-%02d', $year, $month, $day);
        print($array);



        return Command::SUCCESS;
    }

}
