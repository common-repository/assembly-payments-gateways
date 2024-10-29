<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PromisePay\PromisePay;

class AS_Assembly_API {
    private static $username = '';
    private static $user_password = '';
    private static $testmode = true;

    public static function set_user_credentials($username, $user_password, $testmode) {
        self::$username = $username;
        self::$user_password = $user_password;
        self::$testmode = $testmode;
    }

    public static function get_user_credentials_if_not_exist() {
        $options = get_option('assembly_payment_settings');

        if ((!self::$username || !self::$user_password) && $options) {
            self::$username = $options['username'];
            self::$user_password = $options['password'];
            self::$testmode = 'yes' === $options['testmode'];
        }
    }

    public function __construct()
    {
        if (self::$testmode) {
            PromisePay::Configuration()->environment('prelive');
        } else {
            PromisePay::Configuration()->environment('production');
        }
        PromisePay::Configuration()->login(self::$username);
        PromisePay::Configuration()->password(self::$user_password);

        self::get_user_credentials_if_not_exist();
    }

    /**
     * ============ BANK ACCOUNT METHODS =============
     */
    public static function createBankAccount($body) {
        try {
            $response = PromisePay::BankAccount()->create($body);

            AS_Assembly::log(  'createBankAccount Response: ' . print_r( $response, true ) );

            if ($response) return $response;
            else {
                throw new Exception(__('Something went wrong while creating bank account.'));
            }
        } catch (Exception $e) {
            wc_add_notice( $e->getMessage(), 'error' );
            return;
        }
    }

    public static function makeDirectDebitPayment($bankAccountId, $amount)
    {
        try {
            $debitResponse = PromisePay::DirectDebitAuthority()->create(
                array(
                    'account_id' => $bankAccountId,
                    'amount' => floatval($amount) * 100
                )
            );
                AS_Assembly::log(  'makeDirectDebitPayment Response: ' . print_r( $debitResponse, true ) );

            if (!$debitResponse || !array_key_exists('id', $debitResponse)) {
                throw new Exception(__('Cannot create direct debit authorities.'));
            }

            return $debitResponse;
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return;
        }
    }
    /**
     * ============ END OF BANK ACCOUNT METHODS =============
     */

    /**
     * ============ CARD ACCOUNT METHODS =============
     */
    public static function createCardAccount($body) {
        try {
            $response = PromisePay::CardAccount()->create($body);
            AS_Assembly::log(  'createCardAccount Response: ' . print_r( $response, true ) );

            if ($response) return $response;
            else {
                throw new Exception(__('Something went wrong while creating card account.'));
            }
        } catch (Exception $e) {
            wc_add_notice( $e->getMessage(), 'error' );

            AS_Assembly::log(  'createCardAccount error Response: ' . print_r( $e->getMessage(), true ) );

            return;
        }
    }
    public static function getCardAccount($id) {
        try {
            $response = PromisePay::CardAccount()->get($id);
            AS_Assembly::log(  'getCardAccount Response: ' . print_r( $response, true ) );

            if ($response) return $response;
            else {
                throw new Exception(__('Something went wrong while get card account.'));
            }
        } catch (Exception $e) {
            wc_add_notice( $e->getMessage(), 'error' );

            AS_Assembly::log(  'getCardAccount error Response: ' . print_r( $e->getMessage(), true ) );

            return;
        }
    }

    public static function deleteCardAccount($cardId) {
        try {
            $response = PromisePay::CardAccount()->delete($cardId);
            AS_Assembly::log(  'deleteCardAccount Response: ' . print_r( $response, true ) );
            if ($response) return $response;
            else {
                throw new Exception(__('Something went wrong while deleting card account.'));
            }
        } catch (Exception $e) {
//            wc_add_notice( $e->getMessage(), 'error' );
            AS_Assembly::log(  'deleteCardAccount error Response: ' . print_r( $e->getMessage(), true ) );

            return;
        }
    }
    /**
     * ============ END OF CARD ACCOUNT METHODS =============
     */
    /**
     * ============ CHARGE METHODS =============
     */

    /**
     * @param $accountId
     * @param WC_Order $order
     */
    public static function createCharge($accountId, $order)
    {
        try {
            $chargeResponse = PromisePay::Charges()->create(array(
                "account_id" => $accountId,
                "name" => 'Charge for order ' . $order->get_order_number(),
                "amount" => floatval($order->get_total()) * 100,
                "email" => $order->get_billing_email(),
                "zip" => $order->get_billing_postcode(),
                "country" => kia_convert_country_code($order->get_billing_country()),
                "currency" => $order->get_currency(),
                "retain_account" => true,
            ));

            if (!$chargeResponse || !array_key_exists('id', $chargeResponse)) {
                throw new Exception(__('Cannot create charge.'));
            }

                AS_Assembly::log(  'createCharge Request: ' . print_r( $chargeResponse, true ) );

            return $chargeResponse;
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            AS_Assembly::log(  'createCharge error Response: ' . print_r( $e->getMessage(), true ) );

            return;
        }
    }

    /**
     * ============ END OF CHARGE METHODS =============
     */

    /**
     * ============ USER METHODS =============
     */

    /**
     * @param WC_Customer $wpUser
     * @param int $assemblyUserId
     * @return mixed
     */
    public static function createUser($wpUser, $assemblyUserId)
    {
        if (!$wpUser->get_billing_country()) {
            throw new Exception(__('Please set your billing country first.'));
        }
        try {
            $request = array(
                'id' => $assemblyUserId,
                'email' => $wpUser->get_email(),
                'first_name' => $wpUser->get_first_name(),
                'last_name' => $wpUser->get_last_name(),
                'mobile' => $wpUser->get_billing_phone(),
                'address_line_1' => $wpUser->get_billing_address_1(),
                'state' => $wpUser->get_billing_state(),
                'city' => $wpUser->get_billing_city(),
                'zip' => $wpUser->get_billing_postcode(),
                'country' => kia_convert_country_code($wpUser->get_billing_country())
            );


            $response = PromisePay::User()->create($request);
            AS_Assembly::log(  'createUser Request: ' . print_r( $response, true ) );

            return $response;
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            AS_Assembly::log(  'createUser error Request: ' . print_r( $e->getMessage(), true ) );

            return;
        }
    }

    /**
     * @param int $assemblyUserId
     * @param WC_ORder $order
     */
    public static function createUserWithOrder($assemblyUserId, $order)
    {
        try {
            $request = array(
                'id' => $assemblyUserId,
                'email' => $order->get_billing_email(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'mobile' => $order->get_billing_phone(),
                'address_line_1' => $order->get_billing_address_1(),
                'state' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'zip' => $order->get_billing_postcode(),
                'country' => kia_convert_country_code($order->get_billing_country())
            );


            $response = PromisePay::User()->create($request);

            AS_Assembly::log(  'createUserWithOrder Request: ' . print_r( $response, true ) );

            return $response;
        } catch (Exception $e) {
            echo $e->getMessage();
            AS_Assembly::log(  'createUserWithOrder error Request: ' . print_r( $e->getMessage(), true ) );

            die();
        }
    }

    public static function getUserFromAssembly($assembly_user_id)
    {
        try {
            $response = PromisePay::User()->get($assembly_user_id);
            if (array_key_exists('id', $response)) {
                return $response;
            } else throw new Exception(__('User not exist on Assembly server.'));
        } catch(Exception $e) {
            echo $e->getMessage();
            die();
        }
    }
    /**
     * ============ END OF USER METHODS =============
     */
}