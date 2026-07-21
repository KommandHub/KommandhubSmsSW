<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Core\Content\SmsTemplate\Aggregate\SmsTemplateTranslation;

use Kommandhub\SmsSW\Core\Content\SmsTemplate\SmsTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class SmsTemplateTranslationEntity extends TranslationEntity
{
    /**
     * Named after the parent entity, which the DAL derives from
     * `kommandhub_sms_template` — the prefix is part of the property name, not
     * decoration that can be trimmed.
     */
    protected string $kommandhubSmsTemplateId;

    protected string $name;

    protected string $content;

    protected ?SmsTemplateEntity $smsTemplate = null;

    public function getKommandhubSmsTemplateId(): string
    {
        return $this->kommandhubSmsTemplateId;
    }

    public function setKommandhubSmsTemplateId(string $kommandhubSmsTemplateId): void
    {
        $this->kommandhubSmsTemplateId = $kommandhubSmsTemplateId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getSmsTemplate(): ?SmsTemplateEntity
    {
        return $this->smsTemplate;
    }

    public function setSmsTemplate(?SmsTemplateEntity $smsTemplate): void
    {
        $this->smsTemplate = $smsTemplate;
    }
}
