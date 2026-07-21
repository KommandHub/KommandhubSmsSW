import template from './sw-flow-kommandhub-send-sms-modal.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { ShopwareError } = Shopware.Classes;

/**
 * Configuration modal for the Send SMS flow action.
 *
 * The component name is not free to choose: the flow builder derives it from
 * the action name (`action.kommandhub.send.sms` ->
 * `sw-flow-kommandhub-send-sms-modal`), so renaming either side silently breaks
 * the modal open.
 *
 * The saved config is `{ templateId }`, which is exactly what
 * SendSmsAction::handleFlow() reads on the PHP side.
 */
Component.register('sw-flow-kommandhub-send-sms-modal', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    emits: ['process-finish', 'modal-close'],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            templateId: null,
            templateError: null,
        };
    },

    computed: {
        /**
         * Inactive templates are selectable on purpose: wiring a flow before
         * switching the template on is the normal order of work, and the action
         * skips inactive templates at send time with a log line.
         */
        templateCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },
    },

    watch: {
        templateId(value) {
            if (value && this.templateError) {
                this.templateError = null;
            }
        },
    },

    created() {
        this.templateId = this.sequence?.config?.templateId ?? null;
    },

    methods: {
        onSave() {
            if (!this.templateId) {
                this.templateError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            this.$emit('process-finish', {
                ...this.sequence,
                config: { templateId: this.templateId },
            });
            this.onClose();
        },

        onClose() {
            this.templateError = null;
            this.$emit('modal-close');
        },
    },
});
