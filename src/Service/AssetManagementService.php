<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Asset\Folder;
use Psr\Log\LoggerInterface;
use App\Service\FileSecurityService;

class AssetManagementService
{
    private const PRODUCT_IMAGES_PATH = '/products';
    
    public function __construct(
        private FileSecurityService $fileSecurityService,
        private LoggerInterface $logger
    ) {}

    public function uploadProductImage(UploadedFile $imageFile, string $productKey): ?Image
    {
        try {
            // if (!$this->fileSecurityService->validateImageFile($imageFile)) {
            //     $this->logger->warning('Asset upload failed: Image file validation failed', [
            //         'product_key' => $productKey
            //     ]);
            //     return null;
            // }
            $assetFolder = $this->getOrCreateProductsFolder();
            $filename = $this->fileSecurityService->generateImageFilename($imageFile, $productKey);
            $fileContent = $this->fileSecurityService->readImageFileContent($imageFile);
            if (!$fileContent) {
                $this->logger->error('Asset upload failed: Cannot read file content', [
                    'product_key' => $productKey
                ]);
                return null;
            }
            $imageAsset = new Image();
            $imageAsset->setFilename($filename);
            $imageAsset->setParent($assetFolder);
            $imageAsset->setMimeType($imageFile->getMimeType());
            $imageAsset->setData($fileContent);
            $imageAsset->save();
            $this->logger->info('Product image uploaded successfully', [
                'asset_id' => $imageAsset->getId(),
                'product_key' => $productKey,
                'filename' => $filename,
                'mime_type' => $imageFile->getMimeType()
            ]);
            return $imageAsset;
        } catch (\Exception $e) {
            $this->logger->error('Asset upload error', [
                'message' => $e->getMessage(),
                'product_key' => $productKey,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function getOrCreateProductsFolder(): Folder
    {
        $assetFolder = Asset::getByPath(self::PRODUCT_IMAGES_PATH);
        if (!$assetFolder) {
            $assetFolder = new Folder();
            $assetFolder->setFilename(basename(self::PRODUCT_IMAGES_PATH));
            $assetFolder->setParent(Asset::getByPath('/'));
            $assetFolder->save();
            $this->logger->info('Created new products asset folder', [
                'path' => self::PRODUCT_IMAGES_PATH
            ]);
        }
        return $assetFolder;
    }

    public function getOrCreateCategoryFolder(string $categoryName): Folder
    {
        $path = self::PRODUCT_IMAGES_PATH . '/' . $this->sanitizeFolderName($categoryName);
        $folder = Asset::getByPath($path);
        if (!$folder) {
            $parentFolder = $this->getOrCreateProductsFolder();
            $folder = new Folder();
            $folder->setFilename($this->sanitizeFolderName($categoryName));
            $folder->setParent($parentFolder);
            $folder->save();
            $this->logger->info('Created new category asset folder', [
                'category' => $categoryName,
                'path' => $path
            ]);
        }
        return $folder;
    }

    public function getOrCreateProductFolder(string $categoryName, string $productIdentifier): Folder
    {
        $categoryFolder = $this->getOrCreateCategoryFolder($categoryName);
        $productFolderName = $this->sanitizeFolderName($productIdentifier);
        $path = $categoryFolder->getFullPath() . '/' . $productFolderName;
        $folder = Asset::getByPath($path);
        if (!$folder) {
            $folder = new Folder();
            $folder->setFilename($productFolderName);
            $folder->setParent($categoryFolder);
            $folder->save();
            $this->logger->info('Created new product asset folder', [
                'category' => $categoryName,
                'product_identifier' => $productIdentifier,
                'path' => $path
            ]);
        }
        return $folder;
    }

    public function duplicateAssetImage(Image $sourceImage, string $newFilename = null): ?Image
    {
        try {
            if (!$sourceImage) {
                return null;
            }
            $parent = $sourceImage->getParent();
            if (!$parent) {
                $parent = Asset::getByPath(self::PRODUCT_IMAGES_PATH);
            }
            if (!$newFilename) {
                $newFilename = 'copy_' . time() . '_' . $sourceImage->getFilename();
            }
            $newImage = new Image();
            $newImage->setFilename($newFilename);
            $newImage->setParent($parent);
            $newImage->setData($sourceImage->getData());
            $newImage->setMimeType($sourceImage->getMimeType());
            $newImage->save();
            $this->logger->info('Asset image duplicated', [
                'source_id' => $sourceImage->getId(),
                'new_id' => $newImage->getId(),
                'filename' => $newFilename
            ]);
            return $newImage;
        } catch (\Exception $e) {
            $this->logger->error('Error duplicating asset image', [
                'message' => $e->getMessage(),
                'source_id' => $sourceImage->getId()
            ]);
            return null;
        }
    }

    public function getThumbnailUrl(Image $image, string $thumbnailName = 'product_list'): ?string
    {
        try {
            if (!$image) {
                return null;
            }
            $thumbnail = $image->getThumbnail($thumbnailName);
            return $thumbnail ? $thumbnail->getUrl() : null;
        } catch (\Exception $e) {
            $this->logger->error('Error generating thumbnail', [
                'message' => $e->getMessage(),
                'image_id' => $image->getId(),
                'thumbnail' => $thumbnailName
            ]);
            return null;
        }
    }

    private function sanitizeFolderName(string $name): string
    {
        $name = mb_strtolower($name);
        $name = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $name);
        $name = preg_replace('/[^a-z0-9]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return $name ?: 'folder';
    }
    
    public function assetExists(string $path): bool
    {
        return Asset::getByPath($path) !== null;
    }
    
    public function getImageById(int $assetId): ?Image
    {
        try {
            $asset = Asset::getById($assetId);
            return ($asset instanceof Image) ? $asset : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}