<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

abstract class Button extends Field
{
    protected $_template = 'Solutioo_ChatGptProductSearch::system/config/button.phtml';

    abstract protected function getButtonLabel(): string;

    abstract protected function getButtonUrl(): string;

    protected function getButtonClass(): string
    {
        return 'action-secondary';
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->setData('button_label', $this->getButtonLabel());
        $this->setData('button_url', $this->getButtonUrl());
        $this->setData('button_class', $this->getButtonClass());
        return $this->_toHtml();
    }
}
