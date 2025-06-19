<?php

namespace App\Logger;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    public static function create(string $folder, string $channel): LoggerInterface
    {
        $logger = new Logger($channel);
        $logDir = dirname(__DIR__, 2) . '/var/log/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $filePath = "{$logDir}/{$folder}/{$channel}_" . date('Y-m-d_H-i-s') . ".log";
        $logger->pushHandler(new StreamHandler($filePath, Logger::DEBUG));
        return $logger;
    }

    public static function createWithCustomPath(string $filePath): LoggerInterface
    {
        $logger = new Logger(basename($filePath, '.log'));
        $logDir = dirname($filePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logger->pushHandler(new StreamHandler($filePath, Logger::DEBUG));
        return $logger;
    }
}