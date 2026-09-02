<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;

class GenerateToken extends Action
{
    public const ADMIN_RESOURCE = 'Solutioo_ChatGptProductSearch::config';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly WriterInterface $configWriter,
        private readonly EncryptorInterface $encryptor,
        private readonly Random $random,
        private readonly TypeListInterface $cacheTypeList
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $token = $this->random->getRandomString(48);
        $this->configWriter->save('solutioo_chatgpt/delivery/feed_token', $this->encryptor->encrypt($token));
        $this->cacheTypeList->cleanType('config');
        return $this->jsonFactory->create()->setData([
            'success' => true,
            'token' => $token,
            'message' => (string) __('Neues Feed-Token erzeugt und gespeichert. Bitte die Konfiguration neu laden.'),
        ]);
    }
}
