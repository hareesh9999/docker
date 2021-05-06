<?php
if (!defined('ABSPATH')) {
    exit;
}
class OmnisendUpdates
{

    public static function update($vFrom, $vTo)
    {
        //run categories sync
        if (version_compare($vFrom, '1.4.0', '<')) {
            $finished_cat = get_option('omnisend_sync_categories_finished', 0);
            if (wp_next_scheduled('omnisend_init_categories_sync') && $finished_cat == 1) {
                wp_clear_scheduled_hook('omnisend_init_categories_sync');
            } elseif (!wp_next_scheduled('omnisend_init_categories_sync') && $finished_cat != 1) {
                wp_schedule_event(time(), 'two_minutes', 'omnisend_init_categories_sync');
            }
        }
    }

}
