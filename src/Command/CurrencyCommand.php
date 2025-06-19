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

    private const URLS = [
        'https://www.tcmb.gov.tr/kurlar/today.xml',
        'https://www.tcmb.gov.tr/bilgiamackur/today.xml',
    ];
    
    protected function configure()
    {
        $this
            ->setDescription('Retrieve Currency from TCMB')
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        // foreach (self::URLS as $url) {
        //     $array = $this->loadXmlAsArray($url);
        //     if ($array === null) {
        //         $output->writeln("❌ Failed to load XML from: $url");
        //         return Command::FAILURE;
        //     }
        //     $output->writeln("✅ Successfully loaded XML from: $url");
        //     $date = Carbon::createFromFormat('d/m/Y', $array['@attributes']['Date']);
        //     foreach ($array['Currency'] as $currency) {
        //         $currencyName = $currency['CurrencyName'] ?? '';
        //         $currencyCode = $currency['@attributes']['CurrencyCode'] ?? '';
        //     }


        // }


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
        foreach ($array['Currency'] as $currency) {
            $rate = $currency['ForexBuying'] ?? $currency['ExchangeRate'] ?? 0;
            $rate = $rate/$currency['Unit'];
            $currencyName = $currency['CurrencyName']
            $tcmbDate = $array['@attributes']['Date']."\n";
        
        }
        
        print_r($arrayExtra);



        return Command::SUCCESS;
    }

    function loadXmlAsArray(string $url): ?array
    {
        $xml = @simplexml_load_file($url);
        if (!$xml) {
            error_log("❌ XML Loaded Error Url: $url");
            return null;
        }
        $json = json_encode($xml);
        return json_decode($json, true);
    }

}
