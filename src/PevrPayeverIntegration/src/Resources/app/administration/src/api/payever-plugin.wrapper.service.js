const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class PayeverPluginWrapperService extends ApiService {
    constructor(
        httpClient,
        loginService,
        apiEndpoint = 'payever',
        PayeverPluginService,
        PayeverPluginLegacyService,
        PayeverVersionService
    ) {
        super(httpClient, loginService, apiEndpoint);
        this.payeverPluginService = PayeverPluginService;
        this.payeverPluginLegacyService = PayeverPluginLegacyService;
        this.payeverVersionService = PayeverVersionService;
    }

    enqueueProductsAndInventoryExport() {
        let pluginService = this.payeverPluginService;
        if (this.payeverVersionService.isLegacy()) {
            pluginService = this.payeverPluginLegacyService;
        }

        return pluginService.enqueueProductsAndInventoryExport();
    }
    toggleSubscription() {
        let pluginService = this.payeverPluginService;
        if (this.payeverVersionService.isLegacy()) {
            pluginService = this.payeverPluginLegacyService;
        }

        return pluginService.toggleSubscription();
    }
}

export default PayeverPluginWrapperService;

Application.addServiceProvider('PayeverPluginWrapperService', (container) => {
    const initContainer = Application.getContainer('init');
    const nestedContainer = Application.getContainer('nested');

    return new PayeverPluginWrapperService(
        initContainer.httpClient,
        container.loginService,
        'payever',
        nestedContainer.service.PayeverPluginService,
        nestedContainer.service.PayeverPluginLegacyService,
        nestedContainer.service.PayeverVersionService
    );
});
