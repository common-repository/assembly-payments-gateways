<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AS_Assembly_User_Account
{
    protected $assembly_user_id = '';
    protected $user_id = '';
    protected $metadata = array();

    /**
     * AS_Assembly_Customer_Abstract constructor.
     * @param int $user_id
     * @param WC_Order $order
     */
    public function __construct($wp_user_id = 0, $order = null)
    {
        include_once(dirname(__FILE__) . '/includes/class-as-assembly-api.php');

        if ($wp_user_id) {
            $this->set_user_id($wp_user_id);
            // if not exist assembly user id, set it
            $assemblyUserId = get_user_meta($wp_user_id, '_assembly_user_id', true);
            if (!$assemblyUserId && $order) { // assembly user not exist and will be create through checkout
                $assemblyUserId = str_replace('.', '', $order->get_billing_email() . '-' . $this->get_user_id() . '-' . time());
                $assemblyApiObj = new AS_Assembly_API();
                $response = $assemblyApiObj->createUserWithOrder($assemblyUserId, $order);

                if (array_key_exists('id', $response)) {
                    update_user_meta($wp_user_id, '_assembly_user_id', $response['id']);
                    $this->set_assembly_user_id($response['id']);
                    $this->metadata = $response;
                } else {
                    die();
                }
            } else if (!$assemblyUserId && !$order) { // assembly user not exist and will be created through add card page
                if (!is_user_logged_in()) {
                    throw new Exception(__('Customer not found.'));
                }
                $wpUser = new WC_Customer($wp_user_id);
                if (!$wpUser->get_email()) {
                    throw new Exception(__('Customer email is not defined'));
                }
                $assemblyUserId = str_replace('.', '', $wpUser->get_email() . '-' . $wpUser->get_id() . '-' . time());
                $assemblyApiObj = new AS_Assembly_API();
                $response = $assemblyApiObj->createUser($wpUser, $assemblyUserId);

                if (array_key_exists('id', $response)) {
                    update_user_meta($wp_user_id, '_assembly_user_id', $response['id']);
                    $this->set_assembly_user_id($response['id']);
                    $this->metadata = $response;
                } else {
                    die();
                }
            } else {
                // assembly user already exists
                $this->set_assembly_user_id($assemblyUserId);
                $assemblyApiObj = new AS_Assembly_API();
                $response = $assemblyApiObj->getUserFromAssembly($assemblyUserId);

                if (array_key_exists('id', $response)) {
                    $this->metadata = $response;
                } else {
                    die();
                }
            }
        }
    }

    public function set_user_id( $user_id ) {
        $this->user_id = absint( $user_id );
    }
    public function get_user_id() {
        return absint( $this->user_id );
    }

    public function set_assembly_user_id($user_id) {
        $this->assembly_user_id = $user_id;
    }
    public function get_assembly_user_id() {
        return $this->assembly_user_id;
    }
}