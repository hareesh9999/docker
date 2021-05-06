<?php
if (!defined('ABSPATH')) {
    exit;
}
class OmnisendProduct
{

    /*Required*/
    public $productID;
    public $title;
    public $status;
    public $description;
    public $currency;
    public $productUrl;
    public $tags = [];
    public $images = [];
    public $variants = [];

    public static function create($id, $view = '')
    {
        try {
            return new OmnisendProduct($id, $view);
        } catch (OmnisendEmptyRequiredFieldsException $exception) {
            return null;
        }
    }

    private static function checkIfSubcategoriesInProductArray($category_id, $productCatIds, $product_categories)
    {

        $inArray = false;

        $subcats = array();
        foreach ($product_categories as $c) {
            if ($c->parent == $category_id) {
                array_push($subcats, $c);
            }
        }

        foreach ($subcats as $subcat) {
            if (in_array($subcat->term_id, $productCatIds)) {
                $inArray = true;
            } else {
                $inArray = OmnisendProduct::checkIfSubcategoriesInProductArray($subcat->term_id, $productCatIds, $product_categories);
            }
        }

        return $inArray;
    }

    private static function getProductCategoryParent($category_id, $product_categories)
    {

        $parentName = "";

        $parentId = $product_categories[$category_id]->parent;
        if ($parentId > 0) {
            $parentName = $product_categories[$parentId]->name;
            $parentName = OmnisendProduct::getProductCategoryParent($parentId, $product_categories) . $parentName;
            $parentName .= '/';
        }

        return $parentName;
    }

    private function getProductCategoryHierarchy($category_ids, $product_categories)
    {

        foreach ($category_ids as $category_id) {

            $areSubcatsInArray = OmnisendProduct::checkIfSubcategoriesInProductArray($category_id, $category_ids, $product_categories);

            if (!$areSubcatsInArray) {

                // $categoryHierarchy = get_term_by('id', $category_id, 'product_cat')->name;

                $categoryHierarchy = $product_categories[$category_id]->name;

                $categoryHierarchy = OmnisendProduct::getProductCategoryParent($category_id, $product_categories) . $categoryHierarchy;
                array_push($this->categories, $categoryHierarchy);
            }

        }
    }

    private function __construct($id, $view)
    {
        $wcProduct = wc_get_product($id);
        if (empty($wcProduct)) {
            throw new OmnisendEmptyRequiredFieldsException();
        }

        $this->productID = "" . $id;
        $this->title = $wcProduct->get_name();

        if ($view != "picker") {
            if ($wcProduct->get_status() != "publish" || $wcProduct->get_catalog_visibility() == "hidden" || get_post_status($id) == 'auto-draft') {
                $this->status = 'notAvailable';
            } else {
                if ($wcProduct->get_manage_stock() == "yes") {
                    if ($wcProduct->get_stock_quantity() > 0) {
                        $this->status = 'inStock';
                    } else {
                        $this->status = 'outOfStock';
                    }
                } elseif ($wcProduct->get_stock_status() == 'instock') {
                    $this->status = 'inStock';
                } else {
                    $this->status = 'outOfStock';
                }
            }

            $this->createdAt = empty($wcProduct->get_date_created()) ? date(DATE_ATOM, time()) : $wcProduct->get_date_created()->format(DATE_ATOM);
            $this->updatedAt = empty($wcProduct->get_date_modified()) ? date(DATE_ATOM, time()) : $wcProduct->get_date_modified()->format(DATE_ATOM);

            $productTags = get_the_terms($id, 'product_tag');
            if (!empty($productTags) && !is_wp_error($productTags)) {
                foreach ($productTags as $term) {
                    array_push($this->tags, $term->name);
                }
            }

            $this->categoryIDs = array_map('strval', $wcProduct->get_category_ids());
        }
        $this->description = implode(' ', array_slice(explode(' ', preg_replace('#\[[^\]]+\]#', '', $wcProduct->get_description())), 0, 30));
        $this->currency = get_woocommerce_currency();
        $this->productUrl = get_permalink($id);

        $images = array();
        $mainImageID = "";
        $mainImageUrl = '';
        $imageCounter = 0;

        if (!empty($wcProduct->get_image_id()) && $wcProduct->get_image_id() != 0 && wp_get_attachment_url($wcProduct->get_image_id())) {
            $url = esc_url_raw(wp_get_attachment_url($wcProduct->get_image_id()));
            $images[$wcProduct->get_image_id()] = array(
                'imageID' => "" . $wcProduct->get_image_id(),
                'url' => $url,
                'isDefault' => true,
                'variantIDs' => array("" . $id),
            );
            $imageCounter++;
            $mainImageID = $wcProduct->get_image_id();
            $mainImageUrl = $url;
        }

        $mainVariant = [];

        $mainVariant['variantID'] = "" . $id;
        $mainVariant['title'] = $this->title;
        $sku = $wcProduct->get_sku();
        if ($sku != "") {
            $mainVariant["sku"] = $sku;
        }

        $mainVariant['status'] = $this->status;
        $mainVariant['price'] = $wcProduct->get_price();
        if ($mainVariant['price'] == '') {
            $mainVariant['price'] = 0;
        } else {
            $mainVariant['price'] = OmnisendHelper::priceToCents($mainVariant['price']);
        }
        if ($wcProduct->is_on_sale() && OmnisendHelper::priceToCents($wcProduct->get_regular_price()) > 0) {
            $mainVariant['oldPrice'] = OmnisendHelper::priceToCents($wcProduct->get_regular_price());
        }

        $mainVariant['imageID'] = "" . $mainImageID;

        if ($view == "picker") {
            $mainVariant["imageUrl"] = "";
            if ($mainVariant["imageID"] != "") {
                $mainVariant["imageUrl"] = $images[$mainVariant["imageID"]]["url"];
            } else {
                $mainVariant["imageUrl"] = wc_placeholder_img_src();
            }

            $this->variants[$mainVariant['variantID']] = $mainVariant;
        } else {
            array_push($this->variants, $mainVariant);
        }

        if ($wcProduct->is_type('variable')) {
            // $variations = $wcProduct->get_available_variations();
            $variations = $this->get_unhidden_variations($wcProduct);
            foreach ($variations as $variation) {
                $variant = [];

                $variant['variantID'] = "" . $variation['variation_id'];
                $variant['title'] = $this->title;
                if ($variation['sku'] != "") {
                    $variant['sku'] = $variation['sku'];
                }

                if ($variation['is_in_stock']) {
                    $variant['status'] = 'inStock';
                } else {
                    $variant['status'] = 'outOfStock';
                }
                $variant['price'] = $variation['display_price'];
                if ($variant['price'] === '') {
                    $variant['price'] = 0;
                } else {
                    $variant['price'] = OmnisendHelper::priceToCents($variant['price']);
                }
                if ($variation['display_price'] != $variation['display_regular_price']) {
                    $variant['oldPrice'] = OmnisendHelper::priceToCents($variation['display_regular_price']);
                }

                $variant['imageID'] = "";
                if ($variation['image_id'] != "") {
                    if (isset($images[$variation['image_id']])) {
                        $variant['imageID'] = "" . $variation['image_id'];
                    } else if (sizeof($images) > 9 && $view != "picker") {
                        $variant['imageID'] = "" . $mainImageID;
                    } else if (wp_get_attachment_url($variation['image_id'])) {
                        $default = false;
                        if ($mainImageID == "") {
                            $mainImageID = $variation['image_id'];
                            $mainImageUrl = esc_url_raw(wp_get_attachment_url($variation['image_id']));
                            $default = true;
                        }
                        $images[$variation['image_id']] = array(
                            'imageID' => "" . $variation['image_id'],
                            'url' => esc_url_raw(wp_get_attachment_url($variation['image_id'])),
                            'isDefault' => $default,
                        );
                        $variant["imageID"] = "" . $variation['image_id'];
                        $imageCounter++;
                    } else {
                        $variant['imageID'] = "" . $mainImageID;
                    }
                } else {
                    $variant["imageID"] = "" . $mainImageID;
                }

                if ($view == "picker") {
                    if ($variant["imageID"] != "" && array_key_exists($variant["imageID"], $images)) {
                        $variant["imageUrl"] = $images[$variant["imageID"]]["url"];
                    } else {
                        $variant["imageUrl"] = wc_placeholder_img_src();
                    }

                }

                if ($variation["weight"] != "") {
                    $variant["customFields"]["weight"] = $variation["weight_html"];
                }

                if (!empty($variation["attributes"])) {
                    foreach ($variation["attributes"] as $key => $attribute) {
                        if ($attribute != "") {
                            $variant["customFields"][$key] = $attribute;
                        }
                    }
                }

                if ($variant['imageID'] != "" && array_key_exists($variant['imageID'], $images)) {
                    $images[$variant['imageID']]["variantIDs"][] = "" . $variation['variation_id'];
                }
                if ($view == "picker") {
                    $this->variants[$variant['variantID']] = $variant;
                } else {
                    array_push($this->variants, $variant);
                }

            }
        }

        if ($imageCounter < 10 && $wcProduct->get_gallery_image_ids()) {
            foreach ($wcProduct->get_gallery_image_ids() as $galImageId) {
                if (wp_get_attachment_url($galImageId) && $galImageId != $mainImageID) {
                    $default = false;
                    $url = esc_url_raw(wp_get_attachment_url($galImageId));
                    if ($mainImageID == "" || $mainImageID == 0) {
                        $default = true;
                        $mainImageID = $galImageId;
                        $mainImageUrl = $url;
                    }

                    $images[$galImageId] = array(
                        'imageID' => "" . $galImageId,
                        'url' => $url,
                        'isDefault' => $default,
                    );
                    $imageCounter++;
                }
                if ($imageCounter > 9) {
                    break;
                }

            }
        }

        if (count($images) > 0) {
            $vColumn = array_column($this->variants, "variantID");
            foreach ($images as $ki => &$image) {
                if (array_key_exists("variantIDs", $image)) {
                    foreach ($image["variantIDs"] as $k => &$vID) {
                        if (!in_array($vID, $vColumn)) {
                            unset($images[$ki]["variantIDs"][$k]);
                        }
                    }
                }
            }
        }

        if ($view != "picker" && count($images) > 0) {
            $this->images = array_values($images);
        }
        if ($view == "picker") {
            if (empty($this->productID) || empty($this->title) || empty($this->currency)
                || empty($this->productUrl) || empty($this->variants)) {
                throw new OmnisendEmptyRequiredFieldsException();
            }
        } elseif (empty($this->productID) || empty($this->title) || empty($this->status) || empty($this->currency)
            || empty($this->productUrl) || empty($this->variants)) {
            throw new OmnisendEmptyRequiredFieldsException();
        }

    }
    public static function productPicker()
    {
        global $product;
        if ($product != null) {
            $p = OmnisendProduct::create($product->get_id(), "picker");
            if (!empty($p)) {
                echo "<script type='text/javascript'> \n
                        omnisend_product = " . json_encode($p) . " \n
                        window.onload = function () { \n
                            omnisend_pp_push(" . $product->get_id() . ");\n
                        } \n
                    </script> \n";
            }
        }
    }

    public function get_unhidden_variations($wcProduct)
    {
        $available_variations = array();

        foreach ($wcProduct->get_children() as $child_id) {
            $variation = wc_get_product($child_id);
            $available_variations[] = $wcProduct->get_available_variation($variation);
        }
        $available_variations = array_filter($available_variations);

        return $available_variations;
    }
}
