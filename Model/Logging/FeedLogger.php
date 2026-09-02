<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Logging;

use Psr\Log\LoggerInterface;
use Solutioo\ChatGptProductSearch\Model\FeedLogFactory;

class FeedLogger
{
    public function __construct(
        private readonly FeedLogFactory $feedLogFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $result
     */
    public function log(int $storeId, string $action, string $status, string $message, array $result = []): void
    {
        $details = $result;
        unset($details['products'], $details['promotions']);
        if (isset($details['errors']) && is_array($details['errors'])) {
            $details['errors'] = array_slice($details['errors'], 0, 50);
        }
        if (isset($details['warnings']) && is_array($details['warnings'])) {
            $details['warnings'] = array_slice($details['warnings'], 0, 50);
        }

        try {
            $log = $this->feedLogFactory->create();
            $log->setData([
                'store_id' => $storeId,
                'action' => $action,
                'status' => $status,
                'product_count' => (int) ($result['product_count'] ?? $result['valid'] ?? 0),
                'error_count' => isset($result['errors']) && is_array($result['errors'])
                    ? count($result['errors'])
                    : (int) ($result['invalid'] ?? 0),
                'warning_count' => isset($result['warnings']) && is_array($result['warnings'])
                    ? count($result['warnings'])
                    : 0,
                'file_path' => (string) ($result['file'] ?? $result['relative_file'] ?? ''),
                'message' => $message,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $log->save();
        } catch (\Throwable $exception) {
            $this->logger->error('Solutioo ChatGPT feed log failed: ' . $exception->getMessage());
        }

        $this->logger->info(sprintf(
            '[Solutioo_ChatGptProductSearch] store=%d action=%s status=%s %s',
            $storeId,
            $action,
            $status,
            $message
        ));
    }
}
