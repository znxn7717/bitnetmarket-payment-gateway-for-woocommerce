<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Gateway_Bitnetmarket')) {
    class WC_Gateway_Bitnetmarket extends WC_Payment_Gateway {

        private $seller_id;
        private $api_url = 'https://api.bitnetmarket.com';
        private $messages = array();
        private $author = 'bitnetmarket.com';

        public function __construct() {
            $this->id = 'bitnetmarket';
            $this->method_title = __('بیت‌نت‌مارکت', 'bitnetmarket-gateway');
            $this->method_description = __('تنظیمات درگاه پرداخت بیت‌نت‌مارکت برای ووکامرس', 'bitnetmarket-gateway');
            $this->icon = apply_filters('woo_bitnetmarket_logo', WOO_BITNETMARKET_URL . 'assets/images/icon.png');
            $this->has_fields = false;
            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->seller_id = $this->settings['seller_id'];
            $this->success_message = $this->settings['success_message'];
            $this->failed_message = $this->settings['failed_message'];

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }
            add_action('woocommerce_receipt_' . $this->id, array($this, 'send_to_bitnetmarket_gateway'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'return_from_bitnetmarket_gateway'));
            add_action('admin_notices', array($this, 'admin_notice_missing_seller_id'));

            $this->messages = array(
                'waiting' => 'در انتظار پرداخت',
                'expired' => 'پرداخت منقضی شده',
                'failed' => 'پرداخت ناموفق',
                'finished' => 'پرداخت موفق',
            );
        }

        public function init_form_fields() {
            $this->form_fields = apply_filters(
                'WC_Bitnetmarket_Config',
                array(
                    'enabled' => array(
                        'title' => __('فعالسازی/غیرفعالسازی', 'bitnetmarket-gateway'),
                        'type' => 'checkbox',
                        'label' => __('فعالسازی درگاه بیت‌نت‌مارکت', 'bitnetmarket-gateway'),
                        'description' => __('برای فعالسازی درگاه پرداخت بیت‌نت‌مارکت باید چک باکس را تیک بزنید', 'bitnetmarket-gateway'),
                        'default' => 'yes',
                        'desc_tip' => true,
                    ),
                    'title' => array(
                        'title' => __('عنوان درگاه', 'bitnetmarket-gateway'),
                        'type' => 'text',
                        'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'bitnetmarket-gateway'),
                        'default' => __('بیت‌نت‌مارکت', 'bitnetmarket-gateway'),
                        'desc_tip' => true,
                    ),
                    'description' => array(
                        'title' => __('توضیحات درگاه', 'bitnetmarket-gateway'),
                        'type' => 'textarea',
                        'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'bitnetmarket-gateway'),
                        'default' => __('پرداخت امن از طریق درگاه بیت‌نت‌مارکت', 'bitnetmarket-gateway'),
                        'desc_tip' => true,
                    ),
                    'seller_id' => array(
                        'title' => __('شناسه فروشنده (Seller ID)', 'bitnetmarket-gateway'),
                        'type' => 'text',
                        'description' => __('شناسه فروشنده از seller.bitnetmarket.com', 'bitnetmarket-gateway'),
                        'default' => '',
                        'desc_tip' => true,
                        'placeholder' => 'مثال: s6nkcg'
                    ),
                    'success_message' => array(
                        'title' => __('پیام پرداخت موفق', 'bitnetmarket-gateway'),
                        'type' => 'textarea',
                        'description' => __('متن پیامی که می‌خواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می‌توانید از شورت کد {uuid} برای نمایش شناسه یکتا استفاده نمایید .', 'bitnetmarket-gateway'),
                        'default' => __('<a href="https://bitnetmarket.com/pay/{uuid}">{uuid}</a>\nبا تشکر از شما. سفارش شما با موفقیت پرداخت شد', 'bitnetmarket-gateway'),
                    ),
                    'failed_message' => array(
                        'title' => __('پیام پرداخت ناموفق', 'bitnetmarket-gateway'),
                        'type' => 'textarea',
                        'description' => __('متن پیامی که می‌خواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می‌توانید از شورت کد {uuid} برای نمایش شناسه یکتا استفاده نمایید .', 'bitnetmarket-gateway'),
                        'default' => __('<a href="https://bitnetmarket.com/pay/{uuid}">{uuid}</a>\nپرداخت شما ناموفق بوده است . لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید', 'bitnetmarket-gateway'),
                    ),
                )
            );
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        public function send_to_bitnetmarket_gateway($order_id) {
            global $woocommerce;
            $woocommerce->session->order_id_bitnetmarket = $order_id;
            $order = wc_get_order($order_id);
            $currency = $order->get_currency();
            $currency = apply_filters('WC_Bitnetmarket_Currency', $currency, $order_id);

            $amount = intval($order->get_total());
            $amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $amount, $currency);

            // Currency handling
            if (
                strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') ||
                strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') ||
                strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') ||
                strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') ||
                strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
            ) {
                $amount = $amount * 10;
            } else if (strtolower($currency) == strtolower('IRHT')) {
                $amount = $amount * 10000;
            } else if (strtolower($currency) == strtolower('IRHR')) {
                $amount = $amount * 1000;
            } else if (strtolower($currency) == strtolower('IRR')) {
                $amount = $amount;
            }

            $amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $amount, $currency);
            $amount = apply_filters('woocommerce_order_amount_total_Bitnetmarket_gateway', $amount, $currency);

            if ($amount <= 0) {
                wc_add_notice(__('مبلغ نامعتبر', 'bitnetmarket-gateway'), 'error');
                return;
            }

            $callback_url = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Gateway_Bitnetmarket'));

            $description = 'خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $description = apply_filters('WC_Bitnetmarket_Description', $description, $order_id);

            $response = $this->send_payment_request($amount, $callback_url);

            if ($response && isset($response['status']) && $response['status'] == 'success') {
                $uuid = $response['uuid'];
                $pay_url = 'https://bitnetmarket.com/pay/' . $uuid;

                update_post_meta($order_id, '_bitnetmarket_uuid', $uuid);
                $order->add_order_note(sprintf(__('پرداخت به درگاه بیت‌نت‌مارکت ارسال شد. UUID: %s', 'bitnetmarket-gateway'), $uuid));

                wp_redirect($pay_url);
                exit;
            } else {
                $message = $response['detail'] ?? 'نامشخص';
                $order->add_order_note(sprintf(__('خطا در ایجاد پرداخت: %s', 'bitnetmarket-gateway'), $message));
                wc_add_notice(sprintf(__('خطا در اتصال به درگاه: %s', 'bitnetmarket-gateway'), $message), 'error');
            }
        }

        public function return_from_bitnetmarket_gateway() {
            $order_id = isset($_GET['wc_order']) ? intval($_GET['wc_order']) : 0;
            if (!$order_id) {
                $order_id = WC()->session->get('order_id_bitnetmarket');
                WC()->session->__unset('order_id_bitnetmarket');
            }

            if (!$order_id) {
                wp_redirect(wc_get_checkout_url());
                exit;
            }

            $order = wc_get_order($order_id);
            if (!$order || $order->status == 'completed') {
                wp_redirect($this->get_return_url($order));
                exit;
            }

            $uuid = get_post_meta($order_id, '_bitnetmarket_uuid', true);
            if (!$uuid) {
                $order->update_status('failed', __('شناسه یکتا یافت نشد', 'bitnetmarket-gateway'));
                wc_add_notice(__('شناسه یکتا یافت نشد', 'bitnetmarket-gateway'), 'error');
                wp_redirect(wc_get_checkout_url());
                exit;
            }

            $status_response = $this->check_payment_status($uuid);
            if (!$status_response || !isset($status_response['status'])) {
                $order->update_status('failed', __('خطا در بررسی وضعیت', 'bitnetmarket-gateway'));
                wc_add_notice(__('خطا در بررسی وضعیت پرداخت', 'bitnetmarket-gateway'), 'error');
                wp_redirect(wc_get_checkout_url());
                exit;
            }

            $payment_status = $status_response['status'];
            $order->add_order_note(sprintf(__('وضعیت پرداخت: %s', 'bitnetmarket-gateway'), $this->get_status_message($payment_status)));

            if ($payment_status === 'finished') {
                update_post_meta($order_id, '_transaction_id', $uuid);
                $order->payment_complete($uuid);
                WC()->cart->empty_cart();

                $note = sprintf(__('پرداخت موفقیت آمیز بود. شناسه یکتا: %s', 'bitnetmarket-gateway'), $uuid);
                $order->add_order_note($note, 1);

                $notice = wpautop(wptexturize($this->success_message));
                $notice = str_replace("{uuid}", $uuid, $notice);
                wc_add_notice($notice, 'success');

                wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                exit;
            } elseif (in_array($payment_status, array('waiting', 'confirmed'))) {
                $order->update_status('pending', $this->get_status_message($payment_status));
                $pay_url = 'https://bitnetmarket.com/pay/' . $uuid;
                $notice = wpautop(wptexturize("<a href='$pay_url'>{uuid}</a>\nپرداخت در انتظار تکمیل است. می‌توانید با کلیک روی شناسه به صفحه پرداخت بازگردید"));
                $notice = str_replace("{uuid}", $uuid, $notice);
                wc_add_notice($notice, 'notice');
                wp_redirect(wc_get_checkout_url());
                exit;
            } elseif ($payment_status === 'expired') {
                $order->update_status('failed', $this->get_status_message($payment_status));
                $notice = wpautop(wptexturize("<a href='$pay_url'>{uuid}</a>\nپرداخت منقضی شده است. دوباره افدام به پرداخت کنید"));
                $notice = str_replace("{uuid}", $uuid, $notice);
                wc_add_notice($notice, 'error');
                wp_redirect(wc_get_checkout_url());
                exit;
            } else {
                $order->update_status('failed', $this->get_status_message($payment_status));
                $notice = wpautop(wptexturize($this->failed_message));
                $notice = str_replace("{uuid}", $uuid, $notice);
                wc_add_notice($notice, 'error');
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        }

        private function send_payment_request($amount, $callback_url) {
            $url = $this->api_url . '/pg';

            $data = array(
                'seller_id' => $this->seller_id,
                'amount' => $amount,
                'callback_url' => $callback_url
            );

            $args = array(
                'body' => json_encode($data),
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 30,
                'sslverify' => false,
            );

            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                $this->log_error('Payment request error: ' . $response->get_error_message());
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);

            return $result;
        }

        private function check_payment_status($uuid) {
            $url = $this->api_url . '/pg/' . $uuid;

            $args = array(
                'timeout' => 30,
                'sslverify' => false,
            );

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                $this->log_error('Status check error: ' . $response->get_error_message());
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);

            return $result;
        }

        private function get_status_message($status) {
            return isset($this->messages[$status]) ? $this->messages[$status] : 'وضعیت نامشخص';
        }

        private function log_error($message) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Bitnetmarket Gateway Error: ' . $message);
            }
        }

        public function admin_options() {
            ?>
            <h2><?php echo esc_html($this->method_title); ?></h2>
            <p><?php echo esc_html($this->method_description); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <div style="background: #f0f8ff; border-right: 4px solid #2196F3; padding: 12px; margin: 20px 0;">
                <h4 style="margin-top: 0;">راهنمای استفاده:</h4>
                <ol style="margin-bottom: 0;">
                    <li>وارد پلتفرم <a href="https://seller.bitnetmarket.com" target="_blank">seller.bitnetmarket.com</a> شوید</li>
                    <li>از منو یا بخش اطلاعات حساب کاربری تنظیمات، شناسه فروشنده خود را کپی کنید</li>
                    <li>شناسه را در فیلد بالا وارد کرده و ذخیره کنید</li>
                    <li>درگاه پرداخت آماده استفاده است. برای تست، از مبلغ کم استفاده کنید.</li>
                    <li>می‌توانید در پلتفرم <a href="https://seller.bitnetmarket.com" target="_blank">seller.bitnetmarket.com</a> در پنل کاربری خود از قسمت درگاه فروش (Terminal) به تاریخچه و جزئیات کامل پرداخت‌ها دسترسی داشته باشید.</li>
                </ol>
            </div>
            <?php
        }

        public function admin_notice_missing_seller_id() {
            $seller_id = $this->settings['seller_id'];
            if (empty($seller_id) && 'yes' === $this->settings['enabled']) {
                $message = sprintf(
                    __('شناسه فروشنده بیت‌نت‌مارکت خالی است. <a href="%s">اینجا</a> تنظیم کنید.', 'bitnetmarket-gateway'),
                    admin_url('admin.php?page=wc-settings&tab=checkout&section=bitnetmarket')
                );
                echo '<div class="notice notice-warning is-dismissible"><p>' . $message . '</p></div>';
            }
        }
    }
}