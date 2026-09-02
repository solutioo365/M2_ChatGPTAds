<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Model\Config;

class FeedUrl extends Field
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeId = (int) $this->getRequest()->getParam('store');
        if ($storeId <= 0) {
            $storeId = (int) ($this->storeManager->getDefaultStoreView()?->getId() ?: 0);
        }
        $url = $this->config->getPublicFeedUrl($storeId ?: null);
        $escaped = $this->escapeHtml($url);
        return '<div class="solutioo-chatgpt-feed-url">'
            . '<input type="text" readonly="readonly" class="input-text" value="' . $escaped . '" '
            . 'onclick="this.select()" style="width:100%;max-width:640px"/>'
            . '<p class="note">' . $this->escapeHtml(__('Klicken zum Markieren, dann kopieren. Store-Scope oben wählen für die jeweilige URL.')) . '</p>'
            . '</div>';
    }
}
