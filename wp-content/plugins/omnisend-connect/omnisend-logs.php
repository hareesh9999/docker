<?php

/*Plugin view logs page*/
function omnisend_show_logs()
{
    if (!class_exists('WP_List_Table')) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }

    if (isset($_GET['action'])) {
        if ($_GET["action"] == "log_options") {
            if ($_GET["enable"] == 1) {
                update_option("omnisend_logEnabled", 1);
            } else {
                delete_option("omnisend_logEnabled");
            }
        } else if ($_GET['action'] == "clean_log") {
            OmnisendLogger::cleanLogFile();
        }
    }
    $logEnabled = get_option("omnisend_logEnabled");
    ?>
	<div class="settings-page">
	    <div class="omnisend-logo"><a href="http://www.omnisend.com" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__) . 'assets/img/logo.svg'; ?>"></a></div>
		<h1>Omnisend Plugin for Woocommerce - Log file</h1>
	<?php
if ($logEnabled == 1) {
        echo "<div class='logging_status logging_enabled'>Logs are  enabled. <a href='" . $_SERVER['REQUEST_URI'] . "&action=log_options&enable=0'>Disable</a> </div>";
    } else {
        echo "<div class='logging_status logging_disabled'>Logs are  disabled. <a href='" . $_SERVER['REQUEST_URI'] . "&action=log_options&enable=1'>Enable</a></div>";
    }
    $logs = OmnisendLogger::showLogs();
    if (sizeof($logs) == 0) {
        echo '<div>Logfile is clean!</div>';
    } else {
        $fs = sizeof($logs);
        if ($fs > 100000) {
            echo "<div class='logging_status logging_red'>Log file size is > 100M. Logging disabled. Clean log file and enable it.</div>";
        } else if ($fs > 10000) {
            echo "<div class='logging_status logging_disabled'>Log file size is > 10M. PLease clean log.</div>";
        }
        echo "<table class='wp-list-table widefat fixed striped posts'>
        <thead>
            <tr>
                <td class='fixed_date'>Date, GMT</td>
                <td class='fixed_type'>Type</td>
                <td class='fixed_endpoint'>Endpoint</td>
                <td class='fixed_url'>Url</td>
                <td>Message</td>
            </tr>
        </thead>";
        foreach ($logs as $log) {
            echo "<tr><td class='fixed_date'>" . $log->date . "</td>
            <td class='fixed_type omnisend-" . $log->type . "'>" . $log->type . "</td>
            <td class='fixed_endpoint'>" . $log->endpoint . "</td>
            <td class='fixed_url'>" . $log->url . "</td>
            <td>" . $log->message . "</td></tr>";
        }
        echo "</tabvle>";

        if ($logEnabled == 1) {
            $lg = "checked";
        } else {
            $lg = "";
        }
        ?>
        <div>
        <p><a href="admin.php?page=omnisend-logs&action=clean_log" class="button button-primary clean-log">Clean log</a></p>
		<?php
}
    ?>
    </div>
    </div>
	</div>
	<?php

}

?>