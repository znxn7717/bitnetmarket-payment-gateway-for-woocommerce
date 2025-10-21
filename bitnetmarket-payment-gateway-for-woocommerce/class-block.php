<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Bitnetmarket_Gateway_Blocks extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'bitnetmarket';

    public function initialize() {
        $this->settings = get_option('woocommerce_bitnetmarket_settings', []);
        $this->gateway = new WC_Gateway_Bitnetmarket();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'bitnetmarket_gateway-blocks-integration',
            WOO_BITNETMARKET_URL . 'assets/js/bitnetmarket-checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            '1.0.0',  // version
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('bitnetmarket_gateway-blocks-integration');
        }
        return ['bitnetmarket_gateway-blocks-integration'];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description ?? __('پرداخت از طریق بیت‌نت‌مارکت', 'bitnetmarket-payment-gateway-for-woocommerce'),
            'icon' => WOO_BITNETMARKET_URL . 'assets/images/icon.png',
        ];
    }
}