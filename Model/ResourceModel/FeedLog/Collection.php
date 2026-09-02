<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\ResourceModel\FeedLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Solutioo\ChatGptProductSearch\Model\FeedLog;
use Solutioo\ChatGptProductSearch\Model\ResourceModel\FeedLog as FeedLogResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';

    protected function _construct(): void
    {
        $this->_init(FeedLog::class, FeedLogResource::class);
    }
}
