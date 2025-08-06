<?php

namespace App\Connector\Marketplace;

use Pimcore\Model\DataObject\Marketplace;

interface MarketplaceConnectorInterface
{
    /**
     * MarketplaceConnectorInterface constructor.
     * @param Marketplace $marketplace
     */
    public function __construct(Marketplace $marketplace);

    /**
     * Download the marketplace data from API
     * @param bool $forceDownload
     * @return void
     */
    public function download(bool $forceDownload = false): void;

    /**
     * Download the marketplace orders from API
     * @return void
     */
    public function downloadOrders(): void;

    /**
     * Download the marketplace inventory from API
     * @return void
     */
    public function downloadInventory(): void;

    /**
     * Download the marketplace returns from API
     * @return void
     */
    public function downloadReturns(): void;


    /**
     * Set the inventory of a listing through the marketplace API
     *
     * @param int $targetValue
     * @param $sku
     * @param $country
     * @return void
     */
    public function setInventory(int $targetValue, $sku = null, $country = null): void;

    /**
     * Set the price of a listing through the marketplace API
     * @param string $targetPrice
     * @param $targetCurrency
     * @param $sku
     * @param $country
     * @return void
     */
    public function setPrice(string $targetPrice, $targetCurrency = null, $sku = null, $country = null): void;

}
