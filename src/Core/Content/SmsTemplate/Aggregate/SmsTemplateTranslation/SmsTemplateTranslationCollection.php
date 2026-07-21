<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Core\Content\SmsTemplate\Aggregate\SmsTemplateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SmsTemplateTranslationEntity>
 */
class SmsTemplateTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SmsTemplateTranslationEntity::class;
    }
}
