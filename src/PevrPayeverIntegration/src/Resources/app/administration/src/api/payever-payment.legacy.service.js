const { Application } = Shopware;
import PayeverPaymentService from './payever-payment.service';

class PayeverPaymentLegacyService extends PayeverPaymentService {
    getSyncPath() {
        return `${this.getApiBasePath()}/synchronization`;
    }
}

export default PayeverPaymentLegacyService;

Application.addServiceProvider('PayeverPaymentLegacyService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PayeverPaymentLegacyService(initContainer.httpClient, container.loginService);
});

