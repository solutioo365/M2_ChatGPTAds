<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Solutioo_ChatGptProductSearch::log';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Solutioo_ChatGptProductSearch::log');
        $page->getConfig()->getTitle()->prepend(__('ChatGPT Feed-Protokolle'));
        return $page;
    }
}
