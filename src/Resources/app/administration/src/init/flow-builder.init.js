import { SEND_SMS_ACTION, SEND_SMS_ACTION_KEY } from '../constant/kommandhub-sms.constant';

/**
 * Registers the Send SMS action with the flow builder.
 *
 * 6.7 extension point: the flow builder resolves titles, icons, groups and
 * modals through `flowBuilderService` lookup maps, so a plugin extends the maps
 * rather than overriding `sw-flow-sequence-action` (the pattern older guides
 * show). The action itself appears in the selection list because the server
 * exposes every `flow.action`-tagged service; this file only teaches the
 * administration how to present it.
 *
 * A decorator, not a direct `Shopware.Service('flowBuilderService')` call: the
 * service is registered by the sw-flow module, whose load order relative to
 * plugin bundles is not guaranteed. At plugin-eval time the service can be
 * undefined, and a throw here takes the whole plugin bundle down with it. The
 * decorator runs on first access instead, which is necessarily after
 * registration.
 */
Shopware.Application.addServiceProviderDecorator('flowBuilderService', (flowBuilderService) => {
    flowBuilderService.addActionNames({
        SEND_SMS: SEND_SMS_ACTION,
    });

    flowBuilderService.addIcons({
        [SEND_SMS_ACTION_KEY]: 'regular-comments',
    });

    flowBuilderService.addLabels({
        [SEND_SMS_ACTION_KEY]: 'kommandhub-sms.flowAction.label',
    });

    flowBuilderService.addActionGroupMapping({
        [SEND_SMS_ACTION]: 'general',
    });

    flowBuilderService.addDescriptionCallbacks({
        /**
         * Shown on the sequence card once the action is configured. The default
         * would print the raw template UUID, which tells a merchant nothing.
         */
        [SEND_SMS_ACTION]: (context) => context.translator.$tc('kommandhub-sms.flowAction.description'),
    });

    return flowBuilderService;
});
