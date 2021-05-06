<?php
if (!defined('ABSPATH')) {
    exit;
}
class OmnisendContact
{

    /*Required*/
    public $email;
    public $firstName;
    public $lastName;
    public $country;
    public $state;
    public $city;
    public $address;
    public $postalCode;
    public $phone;
    /*Required*/
    public $status;
    /*Required*/
    public $statusDate;

    public static function create($user)
    {
        try {
            return new OmnisendContact($user);
        } catch (OmnisendEmptyRequiredFieldsException $exception) {
            return null;
        }
    }

    private function __construct($user)
    {

        $email = $user->user_email;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->email = $email;
        }
        if (get_user_meta($user->ID, 'first_name', true) !== '') {
            $this->firstName = get_user_meta($user->ID, 'first_name', true);
        } else if ($_POST && isset($_POST['billing_first_name'])) {
            $this->firstName = sanitize_text_field($_POST['billing_first_name']);
        } else if (get_user_meta($user->ID, 'shipping_first_name', true) !== '') {
            $this->firstName = get_user_meta($user->ID, 'shipping_first_name', true);
        } else if (get_user_meta($user->ID, 'billing_first_name', true) !== '') {
            $this->firstName = get_user_meta($user->ID, 'billing_first_name', true);
        } else {
            $this->firstName = $user->display_name;
        }

        if (get_user_meta($user->ID, 'last_name', true) != '') {
            $this->lastName = get_user_meta($user->ID, 'last_name', true);
        } else if ($_POST && isset($_POST['billing_last_name'])) {
            $this->lastName = sanitize_text_field($_POST['billing_last_name']);
        } else if (get_user_meta($user->ID, 'shipping_last_name', true) != '') {
            $this->lastName = get_user_meta($user->ID, 'shipping_last_name', true);
        } else if (get_user_meta($user->ID, 'billing_last_name', true) != "") {
            $this->lastName = get_user_meta($user->ID, 'billing_last_name', true);
        }
        if ($_POST && isset($_POST['shipping_country']) && OmnisendHelper::validCountryCode($_POST['shipping_country'])) {
            $this->countryCode = sanitize_text_field($_POST['shipping_country']);
        } else if ($_POST && isset($_POST['billing_country']) && OmnisendHelper::validCountryCode($_POST['billing_country'])) {
            $this->countryCode = sanitize_text_field($_POST['billing_country']);
        } else if (($billing_country = get_user_meta($user->ID, 'billing_country', true)) != '' && OmnisendHelper::validCountryCode($billing_country)) {
            $this->countryCode = $billing_country;
        } elseif (($shippingCountry = get_user_meta($user->ID, 'shipping_country', true)) != "" && OmnisendHelper::validCountryCode($shippingCountry)) {
            $this->countryCode = $shippingCountry;
        }

        if (!empty($this->countryCode)) {
            $this->country = WC()->countries->countries[$this->countryCode];
        }

        if (($billing_state = get_user_meta($user->ID, 'billing_state', true)) != '') {
            $this->state = $billing_state;
        } else if (($shipping_state = get_user_meta($user->ID, 'shipping_state', true)) != "") {
            $this->state = $shipping_state;
        } else if ($_POST && isset($_POST['billing_state'])) {
            $this->state = sanitize_text_field($_POST['billing_state']);
        }

        if (!empty($this->state) && !empty($this->countryCode)) {
            $states = WC()->countries->get_states($this->countryCode);
            if (!empty($states[$this->state])) {
                $this->stateCode = $this->state;
                $this->state = $states[$this->state];
            }
        }

        if (($billing_city = get_user_meta($user->ID, 'billing_city', true)) != '') {
            $this->city = $billing_city;
        } else if (($shipping_city = get_user_meta($user->ID, 'shipping_city', true)) != "") {
            $this->city = $shipping_city;
        } else if ($_POST && isset($_POST['billing_city'])) {
            $this->city = sanitize_text_field($_POST['billing_city']);
        }

        $address = '';
        if (($address1 = get_user_meta($user->ID, 'billing_address_1', true)) != '') {
            $address .= $address1;
        } else if (($address1 = get_user_meta($user->ID, 'shipping_address_1', true)) != "") {
            $address .= $address1;
        } else if ($_POST && isset($_POST['billing_address_1'])) {
            $address .= sanitize_text_field($_POST['billing_address_1']);
        }

        if (($address2 = get_user_meta($user->ID, 'billing_address_2', true)) != '') {
            $address .= $address2;
        } elseif (($address2 = get_user_meta($user->ID, 'shipping_address_2', true)) != "") {
            $address .= $address2;
        } else if ($_POST && isset($_POST['billing_address_2'])) {
            $address .= sanitize_text_field($_POST['billing_address_2']);
        }
        $this->address = $address;

        if (($postalCode = get_user_meta($user->ID, 'billing_postcode', true)) != '') {
            $this->postalCode = $postalCode;
        } elseif (($postalCode = get_user_meta($user->ID, 'shipping_postcode', true)) != "") {
            $this->postalCode = $postalCode;
        } else if ($_POST && isset($_POST['billing_postcode'])) {
            $this->postalCode = sanitize_text_field($_POST['billing_postcode']);
        }

        $phoneNumber = get_user_meta($user->ID, 'billing_phone', true);
        if ($phoneNumber) {
            $this->phone = $phoneNumber;
        }

        //set tag/list
        $listID = get_option("omnisend_list_id", null);
        if ($listID) {
            $this->lists[] = array("listID" => $listID);
        }
        $this->tags = array();
        $tag = get_option("omnisend_contact_tag", null);
        if ($tag != "") {
            $this->tags[] = $tag;
        } else {
            $listID = get_option("omnisend_list_id", null);
            if ($listID) {
                $this->lists[] = array("listID" => $listID);
            }
        }
        $this->tags[] = "source: woocommerce";

        $this->status = 'nonSubscribed';
        if ($user->user_registered) {
            $this->statusDate = date(DATE_ATOM, strtotime($user->user_registered));
        } else {
            $this->statusDate = date(DATE_ATOM, time());
        }
        if (empty($this->email) || empty($this->status) || empty($this->statusDate)) {
            throw new OmnisendEmptyRequiredFieldsException();
        }

    }
}
