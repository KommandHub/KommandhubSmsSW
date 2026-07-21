# KommandhubSmsSW

Transactional SMS for Shopware 6, sent alongside core email, with multi-provider
direct-carrier routing for African networks.

PHP namespace root: `Kommandhub\SmsSW\` → `src/`.

## Commands

All commands run inside the Docker dev stack (see `Makefile`). The plugin lives
at `custom/static-plugins/KommandhubSmsSW` inside a Shopware install.

- `make up` / `make down` — start / tear down the stack
- `make test` — PHPUnit (`phpunit.dist.xml`). Filter: `make test FILTER=SomeTest`
- `make test-coverage` — coverage text report
- `make analyse` — PHPStan (`phpstan.dist.neon`, level in that file), `src` only
- `make cs` / `make cs-fix` — php-cs-fixer dry-run / apply
- `make validate-plugin` — shopware-cli store-compliance check
- `make shell` — bash into the app container

Run `make cs-fix && make analyse && make test` before committing.

## Architecture

**Feature-first modules** under `src/`, following Shopware's own plugin layout
(cf. SwagPayPal). A top-level directory *is* a boundary; inside it, flat
Symfony-idiomatic folders (`Service`, `Subscriber`, `Handler`, `Struct`,
`Event`, `Controller`) — no `Application/Domain/Infrastructure`
nesting. One obvious home per class.

Cross-cutting, always present:

- `Setting/Service/Config.php` — typed reader over `SystemConfigService`, always
  sales-channel aware.
- `Logging/ConfigurableLogger.php` — PSR-3 wrapper gating output on the
  `enableDebugging` / `logLevels` settings, per sales channel. `error` and above
  are always written.
- `Exception/` — one plugin-scoped exception base.
- `Resources/config/` — `services.yml`, `routes.yml`, `config.xml`, `packages/`.

## Conventions & gotchas

- **DI is autowired** via the `../../*` glob in `services.yml`. Symfony does NOT
  auto-alias an interface to its single implementation — when you add a new
  `*Interface` that is constructor-injected, add an explicit `alias:` entry.
- **Never alias `Psr\Log\LoggerInterface` container-wide.** `services.yml` uses a
  scoped `bind:` so only this plugin's services get the ConfigurableLogger;
  a global alias would hijack Shopware core and every sibling plugin.
- **Config keys are read through `Config`**, never `SystemConfigService`
  directly, and always with the sales-channel id in hand.
- **No vendor name outside its own provider directory.** Vendors live one per
  directory under `Notification/Provider/<Vendor>/` and are reached only through
  `NotificationProviderInterface`. If a shared service needs to know whether it
  is talking to Termii, the abstraction is wrong — add a method to the contract
  instead. `NotificationGatewayInterface` is the seam everything upstream
  depends on.
- **A vendor's own vocabulary stays inside its directory.** Termii calls its
  carrier route a "channel", which is not what the word means here; the setting
  is `termiiRoute` and the mapping lives in `TermiiProvider`. Never let a
  vendor's word for something leak into shared naming.
- **SMS only.** WhatsApp was removed: business-initiated messages may only use
  provider-approved templates, and every vendor models those differently. Adding
  it back needs a template-aware provider contract, not another channel enum.
- **DAL tables and entity names carry the `kommandhub_` prefix.**
  `kommandhub_sms_template`, not `sms_template` — the DAL namespace is global
  and shared with every other plugin. The translation's foreign-key property
  follows from the parent entity name (`kommandhubSmsTemplateId`), so it is not
  decoration that can be trimmed.
- **Provider settings are namespaced** `<providerName><Setting>` and built only
  through `ProviderSettings::key()`. A provider reads its own settings with
  `$this->setting('apiKey')`; it must never read another provider's.
- **Providers throw `TransientProviderException` or `PermanentProviderException`,
  never the base class.** The distinction drives failover and queue retries, so a
  misclassified error either drops a message or bills the merchant twice.
- Keep a change inside its feature module; reach across modules through a
  service, not by deep-linking another module's internals.
- Built assets in `Resources/public/` and `Resources/app/*/dist/` are generated —
  never hand-edit.
- Tests mirror `src/` under `tests/Unit/` (+ `tests/Integration/`). Add a test
  with each behaviour change. Tests needing a booted kernel carry
  `#[Group('kernel')]`; CI runs `--exclude-group kernel`.
