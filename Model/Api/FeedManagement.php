<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Api;

use Solutioo\ChatGptProductSearch\Api\FeedManagementInterface;
use Solutioo\ChatGptProductSearch\Model\Feed\Generator;
use Solutioo\ChatGptProductSearch\Model\Feed\Publisher;
use Solutioo\ChatGptProductSearch\Model\Feed\StatusProvider;
use Solutioo\ChatGptProductSearch\Model\Sftp\Uploader;

class FeedManagement implements FeedManagementInterface
{
    public function __construct(
        private readonly Generator $generator,
        private readonly Publisher $publisher,
        private readonly StatusProvider $statusProvider,
        private readonly Uploader $uploader
    ) {
    }

    public function generate(int $storeId): string
    {
        return $this->encode($this->generator->generate($storeId));
    }

    public function validate(int $storeId): string
    {
        return $this->encode($this->generator->validateOnly($storeId));
    }

    public function status(int $storeId): string
    {
        return $this->encode($this->statusProvider->getStoreStatus($storeId));
    }

    public function syncApi(int $storeId): string
    {
        return $this->encode($this->publisher->syncApi($storeId));
    }

    public function uploadSftp(int $storeId): string
    {
        return $this->encode($this->uploader->upload($storeId));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        unset($data['products'], $data['promotions']);
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
