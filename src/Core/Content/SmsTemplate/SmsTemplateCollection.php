<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Core\Content\SmsTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SmsTemplateEntity>
 */
class SmsTemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SmsTemplateEntity::class;
    }
}
