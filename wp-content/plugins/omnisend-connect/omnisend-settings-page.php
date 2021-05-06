<?php
/*Plugin settings View page*/
function omnisend_show_settings_page()
{
    global $omnisendPluginVersion;
    //action
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case "omnisend_save_tag":
                $tag = sanitize_text_field($_POST["tag"]);
                if ($tag) {
                    update_option("omnisend_contact_tag", $tag);
                } else {
                    delete_option("omnisend_contact_tag");
                }
                break;
            case "omnisend_init_resync":
                delete_option('omnisend_sync_products_finished');
                delete_option('omnisend_sync_orders_finished');
                delete_option('omnisend_sync_contacts_finished');
                delete_option('omnisend_sync_categories_finished');
                delete_option("omnisend_initial_sync");
                delete_metadata("post", "0", "omnisend_last_sync", 'error', true);
                delete_metadata("user", "0", "omnisend_last_sync", 'error', true);
                delete_metadata("term", "0", "omnisend_last_sync", 'error', true);
                break;
        }
    }

    ?>
	<div class="settings-page">
	    <div class="omnisend-logo"><a href="http://www.omnisend.com" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__) . 'assets/img/logo.svg'; ?>"></a></div>
		<h1>Omnisend Plugin for Woocommerce - v.<?php echo $omnisendPluginVersion; ?></h1>
	<?php

/*Check if WooCommerce is active*/
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

        $omnisend_api_key = get_option('omnisend_api_key', null);
        $omnisend_account_id = get_option('omnisend_account_id', null);

        //setup initial sync
        if ($omnisend_api_key !== null) {
            if (get_option('omnisend_initial_sync', null) == null) {
                //setup initial sync
                OmnisendManagerAssistant::initSync();
                update_option('omnisend_initial_sync', date(DATE_ATOM, time()));
            }
        }
        ?>

		<div class="api-key-status">
			<?php
if ($omnisend_api_key !== null) {
            echo '<h3 class="current-api-key">Your site is now successfully connected to Omnisend.<p><span><b>Used API KEY:</b> <span>' . substr_replace($omnisend_api_key, " ...", -15) . '</span></h3><a class="change_api_key">Use different API key</a>';
            $api_dn = "omnisend_dn";
        } else {
            echo '<h4>1. If you do not have Omnisend account yet, please sign up <a href="https://app.omnisend.com/registration" target="_blank">here</a></h4>';
            echo '<h4>2. Paste your Omnisend API key, acquired from your account:</h4>';
            $api_dn = "";
        }
        ?>
        <div class="api-key-form-wrapper <?php echo $api_dn ?>">
        <form id="api-key-form">
				<input type="text" name="api-key" id="api-key" class="regular-text" placeholder="API key">
				<input type="submit" name="api-key-submit" id="api-key-submit" class="button button-primary" value="Save">
                <div class="spinner omni_loader"></div>
			</form>
			<h4 class="response-message"></h4>
			<p><a href="https://support.omnisend.com/api-documentation/generating-api-key" target="_blank">How to acquire Omnisend API key</a></p>
            <p>If your current Omnisend account is already connected with another site, you will need to create a new account and generate your new API key there for this site.</p>
         </div>
		</div>



		<?php
if ($omnisend_api_key !== null) {
            $counts = OmnisendLogger::getSyncCount();
            $permissions = OmnisendManagerAssistant::getApiKeyPermissions();
            $listID = get_option('omnisend_list_id', null);
            $tag = get_option('omnisend_contact_tag', null);
            if ($tag == "" && $listID != "") {
                $list = OmnisendManagerAssistant::getList($listID);
                if ($list && array_key_exists("name", $list) && array_key_exists("listID", $list)) {
                    $tag = mb_substr($list["name"], 0, 60) . " listid:" . $list["listID"];
                    update_option("omnisend_contact_tag", $tag);
                }
            }

            ?>
            <div class="logger-section">
            <h3>Tag settings</h3>
            <p>Contacts will be synced with tag:</p>
            <form method='post'>
                    <input type='hidden' name='action' value='omnisend_save_tag'>
                    <input type="text" name='tag' class='regular-text' value="<?php echo $tag; ?>">
                    <input type='submit' value='Update' class='button button-primary clean-log'>
                    </form>
        </div>
            <div class="logger-section">
            <h3>API Key permissions</h3>
            <?php
$err = 0;
            foreach ($permissions as $key => $p) {
                echo "<b>" . ucfirst($key) . ": </b>";
                if ($p) {
                    echo "OK";
                } else {
                    echo "<span class='omnisend-warn'>Error</span>";
                    $err++;
                }
                echo "<br>";
            }
            if ($err > 0) {
                echo "<p class='omnisend-warn'>Please check API Key permissions at <a href='https://app.omnisend.com'>app.omnisend.com</a></p>";
            }
            ?>
        </div>
		<div class="logger-section">
            <h3>Sync statistics</h3>
			<p>Request to Omnisend count:</p>
			<b>Contacts:</b> <?php echo $counts['contacts']; ?><br>
			<b>Orders:</b> <?php echo $counts['orders']; ?><br>
            <b>Products:</b> <?php echo $counts['products']; ?><br>
            <b>Categories:</b> <?php echo $counts['categories']; ?><br>
            <b>Carts:</b> <?php echo $counts['carts']; ?><br>
        </div>
        <br/>
        <?php
if (get_option('omnisend_sync_products_finished', null) & get_option('omnisend_sync_orders_finished', null) & get_option('omnisend_sync_contacts_finished', null) & get_option('omnisend_sync_categories_finished', null)) {
                ?>
        <form method='post'>
            <input type='hidden' name='action' value='omnisend_init_resync'>
            <input type='submit' value='Sync unsynced' class='button button-primary clean-log'>
        </form>
        <?php
} else {
                echo "<b>Sync in progress.</b>";
            }
        }
    } else {
        /*If Woocommerce is not Installed - message with Woocommerce installation link*/
        $install_link = esc_url(network_admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'));
        if (OmnisendHelper::checkWpWcCompatibility()) {
            ?>
				<div class="omnisend-page">
					<h2 class="omnisend-warning">Please, Install or Activate <a href="<?php echo $install_link; ?>">Woocommerce</a>!</h2>
				</div>
<?php
} else {
            /*If Wordpress version is not supported by Woocommerce - show message*/
            ?>
				<div class="omnisend-page">
					<h2 class="omnisend-warning">Please update Wordpress - current version is not supported by actual Woocommerce version!</h2>
				</div>
<?php
}
    }
    ?>
	</div>
<?php
}
?>