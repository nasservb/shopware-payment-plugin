const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class PayeverPaymentService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'payever') {
        super(httpClient, loginService, apiEndpoint);
    }

    getSyncPath() {
        return `_action/${this.getApiBasePath()}/synchronization`;
    }
    synchronize() {
        return this.httpClient
            .get(
                this.getSyncPath(),
                {
                    headers: this.getBasicHeaders()
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
    getAllowedActions(transactionId, salesChannelId) {
        const apiRoute = `_action/${this.getApiBasePath()}/get-allowed-actions`;

        return this.httpClient.post(
            apiRoute,
            {
                transactionId: transactionId,
                salesChannelId: salesChannelId
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * Get Payment Details.
     *
     * @param paymentId
     * @param salesChannelId
     * @returns {Promise<AxiosResponse<any>>}
     */
    getPaymentDetails(paymentId, salesChannelId) {
        const apiRoute = `_action/${this.getApiBasePath()}/data/get-payment-details`;

        return this.httpClient.post(
            apiRoute,
            {
                paymentId: paymentId,
                salesChannelId: salesChannelId
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * Get Payment Totals.
     *
     * @param orderId
     * @returns {Promise<AxiosResponse<any>>}
     */
    getPaymentTotals(orderId) {
        const apiRoute = `_action/${this.getApiBasePath()}/data/get-payment-totals`;

        return this.httpClient.post(
            apiRoute,
            {
                orderId: orderId
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * Get Order Items.
     *
     * @param orderId
     * @returns {Promise<AxiosResponse<any>>}
     */
    getOrderItems(orderId) {
        const apiRoute = `_action/${this.getApiBasePath()}/data/get-order-items`;

        return this.httpClient.post(
            apiRoute,
            {
                orderId: orderId
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    cancelPayment(transaction) {
        return this.doPaymentAction(transaction, 'cancel');
    }
    shippingPayment(transaction) {
        return this.doPaymentAction(transaction, 'shipping_goods');
    }
    refundPayment(transaction) {
        return this.doPaymentAction(transaction, 'refund');
    }

    doPaymentAction(actionName, params) {
        const apiRoute = `_action/${this.getApiBasePath()}/${actionName}`;

        return this.httpClient.post(
            apiRoute,
            params,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default PayeverPaymentService;

Application.addServiceProvider('PayeverPaymentService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PayeverPaymentService(initContainer.httpClient, container.loginService);
});
