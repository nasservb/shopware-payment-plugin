const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class PayeverPaymentWrapperService extends ApiService {
    constructor(
        httpClient,
        loginService,
        apiEndpoint = 'payever',
        PayeverPaymentService,
        PayeverPaymentLegacyService,
        PayeverVersionService
    ) {
        super(httpClient, loginService, apiEndpoint);
        this.payeverPaymentService = PayeverPaymentService;
        this.payeverPaymentLegacyService = PayeverPaymentLegacyService;
        this.payeverVersionService = PayeverVersionService;
    }

    getAllowedActions(transactionId, salesChannelId) {
        let paymentService = this.payeverPaymentService;
        if (this.payeverVersionService.isLegacy()) {
            paymentService = this.payeverPaymentLegacyService;
        }

        return paymentService.getAllowedActions(transactionId, salesChannelId);
    }

    /**
     * Get Payment Details.
     *
     * @param paymentId
     * @param salesChannelId
     * @returns {Promise<AxiosResponse<any>>}
     */
    getPaymentDetails(paymentId, salesChannelId) {
        let paymentService = this.payeverPaymentService;
        if (this.payeverVersionService.isLegacy()) {
            paymentService = this.payeverPaymentLegacyService;
        }

        return paymentService.getPaymentDetails(paymentId, salesChannelId);
    }

    /**
     * Get Payment Totals.
     *
     * @param orderId
     * @returns {Promise<AxiosResponse<any>>}
     */
    getPaymentTotals(orderId) {
        let paymentService = this.payeverPaymentService;
        if (this.payeverVersionService.isLegacy()) {
            paymentService = this.payeverPaymentLegacyService;
        }

        return paymentService.getPaymentTotals(orderId);
    }

    /**
     * Get Order Items.
     *
     * @param orderId
     * @returns {Promise<AxiosResponse<any>>}
     */
    getOrderItems(orderId) {
        let paymentService = this.payeverPaymentService;
        if (this.payeverVersionService.isLegacy()) {
            paymentService = this.payeverPaymentLegacyService;
        }

        return paymentService.getOrderItems(orderId);
    }

    doPaymentAction(actionName, params) {
        let paymentService = this.payeverPaymentService;
        if (this.payeverVersionService.isLegacy()) {
            paymentService = this.payeverPaymentLegacyService;
        }

        return paymentService.doPaymentAction(actionName, params);
    }
}

export default PayeverPaymentWrapperService;

Application.addServiceProvider('PayeverPaymentWrapperService', (container) => {
    const initContainer = Application.getContainer('init');
    const nestedContainer = Application.getContainer('nested');

    return new PayeverPaymentWrapperService(
        initContainer.httpClient,
        container.loginService,
        'payever',
        nestedContainer.service.PayeverPaymentService,
        nestedContainer.service.PayeverPaymentLegacyService,
        nestedContainer.service.PayeverVersionService
    );
});
