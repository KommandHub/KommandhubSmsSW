const ApiService = Shopware.Classes.ApiService;

/**
 * Client for the plugin's test-message endpoint.
 *
 *
 * Provider failures come back as HTTP 200 with `success: false`, so this
 * service resolves rather than rejects for them; only transport, auth and
 * malformed-request errors reject.
 */
export default class TestMessageApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'kommandhub-sms') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'testMessageApiService';
    }

    /**
     * @param {string} templateId
     * @param {string} recipient raw as typed; the server normalises and validates it
     * @param {string|null} salesChannelId
     * @returns {Promise<{success: boolean, reason: ?string, detail: ?string, messageId: ?string, renderedBody: ?string}>}
     */
    send(templateId, recipient, salesChannelId = null) {
        return this.httpClient
            .post(
                `/_action/kommandhub-sms/sms-template/${templateId}/test-message`,
                { recipient, salesChannelId },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => ApiService.handleResponse(response));
    }
}
