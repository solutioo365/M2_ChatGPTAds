<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model;

use Magento\Framework\Model\AbstractModel;
use Solutioo\ChatGptProductSearch\Model\ResourceModel\FeedLog as FeedLogResource;

class FeedLog extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(FeedLogResource::class);
    }
}
