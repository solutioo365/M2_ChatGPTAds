<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FeedLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('solutioo_chatgpt_feed_log', 'log_id');
    }
}
