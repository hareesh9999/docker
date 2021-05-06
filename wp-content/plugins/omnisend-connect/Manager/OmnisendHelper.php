<?php
if (!defined('ABSPATH')) {
    exit;
}

class OmnisendHelper
{

    public static $numberOfCurlRepeats = 1;

    public static $validCountries = array("AD", "AE", "AF", "AG", "AI", "AL", "AM", "AO", "AQ", "AR", "AS", "AT", "AU", "AW", "AX", "AZ", "BA", "BB", "BD", "BE", "BF", "BG", "BH", "BI", "BJ", "BL", "BM", "BN", "BO", "BQ", "BR", "BS", "BT", "BV", "BW", "BY", "BZ", "CA", "CC", "CD", "CF", "CG", "CH", "CI", "CK", "CL", "CM", "CN", "CO", "CR", "CU", "CV", "CW", "CX", "CY", "CZ", "DE", "DJ", "DK", "DM", "DO", "DZ", "EC", "EE", "EG", "EH", "ER", "ES", "ET", "FI", "FJ", "FK", "FM", "FO", "FR", "GA", "GB", "GD", "GE", "GF", "GG", "GH", "GI", "GL", "GM", "GN", "GP", "GQ", "GR", "GS", "GT", "GU", "GW", "GY", "HK", "HM", "HN", "HR", "HT", "HU", "ID", "IE", "IL", "IM", "IN", "IO", "IQ", "IR", "IS", "IT", "JE", "JM", "JO", "JP", "KE", "KG", "KH", "KI", "KM", "KN", "KP", "KR", "KW", "KY", "KZ", "LA", "LB", "LC", "LI", "LK", "LR", "LS", "LT", "LU", "LV", "LY", "MA", "MC", "MD", "ME", "MF", "MG", "MH", "MK", "ML", "MM", "MN", "MO", "MP", "MQ", "MR", "MS", "MT", "MU", "MV", "MW", "MX", "MY", "MZ", "NA", "NC", "NE", "NF", "NG", "NI", "NL", "NO", "NP", "NR", "NU", "NZ", "OM", "PA", "PE", "PF", "PG", "PH", "PK", "PL", "PM", "PN", "PR", "PS", "PT", "PW", "PY", "QA", "RE", "RO", "RS", "RU", "RW", "SA", "SB", "SC", "SD", "SE", "SG", "SH", "SI", "SJ", "SK", "SL", "SM", "SN", "SO", "SR", "SS", "ST", "SV", "SX", "SY", "SZ", "TC", "TD", "TF", "TG", "TH", "TJ", "TK", "TL", "TM", "TN", "TO", "TR", "TT", "TV", "TW", "TZ", "UA", "UG", "UM", "US", "UY", "UZ", "VA", "VC", "VE", "VG", "VI", "VN", "VU", "WF", "WS", "YE", "YT", "ZA", "ZM", "ZW");

    public static function validCountryCode($countryCode)
    {
        if (in_array($countryCode, Omnisendhelper::$validCountries)) {
            return true;
        }
        return false;
    }

    public static function cleanModelFromEmptyFields($modelObject)
    {
        $cleanModelObject = [];

        foreach ($modelObject as $key => $value) {
            // if ($value != null && $value != "") {
            if (isset($value)) {
                if (is_array($value)) {
                    $cleanModelObject[$key] = OmnisendHelper::cleanModelFromEmptyFields($value);
                } else {
                    $cleanModelObject[$key] = $value;
                }
            }
        }

        return $cleanModelObject;
    }

    public static function validatePhoneNumber($number)
    {
        $phoneNumber = '+' . preg_replace('/[^0-9]/', '', $number);
        $regexp = '/^\++?[0-9]\d{6,14}$/';
        preg_match_all($regexp, $phoneNumber, $matches, PREG_SET_ORDER, 0);
        if (count($matches) > 0) {
            return $phoneNumber;
        } else {
            return false;
        }
    }

    // Make request to Omnisend API
    public static function omnisendApi($link, $method = "POST", $postfields = [])
    {
        global $omnisendPluginVersion;
        OmnisendHelper::$numberOfCurlRepeats++;
        $apiKey = get_option('omnisend_api_key', null);
        if (is_array($postfields) && isset($postfields['apiKey'])) {
            $apiKey = $postfields['apiKey'];
        }
        $result = [];
        $data_string = [];

        if (!empty($postfields)) {
            $data_string = json_encode($postfields, JSON_UNESCAPED_SLASHES);
        }

        $timeout = ini_get('max_execution_time');
        if ($timeout > 10 && $timeout <= 30) {
            $timeout = $timeout - 2;
        } else {
            $timeout = 30;
        }

        $headers = array('Content-Type' => 'application/json', 'X-API-KEY' => $apiKey);
        switch ($method) {
            case "GET":
                $api_response = wp_remote_get($link, array(
                    'timeout' => $timeout,
                    'redirection' => 3,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => $headers,
                    //'body' => $data_string,
                    'cookies' => array(),
                ));
                break;
            default:
                $api_response = wp_remote_post($link, array(
                    'method' => $method,
                    'timeout' => $timeout,
                    'redirection' => 3,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => $headers,
                    'body' => $data_string,
                    'cookies' => array(),
                ));
        }

        if (is_wp_error($api_response)) {
            $result['code'] = '500';
            $result['response'] = $api_response->get_error_message();
        } else {
            $result['code'] = $api_response['response']['code'];
            $result['response'] = $api_response['body'];

            if (OmnisendHelper::$numberOfCurlRepeats == 1 && (intval($result['code']) == 408 || intval($result['code']) == 429 || intval($result['code']) == 503)) {
                $result = OmnisendHelper::omnisendApi($link, $method, $postfields);
            } else {
                OmnisendHelper::$numberOfCurlRepeats = 0;
            }
        }

        return $result;
    }

    public static function checkWpWcCompatibility()
    {

        $wcPath = WP_PLUGIN_DIR . '/woocommerce/readme.txt';
        $versionLine = '';

        if (file_exists($wcPath)) {
            $fh = fopen($wcPath, 'r');
            while ($line = fgets($fh)) {
                if (strpos($line, 'Requires at least') > -1) {
                    $versionLine .= $line;
                }
            }
            fclose($fh);
            $wcSupportedVersion = str_replace('Requires at least: ', '', $versionLine);

            if (floatval(get_bloginfo('version')) < floatval($wcSupportedVersion)) {
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    public static function priceToCents($price)
    {
        return intval(number_format((float) $price * 100, 0, '.', ''));
    }

    public static function omnisendPluginVersion()
    {
        $wcPath = __DIR__ . '/../readme.txt';
        $versionLine = '';
        $version = '';
        if (file_exists($wcPath)) {
            $fh = fopen($wcPath, 'r');
            while ($line = fgets($fh)) {
                if (strpos($line, 'Stable tag:') > -1) {
                    $versionLine .= $line;
                }
            }
            fclose($fh);
            $version = str_replace('Stable tag: ', '', $versionLine);
            $version = preg_replace("/\r|\n/", "", $version);
        }
        return $version;
    }

    public static function getAccountInfo()
    {
        global $omnisendPluginVersion;
        $data = array();
        //php version
        preg_match("#^\d+(\.\d+)*#", defined('PHP_VERSION') ? PHP_VERSION : phpversion(), $phpver);
        $web = explode(" ", $_SERVER['SERVER_SOFTWARE']);

        $timeout = ini_get('max_execution_time');
        if ($timeout > 10 && $timeout <= 30) {
            $timeout = $timeout - 2;
        } else {
            $timeout = 30;
        }

        $data = array(
            'website' => get_home_url(),
            'platform' => 'woocommerce',
            'version' => $omnisendPluginVersion,
            'timeout' => $timeout,
            'createdAt' => date(DATE_ATOM, time()),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : "",
            'webserver' => $web[0],
            'phpVersion' => $phpver[0],
            'platformVersion' => get_bloginfo('version'),
        );
        return $data;
    }

}
