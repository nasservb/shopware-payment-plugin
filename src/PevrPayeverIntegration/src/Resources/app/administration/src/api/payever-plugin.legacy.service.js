const { Application } = Shopware;
import PayeverPluginService from './payever-plugin.service';

class PayeverPluginLegacyService extends PayeverPluginService {
    getNotificationsPath() {
        return `${this.getApiBasePath()}/plugin/notifications`;
    }
    getSubscriptionPath() {
        return `${this.getApiBasePath()}/products-and-inventory/toggle-subscription`;
    }
    getExportPath() {
        return `${this.getApiBasePath()}/products-and-inventory/export`;
    }
    getLogPath() {
        return `${this.getApiBasePath()}/download-payever-log`;
    }
}

export default PayeverPluginLegacyService;

Application.addServiceProvider('PayeverPluginLegacyService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PayeverPluginLegacyService(initContainer.httpClient, container.loginService);
});
