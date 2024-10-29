<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return apply_filters('as_assembly_settings',
    array(
        'enabled' => array(
            'title'       => __( 'Enable/Disable', 'assembly-payment-gateways-bank' ),
            'label'       => __( 'Enable Assembly Bank', 'assembly-payment-gateways-bank' ),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'yes',
        ),
        'title' => array(
            'title'       => __( 'Title', 'assembly-payment-gateways-bank' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'assembly-payment-gateways-bank' ),
            'default'     => __( 'Assembly Bank Account Payment', 'assembly-payment-gateways-bank' ),
            'desc_tip'    => true,
        ),
        'description2' => array(
            'title'       => __( 'Description', 'assembly-payment-gateways-bank' ),
            'type'        => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'assembly-payment-gateways-bank' ),
            'default'     => __( 'Payment Instructions Bank account: 
            Routing number: 123123. 
            Account number : 12341234. 
            Country: Australia', 'assembly-payment-gateways-bank' ),
            'desc_tip'    => true,
        ),
        'logging' => array(
            'title'       => __( 'Logging', 'assembly-payment-gateways-bank' ),
            'label'       => __( 'Log debug messages', 'assembly-payment-gateways-bank' ),
            'type'        => 'checkbox',
            'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'assembly-payment-gateways-bank' ),
            'default'     => 'no',
            'desc_tip'    => true,
        ),
    )
);