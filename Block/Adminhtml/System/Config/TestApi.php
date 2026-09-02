<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Block\Adminhtml\System\Config;

class TestApi extends Button
{
    protected function getButtonLabel(): string
    {
        return (string) __('API-Verbindung testen');
    }

    protected function getButtonUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/config/testApi');
    }
}
