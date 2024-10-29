<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return apply_filters('as_assembly_settings',
    array(
        'enabled' => array(
            'title'       => __( 'Enable/Disable', 'assembly-payment-gateways' ),
            'label'       => __( 'Enable Assembly', 'assembly-payment-gateways' ),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'yes',
        ),
        'title' => array(
            'title'       => __( 'Title', 'assembly-payment-gateways' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'assembly-payment-gateways' ),
            'default'     => __( 'Assembly Credit Card Payment', 'assembly-payment-gateways' ),
            'desc_tip'    => true,
        ),
        'description1' => array(
            'title'       => __( 'Description Credit Card', 'assembly-payment-gateways' ),
            'type'        => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'assembly-payment-gateways' ),
            'default'     => __( '
            Payment Instructions Credit card:
            Card: 4111111111111111. 
            CCV : 123. 
            Account Name: User full name with atleast a space. 
            Exp date: In future', 'assembly-payment-gateways' ),
            'desc_tip'    => true,
        ),
        'testmode' => array(
            'title'       => __( 'Test mode', 'assembly-payment-gateways' ),
            'label'       => __( 'Enable Test Mode', 'assembly-payment-gateways' ),
            'type'        => 'checkbox',
            'description' => __( 'Place the payment gateway in test mode using test API keys.', 'assembly-payment-gateways' ),
            'default'     => 'yes',
            'desc_tip'    => true,
        ),
        'username' => array(
            'title'       => __( 'Username', 'assembly-payment-gateways' ),
            'type'        => 'text',
            'description' => __( 'Username', 'assembly-payment-gateways' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'password' => array(
            'title'       => __( 'Password', 'assembly-payment-gateways' ),
            'type'        => 'text',
            'description' => __( 'Password', 'assembly-payment-gateways' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'saved_cards' => array(
            'title'       => __( 'Saved Cards', 'assembly-payment-gateways' ),
            'label'       => __( 'Enable Payment via Saved Cards', 'assembly-payment-gateways' ),
            'type'        => 'checkbox',
            'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Assembly servers, not on your store.', 'assembly-payment-gateways' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
        'logging' => array(
            'title'       => __( 'Logging', 'assembly-payment-gateways' ),
            'label'       => __( 'Log debug messages', 'assembly-payment-gateways' ),
            'type'        => 'checkbox',
            'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'assembly-payment-gateways' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
    )
);