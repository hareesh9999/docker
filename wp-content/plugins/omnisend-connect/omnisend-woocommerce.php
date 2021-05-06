<?php
/**
 * Plugin Name: Omnisend for Woocommerce
 * Plugin URI: https://www.omnisend.com
 * Description: This plugin connects your Woocommerce site with your Omnisend account.
 * Version: 1.7.6
 * Author: Omnisend
 * Author URI: https://www.omnisend.com
 * Developer: Omnisend
 * Developer URI: https://developers.omnisend.com
 *
 * WC requires at least: 2.2
 * WC tested up to: 5.1.0
 *
 * Copyright: © 2018 Omnisend
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define("OMNISEND_URL", "https://api.omnisend.com/v3/");
define("OMNISEND_SRC", "https://omnisnippet1.com/");

include_once 'Manager/OmnisendLogger.php';
include_once 'Manager/OmnisendManagerAssistant.php';
include_once 'Manager/OmnisendHelper.php';
include_once 'Manager/OmnisendManager.php';
include_once 'Manager/OmnisendUpdates.php';
/*Include Model classes*/
include_once 'Model/OmnisendProduct.php';
include_once 'Model/OmnisendContact.php';
include_once 'Model/OmnisendCart.php';
include_once 'Model/OmnisendOrder.php';
include_once 'Model/OmnisendCategory.php';
/*Include Ajax functions*/
include_once 'omnisend-ajax.php';
/*Include settings page display function*/
include_once 'omnisend-settings-page.php';
/*Include logs page display function*/
include_once 'omnisend-logs.php';
/*Include Woocommerce hooks*/
include_once 'omnisend-woocommerce-hooks.php';
/*Include Woocommerce hooks*/
include_once 'omnisend-restore-cart.php';

$omnisendPluginVersion = OmnisendHelper::omnisendPluginVersion();

/*Declare plugin's settings page*/
add_action('admin_menu', 'omnisend_woocommerce_menu');

function omnisend_woocommerce_menu()
{

    $page_title = 'Omnisend for Woocommerce';
    $menu_title = 'Omnisend';
    $capability = 'manage_options';
    $menu_slug = 'omnisend-woocommerce';
    $function = 'omnisend_show_settings_page';
    $icon_url = plugin_dir_url(__FILE__) . 'assets/img/omnisend-icon.png';

    $position = 2;

    add_menu_page($page_title,
        $menu_title,
        $capability,
        $menu_slug,
        $function,
        $icon_url,
        $position);

    add_submenu_page($menu_slug, __('Logs'), __('Logs'), $capability, 'omnisend-logs', 'omnisend_show_logs');

}

/*Include scripts and styles for settings page*/
add_action('admin_enqueue_scripts', 'omnisend_admin_scripts_and_styles');
function omnisend_admin_scripts_and_styles()
{
    if (isset($_GET['page'])) {
        if ($_GET['page'] == 'omnisend-woocommerce' || $_GET['page'] == 'omnisend-logs') {
            wp_enqueue_script('omnisend-admin-script.js', plugin_dir_url(__FILE__) . 'assets/js/omnisend-admin-script.js?' . time(), array(), '1.0.0', true);
            wp_enqueue_style('omnisend-admin-style.css', plugin_dir_url(__FILE__) . 'assets/css/omnisend-admin-style.css?' . time());
        }
    }
}

/*Include front scripts */
add_action('wp_enqueue_scripts', 'omnisend_front_scripts_and_styles');
function omnisend_front_scripts_and_styles()
{
    wp_enqueue_script('omnisend-front-script.js', plugin_dir_url(__FILE__) . 'assets/js/omnisend-front-script.js?' . time(), array(), '1.0.0', true);
}

/*After plugin activation - go to settings page*/
add_action('activated_plugin', 'omnisend_activation');
function omnisend_activation($plugin)
{
    if ($plugin == plugin_basename(__FILE__)) {
        omnisend_activated();
        exit(wp_redirect(admin_url('admin.php?page=omnisend-woocommerce')));
    }
}

function omnisend_2min_cron($schedules)
{

    $schedules['two_minutes'] = array(
        'interval' => 60 * 2,
        'display' => __('Every 2 minutes', 'omnisend-woocommerce'),
    );

    return $schedules;

}
add_filter('cron_schedules', 'omnisend_2min_cron');

function omnisend_activated()
{
    global $wpdb;
    global $charset_collate;
    $table_name = $wpdb->prefix . "omnisend_logs";
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `date` datetime,
      `type` varchar(10) CHARACTER SET utf8,
      `endpoint` varchar(15) CHARACTER SET utf8,
      `url` varchar(100) CHARACTER SET utf8,
      `message` longtext CHARACTER SET utf8,
       PRIMARY KEY (`id`)
    )$charset_collate;";
    include_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_uninstall_hook(__FILE__, 'omnisend_uninstall');
function omnisend_uninstall()
{
    global $wpdb;
    global $charset_collate;
    $table_name = $wpdb->prefix . "omnisend_logs";
    $sql = "IF EXISTS(SELECT * FROM   $table_name) DROP TABLE $table_name";
    include_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    //remove options
    $plugin_options = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'omnisend_%'");
    foreach ($plugin_options as $option) {
        delete_option($option->option_name);
    }

    delete_metadata("user", "0", "omnisend_last_sync", '', true);
    delete_metadata("post", "0", "omnisend_last_sync", '', true);
    delete_metadata("term", "0", "omnisend_last_sync", '', true);
}
