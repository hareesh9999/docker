<?php
if (!defined('ABSPATH')) {
    exit;
}
class OmnisendOrder
{
    public $orderID;
    public $email;
    public $cartID;
    public $attributionID;
    public $shippingMethod;
    public $discountValue;
    public $currency;
    public $orderSum;
    public $subTotalSum;
    public $discountSum;
    public $taxSum;
    public $createdAt;
    public $paymentMethod;
    public $billingAddress = [];
    public $shippingAddress = [];
    public $products = [];

    public static function create($orderID)
    {
        try {
            return new OmnisendOrder($orderID);
        } catch (OmnisendEmptyRequiredFieldsException $exception) {
            return null;
        }
    }

    private function __construct($orderID)
    {

        $order = wc_get_order($orderID);
        if (empty($order)) {
            throw new OmnisendEmptyRequiredFieldsException();
        }

        $orderData = $order->get_data();
        $this->orderID = "" . $orderID;
        $email = $order->get_billing_email();
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->email = $email;
        } else {
            throw new OmnisendEmptyRequiredFieldsException();
        }

        $this->cartID = "" . get_post_meta($orderID, "omnisend_cartID", true);

        if (isset($_COOKIE['omnisendAttributionID'])) {
            $this->attributionID = $_COOKIE['omnisendAttributionID'];
        }

        $this->shippingMethod = $order->get_shipping_method();
        $this->currency = $order->get_currency();
        $this->orderSum = OmnisendHelper::priceToCents($order->get_total());
        $this->subTotalSum = OmnisendHelper::priceToCents($order->get_subtotal());
        $this->discountSum = $order->get_total_discount() ? OmnisendHelper::priceToCents($order->get_total_discount()) : null;
        $this->taxSum = $order->get_total_tax() ? OmnisendHelper::priceToCents($order->get_total_tax()) : null;
        $this->shippingSum = $order->get_total_shipping() ? OmnisendHelper::priceToCents($order->get_total_shipping()) : null;
        $this->createdAt = empty($order->get_date_created()) ? date(DATE_ATOM, time()) : $order->get_date_created()->format(DATE_ATOM);
        $this->updatedAt = empty($order->get_date_modified()) ? date(DATE_ATOM, time()) : $order->get_date_modified()->format(DATE_ATOM);

        if ($order->get_user()) {
            $this->orderUrl = $order->get_view_order_url() ? esc_url($order->get_view_order_url()) : null;
        }

        $this->paymentMethod = $order->get_payment_method_title();

        if (!empty($order->get_date_paid())) {
            $this->paymentStatus = "paid";
        } else {
            $this->paymentStatus = "awaitingPayment";
        }

        switch ($order->get_status()) {
            case "processing":
                $this->fulfillmentStatus = "inProgress";
                break;
            case "pending":
                $this->fulfillmentStatus = "unfulfilled";
                break;
            case "completed":
                $this->fulfillmentStatus = "fulfilled";
                break;
            case "refunded":
                $this->paymentStatus = "refunded";
                break;
            case "cancelled":
                $this->paymentStatus = "voided";
                $this->canceledDate = empty($order->get_date_modified()) ? date(DATE_ATOM, time()) : $order->get_date_modified()->format(DATE_ATOM);
                break;
        }

        $this->source = $orderData["created_via"];
        $this->contactNote = $order->get_customer_note();

        $this->billingAddress['firstName'] = $orderData['billing']['first_name'];
        $this->billingAddress['lastName'] = $orderData['billing']['last_name'];
        $this->billingAddress['company'] = $orderData['billing']['company'];
        if ($orderData['billing']['country'] && OmnisendHelper::validCountryCode($orderData['billing']['country'])) {
            $this->billingAddress['countryCode'] = $orderData['billing']['country'];
            $this->billingAddress['country'] = WC()->countries->countries[$this->billingAddress['countryCode']];
        }
        if ($orderData['billing']['state']) {
            $this->billingAddress['state'] = $orderData['billing']['state'];
            $states = WC()->countries->get_states($this->billingAddress['countryCode']);
            $this->billingAddress['stateCode'] = $this->billingAddress['state'];
            $this->billingAddress['state'] = $states[$orderData['billing']['state']];
        }

        $this->billingAddress['city'] = $orderData['billing']['city'];
        $this->billingAddress['address'] = $orderData['billing']['address_1'];
        $this->billingAddress['address2'] = $orderData['billing']['address_2'];
        $this->billingAddress['postalCode'] = $orderData['billing']['postcode'];

        $phoneNumber = $orderData['billing']['phone'];
        if ($phoneNumber) {
            $this->billingAddress['phone'] = $phoneNumber;
        }

        if (!empty($orderData['shipping']['first_name'])) {
            $this->shippingAddress['firstName'] = $orderData['shipping']['first_name'];
        } else {
            $this->shippingAddress['firstName'] = $orderData['billing']['first_name'];
        }

        if (!empty($orderData['shipping']['last_name'])) {
            $this->shippingAddress['lastName'] = $orderData['shipping']['last_name'];
        } else {
            $this->shippingAddress['lastName'] = $orderData['billing']['last_name'];
        }

        if (!empty($orderData['shipping']['company'])) {
            $this->shippingAddress['company'] = $orderData['shipping']['company'];
        } else {
            $this->shippingAddress['company'] = $orderData['billing']['company'];
        }

        if (!empty($orderData['shipping']['country']) && OmnisendHelper::validCountryCode($orderData['shipping']['country'])) {
            $this->shippingAddress['country'] = WC()->countries->countries[$orderData['shipping']['country']];
            $this->shippingAddress['countryCode'] = $orderData['shipping']['country'];
        } else if (!empty($orderData['billing']['countryCode']) && OmnisendHelper::validCountryCode($orderData['shipping']['country'])) {
            $this->shippingAddress['countryCode'] = $orderData['billing']['countryCode'];
        }

        if (!empty($orderData['shipping']['state'])) {
            $states = WC()->countries->get_states($orderData['shipping']['country']);
            if (!empty($states[$orderData['shipping']['state']])) {
                $this->shippingAddress['stateCode'] = $orderData['shipping']['state'];
                $this->shippingAddress['state'] = $states[$orderData['shipping']['state']];
            }
        } else {
            if (array_key_exists("state", $this->billingAddress)) {
                $this->shippingAddress['state'] = $this->billingAddress['state'];
            }
            if (array_key_exists('stateCode', $this->billingAddress)) {
                $this->shippingAddress['stateCode'] = $this->billingAddress['stateCode'];
            }
        }

        if (!empty($orderData['shipping']['city'])) {
            $this->shippingAddress['city'] = $orderData['shipping']['city'];
        } else {
            $this->shippingAddress['city'] = $orderData['billing']['city'];
        }

        if (!empty($orderData['shipping']['address_1'])) {
            $this->shippingAddress['address'] = $orderData['shipping']['address_1'];
        } else {
            $this->shippingAddress['address'] = $orderData['billing']['address_1'];
        }

        if (!empty($orderData['shipping']['address_2'])) {
            $this->shippingAddress['address2'] = $orderData['shipping']['address_2'];
        } else {
            $this->shippingAddress['address2'] = $orderData['billing']['address_2'];
        }

        if (!empty($orderData['shipping']['postcode'])) {
            $this->shippingAddress['postalCode'] = $orderData['shipping']['postcode'];
        } else {
            $this->shippingAddress['postalCode'] = $orderData['billing']['postcode'];
        }

        foreach ($order->get_items() as $item_id => $wc_product_data) {
            $product = [];
            $product['tags'] = [];
            $productValid = true;
            /*Required field*/
            $product['productID'] = "" . $wc_product_data['product_id'];
            if (empty($product['productID'])) {
                $product['productID'] = wc_get_order_item_meta($item_id, '_product_id', true);
                if (empty($product['productID'])) {
                    $productValid = false;
                }

            }
            $product['variantID'] = (!empty($wc_product_data->get_variation_id())) ? "" . $wc_product_data->get_variation_id() : "" . $product['productID'];
            if (empty($product['variantID'])) {
                $productValid = false;
            }

            $wcProduct = $wc_product_data->get_product();
            if (is_object($wcProduct)) {
                $product['sku'] = $wcProduct->get_sku();
                $product['weight'] = $wcProduct->get_weight() ? intval($wcProduct->get_weight()) : null;
                $urlTmp = parse_url(wp_get_attachment_url($wcProduct->get_image_id()));
                if (array_key_exists('path', $urlTmp) && $urlTmp['path'] !== '') {
                    $product['imageUrl'] = $urlTmp['scheme'] . '://' . $urlTmp['host'] . $urlTmp['path'];
                }
            }

            $product['variantTitle'] = $wc_product_data->get_name();

            /*Required field*/
            $product['title'] = $wc_product_data->get_name();
            if (empty($product['title'])) {
                $productValid = false;
            }

            /*Required field*/
            $product['quantity'] = intval($wc_product_data->get_quantity());
            if (!isset($product['quantity'])) {
                $productValid = false;
            }

            /*Required field*/
            $product['price'] = OmnisendHelper::priceToCents($order->get_item_total($wc_product_data, true, false));
            if (!isset($product['price'])) {
                $productValid = false;
            }
            $product['categoryIDs'] = array_map('strval', wp_get_post_terms($product['productID'], 'product_cat', array('fields' => 'ids')));

            $productTags = get_the_terms($wc_product_data['product_id'], 'product_tag');
            if (!empty($productTags) && !is_wp_error($productTags)) {
                foreach ($productTags as $term) {
                    array_push($product['tags'], $term->name);
                }
            }

            $product['productUrl'] = get_permalink($wc_product_data['product_id']);
            if ($productValid) {
                array_push($this->products, $product);
            }
        }
        if (empty($this->orderID) || empty($this->email) || empty($this->currency) || !isset($this->orderSum) || empty($this->createdAt)) {
            throw new OmnisendEmptyRequiredFieldsException();
        }

    }

}
