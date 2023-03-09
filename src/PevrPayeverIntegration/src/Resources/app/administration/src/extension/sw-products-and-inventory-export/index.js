const { Component, Mixin } = Shopware;
import template from './sw-products-and-inventory-export.html.twig';

Component.register('sw-products-and-inventory-export', {
    template,
    inject: ['PayeverPluginWrapperService', 'systemConfigApiService'],
    mixins: [
        Mixin.getByName('notification')
    ],
    data() {
        const domain = `${this.$route.params.namespace}.config`;
        return {
            isLoading: false,
            namespace: this.$route.params.namespace,
            domain: domain,
            salesChannelId: null,
            config: {},
            actualConfigData: {},
            isProductsSyncEnabledFilled: false,
            isProductsOutwardSyncEnabledFilled: false
        };
    },
    created() {
        this.loadPayeverConfig()
    },
    methods: {
        loadPayeverConfig() {
            this.systemConfigApiService.getValues(this.domain).then(values => {
                this.isProductsSyncEnabledFilled = !!values['PevrPayeverIntegration.config.isProductsSyncEnabled'];
                this.isProductsOutwardSyncEnabledFilled = !!values['PevrPayeverIntegration.config.isProductsOutwardSyncEnabled'];
                this.config = values;
            });
        },
        enqueueProductsAndInventoryExport(e) {
            this.isLoading = true;
            this.PayeverPluginWrapperService.enqueueProductsAndInventoryExport()
                .then((response) => {
                    if (response.success) {
                        this.displayProductSynchronizationMessage(
                            this.$tc('payever-plugin-config.productSynchronization.exportSuccessMessage')
                        );
                    } else {
                        this.createNotificationError({
                            message: response.errors && response.errors.length > 0
                                ? response.errors.join(', ')
                                : 'Unknown error'
                        });
                    }
                }).catch((errorResponse) => {
                    this.createNotificationError({
                        title: errorResponse.name,
                        message: errorResponse.message
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },
        displayProductSynchronizationMessage(displayMessage) {
            this.createNotificationSuccess({
                title: this.$tc('payever-plugin-config.productSynchronization.title'),
                message: displayMessage
            });
        }
    }
});
