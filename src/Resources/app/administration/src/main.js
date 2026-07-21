import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';
import frFR from './snippet/fr-FR.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('fr-FR', frFR);

import './acl';

import './init/flow-builder.init';

import './component/kommandhub-test-message-modal';
import './component/sw-flow-kommandhub-send-sms-modal';

import './module/sms-template';

import TestMessageApiService from './service/test-message.api.service';

// Registered once for the whole plugin: every channel's detail page injects the
// same service rather than each module defining its own.
Shopware.Application.addServiceProvider('testMessageApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');

    return new TestMessageApiService(initContainer.httpClient, container.loginService);
});
