import template from './sms-template-list.html.twig';

const { Component, Mixin, Data } = Shopware;
const { Criteria } = Data;

/**
 * Follows the core sw-mail-template-list shape: the listing mixin owns paging,
 * sorting and the search term, and this component only says what to fetch and
 * what the columns are.
 */
Component.register('kommandhub-sms-template-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data() {
        return {
            templates: null,
            isLoading: false,
            total: 0,
            sortBy: 'updatedAt',
            sortDirection: 'DESC',
        };
    },

    metaInfo() {
        return { title: this.$createTitle() };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('kommandhub_sms_template');
        },

        criteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            // The event is the column a merchant actually scans the list by, so
            // it is fetched with the list rather than resolved per row.
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        columns() {
            return [
                {
                    property: 'mailTemplateType.name',
                    label: 'kommandhub-sms-template.list.columnEvent',
                    routerLink: 'kommandhub.sms.template.detail',
                    primary: true,
                    allowResize: true,
                    // The association is on the joined entity, which the DAL
                    // cannot sort by from the listing.
                    sortable: false,
                },
                {
                    property: 'senderId',
                    label: 'kommandhub-sms-template.list.columnSenderId',
                    allowResize: true,
                },
                {
                    property: 'updatedAt',
                    label: 'kommandhub-sms-template.list.columnUpdatedAt',
                    allowResize: true,
                },
            ];
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            try {
                this.templates = await this.repository.search(this.criteria, Shopware.Context.api);
                this.total = this.templates.total;
            } catch {
                this.createNotificationError({
                    message: this.$tc('kommandhub-sms-template.list.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * The listing emits this after its own delete call, so the only work
         * left is telling the merchant and refetching the page.
         */
        onDeleteSuccess() {
            this.createNotificationSuccess({
                message: this.$tc('kommandhub-sms-template.list.messageDeleteSuccess'),
            });

            this.getList();
        },

        onDeleteError() {
            this.createNotificationError({
                message: this.$tc('kommandhub-sms-template.list.messageDeleteError'),
            });
        },
    },
});
