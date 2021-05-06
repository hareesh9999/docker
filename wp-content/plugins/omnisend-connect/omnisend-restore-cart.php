<?php
function omnisendRestoreCart()
{
    global $woocommerce;
    if (!empty($_GET['cartID'])) {

        $cartID = sanitize_key($_GET['cartID']);
        $link = OMNISEND_URL . "carts/" . $cartID;
        $curlResult = OmnisendHelper::omnisendApi($link, "GET", []);

        if ($curlResult['code'] == 200) {
            OmnisendLogger::generalLogging("info", "carts", $link, 'Cart with ID ' . $cartID . ' was succesfully restored!');
            $respData = json_decode($curlResult['response']);
            if (null !== WC()->session) {
                WC()->session->set('omnisend_cartID', $respData->cartID);
                WC()->session->set('omnisend_cart_synced', 1);
            }
            if (sizeof($respData->products) > 0) {
                //clean cart before restoring
                $woocommerce->cart->empty_cart();
            }

            foreach ($respData->products as $product) {
                if ($product->variantID != $product->productID) {
                    $woocommerce->cart->add_to_cart($product->productID, $product->quantity, $product->variantID);
                } else {
                    $woocommerce->cart->add_to_cart($product->productID, $product->quantity);
                }
            }
            //Set restored cartID to current cart
            $user_id = get_current_user_id();
            if ($user_id > 0) {
                update_user_meta($user_id, "omnisend_cartID", $respData->cartID);
            }
            parse_str($_SERVER["QUERY_STRING"], $query_params);
            wp_redirect(remove_query_arg("action", add_query_arg($query_params, wc_get_cart_url())));
            exit;
        }
    }
    wp_redirect('/');
    exit;
}
