const { Component, Mixin } = Shopware;
import template from './sw-plugin-config.html.twig';
Component.override('sw-plugin-config', {
    template,

    inject: ['PayeverPaymentLegacyService', 'PayeverPluginLegacyService', 'systemConfigApiService'],

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
            businessIdFilled: false
        };
    },
    created() {
        this.checkApiKeys();
        this.notify();
    },

    methods: {
        notify() {
            this.PayeverPluginLegacyService.getNotifications()
                .then((response) => {
                    response.forEach((notificaion) => {
                        this.createNotificationInfo({
                            title: notificaion.title,
                            message: notificaion.message
                        });
                    });
                });
        },
        checkApiKeys() {
            this.systemConfigApiService.getValues(this.domain).then(values => {
                this.clientIdFilled = !!values['PevrPayeverIntegration.config.clientId'];
                this.clientSecretFilled = !!values['PevrPayeverIntegration.config.clientSecret'];
                this.businessIdFilled = !!values['PevrPayeverIntegration.config.businessUuid'];

                this.config = values;
            });
        },
        support(e) {
               window.zESettings = { analytics: false };
               var s = document.createElement('script');
               s.src = 'https://static.zdassets.com/ekr/snippet.js?key=775ae07f-08ee-400e-b421-c190d7836142';
               s.id = 'ze-snippet';
               s.onload = function () {
                    window['zE'] && window['zE']('webWidget', 'open');
                    window['zE'] && window['zE']('webWidget:on', 'open', function() { e.target.innerText = 'Need help? Chat with us!'; });
               };
               document.head.appendChild(s);

               e.target.innerText = 'Loading chat...';
               e.preventDefault();

               return false;
        },
        onSync() {
            this.isLoading = true;
            this.PayeverPaymentLegacyService.synchronize()
                .then((response) => {
                    if (response.synchronizationValid) {
                        this.createNotificationSuccess({
                            title: this.$tc('payever-plugin-config.synchronize.successTitle'),
                            message: this.$tc('payever-plugin-config.synchronize.successMessage')
                        });

                        response.noticeMessages.forEach((message) => {
                            this.createNotificationInfo({
                                title: this.$tc('payever-plugin-config.synchronize.noticeTitle'),
                                message: message
                            });
                        });
                    } else {
                        let errorMessage = response.errorMessage;
                        if (401 === response.code) {
                            errorMessage = this.$tc('payever-plugin-config.synchronize.wrongCredentials');
                        }
                        this.createNotificationError({
                            title: this.$tc('payever-plugin-config.synchronize.errorTitle'),
                            message: errorMessage
                        });
                    }
                })
                .catch((errorResponse) => {
                    this.createNotificationError({
                        title: errorResponse.name,
                        message: errorResponse.message
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
        setSandboxApiKeys() {
            this.isLoading = true;
            this.config['PevrPayeverIntegration.config.isSandbox'] = true;
            this.config['PevrPayeverIntegration.config.clientId'] = '1454_2ax8i5chkvggc8w00g8g4sk80ckswkw0c8k8scss40o40ok4sk';
            this.config['PevrPayeverIntegration.config.clientSecret'] = '22uvxi05qlgk0wo8ws8s44wo8ccg48kwogoogsog4kg4s8k8k';
            this.config['PevrPayeverIntegration.config.businessUuid'] = 'payever';
            this.systemConfigApiService.saveValues(this.config).then((response) => {
                this.createNotificationSuccess({
                    title: this.$tc('payever-plugin-config.setApi.successTitle'),
                    message: this.$tc('payever-plugin-config.setApi.successMessage')
                });
            }).catch((errorResponse) => {
                this.createNotificationError({
                    title: errorResponse.name,
                    message: errorResponse.message
                });
            }).finally(() => {
                this.checkApiKeys();
                this.isLoading = false;
                window.location.reload();
            });
        },
        downloadLog(e) {
            this.PayeverPluginLegacyService.openLog()
                .then((response) => {
                    if (response.data) {
                        let fileName = 'payever.log';
                        if (response.headers && response.headers['content-disposition']) {
                            let search = 'filename=';
                            let pos = response.headers['content-disposition'].indexOf(search);
                            if (pos !== -1) {
                                fileName = response.headers['content-disposition'].substr(pos + search.length);
                            }
                        }
                        let blobData = new Blob([response.data]);
                        let link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blobData);
                        link.download = fileName;
                        link.click();
                    }
                }).catch((errorResponse) => {
                    this.createNotificationError({
                        title: errorResponse.name,
                        message: errorResponse.message
                    });
            });
        }
    }
});
