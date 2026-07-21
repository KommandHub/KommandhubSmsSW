/**
 * Registers a dedicated admin permission so privileged actions from this plugin
 * can be granted to roles independently of the broad built-in roles.
 *
 * Appears under Settings > Users & permissions > Roles > Additional
 * permissions. The composite identifier `sms.manage` is what both the
 * client-side action and the server-side route `_acl` must check against —
 * a client-only check is decoration, not a permission.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'notifications',
    roles: {
        manage: {
            // Dependencies pull in the built-in roles whose read/update
            // privileges the action needs; `privileges` stays empty so this is
            // a pure gate for the action itself.
            privileges: [],
            dependencies: [],
        },
    },
});
