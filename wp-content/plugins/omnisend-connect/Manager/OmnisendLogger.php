<?php
if (!defined('ABSPATH')) {
    exit;
}

class OmnisendLogger
{

    public static function showLogs()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "omnisend_logs order by id DESC");
    }

    public static function info($message)
    {
        OmnisendLogger::generalLogging('info', '', '', $message);
    }

    public static function warning($message)
    {
        OmnisendLogger::generalLogging('warn', '', '', $message);
    }

    public static function error($message)
    {
        OmnisendLogger::generalLogging('error', '', '', $message);
    }

    public static function cleanLogFile()
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . "omnisend_logs");
    }

    public static function generalLogging($type, $endpoint, $url, $message)
    {
        if (get_option("omnisend_logEnabled") == 1) {
            global $wpdb;
            $table_name = $wpdb->prefix . "omnisend_logs";
            if ($wpdb->get_var("SELECT COUNT(*) FROM $table_name") > 100000) {
                delete_option("omnisend_logEnabled");
            } else {
                $wpdb->insert(
                    $table_name, //table
                    array('type' => $type,
                        'date' => current_time('mysql', 1),
                        'url' => $url,
                        'endpoint' => $endpoint,
                        'message' => $message,
                    )
                );
            }
        }
    }

    public static function countItem($endpoint, $inc = 1)
    {
        $key = "omnisend_" . $endpoint . "_sync_count";
        $count = intval(get_option($key)) + $inc;
        update_option($key, $count);

    }

    public static function getSyncCount()
    {
        $products = get_option("omnisend_products_sync_count");
        $orders = get_option("omnisend_orders_sync_count");
        $contacts = get_option("omnisend_contacts_sync_count");
        $carts = get_option("omnisend_carts_sync_count");
        $categories = get_option("omnisend_categories_sync_count");

        if (!$products) {
            $products = 0;
        }
        if (!$orders) {
            $orders = 0;
        }
        if (!$contacts) {
            $contacts = 0;
        }
        if (!$carts) {
            $carts = 0;
        }

        if (!$categories) {
            $categories = 0;
        }

        $counts = array(
            "products" => $products,
            "contacts" => $contacts,
            "orders" => $orders,
            "carts" => $carts,
            "categories" => $categories,
        );
        return $counts;
    }
}
