import template from './sw-sales-channel-detail.html.twig';

const { Component } = Shopware;

/**
 * @package buyers-experience
 */
Component.override('sw-sales-channel-detail', {
    template,

    methods: {
        getLoadSalesChannelCriteria() {
            const criteria = this.$super('getLoadSalesChannelCriteria');

            criteria.addAssociation('themes');

            return criteria;
        }
    }
});
