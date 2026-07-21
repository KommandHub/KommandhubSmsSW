# SMS for Shopware 6

Transactional SMS for Shopware 6, sent alongside core email, with multi-provider direct-carrier routing for African networks.

[![Shopware](https://img.shields.io/badge/Shopware-~6.6.0%20%7C%7C%20~6.7.0-189eff)](https://www.shopware.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache-2.0-blue)](LICENSE)

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Features](#features)
- [Local development](#local-development)
- [Makefile commands](#makefile-commands)
- [Testing](#testing)
- [Code quality](#code-quality)
- [CI/CD](#cicd)
- [Release process](#release-process)
- [Logging and debugging](#logging-and-debugging)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Requirements

| | |
| --- | --- |
| Shopware | `~6.6.0 || ~6.7.0` |
| PHP | 8.2 or newer |
| Database | MySQL 8.0+ / MariaDB 10.11+ |

## Installation

### Via Composer (recommended)

```bash
composer require kommandhub/sms-sw
bin/console plugin:refresh
bin/console plugin:install --activate KommandhubSmsSW
bin/console cache:clear
```

### Manual upload

Download the release zip and install it under **Extensions > My extensions >
Upload extension**, then activate it.

## Configuration

**Settings > Extensions > SMS for Shopware 6**

Every setting is sales-channel scoped: use the sales-channel selector at the top
of the configuration page to override a value for one channel only.

## Architecture

Feature-first modules under `src/`, following Shopware's own plugin layout. A
top-level directory *is* a boundary; inside it, flat Symfony-idiomatic folders
(`Service`, `Subscriber`, `Handler`, `Struct`, `Event`, `Enum`, `Controller`).
No `Application/Domain/Infrastructure` nesting — one obvious home per class.

Always present:

| Path | Responsibility |
| --- | --- |
| `src/KommandhubSmsSW.php` | plugin bootstrap and lifecycle hooks |
| `src/Setting/Service/Config.php` | typed, sales-channel-aware settings reader |
| `src/Logging/ConfigurableLogger.php` | PSR-3 wrapper gated on the debug settings |
| `src/Exception/` | plugin-scoped exception base |
| `src/Resources/config/` | `services.yml`, `config.xml`, `packages/` |
| `tests/Unit`, `tests/Integration` | mirror `src/` |

## Features

### Administration

Vue/JS sources live in `src/Resources/app/administration/src/`. Built output in
`src/Resources/public/` is **generated** — never hand-edit it, and rebuild with
`make zip` (or `bin/build-administration.sh` in the container) after changing a
source file.

- `main.js` — entry point: registers locales, ACL, modules and services.
- `acl/index.js` — the plugin's admin permissions.
- `snippet/*.json` — one file per locale, mirroring the same key tree.

Any privilege enforced here **must** also be enforced server-side via `_acl` on
the route.

### Messaging providers

`src/Notification/Provider/` is the only place that knows a vendor exists.

| Layer | Responsibility |
| --- | --- |
| `NotificationProviderInterface` | the contract: name, label, **supportedChannels**, configured?, supports?, send, verify |
| `AbstractHttpNotificationProvider` | transport call, transient/permanent classification, logging |
| `<Vendor>/<Vendor>Provider` | one directory per vendor — URLs, auth, payload shape |
| `NotificationProviderRegistry` | every tagged provider, keyed by name; filters by channel |
| `NotificationProviderSelector` | ordering strategy: per-channel default first, then destination match |
| `Gateway/RoutingNotificationGateway` | walks that order, fails over on transient errors |

Everything upstream depends on `NotificationGatewayInterface` and cannot tell
which vendor carried a message, or over which channel.

Shipping providers:

| Provider | Region | Claims |
| --- | --- | --- |
| Termii | West Africa, direct carrier | 234, 233, 225, 221, 254 |
| Sendexa | Ghana | 233 |
| Africa's Talking | East Africa, direct carrier | 254, 256, 255, 250, 265, 251, 234 |
| Twilio | international fallback | everything |

A provider that claims no country codes is willing to carry anything, which is
what a fallback wants. A provider that claims some is *preferred* for those
destinations but still offered elsewhere as a last resort — under-claiming costs
a preference, never delivery.

> **Termii's "channel" is a carrier route**, not a delivery medium — hence the
> setting name `termiiRoute` (`generic` or `dnd`). A vendor's vocabulary stays
> inside its own directory.

#### Adding a provider

1. Create `src/Notification/Provider/<Vendor>/<Vendor>Provider.php` extending
   `AbstractHttpNotificationProvider`.
2. Implement `getName()`, `getLabel()`, `isConfigured()`, `send()`,
   `verifyCredentials()`; override `getCountryCodes()` to claim a region.
3. Add a config card in `config.xml` whose field names are
   `<name><Setting>` — `ProviderSettings::key()` is the single rule — and add
   the name to the `defaultSmsProvider` options.

No existing PHP class changes. The `_instanceof` tag in `services.yml` picks the
provider up, the registry keys it, and the administration lists it.

#### Errors

Providers throw exactly two things, and the distinction is load-bearing:

| Exception | Meaning | Effect |
| --- | --- | --- |
| `TransientProviderException` | timeout, 429, 5xx, unreadable body | next provider, then queue retry |
| `PermanentProviderException` | 4xx, refusal inside a 2xx | stop; logged, not retried |

### Custom entity

One translatable message-template entity, keyed to a core `mail_template_type`
so an SMS is always a sibling of the email Shopware already sends:

| Entity | Table | Definition |
| --- | --- | --- |
| `kommandhub_sms_template` | `kommandhub_sms_template` + `kommandhub_sms_template_translation` | `src/Core/Content/SmsTemplate/` |

It mirrors the layout of Shopware's own `Core/Content/MailTemplate/`: the
merchant-editable `name` and `content` live in the translation aggregate.

The definition and the migration are two halves of one thing and must stay in
step. Migrations are append-only: never edit a released one.

Access them via the generated repositories:

```php
$repository = $container->get('kommandhub_sms_template.repository');
```

### Message queue

`NotificationsMessage` is dispatched onto Shopware's async transport and handled by
`NotificationsMessageHandler` in a worker (`bin/console messenger:consume async`).

Two rules: messages carry **ids, not entities**, and handlers are
**idempotent** — delivery is at-least-once.

### Storefront

- `src/Resources/app/storefront/src/` — JS plugins, registered in `main.js`.
- `src/Resources/views/storefront/` — Twig overrides. **Namespace every block**
  you add (`{% block kommandhub_sms_foo %}`) so it cannot collide with another plugin
  extending the same template.
- `src/Resources/snippet/<locale>/` — storefront translations; every locale file
  must carry the same key tree.

Built output under `src/Resources/app/storefront/dist/` is generated.

### Webhooks

`POST /notifications/webhook` — public, unauthenticated, HMAC-verified.

1. `WebhookSignatureValidator` rejects anything inauthentic (403). This is the
   security boundary; everything downstream trusts the payload.
2. `WebhookEventFactory` maps the provider's event string to a typed event.
   Unknown types are logged and answered **200** so the provider stops retrying.
3. The event is dispatched; subscribers under `Webhook/Subscriber/` do the work.

Register the URL in the provider's dashboard per sales channel domain.


## Local development

The plugin is developed inside a Docker stack that runs a full Shopware install
with this directory mounted at `custom/static-plugins/KommandhubSmsSW`.

```bash
git clone https://github.com/Kommandhub/KommandhubSmsSW.git
cd KommandhubSmsSW

make up     # build the image, start Shopware, install dependencies
make shell  # bash into the container

# inside the container
bin/console plugin:refresh
bin/console plugin:install --activate KommandhubSmsSW
```

Storefront: <http://localhost> · Administration: <http://localhost/admin>
(`admin` / `shopware`).

## Makefile commands

| Command | What it does |
| --- | --- |
| `make up` / `make down` | start / tear down the stack |
| `make restart` | `down` then `up` |
| `make shell` | shell into the container |
| `make test` | PHPUnit; filter with `make test FILTER=SomeTest` |
| `make test-coverage` | coverage text report |
| `make analyse` | PHPStan on `src/` |
| `make cs` / `make cs-fix` | php-cs-fixer dry-run / apply |
| `make validate-plugin` | shopware-cli store-compliance check |
| `make changelog` | render `CHANGELOG.md` as the Store will |
| `make zip` | build a distributable zip into `build/` |
| `make cli ARGS="..."` | any other shopware-cli command |

## Testing

```bash
make test
make test FILTER=ConfigTest
make test-coverage
```

- `tests/Unit/` mirrors `src/`. No kernel, no database — fast, and what CI runs.
- `tests/Integration/` needs a booted Shopware kernel. Mark those tests
  `#[Group('kernel')]`; CI runs `--exclude-group kernel`.
- Add or update a test with every behaviour change.

## Code quality

```bash
make cs-fix && make analyse && make test
```

All three must pass before a commit. PHPStan runs at the level pinned in
`phpstan.dist.neon`; php-cs-fixer enforces PSR-12 plus the rules in
`.php-cs-fixer.dist.php`.

## CI/CD

`.github/workflows/php.yml` runs on every push to `main`/`develop` and on every
pull request: composer validate, PHP lint, PHPStan, php-cs-fixer (dry-run),
PHPUnit with coverage, and a coverage threshold gate.

CI runs **without a Shopware kernel**, so kernel-dependent tests are excluded
there and the plugin bootstrap is excluded from coverage in `phpunit.dist.xml`.

## Release process

1. Land everything on `develop`; make sure the local gate passes.
2. Bump `version` in `composer.json`.
3. Add a `# <version>` section at the top of `CHANGELOG.md` (and the localised
   variants). Check the rendering with `make changelog`.
4. `make validate-plugin` — must be clean for a Store submission.
5. `make zip` — the artefact lands in `build/`.
6. Merge `develop` into `main` and tag the release.

## Logging and debugging

Enable **debug logging** in the plugin configuration; entries land in
`var/log/kommandhub_sms_<env>.log` (rotating, 7 files).

`error` and above are **always** written regardless of the toggle, so production
keeps a trail of failures. Both the toggle and the level filter are
sales-channel scoped — pass the sales channel id in the log context so it
resolves against the right scope:

```php
$this->logger->info('something happened', [
    ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
]);
```

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Never open a
public issue for one, and never paste real credentials into an issue.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Pull requests target `develop`, commits
are signed off (`git commit -s`), and the local gate must pass.

## License

Apache-2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE).
