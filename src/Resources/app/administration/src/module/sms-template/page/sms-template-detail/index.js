import template from './sms-template-detail.html.twig';
import './sms-template-detail.scss';
import { measure } from '../../util/sms-segments';

const { Component, Mixin, Data } = Shopware;
const { Criteria } = Data;

Component.register('kommandhub-sms-template-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    props: {
        templateId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            smsTemplate: null,
            isLoading: false,
            isSaveSuccessful: false,
            showTestModal: false,
        };
    },

    metaInfo() {
        return { title: this.$createTitle(this.identifier) };
    },

    computed: {
        identifier() {
            return this.smsTemplate?.name ?? '';
        },

        repository() {
            return this.repositoryFactory.create('kommandhub_sms_template');
        },

        criteria() {
            const criteria = new Criteria();

            // Translations must be loaded explicitly or switching language in
            // the detail view silently edits the fallback and saves it back
            // over the language the merchant thought they were editing.
            criteria.addAssociation('translations');
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        mailTemplateTypeCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        isNew() {
            return this.smsTemplate?._isNew ?? false;
        },

        /**
         * Live measurement of the body, recomputed as the merchant types.
         *
         * Exposed as a whole object rather than three computed properties so
         * the template renders one consistent snapshot.
         */
        segmentInfo() {
            return measure(this.smsTemplate?.content ?? '');
        },

        /**
         * Warn once the message costs more than one SMS — that is the point
         * where a wording change has a real, recurring price.
         */
        segmentVariant() {
            if (this.segmentInfo.segments > 1) {
                return 'warning';
            }

            return 'info';
        },

        allowSave() {
            return this.acl.can('sms.manage');
        },

        /**
         * A test send needs a persisted record — the server loads the template
         * by id — and a body to render. Unsaved edits are therefore not what
         * gets sent, which the tooltip says out loud.
         */
        canSendTest() {
            return !!this.templateId
                && !this.isLoading
                && !!this.smsTemplate?.content?.trim()
                && this.acl.can('sms.manage');
        },

        tooltipTest() {
            if (!this.acl.can('sms.manage')) {
                return { message: this.$tc('sw-privileges.tooltip.warning'), showOnDisabledElements: true };
            }

            if (!this.templateId) {
                return {
                    message: this.$t('kommandhub-sms-template.detail.tooltipTestUnsaved'),
                    showOnDisabledElements: true,
                };
            }

            if (!this.smsTemplate?.content?.trim()) {
                return {
                    message: this.$t('kommandhub-sms-template.detail.tooltipTestNoContent'),
                    showOnDisabledElements: true,
                };
            }

            return {
                message: this.$t('kommandhub-sms-template.detail.tooltipTestSaved'),
                showOnDisabledElements: true,
            };
        },

        tooltipSave() {
            return {
                message: this.$tc('sw-privileges.tooltip.warning'),
                disabled: this.allowSave,
                showOnDisabledElements: true,
            };
        },
    },

    watch: {
        templateId() {
            this.loadTemplate();
        },
    },

    created() {
        this.loadTemplate();
    },

    methods: {
        loadTemplate() {
            if (!this.templateId) {
                this.smsTemplate = this.repository.create(Shopware.Context.api);

                return;
            }

            this.isLoading = true;

            this.repository
                .get(this.templateId, Shopware.Context.api, this.criteria)
                .then((entity) => {
                    this.smsTemplate = entity;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('kommandhub-sms-template.detail.messageLoadError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        /**
         * Reload on language change so the translated fields come back for the
         * newly selected language rather than keeping the previous one's text.
         */
        onChangeLanguage() {
            this.loadTemplate();
        },

        /**
         * The DAL enforces these too, but its answer is a raw 400 naming a
         * translation id the merchant has never seen. Check here so the message
         * names the field instead.
         *
         * @returns {string|null} snippet key of the first problem, or null
         */
        validate() {
            if (!this.smsTemplate.mailTemplateTypeId) {
                return 'kommandhub-sms-template.detail.messageEventRequired';
            }

            if (!this.smsTemplate.name?.trim()) {
                return 'kommandhub-sms-template.detail.messageNameRequired';
            }

            if (!this.smsTemplate.content?.trim()) {
                return 'kommandhub-sms-template.detail.messageContentRequired';
            }

            return null;
        },

        onSave() {
            const problem = this.validate();

            if (problem !== null) {
                this.createNotificationError({ message: this.$tc(problem) });

                return Promise.resolve();
            }

            this.isLoading = true;
            this.isSaveSuccessful = false;

            return this.repository
                .save(this.smsTemplate, Shopware.Context.api)
                .then(() => {
                    this.isSaveSuccessful = true;

                    this.createNotificationSuccess({
                        message: this.$tc('kommandhub-sms-template.detail.messageSaveSuccess'),
                    });

                    // A newly created record has no route id yet, so replace the
                    // create route with the detail route for the saved entity.
                    if (!this.templateId) {
                        this.$router.push({
                            name: 'kommandhub.sms.template.detail',
                            params: { id: this.smsTemplate.id },
                        });

                        return;
                    }

                    this.loadTemplate();
                })
                .catch((e) => {
                  console.error(e);
                    this.createNotificationError({
                        message: this.$tc('kommandhub-sms-template.detail.messageSaveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onOpenTestModal() {
            this.showTestModal = true;
        },

        onCloseTestModal() {
            this.showTestModal = false;
        },

        onCancel() {
            this.$router.push({ name: 'kommandhub.sms.template.index' });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },
    },
});
