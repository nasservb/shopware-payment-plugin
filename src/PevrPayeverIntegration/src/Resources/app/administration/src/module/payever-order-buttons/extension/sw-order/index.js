const { Component, Mixin, State  } = Shopware;
const { Criteria } = Shopware.Data;
import template from './sw-order.html.twig';
import './sw-order.scss';

Component.override('sw-order-detail-base', {
    template,

    inject: ['PayeverPaymentWrapperService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    watch: {
        orderStateChange(){
            if (!this.isActionLoading) {
                this.isActionLoading = true;
                this.updateAllowedActions();
            }
        }
    },

    computed: {
        orderStateChange() {
            return this.orderOptions && this.transactionOptions && this.deliveryOptions;
        },
        orderStore() {
            return State.getStore('order');
        },
        orderRepository() {
            return this.repositoryFactory.create('order');
        },
    },

    data() {
        return {
            isActionLoading: false,
            allowedActions: [],
            paymentTotals: {
                amount: 0,
                captured: 0,
                refunded: 0,
                cancelled: 0,
                availableForCapturing: 0,
                availableForCancelling: 0,
                availableForRefunding: 0,
                isManual: false
            },
            orderItems: [],
            input: {
                capture: 0,
                cancel: 0,
                refund: 0
            }
        };
    },

    created() {
        if (!this.isActionLoading) {
            this.isActionLoading = true;
            this.updateAllowedActions();
        }
    },

    methods: {
        updateAllowedActions() {
            const criteria = new Criteria(1, 100);
            criteria.addAssociation('currency');
            criteria.addAssociation('transactions');
            criteria.addAssociation('lineItems');
            criteria.addAssociation('deliveries');

            this.orderRepository.get(this.orderId, Shopware.Context.api, criteria).then((order) => {
                const transaction = order.transactions.first();
                if (!this.isPayeverPayment(transaction)) {
                    this.isActionLoading = false;
                    return;
                }

                const payeverPaymentId = transaction.customFields.payever_transaction_id;

                if (!payeverPaymentId) {
                    this.isActionLoading = false;
                    return;
                }

                let salesChannelId = null;
                if (this.order) {
                    salesChannelId = this.order.salesChannelId;
                }

                if (!salesChannelId && order) {
                    salesChannelId = order.salesChannelId;
                }

                if (!salesChannelId) {
                    this.isActionLoading = false;
                    return;
                }

                // Load allowed actions
                this.PayeverPaymentWrapperService.getAllowedActions(payeverPaymentId, salesChannelId)
                    .then((response) => {
                        this.allowedActions = response;
                        this.isActionLoading = false;
                    })
                    .catch(() => {
                        this.isActionLoading = false;
                    });

                // Load payment totals
                this.PayeverPaymentWrapperService.getPaymentTotals(this.orderId)
                    .then((response) => {
                        // Round totals
                        for (const [key, value] of Object.entries(response)) {
                            if (key !== 'isManual') {
                                response[key] = parseFloat(value.toFixed(2));
                            }
                        }

                        this.input.capture = response.availableForCapturing;
                        this.input.cancel = response.availableForCancelling;
                        this.input.refund = response.availableForRefunding;

                        this.paymentTotals = response;
                        this.isActionLoading = false;
                    })
                    .catch(() => {
                        this.isActionLoading = false;
                    });

                // Load order items
                this.PayeverPaymentWrapperService.getOrderItems(this.orderId)
                    .then((response) => {
                        let label = this.$tc(`payever-order-buttons.shippingMethod`);
                        response.map(function(orderItem) {
                            orderItem.checked = true;
                            if (orderItem.item_type === 'shipping') {
                                orderItem.label = label + ' (' + orderItem.label + ')';
                            }

                            // add input
                            orderItem.input = {
                                can_be_captured: orderItem.can_be_captured,
                                can_be_cancelled: orderItem.can_be_cancelled,
                                can_be_refunded: orderItem.can_be_refunded
                            };

                            return orderItem;
                        });

                        this.orderItems = response;
                        this.isActionLoading = false;
                    })
                    .catch(() => {
                        this.isActionLoading = false;
                    });
            });
        },

        isPayeverPayment(transaction) {
            if (!transaction.customFields) {
                return false;
            }

            return transaction.customFields.payever_transaction_id;
        },

        isActionPossible(action) {
            return this.allowedActions && this.allowedActions[action];
        },

        /**
         * Check of order items qtys are available.
         *
         * @returns {boolean}
         */
        isAvailable() {
            let result = false;
            this.orderItems.map(function(orderItem) {
                if (orderItem.quantity > 0) {
                    result = true;
                }
            });

            return result;
        },

        /**
         * Check of order items are available for refunding.
         * @returns {boolean}
         */
        isRefundItemAvailable() {
            let result = false;
            this.orderItems.map(function(orderItem) {
                if (orderItem.quantity > 0 && orderItem.can_be_refunded > 0) {
                    result = true;
                }
            });

            return result;
        },

        /**
         * Check of order items are available for shipping.
         * @returns {boolean}
         */
        isShippingGoodsAvailable() {
            let result = false;
            this.orderItems.map(function(orderItem) {
                if (orderItem.quantity > 0 && orderItem.can_be_captured > 0) {
                    result = true;
                }
            });

            return result;
        },

        /**
         * Check of order items are available for cancelling.
         * @returns {boolean}
         */
        isCancelItemAvailable() {
            let result = false;
            this.orderItems.map(function(orderItem) {
                if (orderItem.quantity > 0 && orderItem.can_be_cancelled > 0) {
                    result = true;
                }
            });

            return result;
        },

        isShippingGoodsAmountAction(action) {
            return this.isAvailable() && action === 'shipping_goods';
        },

        isRefundAmountAction(action) {
            return this.isAvailable() && action === 'refund';
        },

        isCancelAction(action) {
            return this.isAvailable() && action === 'cancel';
        },

        isRefundItemAction(action) {
            return this.isAvailable() && action === 'refundItem';
        },

        isCancelItemAction(action) {
            return this.isAvailable() && action === 'cancelItem';
        },

        isShippingGoodsAction(action) {
            return this.isAvailable() && action === 'shippingGoods';
        },

        /**
         * Checks if manual action was applied.
         *
         * @returns {boolean}
         */
        isManualActionApplied() {
            return this.paymentTotals.isManual;
        },

        /**
         * Get Available amount for capturing
         * @returns float
         */
        getAvailableAmountForCapturing() {
            return this.paymentTotals.availableForCapturing;
        },

        /**
         * Get Available amount for cancelling
         * @returns float
         */
        getAvailableAmountForCancelling() {
            return this.paymentTotals.availableForCancelling;
        },

        /**
         * Get Available amount for refunding
         * @returns float
         */
        getAvailableAmountForRefunding() {
            return this.paymentTotals.availableForRefunding;
        },

        /**
         * Do Transaction Action.
         *
         * @param order
         * @param transaction
         * @param action
         */
        doTransactionAction(order, transaction, action) {
            if (!this.isPayeverPayment(transaction)) {
                return;
            }

            this.isActionLoading = true;
            let params = {
                transaction: transaction.id
            };

            // Shipping goods action
            if (this.isShippingGoodsAction(action)) {
                let i = 0, shippingItemsArray = {};
                this.$refs.itemIdCapture.forEach((item) => {
                    if (this.$refs.checkboxCapture[i].value) {
                        shippingItemsArray[item.value] = this.$refs.quantityCapture[i].value;
                    }
                    i++;
                });

                params.items = shippingItemsArray;
            }

            // Refunds Items action
            if (this.isRefundItemAction(action)) {
                let i = 0, refundItemsArray = {};

                this.$refs.itemIdRefund.forEach((item) => {
                    if (this.$refs.checkboxRefund[i].value) {
                        refundItemsArray[item.value] = this.$refs.quantityRefund[i].value;
                    }
                    i++;
                });

                params.items = refundItemsArray;
            }

            // Cancel Items action
            if (this.isCancelItemAction(action)) {
                let i = 0, cancelItemsArray = {};

                this.$refs.itemIdCancel.forEach((item) => {
                    if (this.$refs.checkboxCancel[i].value) {
                        cancelItemsArray[item.value] = this.$refs.quantityCancel[i].value;
                    }
                    i++;
                });

                params.items = cancelItemsArray;
            }

            // Shipping goods (amount) action
            if (this.isShippingGoodsAmountAction(action)) {
                let captureAmount = 0;
                if (this.$refs.hasOwnProperty('payeverCapture')) {
                    // Partial capture
                    captureAmount = this.$refs.payeverCapture[0].value;
                    if (!captureAmount || (typeof captureAmount === 'string' && captureAmount.length === 0)) {
                        // Use whole amount
                        captureAmount = this.getAvailableAmountForCapturing();
                    }

                    if (typeof captureAmount === 'string' || captureAmount instanceof String) {
                        captureAmount = captureAmount.replace(',', '.');
                    }

                    // Verify amount
                    if (isNaN(captureAmount) || captureAmount > order.amountTotal || captureAmount > this.getAvailableAmountForCapturing()) {
                        this.createNotificationError({
                            title: this.$tc(`payever-order-buttons.amountErrorTitle`),
                            message: this.$tc(`payever-order-buttons.amountErrorMessage`),
                        });

                        this.isActionLoading = false;

                        return;
                    }
                }

                params.amount = captureAmount;
            }

            // Refund amount
            if (this.isRefundAmountAction(action)) {
                let refundAmount = 0;
                if (this.$refs.hasOwnProperty('payeverRefund')) {
                    // Partial refund
                    refundAmount = this.$refs.payeverRefund[0].value;
                    if (!refundAmount || (typeof refundAmount === 'string' && refundAmount.length === 0)) {
                        // Use whole amount
                        refundAmount = this.getAvailableAmountForRefunding();
                    }

                    if (typeof refundAmount === 'string' || refundAmount instanceof String) {
                        refundAmount = refundAmount.replace(',', '.');
                    }

                    // Verify amount
                    if (isNaN(refundAmount) || refundAmount > order.amountTotal || refundAmount > this.getAvailableAmountForRefunding()) {
                        this.createNotificationError({
                            title: this.$tc(`payever-order-buttons.amountErrorTitle`),
                            message: this.$tc(`payever-order-buttons.amountErrorMessage`),
                        });

                        this.isActionLoading = false;

                        return;
                    }
                }

                params.amount = refundAmount;
            }

            // Cancel amount
            if (this.isCancelAction(action)) {
                let cancelAmount = 0;
                if (this.$refs.hasOwnProperty('payeverCancel')) {
                    // Partial cancel
                    cancelAmount = this.$refs.payeverCancel[0].value;
                    if (!cancelAmount || (typeof cancelAmount === 'string' && cancelAmount.length === 0)) {
                        // Use whole amount
                        cancelAmount = this.getAvailableAmountForCancelling();
                    }

                    if (typeof cancelAmount === 'string' || cancelAmount instanceof String) {
                        cancelAmount = cancelAmount.replace(',', '.');
                    }

                    // Verify amount
                    if (isNaN(cancelAmount) || cancelAmount > order.amountTotal || cancelAmount > this.getAvailableAmountForCancelling()) {
                        this.createNotificationError({
                            title: this.$tc(`payever-order-buttons.amountErrorTitle`),
                            message: this.$tc(`payever-order-buttons.amountErrorMessage`),
                        });

                        this.isActionLoading = false;

                        return;
                    }
                }

                params.amount = cancelAmount;
            }

            var self = this;
            this.PayeverPaymentWrapperService.doPaymentAction(action, params)
                .then(() => {
                    self.isActionLoading = false;

                    this.createNotificationSuccess({
                        title: this.$tc(`payever-order-buttons.${action}.successTitle`),
                        message: this.$tc(`payever-order-buttons.${action}.successMessage`)
                    });

                    setTimeout(function () {
                        location.reload();
                    }, 5000);

                    this.updateAllowedActions();
                })
                .catch((error) => {
                    self.isActionLoading = false;

                    if (error.response) {
                        // The request was made and the server responded with a status code
                        // that falls out of the range of 2xx
                        this.createNotificationError({
                            title: this.$tc(`payever-order-buttons.${action}.errorTitle`),
                            message: error.response.data.message
                        });
                    } else if (error.request) {
                        // The request was made but no response was received
                        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
                        // http.ClientRequest in node.js
                        console.warn(error.request);
                    } else {
                        // Something happened in setting up the request that triggered an Error
                        console.warn(error.message);
                        this.createNotificationError({
                            title: this.$tc(`payever-order-buttons.${action}.errorTitle`),
                            message: this.$tc(`payever-order-buttons.${action}.errorTitle`)
                        });
                    }
                });
        },

        hasPayeverPayment(order) {
            const me = this;
            let isPayever = false;

            if (!order.transactions) {
                return false;
            }

            order.transactions.map(function(transaction) {
                if (me.isPayeverPayment(transaction)) {
                    isPayever = true;
                }
            });
            return isPayever;
        },
    }
});
