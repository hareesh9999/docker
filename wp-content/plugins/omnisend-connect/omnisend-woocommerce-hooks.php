<?php
//global variables
$cart_converted = false;
$pickerProductSet = false;

/**PRODUCTS **/
add_action('woocommerce_update_product', 'omnisend_on_product_change', 100, 1);
add_action('trash_product', 'omnisend_product_delete');

// product create or update
function omnisend_on_product_change($post_id)
{
    remove_action('woocommerce_update_product', 'omnisend_on_product_change');
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        OmnisendManager::pushProductToOmnisend($post_id);
    }
}

/* product create or update */
function omnisend_product_delete($post_id)
{
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        OmnisendManager::deleteProductFromOmnisend($post_id);
    }
}

/* product page - add Product Picker */
add_action('woocommerce_after_single_product', 'omnisend_product_picker', 5);
function omnisend_product_picker()
{
    global $pickerProductSet;
    if ($pickerProductSet == false) {
        $pickerProductSet = true;
        OmnisendProduct::productPicker();
    }
}

/**PRODUCT CATEGORIES **/
add_action('edited_product_cat', 'omnisend_on_category_change', 10, 2);
add_action('create_product_cat', 'omnisend_on_category_change', 10, 2);
add_action('delete_product_cat', 'omnisend_category_delete', 10, 1);

// category create or update
function omnisend_on_category_change($term_id, $tt_id = '')
{
    remove_action('edited_product_cat', 'omnisend_on_category_change');
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        OmnisendManager::pushCategoryToOmnisend($term_id);
    }
}

/* category create or update */
function omnisend_category_delete($post_id)
{
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        OmnisendManager::deleteCategoryFromOmnisend($post_id);
    }
}

/* CONTACT */

/*Hook for triggering action on user create or update*/
add_action('user_register', 'omnisend_on_user_register', 10, 1);
add_action('profile_update', 'omnisend_on_user_update', 10, 2);
function omnisend_on_user_update($user_id, $old_user_data = "")
{
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        return OmnisendManager::pushContactToOmnisend(get_userdata($user_id));
    }
}

function omnisend_on_user_register($user_id)
{
    $res = omnisend_on_user_update($user_id, "");
    if (array_key_exists("contactID", $res)) {
        OmnisendManager::saveContact("", $res['contactID']);
    }

}

/**ORDERS*/

/*Hook for triggering action when order created*/
//add_action('woocommerce_thankyou', 'omnisend_order_created', 10, 1);
add_action('woocommerce_checkout_update_order_meta', 'omnisend_order_created', 20, 2);
function omnisend_order_created($order_id)
{
    global $cart_converted;
    $cart_converted = true;
    //add cartID to order, if doesn't exist
    $cart_id = null === WC()->session ? "" : WC()->session->get('omnisend_cartID');
    if ($cart_id != "") {
        add_post_meta($order_id, 'omnisend_cartID', $cart_id, true);
    }
    if (isset($_COOKIE['omnisendAttributionID'])) {
        add_post_meta($order_id, 'omnisendAttributionID', $_COOKIE['omnisendAttributionID'], true);
    }
    OmnisendManager::pushOrderToOmnisend($order_id);
    OmnisendManagerAssistant::unsetUserCart(false);
}

/* Hook trigered when admin updates order */
add_action('woocommerce_process_shop_order_meta', 'omnisend_order_updated', 10, 2);
function omnisend_order_updated($order_id, $order)
{
    if (is_admin()) {
        OmnisendManager::pushOrderToOmnisend($order_id);
    }
}

/**Fulfillment statuses*/
/*Hook for triggering action when order staus is changed to Processing*/
add_action('woocommerce_order_status_processing', 'omnisend_order_processing', 10, 1);
function omnisend_order_processing($order_id)
{
    OmnisendManager::updateOrderStatus($order_id, "fulfillment", "inProgress");
}
/*Hook for triggering action when order staus is changed to Processing*/
add_action('woocommerce_order_status_completed', 'omnisend_order_completed', 10, 1);
function omnisend_order_completed($order_id)
{
    OmnisendManager::updateOrderStatus($order_id, "fulfillment", "fulfilled");
}

/**Payment statuses*/
/*Hook for triggering action when order staus is changed to Pending*/
add_action('woocommerce_order_status_pending', 'omnisend_order_pending', 10, 1);
function omnisend_order_pending($order_id)
{
    OmnisendManager::updateOrderStatus($order_id, "payment", "awaitingPayment");
}
/*Hook for triggering action when order staus is changed to Cancelled*/
add_action('woocommerce_order_status_cancelled', 'omnisend_order_cancelled', 10, 1);
function omnisend_order_cancelled($order_id)
{
    OmnisendManager::updateOrderStatus($order_id, "payment", "voided");
}
/*Hook for triggering action when order staus is changed to Refunded*/
add_action('woocommerce_order_status_refunded', 'omnisend_order_refunded', 10, 1);
function omnisend_order_refunded($order_id)
{
    OmnisendManager::updateOrderStatus($order_id, "payment", "refunded");
}
/*Hook for triggering action when order Payment is complete*/
add_action('woocommerce_payment_complete', 'omnisend_order_payment_completed', 10, 1);
function omnisend_order_payment_completed($order_id)
{
    OmnisendManager::updateOrderStatus($order_id, "payment", "paid");
}

/*Hook for triggering action when order Payment failed (order status set to Failed)*/
add_action('woocommerce_order_status_failed', 'omnisend_order_payment_failed', 10, 1);
function omnisend_order_payment_failed($order_id)
{
    OmnisendManager::updateOrderStatus($order_id, "payment", "awaitingPayment");
}

/** CARTS **/
add_action('woocommerce_after_calculate_totals', 'omnisend_cart_updated', 100, 2);
function omnisend_cart_updated()
{
    global $cart_converted;
    if (!$cart_converted) {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            if (!WC()->cart->is_empty()) {
                OmnisendManager::pushCartToOmnisend();
            } else {
                OmnisendManager::pushCartToOmnisend();
            }
        }
    }
}

add_action('woocommerce_cart_item_removed', 'omnisend_cart_delete', 10, 2);
function omnisend_cart_delete()
{
    global $cart_converted;
    if (!$cart_converted) {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            if (WC()->cart->is_empty()) {
                OmnisendManager::pushCartToOmnisend();
            }
        }
    }
}

/*Restore cart URL*/
function omisend_restore_cart_page()
{
    if (isset($_REQUEST['action'])) {
        if ($_REQUEST['action'] == "restoreCart") {
            omnisendRestoreCart();
        }
    }
}
add_action('wp', 'omisend_restore_cart_page');

/* Identify user after login - save cookie */
function omnisend_wplogin($user_login, $user)
{
    OmnisendManager::saveContact($user);
}
add_action('wp_login', 'omnisend_wplogin', 10, 2);

/*Add code snippet to the footer, if account ID is setted*/
add_action('wp_footer', function () {
    global $pickerProductSet, $omnisendPluginVersion;

    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
        && OmnisendHelper::checkWpWcCompatibility()) {

        $omnisend_account_id = get_option('omnisend_account_id', null);
        if ($omnisend_account_id !== null) {
            ?>
    <script type="text/javascript">
    //OMNISEND-SNIPPET-SOURCE-CODE-V1
    window.omnisend = window.omnisend || [];
    omnisend.push([ "accountID", "<?php echo get_option('omnisend_account_id', null); ?>"]);
    omnisend.push([ "track", "$pageViewed"]);
    !function(){ var e=document.createElement("script");e.type="text/javascript",e.async=!0,e.src= "<?php echo OMNISEND_SRC; ?>inshop/launcher-v2.js"; var t=document.getElementsByTagName("script")[0];t.parentNode.insertBefore(e,t)}();
    //platform: woocommerce
    //plugin version: <?php echo $omnisendPluginVersion; ?>

    </script>

    <?php
if (is_product() && !$pickerProductSet) {
                $pickerProductSet = true;
                OmnisendProduct::productPicker();
            }
        }
    }
});

/*Add verification tag */
add_action('wp_head', function () {
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
        && OmnisendHelper::checkWpWcCompatibility()) {

        $omnisend_account_id = get_option('omnisend_account_id', null);
        if ($omnisend_account_id !== null) {
            ?>
          <meta name="omnisend-site-verification" content="<?php echo get_option('omnisend_account_id', null); ?>" />

<?php
}
    }

});

function omnisend_plugin_updates()
{
    global $omnisendPluginVersion;
    if (is_admin() && !empty(get_option('omnisend_api_key', null))) {
        //only for admin
        $updateInfo = false;
        $versionDB = get_option('omnisend_plugin_version', '1.0.0');
        if (version_compare($versionDB, $omnisendPluginVersion, '<')) {
            OmnisendUpdates::update($versionDB, $omnisendPluginVersion);
            update_option("omnisend_plugin_version", $omnisendPluginVersion);
            $updateInfo = true;
        } else if ($versionDB != $omnisendPluginVersion) {
            $updateInfo = true;
        }
        if (get_option('omnisend_wp_version', null) != get_bloginfo('version')) {
            $updateInfo = true;
        }
        if ($updateInfo) {
            OmnisendManager::updateAccountInfo();
        }
    }
}
add_action('plugins_loaded', 'omnisend_plugin_updates');
?>