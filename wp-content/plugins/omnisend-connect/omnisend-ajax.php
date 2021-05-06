<?php
if (!defined('ABSPATH')) {
    exit;
}

//OmnisendManagerAssistant::
/*Update Omnisend API key*/
add_action('wp_ajax_save_omnisend_api_key', 'save_omnisend_api_key');
add_action('wp_ajax_nopriv_save_omnisend_api_key', 'save_omnisend_api_key');

function save_omnisend_api_key()
{
    $result = [];
    $result['success'] = false;
    $result['omnisend_api_key'] = sanitize_text_field(trim($_POST['omnisend_api_key']));

    if (isset($result['omnisend_api_key']) && $result['omnisend_api_key'] == get_option('omnisend_api_key', null)) {
        $result['msg'] = "Error: this API key is already in use, please use a new one.";
        OmnisendLogger::warning($result['msg']);
    } else if (isset($result['omnisend_api_key'])) {

        $account_id = substr($result['omnisend_api_key'], 0, strpos($result['omnisend_api_key'], '-'));
        //check if there was different accound ID and set resync
        if (get_option("omnisend_account_id", null) && get_option("omnisend_account_id", null) != $account_id) {
            delete_metadata("post", "0", "omnisend_last_sync", '', true);
            delete_metadata("user", "0", "omnisend_last_sync", '', true);
            delete_metadata("term", "0", "omnisend_last_sync", '', true);
            delete_option("omnisend_initial_sync");
        }

        /*Save Account ID into database*/
        update_option('omnisend_account_id', $account_id);

        /*Check if API key is valid*/
        $link = OMNISEND_URL . "accounts";
        $response = OmnisendHelper::omnisendApi($link, "GET", ['apiKey' => $result['omnisend_api_key']]);
        if ($response['code'] == 200) {
            $r = json_decode($response['response'], true);
            if ($r['verified'] == true) {
                $result['success'] = true;
                //write to DB
                // /*Save API key into database*/
                update_option('omnisend_api_key', $result['omnisend_api_key']);
                OmnisendManager::updateAccountInfo();
            } else {
                //try to verify
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                $link = OMNISEND_URL . "accounts";

                $data = OmnisendHelper::getAccountInfo();
                $data['apiKey'] = $result['omnisend_api_key'];
                $data['verificationUrl'] = plugin_dir_url(__FILE__) . "omnisend-verify.php";
                $response = OmnisendHelper::omnisendApi($link, "POST", $data);
                if ($response['code'] == 200) {
                    //got answer
                    $r = json_decode($response['response'], true);
                    if ($r['verified'] == true) {
                        $result['success'] = true;
                        $result['msg'] = "Account verified.";
                        OmnisendLogger::info('Verifiction successful.');
                        //write to DB
                        // /*Save API key into database*/
                        update_option('omnisend_api_key', $result['omnisend_api_key']);

                        OmnisendLogger::info('API KEY saved. ');
                        OmnisendManagerAssistant::initSync("all");

                        $response['api_key'] = $result['omnisend_api_key'];
                        $response['body'] = 'API key setted successfully! All Contacts, Products and Orders will be synchronized with Omnisend in Background Process.';

                    } else {
                        if (array_key_exists('error', $r) && $r['error'] != "") {
                            $result['msg'] = $r['error'];
                            OmnisendLogger::generalLogging("warn", "accounts", $link, $r['error']);
                        } else {
                            $result['msg'] = "Error: we are unable to verify your site. Please check if your site is accessible and retry. Refer to our <a href='https://support.omnisend.com/' target='_blank'>Knowledge Base</a> if the issue persists.";
                            OmnisendLogger::generalLogging("warn", "accounts", $link, $result['msg']);
                        }
                    }
                } else {
                    $result['msg'] = "Error: while API key is correct, we are unable to verify your site. Please try again in a couple of minutes. Refer to our <a href='https://support.omnisend.com/' target='_blank'>Knowledge Base</a> if the issue persists.";
                    OmnisendLogger::generalLogging("warn", "accounts", $link, $result['msg']);
                }
            }
        } else {
            $result['msg'] = "Error: we are unable to verify your site. Please check if your API key is correct. Refer to our <a href='https://support.omnisend.com/' target='_blank'>Knowledge Base</a> to troubleshoot.";
            OmnisendLogger::warning($result['msg']);
            delete_option('omnisend_account_id');
        }
    }
    echo json_encode($result);
    exit;
}
