import './page/sms-template-list';
import './page/sms-template-detail';


const { Module } = Shopware;

/**
 * SMS templates: one message body per Shopware mail template type.
 *
 * Registered as a settings item rather than a top-level menu entry — this is
 * shop configuration a merchant visits occasionally, and it belongs beside the
 * mail templates it parallels rather than competing with Orders and Products
 * in the main navigation.
 *
 * `settingsItem.group` only accepts Shopware's own groups ('shop', 'system',
 * 'plugins'); 'plugins' is where third-party settings live, and the shared
 * "Notifications" grouping comes from both modules using the same group and a
 * common label prefix.
 */
Module.register('kommandhub-sms-template', {
    type: 'plugin',
    name: 'kommandhub-sms-template',
    title: 'kommandhub-sms-template.general.mainMenuItemGeneral',
    description: 'kommandhub-sms-template.general.descriptionTextModule',
    color: '#37d046',
    icon: 'regular-comments',
    favicon: 'icon-module-settings.png',
    entity: 'kommandhub_sms_template',

    routes: {
        index: {
            component: 'kommandhub-sms-template-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.plugins',
                privilege: 'sms.manage',
            },
        },
        detail: {
            component: 'kommandhub-sms-template-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'kommandhub.sms.template.index',
                privilege: 'sms.manage',
            },
            props: {
                default: (route) => ({ templateId: route.params.id }),
            },
        },
        create: {
            component: 'kommandhub-sms-template-detail',
            path: 'create',
            meta: {
                parentPath: 'kommandhub.sms.template.index',
                privilege: 'sms.manage',
            },
        },
    },

    settingsItem: [
        {
            group: 'plugins',
            to: 'kommandhub.sms.template.index',
            icon: 'regular-comments',
            name: 'kommandhub-sms-template',
            label: 'kommandhub-sms-template.general.mainMenuItemGeneral',
            privilege: 'sms.manage',
        },
    ],
});
