import template from './kommandhub-test-message-modal.html.twig';

const { Component } = Shopware;

/**
 * "Send test message" dialog.
 *
 * The caller passes a template id and this component owns the phone input, the
 * request and the notifications. Snippets live under
 * `kommandhub-sms.testMessage.*` rather than in the module's
 * namespace so a second module could reuse the dialog unchanged.
 */
Component.register('kommandhub-test-message-modal', {
    template,

    inject: ['testMessageApiService'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        templateId: {
            type: String,
            required: true,
        },
        salesChannelId: {
            type: String,
            required: false,
            default: null,
        },
    },

    emits: ['modal-close'],

    data() {
        return {
            recipient: '',
            isSending: false,
            /** Last rendered body, shown so the sample variables are visible. */
            preview: null,
        };
    },

    computed: {
        canSend() {
            return !this.isSending && this.recipient.trim().length > 0;
        },
    },

    methods: {
        onClose() {
            this.$emit('modal-close');
        },

        async onSend() {
            if (!this.canSend) {
                return;
            }

            this.isSending = true;
            this.preview = null;

            try {
                const result = await this.testMessageApiService.send(
                    this.templateId,
                    this.recipient,
                    this.salesChannelId,
                );

                this.preview = result.renderedBody || null;

                if (result.success) {
                    this.createNotificationSuccess({
                        message: this.$t('kommandhub-sms.testMessage.messageSent', {
                            recipient: this.recipient,
                        }),
                    });

                    this.onClose();

                    return;
                }

                this.createNotificationError({ message: this.describeFailure(result) });
            } catch {
                // A transport or permission failure never reached the service,
                // so there is no structured reason to translate.
                this.createNotificationError({
                    message: this.$t('kommandhub-sms.testMessage.errorUnexpected'),
                });
            } finally {
                this.isSending = false;
            }
        },

        /**
         * Turns the service's reason code into merchant-facing wording, and
         * appends the provider's own words when it gave any.
         *
         * An unrecognised reason still produces a usable message rather than a
         * blank notification — reason codes are added server-side and the
         * administration bundle may be older than the plugin.
         */
        describeFailure(result) {
            const known = [
                'templateNotFound',
                'templateEmpty',
                'renderFailed',
                'invalidRecipient',
                'providerRejected',
                'providerUnavailable',
                'noProviderConfigured',
                'missingRecipient',
            ];

            const key = known.includes(result.reason) ? result.reason : 'errorUnexpected';
            let message = this.$t(`kommandhub-sms.testMessage.error.${key}`);

            if (result.detail) {
                message += ` (${result.detail})`;
            }

            return message;
        },
    },
});
