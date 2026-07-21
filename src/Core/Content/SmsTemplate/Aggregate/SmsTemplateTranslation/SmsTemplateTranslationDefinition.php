<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Core\Content\SmsTemplate\Aggregate\SmsTemplateTranslation;

use Kommandhub\SmsSW\Core\Content\SmsTemplate\SmsTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Per-language body of an SMS template.
 *
 * The composite primary key (`sms_template_id`, `language_id`) is contributed
 * by EntityTranslationDefinition — do not declare it here.
 */
class SmsTemplateTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'kommandhub_sms_template_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SmsTemplateTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SmsTemplateTranslationCollection::class;
    }

    public function getParentDefinitionClass(): string
    {
        return SmsTemplateDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new LongTextField('content', 'content'))->addFlags(new Required(), new ApiAware()),
        ]);
    }
}
