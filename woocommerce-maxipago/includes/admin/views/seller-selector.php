<!--
    Seller selector:
        Requires:
            - $sellers: An array of $seller¹ object;
            - $selected_seller: Check for $seller->id or '';
            - $empty_selection_title: Title for an empty selection (i.e. 'Select One...', 'Choose One...', 'Filter by Seller...')
            - $show_label: true/false

        ¹ $seller required properties = array( 'seller_merchant_id', 'seller_name' );
-->

<?php
    if(!isset($sellers))
        $sellers = array();

    if(!isset($selected_seller))
        $selected_seller = '';

    if(!isset($empty_selection_title))
        $empty_selection_title = __('Choose One...', 'woocommerce-maxipago');

    if(!isset($show_label))
        $show_label = false;

    if($show_label)
    {
        echo "<label for=\"_seller_selector\">" . __('Seller', 'woocommerce-maxipago') . "</label>";
    }

    echo "<select id=\"_seller_selector\" class=\"select short\" name=\"merchant_id\" value=\"selected_seller\">";

        echo "<option value=\"\">$empty_selection_title</option>";

        foreach($sellers as $seller) {
            $seller_id = $seller['seller_merchant_id'];
            $seller_name = $seller['seller_name'];
            $selected = $selected_seller == $seller_id ? 'selected' : '';
            echo "<option value=\"$seller_id\" $selected>$seller_name</option>";
        }

    echo "</select>";
?>