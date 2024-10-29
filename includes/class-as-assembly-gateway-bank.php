<?php

class AS_Gateway_Assembly_Bank extends WC_Payment_Gateway_eCheck
{
    public $testmode;
    public $username;
    public $password;
    public $logging;
    public $description2;

    public function __construct()
    {
        $this->id = 'assembly_bank';
        $this->method_title = __('Assembly Payments Bank', 'assembly-payment-gateways-bank');
        $this->method_description = sprintf(__('Accept Bank Account payments via Assembly.'));
        $this->has_fields = true;

        $this->init_form_fields();

        // load settings
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description2 = $this->get_option('description2');
        $this->logging = 'yes' === $this->get_option('logging');

        $ccOptions = get_option('assembly_payment_settings');
        $this->testmode = 'yes' === $ccOptions['testmode'];
        $this->username = $ccOptions['username'];
        $this->password = $ccOptions['password'];

        AS_Assembly_API::set_user_credentials($this->username, $this->password, $this->testmode);
        update_option('assembly_payment_settings_bank', $this->settings);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = include('settings-formfields-assembly-bank.php');
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
            $pay_button_text = __('Add Card', 'assembly-payment-gateways-bank');
            $total = '';
        } else {
            $pay_button_text = '';
        }

        echo '<div
			id="assembly-payment-data"
			data-panel-label="' . esc_attr($pay_button_text) . '"
			data-description="'. esc_attr($this->description2) .'"
			data-email="' . esc_attr($user_email) . '"
			data-amount="' . esc_attr($total) . '">';

        if ( $this->description2 ) {
            echo '<p>'.wpautop( wp_kses_post( $this->description2) ).'</p>';
        }

        $this->form();

        echo '</div>';
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        try {
            $postData = $_POST;

            $assemblyUser = new AS_Assembly_User_Account(get_current_user_id(), $order); // get assembly user obj
            $bankAccount = $this->get_bank_account($assemblyUser, $postData);

            if ($bankAccount) {
                $assemblyApiObj = new AS_Assembly_API();
                $directDebitResponse = $assemblyApiObj->makeDirectDebitPayment($bankAccount['id'], $order->get_total());

                if ($directDebitResponse && $directDebitResponse['state'] === 'approved') {
                    $order->payment_complete($directDebitResponse['id']);
                    update_post_meta($order_id, 'Assembly Direct Debit ID', $directDebitResponse['id']);

                    // Remove cart.
                    WC()->cart->empty_cart();

                    // Return thank you page redirect.
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
                    );
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

    /**
     * @param AS_Assembly_User_Account $assemblyUser
     * @param array $params
     */
    public function get_bank_account($assemblyUser, $params)
    {
        $body = array(
            'user_id' => $assemblyUser->get_assembly_user_id(),
            'bank_name' => $params['assembly_bank-bank-name'],
            'account_name' => $params['assembly_bank-account-number'],
            'routing_number' => $params['assembly_bank-routing-number'],
            'account_number' => $params['assembly_bank-account-number'],
            'account_type' => $params['assembly_bank-account-type'],
            'holder_type' => $params['assembly_bank-holder-type'],
            'country' => kia_convert_country_code($params['assembly_bank-country'])
        );

        $assemblyApiObj = new AS_Assembly_API();
        $createCardResponse = $assemblyApiObj->createBankAccount($body);

        return $createCardResponse;
    }

    /**
     * Outputs fields for entering eCheck information.
     * @since 2.6.0
     */
    public function form() {
        $fields = array();

        $countries = new WC_Countries();
        $countryList = $countries->get_countries();
        $countryHtml = '<p class="form-row">
				<label for="' . esc_attr( $this->id ) . '-country">' . esc_html__( 'Country', 'woocommerce' ) . ' <span class="required">*</span></label>
				<select id="' . esc_attr( $this->id ) . '-country" type="text" autocomplete="off" name="' . esc_attr( $this->id ) . '-country">';
        foreach ($countryList as $code => $countryName) {
            $optionHtml = '<option value="' . $code . '">' . $countryName .'</option>';
            $countryHtml .= $optionHtml;
        }
        $countryHtml .= '</select></p>';

        $default_fields = array(
            'bank-name' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-bank-name">' . esc_html__( 'Bank name', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-bank-name" class="input-text" type="text" placeholder="Bank name" name="' . esc_attr( $this->id ) . '-bank-name" />
			</p>',
            'account-name' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-account-name">' . esc_html__( 'Account name', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-account-name" class="input-text" type="text" autocomplete="off" placeholder="Account name" name="' . esc_attr( $this->id ) . '-account-name" />
			</p>',
            'routing-number' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-routing-number">' . esc_html__( 'Routing number', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-routing-number" class="input-text wc-echeck-form-routing-number" type="text" maxlength="9" autocomplete="off" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" name="' . esc_attr( $this->id ) . '-routing-number" />
			</p>',
            'account-number' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-account-number">' . esc_html__( 'Account number', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-account-number" class="input-text wc-echeck-form-account-number" placeholder="Account number" type="text" autocomplete="off" name="' . esc_attr( $this->id ) . '-account-number" maxlength="17" />
			</p>',
            'account-type' => '<p class="form-row">
				<label for="' . esc_attr( $this->id ) . '-account-type">' . esc_html__( 'Account type', 'woocommerce' ) . ' <span class="required">*</span></label>
				<select id="' . esc_attr( $this->id ) . '-account-type" type="text" autocomplete="off" name="' . esc_attr( $this->id ) . '-account-type">
				    <option value="savings">Savings</option>
				    <option value="checking">Checking</option>
				</select>
			</p>',
            'holder-type' => '<p class="form-row">
				<label for="' . esc_attr( $this->id ) . '-holder-type">' . esc_html__( 'Holder type', 'woocommerce' ) . ' <span class="required">*</span></label>
				<select id="' . esc_attr( $this->id ) . '-holder-type" type="text" autocomplete="off" name="' . esc_attr( $this->id ) . '-holder-type">
				    <option value="personal">Personal</option>
				    <option value="business">Business</option>
				</select>
			</p>',
            'country' => $countryHtml
        );

        $fields = wp_parse_args( $fields, apply_filters( 'woocommerce_echeck_form_fields', $default_fields, $this->id ) );
        ?>

        <fieldset id="<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-echeck-form wc-payment-form'>
            <?php do_action( 'woocommerce_echeck_form_start', $this->id ); ?>
            <?php
                foreach ( $fields as $field ) {
                    echo $field;
                }
            ?>
            <?php do_action( 'woocommerce_echeck_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset><?php
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