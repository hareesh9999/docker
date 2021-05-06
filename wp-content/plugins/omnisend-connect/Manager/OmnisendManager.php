<?php
if (!defined('ABSPATH')) {
    exit;
}

$omnisend_product = "";

class OmnisendEmptyRequiredFieldsException extends Exception
{}

class OmnisendManager
{

    /**
     *    Manager's METHODS
     *
     *        pushCartToOmnisend()
     *        pushContactToOmnisend($user)
     *        pushProductToOmnisend($productId)
     *        pushOrderToOmnisend($orderId, $put, $iter)
     *
     *        updateOrderStatus($orderId, $statusType, $orderStatus)
     *
     *        deleteProductFromOmnisend($id)
     *        deleteOrderFromOmnisend($id)
     *
     *        pushAllContactsToOmnisend()
     *        pushAllProductsToOmnisend()
     *        pushAllOrdersToOmnisend()
     */

    /*Push or update Woocommerce cart in Omnisend account*/
    public static function pushCartToOmnisend($put = 0, $iter = 0)
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            $preparedCart = OmnisendCart::create();
            $returnResult = array();
            /*If cart created successfully, push cart to Omnisend*/
            if ($preparedCart) {
                $user_id = get_current_user_id();
                $preparedCart = OmnisendHelper::cleanModelFromEmptyFields($preparedCart);
                $lastSync = "";
                if (null !== WC()->session) {
                    $lastSync = WC()->session->get('omnisend_cart_synced');
                }

                //check if cart wasn't pushed before with same $put
                if (null === WC()->session || WC()->session->get("omnisend_cart") != $preparedCart) {
                    if ($put == 1 || ($lastSync != "" && $put == 0)) {
                        $put = 1;
                        /*If cart already exists - try to update*/
                        $link = OMNISEND_URL . "carts/" . $preparedCart['cartID'];
                        $curlResult = OmnisendHelper::omnisendApi($link, "PUT", $preparedCart);
                        $method = "PUT";
                    } else {
                        $put = 0;
                        $link = OMNISEND_URL . "carts";
                        $curlResult = OmnisendHelper::omnisendApi($link, "POST", $preparedCart);
                        $method = "POST";
                    }
                    if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
                        OmnisendLogger::generalLogging("info", "carts", $link, 'Cart #' . $preparedCart['cartID'] . ' was succesfully pushed to Omnisend.');
                        if (null !== WC()->session) {
                            WC()->session->set('omnisend_cart_synced', 1);
                            WC()->session->set("omnisend_cart", $preparedCart);
                        }
                        OmnisendLogger::countItem("carts");
                    } elseif ($curlResult['code'] == 403) {
                        OmnisendLogger::generalLogging("warn", "carts", $link, 'Unable to push cart #' . $preparedCart['cartID'] . " to Omnisend. You don't have rights to push carts.");
                    } elseif ($curlResult['code'] == 400 || $curlResult['code'] == 404 || $curlResult['code'] == 422) {
                        if ($iter == 0) {
                            //try other method
                            OmnisendManager::pushCartToOmnisend($put + 1, $iter + 1);
                        } else {
                            OmnisendLogger::generalLogging("warn", "carts", $link, 'Unable to push cart #' . $preparedCart['cartID'] . ' to Omnisend.' . $curlResult['response']);
                            if (empty($lastSync) && null !== WC()->session) {
                                WC()->session->set('omnisend_cart_synced', null);
                            }
                        }
                    } else {
                        OmnisendLogger::generalLogging("warn", "carts", $link, 'Unable to push cart #' . $preparedCart['cartID'] . ' to Omnisend. May be server error. ' . $curlResult['response']);
                    }
                    $returnResult['message'] = $curlResult['response'];
                }

            } else {
                $returnResult['message'] = 'Unable to push cart to Omnisend. Cart is empty. Or user was not identified';
            }

            if (isset($curlResult)) {$returnResult['code'] = $curlResult['code'];}
            return $returnResult;
        }

    }

    /*Push or update WP contact in Omnisend account*/
    public static function pushContactToOmnisend($user)
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            $returnResult = array();
            if (!empty($user)) {
                $preparedContact = OmnisendContact::create($user);

                /*If all required fields are set, push contact to Omnisend*/
                if ($preparedContact) {
                    $preparedContact = OmnisendHelper::cleanModelFromEmptyFields($preparedContact);
                    if (is_admin() || null === WC()->session || WC()->session->get("omnisend_contact") != $preparedContact) {
                        /*Add or Update contact*/
                        $link = OMNISEND_URL . "contacts";
                        $curlResult = OmnisendHelper::omnisendApi($link, "POST", $preparedContact);
                        if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
                            $r = json_decode($curlResult['response'], true);

                            OmnisendLogger::generalLogging("info", "contacts", $link, 'Contact ' . $preparedContact['email'] . ' was succesfully pushed to Omnisend.');
                            update_user_meta($user->ID, 'omnisend_last_sync', date(DATE_ATOM, time()));
                            if (!empty($r["contactID"])) {
                                update_user_meta($user->ID, 'omnisend_contactID', $r["contactID"]);
                                $returnResult['contactID'] = $r["contactID"];
                            }
                            if (!is_admin() && null !== WC()->session) {
                                WC()->session->set('omnisend_contact', $preparedContact);
                            }

                            OmnisendLogger::countItem("contacts");
                        } elseif ($curlResult['code'] == 403) {
                            OmnisendLogger::generalLogging("warn", "contacts", $link, 'Unable to push contact ' . $preparedContact['email'] . " to Omnisend. You don't have rights to push contacts.");
                        } elseif ($curlResult['code'] == 400 || $curlResult['code'] == 422) {
                            OmnisendLogger::generalLogging("warn", "contacts", $link, 'Unable to push contact ' . $preparedContact['email'] . ' to Omnisend.' . $curlResult['response']);
                            update_user_meta($user->ID, 'omnisend_last_sync', 'error');
                        } else {
                            OmnisendLogger::generalLogging("warn", "contacts", $link, 'Unable to push contact ' . $preparedContact['email'] . ' to Omnisend. May be server error. ' . $curlResult['response']);
                        }
                        $returnResult['message'] = $curlResult['response'];
                    }

                } else {
                    $returnResult['message'] = 'Unable to push contact ' . $preparedContact['email'] . ' to Omnisend. One or more required fields are empty or invalid';
                    OmnisendLogger::generalLogging("warn", "contacts", $link, $returnResult['message']);
                }
            } else {
                $returnResult['message'] = 'Unable to push contact to Omnisend. User with such ID does not exist';
                OmnisendLogger::generalLogging("warn", "contacts", '', $returnResult['message']);
            }
            if (isset($curlResult)) {$returnResult['code'] = $curlResult['code'];}
            return $returnResult;
        }

    }

    //save contactID to cookie after user identification
    public static function saveContact($user, $contactID = '')
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            if (!is_admin()) {
                if ($contactID == "") {
                    $user_id = $user->ID;
                    $contactID = get_user_meta($user_id, "omnisend_contactID", true);
                    if (empty($contactID) && $user && $user->email) {
                        $contactID = OmnisendManagerAssistant::getContactIDFromOmnisend($user->user_email);
                        OmnisendLogger::generalLogging("info", "contacts", $link, "Got contactID " . $contactID . " for logged in user " . $user->user_email);
                        if (!empty($contactID)) {
                            update_user_meta($user_id, "omnisend_contactID", $contactID);
                        }
                    }
                }

                if (!empty($contactID)) {
                    OmnisendLogger::info("Saving to cookie omnisendContactID: " . $contactID);
                    $path = get_option('siteurl');
                    $host = parse_url(get_option('siteurl'), PHP_URL_HOST);
                    $expiry = strtotime('+1 year');
                    setcookie('omnisendContactID', $contactID, $expiry, "/", $host);
                }
            }
        }

    }

    /*Push or update product category in omnisend account*/
    public static function pushCategoryToOmnisend($termId = '', $put = 0, $iter = 0)
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            $preparedCategory = OmnisendCategory::create($termId);
            $returnResult = array();
            /*If all required fields are set, push product to Omnisend*/
            if ($preparedCategory) {

                $preparedCategory = OmnisendHelper::cleanModelFromEmptyFields($preparedCategory);
                $lastSync = get_term_meta($termId, 'omnisend_last_sync', true);

                if ($put == 1 || (!empty($lastSync) && $lastSync != "error" && $put == 0)) {
                    $put = 1;
                    /*If product already exists - try to update*/
                    $link = OMNISEND_URL . "categories/" . $termId;
                    $curlResult = OmnisendHelper::omnisendApi($link, "PUT", $preparedCategory);
                } else {
                    $put = 0;
                    $link = OMNISEND_URL . "categories";
                    $curlResult = OmnisendHelper::omnisendApi($link, "POST", $preparedCategory);
                }

                if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
                    OmnisendLogger::generalLogging("info", "categories", $link, 'Category #' . $termId . ' was succesfully pushed to Omnisend.');
                    update_term_meta($termId, 'omnisend_last_sync', date(DATE_ATOM, time()));
                    OmnisendLogger::countItem("categories");
                } elseif ($curlResult['code'] == 403) {
                    OmnisendLogger::generalLogging("warn", "categories", $link, 'Unable to push category #' . $termId . " to Omnisend. You don't have rights to push categories.");
                } elseif ($curlResult['code'] == 400 || $curlResult['code'] == 404 || $curlResult['code'] == 422) {
                    if ($iter == 0) {
                        //try other method
                        OmnisendManager::pushCategoryToOmnisend($termId, $put + 1, $iter + 1);
                    } else {
                        OmnisendLogger::generalLogging("warn", "categories", $link, 'Unable to push category #' . $termId . " to Omnisend." . $curlResult['response']);
                        if (empty($lastSync)) {
                            update_term_meta($termId, 'omnisend_last_sync', 'error');
                        }
                    }
                } else {
                    OmnisendLogger::generalLogging("warn", "categories", $link, 'Unable to push category #' . $termId . " to Omnisend. May be server error. " . $curlResult['response']);
                }

                $returnResult['message'] = $curlResult['response'];

            } else {
                $returnResult['message'] = 'Unable to push category #' . $termId . ' to Omnisend. One or more required fields are empty or invalid';
                OmnisendLogger::generalLogging("warn", "categories", '', $returnResult['message']);
            }

            if (isset($curlResult)) {$returnResult['code'] = $curlResult['code'];}
            return $returnResult;
        }

    }

    //Delete category
    public static function deleteCategoryFromOmnisend($id)
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            $link = OMNISEND_URL . "categories/" . $id;
            $curlResult = OmnisendHelper::omnisendApi($link, "DELETE", []);
            return $curlResult['response'];
        }

    }

    /*Push or update product in omnisend account*/
    public static function pushProductToOmnisend($productId = '', $put = 0, $iter = 0)
    {
        global $omnisend_product;
        if (!empty(get_option('omnisend_api_key', null))) {
            $preparedProduct = OmnisendProduct::create($productId);
            $returnResult = array();
            /*If all required fields are set, push product to Omnisend*/
            if ($preparedProduct) {

                $preparedProduct = OmnisendHelper::cleanModelFromEmptyFields($preparedProduct);
                $lastSync = get_post_meta($productId, 'omnisend_last_sync', true);
                if ($omnisend_product != $preparedProduct) {
                    if ($put == 1 || (!empty($lastSync) && $lastSync != "error" && $put == 0)) {
                        $put = 1;
                        /*If product already exists - try to update*/
                        $link = OMNISEND_URL . "products/" . $productId;
                        $curlResult = OmnisendHelper::omnisendApi($link, "PUT", $preparedProduct);
                    } else {
                        $put = 0;
                        $link = OMNISEND_URL . "products";
                        $curlResult = OmnisendHelper::omnisendApi($link, "POST", $preparedProduct);
                    }

                    if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
                        OmnisendLogger::generalLogging("info", "products", $link, 'Product #' . $productId . ' was succesfully pushed to Omnisend.');
                        update_post_meta($productId, 'omnisend_last_sync', date(DATE_ATOM, time()));
                        OmnisendLogger::countItem("products");
                        $omnisend_product = $preparedProduct;
                    } elseif ($curlResult['code'] == 403) {
                        OmnisendLogger::generalLogging("warn", "products", $link, 'Unable to push procduct #' . $productId . " to Omnisend. You don't have rights to push products.");
                    } elseif ($curlResult['code'] == 400 || $curlResult['code'] == 404 || $curlResult['code'] == 422) {
                        if ($iter == 0) {
                            //try other method
                            OmnisendManager::pushProductToOmnisend($productId, $put + 1, $iter + 1);
                        } else {
                            OmnisendLogger::generalLogging("warn", "products", $link, 'Unable to push procduct #' . $productId . " to Omnisend." . $curlResult['response']);
                            if (empty($lastSync)) {
                                update_post_meta($productId, 'omnisend_last_sync', 'error');
                            }
                        }
                    } else {
                        OmnisendLogger::generalLogging("warn", "products", $link, 'Unable to push procduct #' . $productId . " to Omnisend. May be server error. " . $curlResult['response']);
                    }
                    $returnResult['message'] = $curlResult['response'];
                }

            } else {
                $returnResult['message'] = 'Unable to push product #' . $productId . ' to Omnisend. One or more required fields are empty or invalid';
                OmnisendLogger::generalLogging("warn", "products", '', $returnResult['message']);
            }

            if (isset($curlResult)) {$returnResult['code'] = $curlResult['code'];}
            return $returnResult;
        }

    }

    /*Push or Woocommerce order to Omnisend account*/
    public static function pushOrderToOmnisend($orderId, $put = 0, $iter = 0)
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            $preparedOrder = OmnisendOrder::create($orderId);
            $returnResult = array();
            /*If all required fields are set, push order to Omnisend*/
            if ($preparedOrder) {

                $preparedOrder = OmnisendHelper::cleanModelFromEmptyFields($preparedOrder);
                $lastSync = get_post_meta($orderId, 'omnisend_last_sync', true);
                if ($put == 1 || (!empty($lastSync) && $lastSync != "error" && $put == 0)) {
                    $put = 1;
                    /*If order already exists - try to update*/
                    $link = OMNISEND_URL . "orders/" . $orderId;
                    $curlResult = OmnisendHelper::omnisendApi($link, "PUT", $preparedOrder);
                } else {
                    $put = 0;
                    $link = OMNISEND_URL . "orders";
                    $curlResult = OmnisendHelper::omnisendApi($link, "POST", $preparedOrder);
                }

                if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
                    OmnisendLogger::generalLogging("info", "orders", $link, 'Order #' . $orderId . ' was succesfully pushed to Omnisend.');
                    update_post_meta($orderId, 'omnisend_last_sync', date(DATE_ATOM, time()));
                    OmnisendLogger::countItem("orders");
                } elseif ($curlResult['code'] == 403) {
                    OmnisendLogger::generalLogging("warn", "orders", $link, 'Unable to push order #' . $orderId . " to Omnisend. You don't have rights to push orders.");
                } elseif ($curlResult['code'] == 400 || $curlResult['code'] == 404 || $curlResult['code'] == 422) {
                    if ($iter == 0) {
                        //try other method
                        OmnisendManager::pushOrderToOmnisend($orderId, $put + 1, $iter + 1);
                    } else {
                        OmnisendLogger::generalLogging("warn", "orders", $link, 'Unable to push order #' . $orderId . ' to Omnisend.' . $curlResult['response']);
                        if (empty($lastSync)) {
                            update_post_meta($orderId, 'omnisend_last_sync', 'error');
                        }
                    }
                } else {
                    OmnisendLogger::generalLogging("warn", "orders", $link, 'Unable to push order #' . $orderId . ' to Omnisend. May be server error. ' . $curlResult['response']);
                }

                $returnResult['message'] = $curlResult['response'];

            } else {
                $returnResult['message'] = 'Unable to push Order #' . $orderId . ' to Omnisend. One or more required fields are empty or invalid';
                OmnisendLogger::generalLogging("warn", "orders", '', $returnResult['message']);
            }

            if (isset($curlResult)) {$returnResult['code'] = $curlResult['code'];}
            return $returnResult;
        }

    }

    public static function updateOrderStatus($orderId, $statusType, $orderStatus)
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            $curlResult = array();
            $curlResult['response'] = '';
            $postData = [];
            if ($statusType == "fulfillment") {
                $postData["fulfillmentStatus"] = $orderStatus;
            } else {
                $postData["paymentStatus"] = $orderStatus;
            }

            if ($orderStatus == "voided") {
                $postData["canceledDate"] = date(DATE_ATOM, time());
            }

            $lastSync = get_post_meta($orderId, 'omnisend_last_sync', true);
            if (empty($lastSync) || $lastSync == "error") {
                OmnisendManager::pushOrderToOmnisend($orderId, 0);
            } else {
                /*If order already exists - try to update*/
                $link = OMNISEND_URL . "orders/" . $orderId;
                $curlResult = OmnisendHelper::omnisendApi($link, "PATCH", $postData);
                if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
                    OmnisendLogger::generalLogging("info", "orders", $link, 'Order #' . $orderId . ' status change was succesfully pushed to Omnisend.');
                    update_post_meta($orderId, 'omnisend_last_sync', date(DATE_ATOM, time()));
                    OmnisendLogger::countItem("orders");
                } elseif ($curlResult['code'] == 403) {
                    OmnisendLogger::generalLogging("warn", "orders", $link, 'Unable to push order #' . $orderId . " status change to Omnisend. You don't have rights to push orders.");
                } elseif ($curlResult['code'] == 400 || $curlResult['code'] == 404 || $curlResult['code'] == 422) {
                    OmnisendLogger::generalLogging("warn", "orders", $link, 'Unable to push order #' . $orderId . ' status change to Omnisend. ' . $curlResult['response']);
                    if (empty($lastSync)) {
                        update_post_meta($orderId, 'omnisend_last_sync', 'error');
                    }
                } else {
                    OmnisendLogger::generalLogging("warn", "orders", $link, 'Unable to push order #' . $orderId . ' status change to Omnisend. May be server error. ' . $curlResult['response']);
                }
            }

            return $curlResult['response'];
        }

    }

    //Delete Product
    public static function deleteProductFromOmnisend($id)
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            $link = OMNISEND_URL . "products/" . $id;
            $curlResult = OmnisendHelper::omnisendApi($link, "DELETE", []);

            return $curlResult['response'];
        }

    }

    //Delete Order
    public static function deleteOrderFromOmnisend($id)
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            $link = OMNISEND_URL . "orders/" . $id;
            $curlResult = OmnisendHelper::omnisendApi($link, "DELETE", []);

            return $curlResult['response'];
        }

    }

    //Update account info
    public static function updateAccountInfo($data = "")
    {
        if (!empty(get_option('omnisend_api_key', null))) {
            if ($data == "") {
                $data = OmnisendHelper::getAccountInfo();
            }
            $link = OMNISEND_URL . "accounts/" . get_option('omnisend_account_id', null);
            $curlResult = OmnisendHelper::omnisendApi($link, "POST", $data);
            if ($curlResult['code'] >= 200 && $curlResult['code'] < 300) {
                update_option('omnisend_wp_version', get_bloginfo('version'));
                OmnisendLogger::generalLogging("info", "account", $link, 'Account information has been updated.');
            } elseif ($curlResult['code'] == 403) {
                OmnisendLogger::generalLogging("warn", "account", $link, 'Unable to update account information');
            } elseif ($curlResult['code'] == 400 || $curlResult['code'] == 404 || $curlResult['code'] == 422) {
                OmnisendLogger::generalLogging("warn", "account", $link, 'Unable to update account information. ' . $curlResult['response']);
            } else {
                OmnisendLogger::generalLogging("warn", "account", $link, 'Unable to update account information. May be server error. ' . $curlResult['response']);
            }
        }
    }
}
