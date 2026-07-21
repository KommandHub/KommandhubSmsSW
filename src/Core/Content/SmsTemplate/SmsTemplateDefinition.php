<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Core\Content\SmsTemplate;

use Kommandhub\SmsSW\Core\Content\SmsTemplate\Aggregate\SmsTemplateTranslation\SmsTemplateTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * DAL definition for the `sms_template` table.
 *
 * Mirrors the layout of Shopware's own MailTemplateDefinition: one row per
 * mail template type, with the merchant-editable body held in the translation
 * aggregate so a shop can phrase the same notification differently per
 * language.
 *
 * The definition is the single source of truth for the DAL; the SQL table is
 * created by a Migration and the two must stay in step.
 */
class SmsTemplateDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'kommandhub_sms_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SmsTemplateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SmsTemplateCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('mail_template_type_id', 'mailTemplateTypeId', MailTemplateTypeDefinition::class))
                ->addFlags(new Required(), new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),

            // Not translatable: an approved sender ID is registered with the
            // carrier per account, not per shopper language.
            (new StringField('sender_id', 'senderId'))->addFlags(new ApiAware()),

            (new TranslatedField('name'))->addFlags(new ApiAware()),
            (new TranslatedField('content'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('mailTemplateType', 'mail_template_type_id', MailTemplateTypeDefinition::class, 'id', false),
            (new TranslationsAssociationField(SmsTemplateTranslationDefinition::class, 'kommandhub_sms_template_id'))
                ->addFlags(new Required()),
        ]);
    }
}
