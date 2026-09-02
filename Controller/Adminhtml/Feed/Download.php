<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Solutioo\ChatGptProductSearch\Model\Feed\FileStorage;
use Solutioo\ChatGptProductSearch\Model\Feed\Generator;

class Download extends Action
{
    public const ADMIN_RESOURCE = 'Solutioo_ChatGptProductSearch::feed';

    public function __construct(
        Context $context,
        private readonly FileStorage $storage,
        private readonly Generator $generator,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $storeId = (int) $this->getRequest()->getParam('store_id', 1);
        $relative = $this->storage->getProductsRelativePath($storeId);
        if (!$this->storage->exists($relative)) {
            $this->generator->generate($storeId);
        }
        return $this->fileFactory->create(
            basename($relative),
            ['type' => 'filename', 'value' => $relative],
            DirectoryList::VAR_DIR,
            $this->storage->mimeType($relative)
        );
    }
}
