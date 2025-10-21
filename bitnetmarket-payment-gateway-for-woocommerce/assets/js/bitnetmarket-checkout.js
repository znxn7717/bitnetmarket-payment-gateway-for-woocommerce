const bitnetmarket_settings = window.wc.wcSettings.getSetting("bitnetmarket_data", {});
const bitnetmarket_label = window.wp.htmlEntities.decodeEntities(bitnetmarket_settings.title) || window.wp.i18n.__("Bitnetmarket", "bitnetmarket-payment-gateway-for-woocommerce");

const Bitnetmarket_icon = Object(window.wp.element.createElement)("img", {
    src: bitnetmarket_settings.icon,
    alt: window.wp.htmlEntities.decodeEntities(bitnetmarket_settings.title),
    style: { marginLeft: "10px", display: "inline-block" },
});

const bitnetmarket_label_with_icon = window.wp.element.createElement("span", null, [
    Bitnetmarket_icon,
    bitnetmarket_label,
]);

const bitnetmarket_Content = () => {
    return window.wp.htmlEntities.decodeEntities(
        bitnetmarket_settings.description || window.wp.i18n.__("پرداخت امن از طریق بیت‌نت‌مارکت", "bitnetmarket-payment-gateway-for-woocommerce")
    );
};

const Bitnetmarket_Block_Gateway = {
    name: "bitnetmarket",
    label: bitnetmarket_label_with_icon,
    content: Object(window.wp.element.createElement)(bitnetmarket_Content, null),
    edit: Object(window.wp.element.createElement)(bitnetmarket_Content, null),
    canMakePayment: () => true,
    ariaLabel: bitnetmarket_label,
    supports: {
        features: bitnetmarket_settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Bitnetmarket_Block_Gateway);