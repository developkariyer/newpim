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
use Pimcore\Model\DataObject\Marketplace;
use Pimcore\Db;


#[AsCommand(
    name: 'app:marketplace',
    description: 'Marketplaces Sync Command',
)]
class MarketplaceCommand extends Command
{
    private TrendyolConnector $trendyolConnector;
    private ShopifyConnector $shopifyConnector;

    public function __construct()
    {
        parent::__construct();
        $marketplace = Marketplace::getById(1495);
        //$this->trendyolConnector = new TrendyolConnector($marketplace);
        $this->shopifyConnector = new ShopifyConnector($marketplace);
    }

    // protected function configure(): void
    // {
    //     $this
    //         ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Yapılacak işlem (download, orders, returns, inventory)', 'download')
    //         ->setHelp('Bu komut Trendyol API\'sinden verileri çeker ve veritabanına kaydeder.');
    // }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        

        $this->trendyolConnector->download();
        return Command::SUCCESS;
    }

}