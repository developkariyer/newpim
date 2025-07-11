<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;

class SearchService
{
    public function __construct(
        private LoggerInterface $logger,
        private DataProcessingService $dataProcessor
    ) {}

    public function buildSearchCondition(string $query, bool $includePublishedCheck = true): string
    {
        if (empty($query)) {
            return $includePublishedCheck ? "published = 1" : "";
        }    
        $escapedQuery = addslashes($query);
        $condition = "LOWER(`key`) LIKE LOWER('%{$escapedQuery}%')";
        if ($includePublishedCheck) {
            $condition = "published = 1 AND $condition";
        }
        return $condition;
    }

    public function findProductByQuery(string $query, int $limit = 1): ?Product
    {
        if (empty($query)) {
            return null;
        }
        $listing = new ProductListing();
        $listing->setCondition('productIdentifier LIKE ? OR name LIKE ?', ["%$query%", "%$query%"]);
        $listing->setLimit($limit);
        $products = $listing->load();
        $result = $products[0] ?? null;
        return $result;
    }

    public function getGenericListing(string $listingClass, string $condition = "published = 1", ?callable $nameGetter = null): array
    {
        $listing = new $listingClass();
        $listing->setCondition($condition);
        $listing->load();
        $results = [];
        foreach ($listing as $item) {
            $results[] = [
                'id' => $item->getId(),
                'name' => $nameGetter ? $nameGetter($item) : $item->getKey(),
            ];
        }
        return $results;
    }

    public function getObjectById(string $className, int $id): ?object
    {
        if (!class_exists($className)) {
            return null;
        }
        try {
            $object = $className::getById($id);
            return $object;
        } catch (\Exception $e) {
            return null;
        }
    }

}