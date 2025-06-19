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
    name: 'app:currency',
    description: 'Retrieve Currency!'
)]
class CurrencyCommand extends AbstractCommand
{
    private $logger;

    private const URLS = [
        'https://www.tcmb.gov.tr/kurlar/today.xml',
        'https://www.tcmb.gov.tr/bilgiamackur/today.xml',
    ];

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct();
        $this->setDescription('Retrieve and update currency rates from TCMB XML feeds.');
        $this->logger = LoggerFactory::create('Command', 'CurrencyCommand');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (self::URLS as $url) {
            $array = $this->loadXmlAsArray($url);
            if ($array === null) {
                $this->logger->error("[" . __METHOD__ . "] ❌ Failed to load XML from: $url");
                return Command::FAILURE;
            }
            $this->logger->info("[" . __METHOD__ . "] ✅ Successfully loaded XML from: $url");
            $date = Carbon::createFromFormat('m/d/Y', $array['@attributes']['Date']);
            foreach ($array['Currency'] as $currency) {
                $currencyName = $currency['CurrencyName'] ?? '';
                $currencyCode = $currency['@attributes']['CurrencyCode'] ?? '';
                if (empty($currencyName) || empty($currencyCode)) {
                    $this->logger->error("[" . __METHOD__ . "] ❌ Missing currency name or code for date: {$date->format('Y-m-d')}");
                    continue;
                }
                $rate = $currency['ForexBuying'] ?? $currency['ExchangeRate'] ?? null;
                if ($rate === null) {
                    $this->logger->error("[" . __METHOD__ . "] ❌ Missing rate for currency: $currencyName ($currencyCode) on {$date->format('Y-m-d')}");
                    continue;
                }
                $currenyUnit = $currency['Unit'] ?? 0;
                if ($currenyUnit <= 0) {
                    $this->logger->error("[" . __METHOD__ . "] ❌ Invalid currency unit for: $currencyName ($currencyCode) on {$date->format('Y-m-d')}");
                    continue;
                } 
                try {
                    $rate = $rate / $currenyUnit;
                    $this->logger->info("[" . __METHOD__ . "] ✅ Rate calculated for: $currencyName ($currencyCode) - Rate: $rate on {$date->format('Y-m-d')}");
                } catch (\Throwable $e) {
                    $this->logger->error("[" . __METHOD__ . "] ❌ Error calculating rate for: $currencyName ($currencyCode) - {$e->getMessage()}");
                    continue;
                }
                $currencyObject = Currency::getByCurrencyCode($currencyCode, ['limit' => 1,'unpublished' => true]);
                if (!$currencyObject) {
                    $this->logger->info("[" . __METHOD__ . "] ❌ No existing currency object found for: $currencyName ($currencyCode), creating new one.");
                    $currencyObject = new Currency();
                    $currencyObject->setParent(Folder::getByPath('/Ayarlar/Sabitler/Döviz-Kurları'));
                    $currencyObject->setKey(trim($currencyName));
                    $currencyObject->setCurrencyCode(strtoupper($currencyCode));
                    $currencyObject->setCurrencyName($currencyName);
                } 
                $currencyObject->setRate($rate);
                $currencyObject->setDate($date);
                $currencyObject->save();
                $this->logger->info("[" . __METHOD__ . "] ✅ Currency object saved for: $currencyName ($currencyCode) with rate: $rate on {$date->format('Y-m-d')}");
            }
        }
        return Command::SUCCESS;
    }

    private function loadXmlAsArray(string $url): ?array
    {
        $xml = @simplexml_load_file($url);
        if (!$xml) {
            $this->logger->error("[" . __METHOD__ . "] ❌ XML Loaded Error Url: $url");
            return null;
        }
        $json = json_encode($xml);
        return json_decode($json, true);
    }

}
