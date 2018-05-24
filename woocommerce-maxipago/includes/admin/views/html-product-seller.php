<?php

if (!defined('ABSPATH')) {
    exit;
}

global $post;

$sellers = get_option('sellers');
$selected_seller = get_post_meta($post->ID,'seller_id',true);
$empty_selection_title = __('Select a seller...','woocommerce-maxipago');
$show_label = true;

include_once 'seller-selector.php';