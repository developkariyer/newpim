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
        
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        foreach (self::URLS as $url) {
            $array = $this->loadXmlAsArray($url);
            if ($array === null) {
                $output->writeln("❌ Failed to load XML from: $url");
                return Command::FAILURE;
            }
            $output->writeln("✅ Successfully loaded XML from: $url");
            $date = Carbon::createFromFormat('m/d/Y', $array['@attributes']['Date']);
            foreach ($array['Currency'] as $currency) {
                $currencyName = $currency['CurrencyName'] ?? '';
                $currencyCode = $currency['@attributes']['CurrencyCode'] ?? '';
                if (empty($currencyName) || empty($currencyCode)) {
                    $output->writeln("❌ Missing currency name or code for date: {$date->format('Y-m-d')}");
                    continue;
                }
                $output->writeln("Processing currency: $currencyName ($currencyCode) for date: {$date->format('Y-m-d')}");
                $rate = $currency['ForexBuying'] ?? $currency['ExchangeRate'] ?? null;
                if ($rate === null) {
                    $output->writeln("❌ Missing rate for currency: $currencyName ($currencyCode)");
                    continue;
                }
                $currenyUnit = $currency['Unit'] ?? 0;
                if ($currenyUnit <= 0) {
                    $output->writeln("❌ Invalid currency unit for: $currencyName ($currencyCode)");
                    continue;
                } 
                $rate = $rate / $currenyUnit;
                $output->writeln("✅ Rate for $currencyName ($currencyCode): $rate on {$date}");
            }
        }


        // $urlExtra = "https://www.tcmb.gov.tr/bilgiamackur/today.xml";
        // $xmlExtra = simplexml_load_file($urlExtra);
        // $jsonExtra = json_encode($xmlExtra  );
        // $arrayExtra = json_decode($jsonExtra, TRUE);
       
        
        // print_r($arrayExtra);



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
