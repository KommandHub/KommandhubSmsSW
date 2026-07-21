<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Core\Content\SmsTemplate;

use Kommandhub\SmsSW\Core\Content\SmsTemplate\Aggregate\SmsTemplateTranslation\SmsTemplateTranslationCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SmsTemplateEntity extends Entity
{
    use EntityIdTrait;

    protected string $mailTemplateTypeId;

    protected bool $active = true;

    protected ?string $senderId = null;

    /**
     * Translated fields are null until the translation is loaded for the
     * requested language.
     */
    protected ?string $name = null;

    protected ?string $content = null;

    protected ?MailTemplateTypeEntity $mailTemplateType = null;

    protected ?SmsTemplateTranslationCollection $translations = null;

    public function getMailTemplateTypeId(): string
    {
        return $this->mailTemplateTypeId;
    }

    public function setMailTemplateTypeId(string $mailTemplateTypeId): void
    {
        $this->mailTemplateTypeId = $mailTemplateTypeId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * Overrides the provider's configured sender for this template only; null
     * falls back to the provider setting.
     */
    public function getSenderId(): ?string
    {
        return $this->senderId;
    }

    public function setSenderId(?string $senderId): void
    {
        $this->senderId = $senderId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getMailTemplateType(): ?MailTemplateTypeEntity
    {
        return $this->mailTemplateType;
    }

    public function setMailTemplateType(?MailTemplateTypeEntity $mailTemplateType): void
    {
        $this->mailTemplateType = $mailTemplateType;
    }

    public function getTranslations(): ?SmsTemplateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(?SmsTemplateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
