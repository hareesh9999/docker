<?php
if (!defined('ABSPATH')) {
    exit;
}
class OmnisendManagerAssistant
{

    public static function batchCheck()
    {
        $batches = get_option('omnisend_batches_inProgress');
        $remove_batches = array();
        if (!empty($batches)) {
            $i = 0;
            $renew_orders = 0;
            $renew_products = 0;
            $renew_contacts = 0;
            foreach ($batches as $key => $batchID) {
                $link = OMNISEND_URL . "batches/" . $batchID;
                $response = OmnisendHelper::omnisendApi($link, "GET", []);
                if ($response['code'] >= 200 && $response['code'] < 300) {
                    $r = json_decode($response['response'], true);
                    if ($r["status"] == "finished" || $r["status"] == "stopped") {
                        if ($r['errorsCount'] != 0) {
                            //check items
                            $link = OMNISEND_URL . "batches/" . $batchID . "/items";
                            $response_batch = OmnisendHelper::omnisendApi($link, "GET", []);
                            if ($response_batch['code'] >= 200 && $response_batch['code'] < 300) {
                                $r_batch = json_decode($response_batch['response'], true);
                                if (!empty($r_batch['errors'])) {
                                    foreach ($r_batch['errors'] as $item) {
                                        if ($item['responseCode'] == "503" || $item['responseCode'] == "429" || $item['responseCode'] == "408" || $item['responseCode'] == "403") {
                                            //retry
                                            if ($r["endpoint"] == "orders") {
                                                $last_sync = get_post_meta($item['request']['orderID'], 'omnisend_last_sync', true);
                                                if ($last_sync != "" && $last_sync != "error") {
                                                    $last_sync = strtotime($last_sync);
                                                }
                                                if ($last_sync != "error" && ($last_sync < (strtotime($r["createdAt"]) + 30) || $last_sync == "")) {
                                                    delete_post_meta($item['request']['orderID'], 'omnisend_last_sync');
                                                    $renew_orders = 1;
                                                }
                                            } elseif ($r["endpoint"] == "products") {
                                                $last_sync = get_post_meta($item['request']['productID'], 'omnisend_last_sync', true);
                                                if ($last_sync != "" && $last_sync != "error") {
                                                    $last_sync = strtotime($last_sync);
                                                }
                                                if ($last_sync != "error" && ($last_sync < (strtotime($r["createdAt"]) + 30) || $last_sync == "")) {
                                                    delete_post_meta($item['request']['productID'], 'omnisend_last_sync');
                                                    $renew_products = 1;
                                                }
                                            } else if ($r["endpoint"] == "contacts") {
                                                $user = get_user_by("email", $item['request']['email']);
                                                if (!empty($user)) {
                                                    $last_sync = get_user_meta($user->ID, 'omnisend_last_sync', true);
                                                    if ($last_sync != "" && $last_sync != "error") {
                                                        $last_sync = strtotime($last_sync);
                                                    }
                                                    if ($last_sync != "error" && ($last_sync < (strtotime($r["createdAt"]) + 30) || $last_sync == "")) {
                                                        delete_user_meta($user->ID, 'omnisend_last_sync');
                                                        $renew_contacts = 1;
                                                    }

                                                }

                                            }
                                        }
                                    }
                                }
                            }

                        }
                        //remove batch from inProgress
                        $remove_batches[] = $batchID;

                    }
                } else if ($response['code'] == 404) {
                    $remove_batches[] = $batchID;
                }
                if ($i > 3) {
                    break;
                }

                $i++;
            }
            //update
            $batches = get_option('omnisend_batches_inProgress');
            update_option('omnisend_batches_inProgress', array_diff($batches, $remove_batches));

            //reshedule sync cron jobs
            if ($renew_orders == 1) {
                delete_option('omnisend_sync_orders_finished');
                if (!wp_next_scheduled('omnisend_init_orders_sync')) {
                    wp_clear_scheduled_hook('omnisend_init_orders_sync');
                }
            }
            if ($renew_products == 1) {
                delete_option('omnisend_sync_products_finished');
                if (!wp_next_scheduled('omnisend_init_products_sync')) {
                    wp_clear_scheduled_hook('omnisend_init_products_sync');
                }
            }
            if ($renew_contacts == 1) {
                delete_option('omnisend_sync_contacts_finished');
                if (!wp_next_scheduled('omnisend_init_contacts_sync')) {
                    wp_clear_scheduled_hook('omnisend_init_contacts_sync');
                }
            }
        }

    }
    public static function getList($listID)
    {
        $link = OMNISEND_URL . "lists/" . $listID;
        $curlResult = OmnisendHelper::omnisendApi($link, "GET", []);
        if ($curlResult['code'] == 200) {
            return json_decode($curlResult['response'], true);
        } else {
            return false;
        }
    }

    public static function getApiKeyPermissions()
    {
        $permissions = array();
        $link = OMNISEND_URL . "accounts";
        $curlResult = OmnisendHelper::omnisendApi($link, "GET", []);
        $permissions['contacts'] = false;
        $permissions['orders'] = false;
        $permissions['products'] = false;
        $permissions['carts'] = false;
        $permissions['lists'] = false;

        if (array_key_exists("response", $curlResult)) {
            if ($curlResult['code'] != 500) {
                $r = json_decode($curlResult['response'], true);
                if (array_key_exists("apiKeyPermissions", $r)) {
                    $permissions['contacts'] = $r['apiKeyPermissions']['contacts'];
                    if ($r['apiKeyPermissions']['contactsSafe'] || $r['apiKeyPermissions']['contacts']) {
                        $permissions['contacts'] = true;
                    }
                    $permissions['orders'] = $r['apiKeyPermissions']['orders'];
                    $permissions['products'] = $r['apiKeyPermissions']['products'];
                    $permissions['carts'] = $r['apiKeyPermissions']['carts'];
                    $permissions['lists'] = $r['apiKeyPermissions']['lists'];
                }
            }
        }

        return $permissions;

    }

    public static function getContactIDFromOmnisend($email)
    {
        $link = OMNISEND_URL . "contacts?email=" . urlencode($email);
        $curlResult = OmnisendHelper::omnisendApi($link, "GET", []);
        if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
            $r = json_decode($curlResult['response'], true);
            return $r["contacts"][0]["contactID"];
        } else {
            return;
        }
    }

    public static function unsetUserCart($all = false)
    {
        if ($all && null !== WC()->session) {
            WC()->session->set('omnisend_cart_synced', null);
            WC()->session->set('omnisend_cartID', null);
            WC()->session->set('omnisend_cart', null);
        }

        $user_id = get_current_user_id();
        if ($user_id > 0) {
            delete_user_meta($user_id, 'omnisend_cartID');
            delete_user_meta($user_id, 'omnisend_cart_synced');
        }
    }

    public static function getEmailFromOmnisend($contactID)
    {
        $link = OMNISEND_URL . "contacts/" . $contactID;
        $curlResult = OmnisendHelper::omnisendApi($link, "GET", []);
        return $curlResult['response'];
    }

    //unsynced products sync via batches
    public static function syncAllProducts()
    {
        if (get_option("omnisend_sync_products_finished") != 1 && !empty(get_option('omnisend_api_key', null))) {
            $products = get_posts(array(
                'fields' => 'ids',
                'posts_per_page' => '1000',
                'post_type' => 'product',
                'has_password' => false,
                'post_status' => 'publish',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'omnisend_last_sync',
                        'compare' => 'NOT EXISTS',
                        'value' => '',
                    ),
                ),
            ));
            if (empty($products)) {
                OmnisendLogger::info('Initial products sync finished');
                update_option('omnisend_sync_products_finished', 1);
                if (wp_next_scheduled('omnisend_init_products_sync')) {
                    wp_clear_scheduled_hook('omnisend_init_products_sync');
                }

                return;
            }
            //form batch request and save batchID
            $args = array();
            $args["method"] = "POST";
            $args["endpoint"] = "products";
            $args["items"] = array();
            foreach ($products as $productID) {
                $preparedProduct = OmnisendProduct::create($productID);
                if ($preparedProduct) {
                    $preparedProduct = OmnisendHelper::cleanModelFromEmptyFields($preparedProduct);
                    $args["items"][] = $preparedProduct;
                }
            }
            $link = OMNISEND_URL . "batches";
            $response = OmnisendHelper::omnisendApi($link, "POST", $args);
            if ($response['code'] >= 200 && $response['code'] < 300) {
                //batch ID
                $status = date(DATE_ATOM, time());
                $r = json_decode($response['response'], true);
                $batchID = $r["batchID"];
                if (strlen($batchID) == 24) {
                    //write batch to check response later
                    $batches_inProgress = get_option("omnisend_batches_inProgress");
                    if (!is_array($batches_inProgress)) {
                        $batches_inProgress = array();
                    }
                    if (!in_array($batchID, $batches_inProgress)) {
                        $batches_inProgress[] = $batchID;
                        update_option("omnisend_batches_inProgress", $batches_inProgress);
                    }
                    OmnisendLogger::generalLogging("info", "batches", $link, 'Batch initial sync products was succesfully pushed to Omnisend.');
                    OmnisendLogger::countItem("products", $r['totalCount']);
                } else {
                    OmnisendLogger::generalLogging("warn", "batches", $link, 'Batch error: unable to initial sync products to Omnisend.');
                    $status = "error";
                }
            } else {
                OmnisendLogger::generalLogging("warn", "batches", $link, 'Batch error: unable to initial sync products to Omnisend.');
                $status = "error";
            }

            foreach ($products as $productID) {
                //update products with last update date or "error"
                update_post_meta($productID, 'omnisend_last_sync', $status);
            }
        } else {
            if (wp_next_scheduled('omnisend_init_products_sync')) {
                wp_clear_scheduled_hook('omnisend_init_products_sync');
            }
        }
    }

    //unsynced categories sync
    public static function syncAllCategories()
    {
        if (get_option("omnisend_sync_categories_finished") != 1 && !empty(get_option('omnisend_api_key', null))) {
            $categories = get_categories(array(
                'taxonomy' => 'product_cat',
                'number' => 40,
                'hierarchical' => 0,
                'hide_empty' => 0,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'omnisend_last_sync',
                        'compare' => 'NOT EXISTS',
                        'value' => '',
                    ),
                ),
            ));

            if (empty($categories)) {
                OmnisendLogger::info('Initial catgories sync finished');
                update_option('omnisend_sync_categories_finished', 1);
                if (wp_next_scheduled('omnisend_init_categories_sync')) {
                    wp_clear_scheduled_hook('omnisend_init_categories_sync');
                }
                return;
            }

            $link = OMNISEND_URL . "categories";
            foreach ($categories as $category) {
                $preparedCategory['categoryID'] = "" . $category->term_id;
                $preparedCategory['title'] = $category->name;
                $curlResult = OmnisendHelper::omnisendApi($link, "POST", $preparedCategory);
                if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
                    OmnisendLogger::generalLogging("info", "categories", $link, 'Category #' . $category->term_id . ' was succesfully pushed to Omnisend.');
                    update_term_meta($category->term_id, 'omnisend_last_sync', date(DATE_ATOM, time()));
                    OmnisendLogger::countItem("categories");
                } elseif ($curlResult['code'] == 403) {
                    OmnisendLogger::generalLogging("warn", "categories", $link, 'Unable to push category #' . $category->term_id . " to Omnisend. You don't have rights to push categories.");
                } elseif ($curlResult['code'] == 400 || $curlResult['code'] == 422) {
                    OmnisendLogger::generalLogging("warn", "categories", $link, 'Unable to push category #' . $category->term_id . " to Omnisend." . $curlResult['response']);
                    update_term_meta($category->term_id, 'omnisend_last_sync', 'error');
                } else {
                    OmnisendLogger::generalLogging("warn", "categories", $link, 'Unable to push category #' . $category->term_id . " to Omnisend. May be server error. " . $curlResult['response']);
                }
            }

        } else {
            if (wp_next_scheduled('omnisend_init_categories_sync')) {
                wp_clear_scheduled_hook('omnisend_init_categories_sync');
            }
        }
    }

    //unsynced orders sync via batches
    public static function syncAllOrders()
    {
        if (get_option("omnisend_sync_orders_finished") != 1 && !empty(get_option('omnisend_api_key', null))) {
            $orders = get_posts(array(
                'fields' => 'ids',
                'posts_per_page' => '500',
                'post_type' => 'shop_order',
                'post_status' => array(wc_get_order_statuses()),
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'omnisend_last_sync',
                        'compare' => 'NOT EXISTS',
                        'value' => '',
                    ),
                ),
            ));

            if (empty($orders)) {
                OmnisendLogger::info('Initial orders sync finished');
                update_option('omnisend_sync_orders_finished', 1);
                if (wp_next_scheduled('omnisend_init_orders_sync')) {
                    wp_clear_scheduled_hook('omnisend_init_orders_sync');
                }

                return;
            }
            //form batch request and save batchID
            $args = array();
            $args["method"] = "POST";
            $args["endpoint"] = "orders";
            $args["items"] = array();
            foreach ($orders as $orderID) {
                $preparedOrder = OmnisendOrder::create($orderID);
                if ($preparedOrder) {
                    $preparedOrder = OmnisendHelper::cleanModelFromEmptyFields($preparedOrder);
                    $args["items"][] = $preparedOrder;
                }
            }
            $link = OMNISEND_URL . "batches";
            $response = OmnisendHelper::omnisendApi($link, "POST", $args);
            if ($response['code'] >= 200 && $response['code'] < 300) {
                //batch ID
                $status = date(DATE_ATOM, time());
                $r = json_decode($response['response'], true);
                $batchID = $r["batchID"];
                if (strlen($batchID) == 24) {
                    //write batch to check response later
                    $batches_inProgress = get_option("omnisend_batches_inProgress");
                    if (!is_array($batches_inProgress)) {
                        $batches_inProgress = array();
                    }
                    if (!in_array($batchID, $batches_inProgress)) {
                        $batches_inProgress[] = $batchID;
                        update_option("omnisend_batches_inProgress", $batches_inProgress);
                    }
                    OmnisendLogger::generalLogging("info", "batches", $link, 'Batch initial sync orders was succesfully pushed to Omnisend.');
                    OmnisendLogger::countItem("orders", $r['totalCount']);
                } else {
                    OmnisendLogger::generalLogging("warn", "batches", $link, 'Batch error: unable to initial sync orders to Omnisend.');
                    $status = "error";
                }
            } else {
                OmnisendLogger::generalLogging("warn", "batches", $link, 'Batch error: unable to initial sync orders to Omnisend.');
                $status = "error";
            }

            foreach ($orders as $orderID) {
                //update orders with last update date or "error"
                update_post_meta($orderID, 'omnisend_last_sync', $status);
            }
        } else {
            if (wp_next_scheduled('omnisend_init_orders_sync')) {
                wp_clear_scheduled_hook('omnisend_init_orders_sync');
            }
        }
    }

    //unsynced contacts sync via batches
    public static function syncAllContacts()
    {
        if (get_option("omnisend_sync_contacts_finished") != 1 && !empty(get_option('omnisend_api_key', null))) {
            $wp_user_query = new WP_User_Query(array(
                'number' => 1000,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'omnisend_last_sync',
                        'compare' => 'NOT EXISTS',
                        'value' => '',
                    ),
                ),
            ));
            $users = $wp_user_query->get_results();

            if (empty($users)) {
                OmnisendLogger::info('Initial contacts sync finished');
                update_option('omnisend_sync_contacts_finished', 1);
                if (wp_next_scheduled('omnisend_init_contacts_sync')) {
                    wp_clear_scheduled_hook('omnisend_init_contacts_sync');
                }

                return;
            }

            //form batch request and save batchID
            $args = array();
            $args["method"] = "POST";
            $args["endpoint"] = "contacts";
            $args["items"] = array();
            foreach ($users as $user) {
                $preparedContact = OmnisendContact::create($user);
                if ($preparedContact) {
                    $preparedContact = OmnisendHelper::cleanModelFromEmptyFields($preparedContact);
                    $args["items"][] = $preparedContact;
                }
            }
            $link = OMNISEND_URL . "batches";
            $response = OmnisendHelper::omnisendApi($link, "POST", $args);
            if ($response['code'] >= 200 && $response['code'] < 300) {
                //batch ID
                $status = date(DATE_ATOM, time());
                $r = json_decode($response['response'], true);
                $batchID = $r["batchID"];
                if (strlen($batchID) == 24) {
                    //write batch to check response later
                    $batches_inProgress = get_option("omnisend_batches_inProgress");
                    if (!is_array($batches_inProgress)) {
                        $batches_inProgress = array();
                    }
                    if (!in_array($batchID, $batches_inProgress)) {
                        $batches_inProgress[] = $batchID;
                        update_option("omnisend_batches_inProgress", $batches_inProgress);
                    }
                    OmnisendLogger::generalLogging("info", "batches", $link, 'Batch initial sync contacts was succesfully pushed to Omnisend.');
                    OmnisendLogger::countItem("contacts", $r['totalCount']);
                } else {
                    OmnisendLogger::generalLogging("warn", "batches", $link, 'Batch error: unable to initial sync contacts to Omnisend.');
                    $status = "error";
                }
            } else {
                OmnisendLogger::generalLogging("warn", "batches", $link, 'Batch error: unable to initial sync contacts to Omnisend.');
                $status = "error";
            }

            foreach ($users as $user) {
                //update contacts with last update date or "error"
                update_user_meta($user->ID, 'omnisend_last_sync', $status);
            }
        } else {
            if (wp_next_scheduled('omnisend_init_contacts_sync')) {
                wp_clear_scheduled_hook('omnisend_init_contacts_sync');
            }
        }
    }

    public static function initSync()
    {
        //products
        $finished_p = get_option('omnisend_sync_products_finished', 0);
        if (wp_next_scheduled('omnisend_init_products_sync') && $finished_p == 1) {
            wp_clear_scheduled_hook('omnisend_init_products_sync');
        } elseif (!wp_next_scheduled('omnisend_init_products_sync') && $finished_p != 1) {
            wp_schedule_event(time(), 'two_minutes', 'omnisend_init_products_sync');
        }

        //orders
        $finished_o = get_option('omnisend_sync_orders_finished', 0);
        if (wp_next_scheduled('omnisend_init_orders_sync') && $finished_o == 1) {
            wp_clear_scheduled_hook('omnisend_init_orders_sync');
        } elseif (!wp_next_scheduled('omnisend_init_orders_sync') && $finished_o != 1) {
            wp_schedule_event(time(), 'two_minutes', 'omnisend_init_orders_sync');
        }

        //contacts
        $finished_c = get_option('omnisend_sync_contacts_finished', 0);
        if (wp_next_scheduled('omnisend_init_contacts_sync') && $finished_c == 1) {
            wp_clear_scheduled_hook('omnisend_init_contacts_sync');
        } elseif (!wp_next_scheduled('omnisend_init_contacts_sync') && $finished_c != 1) {
            wp_schedule_event(time(), 'two_minutes', 'omnisend_init_contacts_sync');
        }

        //categories
        $finished_cat = get_option('omnisend_sync_categories_finished', 0);
        if (wp_next_scheduled('omnisend_init_categories_sync') && $finished_cat == 1) {
            wp_clear_scheduled_hook('omnisend_init_categories_sync');
        } elseif (!wp_next_scheduled('omnisend_init_categories_sync') && $finished_cat != 1) {
            wp_schedule_event(time(), 'two_minutes', 'omnisend_init_categories_sync');
        }

        //batch check cron
        if (!wp_next_scheduled('omnisend_batch_check')) {
            wp_schedule_event(time(), 'two_minutes', 'omnisend_batch_check');
        }

    }

}

add_action('omnisend_init_products_sync', 'OmnisendManagerAssistant::syncAllProducts');
add_action('omnisend_init_categories_sync', 'OmnisendManagerAssistant::syncAllCategories');
add_action('omnisend_init_orders_sync', 'OmnisendManagerAssistant::syncAllOrders');
add_action('omnisend_init_contacts_sync', 'OmnisendManagerAssistant::syncAllContacts');
add_action('omnisend_batch_check', 'OmnisendManagerAssistant::batchCheck');
