<?php

namespace App\Service;

use Doctrine\DBAL\Exception;
use PDOException;
use Pimcore\Db;
use App\Logger\LoggerFactory;


class DatabaseService
{
    private $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::create('Service', 'DatabaseService');
    }

    public function executeSql(string $sql, array $params = [])
    {
        try {
            $db = Db::get();
            $stmt = $db->prepare($sql);
            $stmt->executeStatement($params);
        } catch (\Exception $e) {
            $this->logger->error("[" . __METHOD__ . "] ❌ SQL Execution Error: {$e->getMessage()}", [
                'sql' => $sql,
                'params' => $params
            ]);
            throw new PDOException("Database error occurred: " . $e->getMessage(), (int)$e->getCode());
        }
        $this->logger->info("[" . __METHOD__ . "] ✅ SQL Executed Successfully", [
            'sql' => $sql,
            'params' => $params
        ]);
    }

    public function fetchAllSql(string $sql, array $params = [])
    {
        try {
            $db = Db::get();
            return $db->fetchAllAssociative($sql, $params);
        } catch (\Exception $e) {
            echo "Fetch From SQL Error: " . $sql . "\n" . $e->getMessage() . "\n";
        }
    }

    
}