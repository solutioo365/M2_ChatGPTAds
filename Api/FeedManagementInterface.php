<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Api;

interface FeedManagementInterface
{
    /**
     * @param int $storeId
     * @return string
     */
    public function generate(int $storeId): string;

    /**
     * @param int $storeId
     * @return string
     */
    public function validate(int $storeId): string;

    /**
     * @param int $storeId
     * @return string
     */
    public function status(int $storeId): string;

    /**
     * @param int $storeId
     * @return string
     */
    public function syncApi(int $storeId): string;

    /**
     * @param int $storeId
     * @return string
     */
    public function uploadSftp(int $storeId): string;
}
