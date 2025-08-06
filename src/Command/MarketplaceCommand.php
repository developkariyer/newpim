<?php

namespace App\Command;

use App\Connector\Marketplace\TrendyolConnector;
use App\Connector\Marketplace\ShopifyConnector;
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
    private array $connectors = [];

    public function __construct()
    {
        parent::__construct();
        // $marketplaceListingObject = new Marketplace\Listing();
        // $marketplaces = $marketplaceListingObject->load();
        // if (empty($marketplaces)) {
        //     throw new \Exception('No marketplaces found. Please create a marketplace first.');
        // }
        // foreach ($marketplaces as $marketplace) {
        //     if ($marketplace->getMarketplaceType() === 'Trendyol') {
        //         $this->connectors[] = new TrendyolConnector($marketplace);
        //     }   
        //     // if ($marketplace->getMarketplaceType() === 'Shopify') {
        //     //     $this->connectors[] = new ShopifyConnector($marketplace);
        //     // }
        // }
        $marketplace = Marketplace::getById(30);
        if ($marketplace) {
            $this->connectors[] = new TrendyolConnector($marketplace);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->connectors as $connector) {
            echo "Starting sync for " . get_class($connector) . "\n";
            $connector->download();
            echo "Completed sync for " . get_class($connector) . "\n\n";
        }
        return Command::SUCCESS;
    }

}