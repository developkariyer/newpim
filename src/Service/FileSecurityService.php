<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;

class FileSecurityService
{
    private const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const IMAGE_MAGIC_NUMBERS = [
        'jpg' => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'gif' => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"],
        'webp' => ["\x52\x49\x46\x46"]
    ];
    private const MAX_FILENAME_LENGTH = 255;
    private const SUSPICIOUS_PATTERNS = [
        '/<\?php/i',
        '/<script/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload=/i',
        '/onerror=/i'
    ];
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function validateImageFile(?UploadedFile $imageFile): bool
    {
        if (!$imageFile || !$imageFile->isValid()) {
            $this->logger->warning('Security: Invalid image file object');
            return false;
        }
        if ($imageFile->getSize() > self::MAX_IMAGE_SIZE) {
            $this->logger->warning('Security: Image file too large', [
                'size' => $imageFile->getSize(),
                'max_allowed' => self::MAX_IMAGE_SIZE
            ]);
            return false;
        }
        if ($imageFile->getSize() < 100) {
            $this->logger->warning('Security: Image file too small, possibly empty', [
                'size' => $imageFile->getSize()
            ]);
            return false;
        }
        if (!in_array($imageFile->getMimeType(), self::ALLOWED_IMAGE_TYPES)) {
            $this->logger->warning('Security: Invalid MIME type', [
                'mime_type' => $imageFile->getMimeType(),
                'allowed_types' => self::ALLOWED_IMAGE_TYPES
            ]);
            return false;
        }
        if (!$this->validateImageExtension($imageFile)) {
            return false;
        }
        if (!$this->validateImageMagicNumbers($imageFile)) {
            return false;
        }
        if (!$this->validateImageFilename($imageFile)) {
            return false;
        }
        if (!$this->scanImageContent($imageFile)) {
            return false;
        }
        $this->logger->info('Security: Image file passed all validation checks', [
            'filename' => $imageFile->getClientOriginalName(),
            'mime_type' => $imageFile->getMimeType()
        ]);
        return true;
    }

    private function validateImageExtension(UploadedFile $imageFile): bool
    {
        $originalName = $imageFile->getClientOriginalName();
        if (empty($originalName)) {
            $this->logger->warning('Security: Missing original filename');
            return false;
        }
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (empty($extension)) {
            $this->logger->warning('Security: No file extension found');
            return false;
        }
        if (!in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS)) {
            $this->logger->warning('Security: Extension not in whitelist', [
                'extension' => $extension,
                'allowed' => self::ALLOWED_IMAGE_EXTENSIONS
            ]);
            return false;
        }
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $suspiciousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'pht', 'phar', 'jsp', 'asp', 'aspx', 'js', 'html', 'htm'];
        foreach ($suspiciousExtensions as $suspExt) {
            if (stripos($filename, '.' . $suspExt) !== false) {
                $this->logger->warning('Security: Suspicious double extension detected', [
                    'filename' => $originalName
                ]);
                return false;
            }
        }
        return true;
    }

    private function validateImageMagicNumbers(UploadedFile $imageFile): bool
    {
        $filePath = $imageFile->getPathname();
        
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->logger->warning('Security: Cannot read file for magic number check', [
                'path' => $filePath
            ]);
            return false;
        }
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            $this->logger->warning('Security: Cannot open file for reading', [
                'path' => $filePath
            ]);
            return false;
        }
        $header = fread($handle, 20);
        fclose($handle);
        if ($header === false || strlen($header) < 4) {
            $this->logger->warning('Security: Cannot read file header or file too small');
            return false;
        }
        $extension = strtolower(pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION));
        if (!isset(self::IMAGE_MAGIC_NUMBERS[$extension])) {
            $this->logger->warning('Security: No magic numbers defined for extension', [
                'extension' => $extension
            ]);
            return false;
        }
        $validMagicNumbers = self::IMAGE_MAGIC_NUMBERS[$extension];
        $isValid = false;
        foreach ($validMagicNumbers as $magicNumber) {
            if (substr($header, 0, strlen($magicNumber)) === $magicNumber) {
                $isValid = true;
                break;
            }
        }
        if (!$isValid) {
            $this->logger->warning('Security: Magic number mismatch for file', [
                'extension' => $extension,
                'header_hex' => bin2hex(substr($header, 0, 8))
            ]);
            return false;
        }
        if ($extension === 'webp') {
            if (substr($header, 8, 4) !== 'WEBP') {
                $this->logger->warning('Security: Invalid WebP format signature');
                return false;
            }
        }
        return true;
    }

    private function validateImageFilename(UploadedFile $imageFile): bool
    {
        $originalName = $imageFile->getClientOriginalName();
        if (strlen($originalName) > self::MAX_FILENAME_LENGTH) {
            $this->logger->warning('Security: Filename too long', [
                'length' => strlen($originalName),
                'max_allowed' => self::MAX_FILENAME_LENGTH
            ]);
            return false;
        }
        $dangerousChars = ['..', '/', '\\', '<', '>', ':', '"', '|', '?', '*', "\0"];
        foreach ($dangerousChars as $char) {
            if (strpos($originalName, $char) !== false) {
                $this->logger->warning('Security: Dangerous character in filename', [
                    'character' => $char,
                    'filename' => $originalName
                ]);
                return false;
            }
        }
        if (strpos($originalName, "\0") !== false) {
            $this->logger->warning('Security: Null byte in filename detected');
            return false;
        }
        if (preg_match('/[\x00-\x1F\x7F-\x9F]/', $originalName)) {
            $this->logger->warning('Security: Control characters in filename', [
                'filename' => $originalName
            ]);
            return false;
        }
        return true;
    }

    private function scanImageContent(UploadedFile $imageFile): bool
    {
        $filePath = $imageFile->getPathname();
        $content = file_get_contents($filePath, false, null, 0, 8192);
        if ($content === false) {
            $this->logger->warning('Security: Cannot read file content for scanning');
            return false;
        }
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logger->warning('Security: Suspicious content pattern detected', [
                    'pattern' => $pattern
                ]);
                return false;
            }
        }
        if (stripos($content, '<?') !== false || stripos($content, '<%') !== false) {
            $this->logger->warning('Security: Embedded script tags detected');
            return false;
        }
        return true;
    }
    
    public function generateSafeFilename(string $input): string
    {
        $filename = mb_strtolower($input);
        $filename = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $filename);
        $filename = preg_replace('/[^a-z0-9]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        return $filename ?: 'file';
    }

    public function generateImageFilename(UploadedFile $imageFile, string $baseName): string
    {
        $extension = strtolower(pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION)) ?: 'jpg';
        $safeFilename = $this->generateSafeFilename($baseName);
        return $safeFilename . '_' . time() . '_' . uniqid() . '.' . $extension;
    }
    
    public function readImageFileContent(UploadedFile $imageFile): ?string
    {
        $tempPath = $imageFile->getPathname();
        if (!file_exists($tempPath) || !is_readable($tempPath)) {
            $this->logger->warning('File not readable', [
                'path' => $tempPath
            ]);
            return null;
        }
        $content = file_get_contents($tempPath);
        if ($content === false || strlen($content) === 0) {
            $this->logger->warning('Cannot read file content or empty file');
            return null;
        }
        return $content;
    }

    public function getMaxImageSize(): int
    {
        return self::MAX_IMAGE_SIZE;
    }

    public function getAllowedImageTypes(): array
    {
        return self::ALLOWED_IMAGE_TYPES;
    }
}