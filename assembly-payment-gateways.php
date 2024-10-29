<?php

use  \PromisePay\Configuration;

/*
 * Plugin Name: Assembly Payments Gateways
 * Plugin URI: https://wordpress.org/plugins/assembly-payments-gateways/
 * Description: Custom-made Assembly Payments Gateway
 * Author: simonunderwood
 * Author URI: https://assemblypayments.com/
 * Version: 1.0.1
 * Text Domain: assembly-payments-gateways
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
define( 'AS_ASSEMBLY_VERSION', '1.0.1' );
if (!class_exists('AS_Assembly')) {
    class AS_Assembly
    {
        private static $instance;

        private static $log;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        protected function __construct()
        {
            add_action( 'admin_init', array( $this, 'check_environment' ) );
            add_action('plugins_loaded', array($this, 'init'));
        }

        public function init() {
            $path = plugin_dir_path(__FILE__);
            require_once $path . 'promisepay-php-master/autoload.php';
            include_once(dirname(__FILE__) . '/includes/class-as-assembly-api.php');
            include_once(dirname(__FILE__) . '/includes/customer/class-as-assembly-user-account.php');
            include_once(dirname(__FILE__) . '/includes/country-code/country_code_convert.php');

            $this->init_gateway();

            add_action( 'woocommerce_payment_token_deleted', array( $this, 'woocommerce_payment_token_deleted' ), 10, 2 );
        }

        public function check_environment() {
            if ( ! defined( 'IFRAME_REQUEST' ) && ( AS_ASSEMBLY_VERSION !== get_option( 'as_assembly_version' ) ) ) {
                $this->install();

                do_action( 'woocommerce_assembly_updated' );
            }
        }
        private static function _update_plugin_version() {
            delete_option( 'as_assembly_version' );
            update_option( 'as_assembly_version', AS_ASSEMBLY_VERSION );

            return true;
        }
        public function install() {
            if ( ! defined( 'AS_ASSEMBLY_INSTALLING' ) ) {
                define( 'AS_ASSEMBLY_INSTALLING', true );
            }

            $this->_update_plugin_version();
        }

        public function woocommerce_payment_token_deleted($token_id, $token)
        {
            if ( 'assembly' === $token->get_gateway_id() ) {
                $assembly_api_obj = new AS_Assembly_API();
                $assembly_api_obj->deleteCardAccount($token->get_token());
            }
        }

        public function init_gateway() {
            if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
                return;
            }

            include_once(dirname(__FILE__) . '/includes/class-as-assembly-gateway.php');
            include_once(dirname(__FILE__) . '/includes/class-as-assembly-gateway-bank.php');
            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
        }

        public function add_gateways($methods) {
            $methods[] = 'AS_Gateway_Assembly';
            $methods[] = 'AS_Gateway_Assembly_Bank';

            return $methods;
        }

        public static function log( $message ) {
            if ( empty( self::$log ) ) {
                self::$log = new WC_Logger();
            }
            self::$log->add( 'assembly-payment-gateways', $message );
        }
    }

    $GLOBALS['as_assembly'] = AS_Assembly::get_instance();
}