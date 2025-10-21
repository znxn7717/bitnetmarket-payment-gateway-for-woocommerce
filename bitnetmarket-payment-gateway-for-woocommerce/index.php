<?php
/*
Plugin Name: BitnetMarket Payment Gateway for WooCommerce
Plugin URI: https://github.com/znxn7717/bitnetmarket-payment-gateway-for-woocommerce
Description: درگاه پرداخت بیت‌نت‌مارکت امکان پذیرش رمزارزها را به سادگی و امنیت کامل به فروشگاه شما اضافه می‌کند. روش پرداخت با رمزارزهای مختلف را در سایت خود فعال کرده و تجربه خریدی مدرن، سریع و بدون مرز برای مشتریان‌تان فراهم کنید. راه‌اندازی آنی، تراکنش‌های جهانی، آزادی مالی.
Version: 1.0.0
Author: BitnetMarket
Author URI: https://github.com/znxn7717/bitnetmarket-payment-gateway-for-woocommerce
Requires at least: 5.0
Tested up to: 6.8
WC requires at least: 6.0
WC tested up to: 9.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
*/

if (!defined('ABSPATH')) {
    exit;
}

define('WOO_BITNETMARKET_DIR', plugin_dir_path(__FILE__));
define('WOO_BITNETMARKET_URL', plugin_dir_url(__FILE__));

function load_bitnetmarket_woo_gateway() {
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_bitnetmarket_gateway');
    function woocommerce_add_bitnetmarket_gateway($methods) {
        $methods[] = 'WC_Gateway_Bitnetmarket';
        return $methods;
    }
    require_once(WOO_BITNETMARKET_DIR . 'class-wc-gateway-bitnetmarket.php');
}
add_action('plugins_loaded', 'load_bitnetmarket_woo_gateway', 0);

function declare_bitnetmarket_cart_checkout_blocks_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'declare_bitnetmarket_cart_checkout_blocks_compatibility');

add_action('woocommerce_blocks_loaded', 'bitnetmarket_register_payment_method_type');
function bitnetmarket_register_payment_method_type() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }
    require_once WOO_BITNETMARKET_DIR . 'class-block.php';
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new Bitnetmarket_Gateway_Blocks());
        }
    );
}

// نمایش پیام عدم نصب WooCommerce
add_action('admin_notices', 'bitnetmarket_woocommerce_missing_notice');
function bitnetmarket_woocommerce_missing_notice() {
    if (!class_exists('WC_Payment_Gateway')) {
        echo '<div class="error notice"><p>' . esc_html(__('افزونه "درگاه پرداخت بیت‌نت‌مارکت برای ووکامرس" نیاز به نصب و فعال بودن ووکامرس دارد', 'bitnetmarket-payment-gateway-for-woocommerce')) . '</p></div>';
    }
}