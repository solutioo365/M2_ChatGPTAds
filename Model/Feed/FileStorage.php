<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Solutioo\ChatGptProductSearch\Model\Config;
use Solutioo\ChatGptProductSearch\Model\Config\Source\FeedFormat;

class FileStorage
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly Config $config
    ) {
    }

    public function getDirectory(): WriteInterface
    {
        return $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    public function getRelativeDir(int $storeId): string
    {
        return 'chatgpt_feed/store_' . $storeId;
    }

    public function getProductsRelativePath(int $storeId, ?string $format = null): string
    {
        $format = $format ?: $this->config->getFeedFormat($storeId);
        $name = $this->productsBasename($format);
        if ($this->config->compressOutput($storeId) && !str_ends_with($name, '.gz')) {
            $name .= '.gz';
        }
        return $this->getRelativeDir($storeId) . '/' . $name;
    }

    public function getPromotionsRelativePath(int $storeId): string
    {
        $name = 'promotions.jsonl';
        if ($this->config->compressOutput($storeId)) {
            $name .= '.gz';
        }
        return $this->getRelativeDir($storeId) . '/' . $name;
    }

    public function getHeaderRelativePath(int $storeId): string
    {
        return $this->getRelativeDir($storeId) . '/header.json';
    }

    public function getAbsolutePath(string $relative): string
    {
        return $this->getDirectory()->getAbsolutePath($relative);
    }

    public function write(string $relative, string $contents): string
    {
        $directory = $this->getDirectory();
        $directory->create(dirname($relative));
        $directory->writeFile($relative, $contents);
        return $directory->getAbsolutePath($relative);
    }

    public function read(string $relative): string
    {
        $directory = $this->getDirectory();
        if (!$directory->isExist($relative)) {
            return '';
        }
        return $directory->readFile($relative);
    }

    public function exists(string $relative): bool
    {
        return $this->getDirectory()->isExist($relative);
    }

    public function mimeType(string $relative): string
    {
        if (str_ends_with($relative, '.gz')) {
            return 'application/gzip';
        }
        if (str_ends_with($relative, '.jsonl') || str_ends_with($relative, '.json')) {
            return 'application/json';
        }
        if (str_ends_with($relative, '.tsv') || str_ends_with($relative, '.txt')) {
            return 'text/tab-separated-values';
        }
        return 'text/csv';
    }

    public function productsBasename(string $format): string
    {
        return match ($format) {
            FeedFormat::OPENAI_CSV, FeedFormat::GOOGLE_CSV => 'products.csv',
            FeedFormat::OPENAI_TSV, FeedFormat::GOOGLE_TSV => 'products.tsv',
            default => 'products.jsonl',
        };
    }
}
