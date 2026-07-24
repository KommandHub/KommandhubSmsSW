# 0.9.0-beta.1

First public beta. Feature-complete for transactional SMS; provider delivery
has been verified against provider error responses but not yet across a large
volume of live sends, which is what the beta period is for. Start on a test
sender and a small audience before switching a storefront over.

- Send transactional SMS alongside Shopware's own emails. A "Send SMS" action
  in the Flow Builder delivers an SMS template for any event — order placed,
  order shipped, password reset — without replacing the email.
- Manage SMS templates per event in the Administration, with a live character
  and segment counter that flags when a message spills into a second billed
  part or drops to Unicode.
- Choose from four SMS providers, with automatic fail-over: Termii (West
  Africa), Sendexa (Ghana, beta), Africa's Talking (East Africa) and Twilio
  (international fallback). Set a default; the plugin prefers a provider that
  covers the destination country and falls back to the rest.
- No default provider is pre-selected: pick and configure one before going
  live, so a shop never sends through a provider it was not set up for.
- "Send test message" on each template delivers to a number you choose, using
  sample order data, and reports back exactly why a send failed.
- Storefront phone fields gain a country dial-code selector next to the number,
  so the country is the shopper's choice rather than a single shop-wide setting.
  Works across registration, address management, checkout and CMS forms.
- A customer with no usable mobile number is skipped and logged; the rest of the
  flow, including the order-confirmation email, still runs.
- Delivery-status webhooks are received and logged, signature-verified per sales
  channel.

<!--
Format notes (this file is rendered by the Shopware Store):

- One `# <version>` heading per release, newest first, separated by `---`.
- Bullets describe user-visible behaviour, not commits. "Refunds no longer
  exceed the captured amount", not "refactor RefundProcessor".
- `make changelog` renders this exactly as the Store will display it.
- Localised variants live in CHANGELOG_de-DE.md / CHANGELOG_fr-FR.md and must
  carry the same version headings.
-->
