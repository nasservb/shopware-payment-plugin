const { Component, Mixin } = Shopware;
import template from './sw-products-and-inventory-toggle-subscription.html.twig';

Component.register('sw-products-and-inventory-toggle-subscription', {
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
            clientIdFilled: false,
            clientSecretFilled: false,
            businessIdFilled: false,
            buttonClass: 'sw-products-and-inventory-toggle-subscription-button'
        };
    },
    created() {
        this.loadPayeverConfig()
    },
    methods: {
        loadPayeverConfig() {
            this.systemConfigApiService.getValues(this.domain).then(values => {
                this.clientIdFilled = !!values['PevrPayeverIntegration.config.clientId'];
                this.clientSecretFilled = !!values['PevrPayeverIntegration.config.clientSecret'];
                this.businessIdFilled = !!values['PevrPayeverIntegration.config.businessUuid'];
                this.config = values;
                var productSubscription = document.getElementsByClassName(this.buttonClass)[0];
                if (productSubscription) {
                    if (values['PevrPayeverIntegration.config.isProductsSyncEnabled']) {
                        productSubscription.innerText = this.$tc('payever-plugin-config.productSynchronization.subscriptionDisableTitle');
                    } else {
                        productSubscription.innerText =  this.$tc('payever-plugin-config.productSynchronization.subscriptionEnableTitle');
                    }
                }
            });
        },
        toggleSubscription(e) {
            this.PayeverPluginWrapperService.toggleSubscription()
                .then((response) => {
                    let controlChecked = false;
                    let control = document.getElementsByName('PevrPayeverIntegration.config.isProductsSyncEnabled')[0];
                    if (response.isActive) {
                        controlChecked = true;
                        e.target.innerText = this.$tc('payever-plugin-config.productSynchronization.subscriptionDisableTitle');
                        this.displayProductSynchronizationMessage(this.$tc('payever-plugin-config.productSynchronization.subscriptionEnabledSuccessMessage'));
                        window.location.reload();
                    } else if (response.errors.length > 0) {
                        this.createNotificationError({
                            title: this.$tc('payever-plugin-config.productSynchronization.subscriptionFailedTitle'),
                            message: response.errors.join(',')
                        });
                        window.location.reload();
                    } else {
                        e.target.innerText = this.$tc('payever-plugin-config.productSynchronization.subscriptionEnableTitle');
                        this.displayProductSynchronizationMessage(this.$tc('payever-plugin-config.productSynchronization.subscriptionDisabledSuccessMessage'));
                        window.location.reload();
                    }
                    if (control) {
                        control.checked = controlChecked;
                    }
                }).catch((errorResponse) => {
                    this.createNotificationError({
                        title: errorResponse.name,
                        message: errorResponse.message
                    });
                })
        },
        displayProductSynchronizationMessage(displayMessage) {
            this.createNotificationSuccess({
                title: this.$tc('payever-plugin-config.productSynchronization.title'),
                message: displayMessage
            });
        }
    }
});
