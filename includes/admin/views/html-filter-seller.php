<?php

if (!defined('ABSPATH')) {
    exit;
}

global $post;

$sellers = get_option('sellers');
$selected_seller = '';
$empty_selection_title = __('Filter by Seller...','woocommerce-maxipago');
$show_label = false;

if ( ! empty( $_GET['merchant_id'] ) ) {
    $selected_seller     = absint( $_GET['merchant_id'] );
}

include_once 'seller-selector.php';