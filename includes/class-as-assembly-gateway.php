<?php

class AS_Gateway_Assembly extends WC_Payment_Gateway_CC
{
    public $testmode;
    public $username;
    public $password;
    public $capture;
    public $saved_cards;
    public $logging;
    public $description1;

    public function __construct()
    {
        $this->id = 'assembly';
        $this->method_title = __('Assembly Payments', 'assembly-payment-gateways');
        $this->method_description = sprintf(__('Accept Credit Card payments via Assembly.'));
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'tokenization',
            'refunds',
            'add_payment_method'
        );

        // load settings form fields
        $this->init_form_fields();

        // load settings
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description1 = $this->get_option('description1');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->username = $this->get_option('username');
        $this->password = $this->get_option('password');
//        $this->capture = 'yes' === $this->get_option('capture');
        $this->saved_cards = 'yes' === $this->get_option('saved_cards');
        $this->logging = 'yes' === $this->get_option('logging');

        // load keys into api
        AS_Assembly_API::set_user_credentials($this->username, $this->password, $this->testmode);
        update_option('assembly_payment_settings', $this->settings);

        // Add hooks
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts')); // not yet use this
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = include('settings-formfields-assembly.php');
    }

    public function payment_scripts()
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page()) {
            return;
        }

        //check for test mode
        if ($this->testmode) {
            wp_enqueue_script('assembly', 'https://js.prelive.promisepay.com/PromisePay.js');
        } else {
            wp_enqueue_script('assembly', 'https://js.promisepay.com/PromisePay.js');
        }
        wp_enqueue_script('se_assembly', plugins_url('../assets/js/assembly.js', __FILE__), array('jquery-payment', 'assembly'));
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available()
    {
        if ($this->enabled === 'yes') {
            if (!$this->username || !$this->password) {
                return false;
            }
            return true;
        }
        return false;
    }


    public function payment_fields()
    {
        $user = wp_get_current_user();
        $total = WC()->cart->total;
        $display_token = $this->supports('tokenization') && is_checkout() && $this->saved_cards;

        // If paying from order, we need to get total from order not cart.
        if (isset($_GET['pay_for_order']) && !empty($_GET['key'])) {
            $order = wc_get_order(wc_get_order_id_by_order_key(wc_clean($_GET['key'])));
            $total = $order->get_total();
        }

        if ($user->ID) {
            $user_email = get_user_meta($user->ID, 'billing_email', true);
            $user_email = $user_email ? $user_email : $user->user_email;
        } else {
            $user_email = '';
        }

        if (is_add_payment_method_page()) {
            $pay_button_text = __('Add Card', 'assembly-payment-gateways');
            $total = '';
        } else {
            $pay_button_text = '';
        }

        echo '<div
			id="assembly-payment-data"
			data-panel-label="' . esc_attr($pay_button_text) . '"
			data-description1=""
			data-email="' . esc_attr($user_email) . '"
			data-amount="' . esc_attr($total) . '"
			data-allow-remember-me="' . esc_attr($this->saved_cards ? 'true' : 'false') . '">'; //todo: change this

        if ( $this->description1 ) {
            echo '<p>'.wpautop( wp_kses_post( $this->description1 ) ).'</p>';
        }

        if ($display_token) {
            $this->saved_payment_methods();
        }

        $this->form();

        if ($this->saved_cards) {
            $this->save_payment_method_checkbox();
        }

        echo '</div>';
    }

    /**
     * @param AS_Assembly_User_Account $assemblyUser
     * @param $params
     * @return mixed
     */
    public function get_card_account($assemblyUser, $params = null)
    {
        $expiry = preg_replace('/\s+/', '', $params['assembly-card-expiry']);
        $explode = explode('/', $expiry);

        $params['assembly-card-number'] = preg_replace('/\s+/', '', $params['assembly-card-number']);

        $body = array(
            'user_id' => $assemblyUser->get_assembly_user_id(),
            'full_name' => $params['assembly-card-name'],
            'number' => $params['assembly-card-number'],
            'expiry_month' => $explode[0],
            'expiry_year' => '20' . $explode[1],
            'cvv' => $params['assembly-card-cvc']
        );

        $assemblyApiObj = new AS_Assembly_API();
        $createCardResponse = $assemblyApiObj->createCardAccount($body);

//        if ($createCardResponse && class_exists('WC_Payment_Token_CC') && $this->saved_cards && array_key_exists('wc-assembly-new-payment-method', $params)) {
//            $token = new WC_Payment_Token_CC();
//            $token->set_token($createCardResponse['id']);
//            $token->set_gateway_id('assembly');
//            $token->set_card_type('Visa');
//            $token->set_last4(substr($params['assembly-card-number'], -4));
//            $token->set_expiry_month($explode[0]);
//            $token->set_expiry_year('20' . $explode[1]);
//            $token->set_user_id(get_current_user_id());
//            $token->save();
//        }
        if ( 'yes' === $this->logging ) {
            AS_Assembly::log(  'createUser error Request: ' . print_r( $createCardResponse, true ) );
        }
        return $createCardResponse;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        try {
            $postData = $_POST;
            $assemblyUser = new AS_Assembly_User_Account(get_current_user_id(), $order); // get assembly user obj

//            // start create assembly item and set order status to hold
//            $assemblyApiObj = new SE_Assembly_API();
//            $itemResponse = $assemblyApiObj->createItem($assemblyUser, $order, $this->seller_id);
//
//            $order->update_status('on-hold', sprintf(__('Assembly item approved (Item ID: %s). make payment, or cancel.', 'assembly-payment-gateways'), $itemResponse['id']));
//            // end create assembly item

            if ($postData['wc-assembly-payment-token'] === 'new') {
                $cardAccount = $this->get_card_account($assemblyUser, $postData); // assembly card account

                if ($cardAccount) { // card account created
                    $assemblyApiObj = new AS_Assembly_API();
                    $makePaymentResponse = $assemblyApiObj->createCharge($cardAccount['id'], $order);

                    if (!$makePaymentResponse) {
                        throw new Exception(__('Something went wrong while creating charge.'));
                    }
                    if ($makePaymentResponse['state'] == 'completed'||$makePaymentResponse['state'] == 'payment_held') {
                        $assemblyApiObj = new AS_Assembly_API();
                        $getCardResponse = $assemblyApiObj->getCardAccount($cardAccount['id']);
                            if (class_exists('WC_Payment_Token_CC') && $this->saved_cards) {
                                $token = new WC_Payment_Token_CC();
                                $token->set_token($cardAccount['id']);
                                $token->set_gateway_id('assembly');
                                $token->set_card_type($getCardResponse['card']['type']);
                                $token->set_last4(substr($getCardResponse['card']['number'], -4));
                                $token->set_expiry_month($getCardResponse['card']['expiry_month']);
                                $token->set_expiry_year($getCardResponse['card']['expiry_year']);
                                $token->set_user_id(get_current_user_id());
                                $token->save();
                            }
                        $order->payment_complete($makePaymentResponse['id']);
                        update_post_meta($order_id, 'Assembly Charge ID', $makePaymentResponse['id']);

                        // Remove cart.
                        WC()->cart->empty_cart();

                        // Return thank you page redirect.
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order),
                        );
                    }
                }
            } else {
                $tokenId = $postData['wc-assembly-payment-token'];
                $token = WC_Payment_Tokens::get($tokenId);

                if (!$token || $token->get_user_id() !== get_current_user_id()) {
                    WC()->session->set('refresh_totals', true);
                    throw new Exception(__('Invalid payment method. Please input a new card number.', 'woocommerce-gateway-stripe'));
                }
                $cardAccountId = $token->get_token();
                $assemblyApiObj = new AS_Assembly_API();
                $makePaymentResponse = $assemblyApiObj->createCharge($cardAccountId, $order);

                if ($makePaymentResponse) {
                    if ($makePaymentResponse['state'] == 'completed') {
                        $order->payment_complete($makePaymentResponse['id']);
                        update_post_meta($order_id, 'Assembly Charge ID', $makePaymentResponse['id']);

                        // Remove cart.
                        WC()->cart->empty_cart();

                        // Return thank you page redirect.
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order),
                        );
                    }
                } else {
                    throw new Exception(__('Something went wrong while capturing the payment.'));
                }
            }

        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            if ($order->has_status(array('pending', 'failed'))) {
                $this->send_failed_order_email($order_id);
            }

            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }
    }

    public function add_payment_method()
    {
        if (empty($_POST['assembly-card-number']) ||
            empty($_POST['assembly-card-expiry']) ||
            empty($_POST['assembly-card-name']) ||
            !is_user_logged_in()
        ) {
            wc_add_notice(__('There was a problem adding the card.', 'assembly-payment-gateways'), 'error');
            return;
        }

        $assemblyUser = new AS_Assembly_User_Account(get_current_user_id());
        $this->get_card_account($assemblyUser, $_POST);
        return array(
            'result' => 'success',
            'redirect' => wc_get_endpoint_url('payment-methods'),
        );
    }

    public function form()
    {
        wp_enqueue_script('wc-credit-card-form');

        $fields = array();

        $cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr($this->id) . '-card-cvc">' . esc_html__('Card code', 'woocommerce') . ' <span class="required">*</span></label>
			<input id="' . esc_attr($this->id) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" name="' . esc_attr($this->id) . '-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__('CVC', 'woocommerce') . '" ' . $this->field_name('card-cvc') . ' style="width:100px" />
		</p>';

        $default_fields = array(
            'card-number-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-number">' . esc_html__('Card number', 'woocommerce') . ' <span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-number" name="' . esc_attr($this->id) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name('card-number') . ' />
			</p>',
            'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr($this->id) . '-card-expiry">' . esc_html__('Expiry (MM/YY)', 'woocommerce') . ' <span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-expiry" name="' . esc_attr($this->id) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__('MM / YY', 'woocommerce') . '" ' . $this->field_name('card-expiry') . ' />
			</p>',
        );

        if (!$this->supports('credit_card_form_cvc_on_saved_method')) {
            $default_fields['card-cvc-field'] = $cvc_field;
        }

        $default_fields['card-name-field'] = '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-name">' . esc_html__('Account Name', 'woocommerce') . ' <span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-name" name="' . esc_attr($this->id) . '-card-name" class="input-text" autocapitalize="yes" placeholder="Card name" spellcheck="yes" type="tel"' . $this->field_name('card-name') . ' />
			</p>';

        $default_fields['card-token'] = '<input id="' . esc_attr($this->id) . '-card-token" style="display: none;" class="input-text"' . $this->field_name('card-token') . ' />';

        $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
        ?>

        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
            <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
            <?php
            foreach ($fields as $field) {
                echo $field;
            }
            ?>
            <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
        <?php

        if ($this->supports('credit_card_form_cvc_on_saved_method')) {
            echo '<fieldset>' . $cvc_field . '</fieldset>';
        }
    }

    public function send_failed_order_email($order_id)
    {
        $emails = WC()->mailer()->get_emails();
        if (!empty($emails) && !empty($order_id)) {
            $emails['WC_Email_Failed_Order']->trigger($order_id);
        }
    }

    public function log( $message ) {
        $options = get_option('assembly_payment_settings');

        if ( 'yes' === $options['logging'] ) {
            AS_Assembly::log($message);
        }
    }
}