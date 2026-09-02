<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Block\Adminhtml\System\Config;

class GenerateToken extends Button
{
    protected function getButtonLabel(): string
    {
        return (string) __('Neues Token erzeugen');
    }

    protected function getButtonUrl(): string
    {
        return $this->getUrl('solutioo_chatgpt/config/generateToken');
    }
}
