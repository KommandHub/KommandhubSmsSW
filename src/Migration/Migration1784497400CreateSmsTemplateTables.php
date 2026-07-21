<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Installs the plugin's schema.
 *
 * A single migration on purpose: the plugin is pre-release and was renamed from
 * KommandhubNotificationsSW, so Shopware replays migrations from the beginning
 * under the new technical name. Replaying the old create-then-alter chain —
 * including a WhatsApp table a later step dropped — would be noise nobody can
 * ever benefit from. From the first release onwards migrations are append-only:
 * never edit this one, add a new one.
 *
 * Tables carry the `kommandhub_` prefix. `sms_template` sits in Shopware's
 * global table namespace, where any other plugin could reasonably claim the
 * same obvious name; the prefix makes a collision impossible.
 */
class Migration1784497400CreateSmsTemplateTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784497400;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kommandhub_sms_template` (
                `id`                     BINARY(16)   NOT NULL,
                `mail_template_type_id`  BINARY(16)   NOT NULL,
                `active`                 TINYINT(1)   NOT NULL DEFAULT 1,
                `sender_id`              VARCHAR(255) NULL,
                `created_at`             DATETIME(3)  NOT NULL,
                `updated_at`             DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                KEY `fk.kommandhub_sms_template.mail_template_type_id` (`mail_template_type_id`),
                CONSTRAINT `fk.kommandhub_sms_template.mail_template_type_id`
                    FOREIGN KEY (`mail_template_type_id`) REFERENCES `mail_template_type` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kommandhub_sms_template_translation` (
                `kommandhub_sms_template_id` BINARY(16)   NOT NULL,
                `language_id`                BINARY(16)   NOT NULL,
                `name`                       VARCHAR(255) NOT NULL,
                `content`                    LONGTEXT     NOT NULL,
                `created_at`                 DATETIME(3)  NOT NULL,
                `updated_at`                 DATETIME(3)  NULL,
                PRIMARY KEY (`kommandhub_sms_template_id`, `language_id`),
                KEY `fk.kommandhub_sms_template_translation.language_id` (`language_id`),
                CONSTRAINT `fk.kommandhub_sms_template_translation.template_id`
                    FOREIGN KEY (`kommandhub_sms_template_id`) REFERENCES `kommandhub_sms_template` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.kommandhub_sms_template_translation.language_id`
                    FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Intentionally empty. The former plugin's settings are left in place:
        // a developer rolling back to it would want them intact, and stale rows
        // nothing reads are harmless.
    }
}
