const PluginManager = window.PluginManager;

// Lazily imported so the bundle only loads on pages that render a phone field.
PluginManager.register(
    'KommandhubDialCodePhone',
    () => import('./dial-code-phone/dial-code-phone.plugin'),
    '[data-kommandhub-dial-code-phone]'
);
