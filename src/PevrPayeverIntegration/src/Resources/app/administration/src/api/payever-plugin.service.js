const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class PayeverPluginService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'payever') {
        super(httpClient, loginService, apiEndpoint);
    }

    getNotificationsPath() {
        return `_action/${this.getApiBasePath()}/plugin/notifications`;
    }
    getNotifications() {
        return this.httpClient
            .get(
                this.getNotificationsPath(),
                {
                    headers: this.getBasicHeaders()
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
    getSubscriptionPath() {
        return `_action/${this.getApiBasePath()}/products-and-inventory/toggle-subscription`;
    }
    toggleSubscription() {
        return this.httpClient.post(
            this.getSubscriptionPath(),
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
    getExportPath() {
        return `_action/${this.getApiBasePath()}/products-and-inventory/export`;
    }
    enqueueProductsAndInventoryExport() {
        return this.httpClient.post(
            this.getExportPath(),
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
    getLogPath() {
        return `_action/${this.getApiBasePath()}/download-payever-log`;
    }
    openLog() {
        return this.httpClient.get(
            this.getLogPath(),
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return response;
        });
    }
}

export default PayeverPluginService;

Application.addServiceProvider('PayeverPluginService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PayeverPluginService(initContainer.httpClient, container.loginService);
});
