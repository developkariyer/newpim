<?php

namespace App\Command;

use App\Connector\Marketplace\TrendyolConnector;
use App\Service\DatabaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Pimcore\Db;


#[AsCommand(
    name: 'app:marketplace',
    description: 'Marketplaces Sync Command',
)]
class MarketplaceCommand extends Command
{
    private TrendyolConnector $trendyolConnector;

    public function __construct(DatabaseService $databaseService)
    {
        parent::__construct();
        $this->trendyolConnector = new TrendyolConnector($databaseService, 'TrendyolIwa');
    }

    // protected function configure(): void
    // {
    //     $this
    //         ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Yapılacak işlem (download, orders, returns, inventory)', 'download')
    //         ->setHelp('Bu komut Trendyol API\'sinden verileri çeker ve veritabanına kaydeder.');
    // }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sku = "IAS0210V0BMM";
        $db = Db::get();
        $sql = "SELECT 
                    marketplace_key, 
                    marketplace_sku, 
                    marketplace_price, 
                    marketplace_currency, 
                    marketplace_stock, 
                    status, 
                    marketplace_product_url,
                    last_updated
                FROM iwa_marketplaces_catalog 
                WHERE marketplace_sku = ? 
                ORDER BY marketplace_key ASC";
        $listings = $db->fetchAllAssociative($sql, [$sku]);
        print_r($listings);

        ///$this->trendyolConnector->download();
        return Command::SUCCESS;
    }

}