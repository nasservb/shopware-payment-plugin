const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class PayeverVersionService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'payever') {
        super(httpClient, loginService, apiEndpoint);
    }

    isLegacy() {
        let result = false;
        const version = Shopware.Context.app.config.version;
        const matches = version.match(/(\d+)\.(\d+)\.(\d+)/i);
        if (matches[2] && parseInt(matches[2]) < 4) {
            result = true;
        }

        return result;
    }
}

export default PayeverVersionService;

Application.addServiceProvider('PayeverVersionService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PayeverVersionService(initContainer.httpClient, container.loginService);
});
