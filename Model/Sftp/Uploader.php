<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Sftp;

use Magento\Framework\Filesystem\Io\Sftp;
use Solutioo\ChatGptProductSearch\Model\Config;
use Solutioo\ChatGptProductSearch\Model\Feed\FileStorage;
use Solutioo\ChatGptProductSearch\Model\Logging\FeedLogger;

class Uploader
{
    public function __construct(
        private readonly Config $config,
        private readonly FileStorage $storage,
        private readonly FeedLogger $feedLogger,
        private readonly Sftp $sftp
    ) {
    }

    /**
     * @return array{success: bool, message: string, files: string[]}
     */
    public function upload(int $storeId): array
    {
        if (!$this->config->isSftpEnabled($storeId)) {
            throw new \RuntimeException((string) __('SFTP ist für diesen Store nicht aktiviert.'));
        }

        $relative = $this->storage->getProductsRelativePath($storeId);
        if (!$this->storage->exists($relative)) {
            throw new \RuntimeException((string) __('Kein Feed vorhanden. Bitte zuerst erzeugen.'));
        }

        $local = $this->storage->getAbsolutePath($relative);
        $remoteName = $this->config->getSftpFilename($storeId);
        $remoteDir = $this->config->getSftpRemotePath($storeId);
        $remoteFile = rtrim($remoteDir, '/') . '/' . ltrim($remoteName, '/');

        $this->connect($storeId);
        try {
            $this->ensureRemoteDir($remoteDir);
            if (!$this->sftp->write($remoteFile, $local)) {
                throw new \RuntimeException((string) __('SFTP-Schreibvorgang nach %1 fehlgeschlagen.', $remoteFile));
            }
        } finally {
            $this->sftp->close();
        }

        $files = [$remoteFile];
        $result = [
            'success' => true,
            'message' => (string) __('SFTP-Upload nach %1 erfolgreich.', $remoteFile),
            'files' => $files,
            'file' => $local,
        ];
        $this->feedLogger->log($storeId, 'sftp', 'success', $result['message'], $result);
        return $result;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function test(int $storeId): array
    {
        $this->connect($storeId);
        $this->sftp->close();
        return [
            'success' => true,
            'message' => (string) __('SFTP-Verbindung zu %1 erfolgreich.', $this->config->getSftpHost($storeId)),
        ];
    }

    private function connect(int $storeId): void
    {
        $host = $this->config->getSftpHost($storeId);
        if ($host === '') {
            throw new \RuntimeException((string) __('SFTP-Host fehlt.'));
        }

        $args = [
            'host' => $host . ':' . $this->config->getSftpPort($storeId),
            'username' => $this->config->getSftpUsername($storeId),
            'password' => $this->config->getSftpPassword($storeId),
            'timeout' => 60,
        ];
        $key = $this->config->getSftpPrivateKey($storeId);
        if ($key !== '') {
            $args['private_key'] = $key;
        }

        $this->sftp->open($args);
    }

    private function ensureRemoteDir(string $path): void
    {
        if ($path === '' || $path === '/') {
            return;
        }
        try {
            $this->sftp->cd($path);
        } catch (\Throwable) {
            $this->sftp->mkdir($path, 0755, true);
        }
    }
}
