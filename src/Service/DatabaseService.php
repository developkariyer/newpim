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
            $result =  $db->fetchAllAssociative($sql, $params);
            $this->logger->info("[" . __METHOD__ . "] ✅ SQL Query Executed Successfully", [
                'sql' => $sql,
                'params' => $params,
                'row_count' => count($result)
            ]);
            return $result;
        } catch (\Exception $e) {
            this->logger->error("[" . __METHOD__ . "] ❌ SQL Query Error: {$e->getMessage()}", [
                'sql' => $sql,
                'params' => $params,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new PDOException("Database query error occurred: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    
}