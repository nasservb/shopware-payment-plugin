<?php

namespace Payever\Tests;

class DisabledCacheContext extends \Shopware\Core\Framework\Context
{
    /**
     * @var bool
     */
    private $useCache = false;

    /**
     * {@inheritDoc}
     */
    public function getUseCache(): bool
    {
        return $this->useCache;
    }
}
