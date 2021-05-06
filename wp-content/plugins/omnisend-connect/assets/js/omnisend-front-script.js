function omnisend_pp_push(variantID) {
	if (jQuery('input[name=variation_id]').length) {
		var selectedVariant = jQuery('input[name=variation_id]').val();
		if (selectedVariant > 0) variantID = selectedVariant;
	}

	if (Object.prototype.toString.call("omnisend_product") === '[object String]' && typeof omnisend_product !== 'undefined') {
		window.omnisend = window.omnisend || [];
		try {
			variantID = omnisend_product["variants"][variantID]["variantID"];
		} catch (err) {
			if (omnisend_product.hasOwnProperty('variants')) {
				for (var p in omnisend_product["variants"])
					if (omnisend_product["variants"].hasOwnProperty(p)) {
						variantID = omnisend_product["variants"][p]["variantID"];
						break;
					};
			} else return;
		}
		try {
			var data = {
				$productID: omnisend_product["productID"],
				$variantID: omnisend_product["variants"][variantID]["variantID"],
				$currency: omnisend_product["currency"],
				$price: omnisend_product["variants"][variantID]["price"],
				$title: omnisend_product["title"],
				$description: omnisend_product["description"],
				$imageUrl: omnisend_product["variants"][variantID]["imageUrl"],
				$productUrl: omnisend_product["productUrl"]
			};
			if (typeof omnisend_product["variants"][variantID]["oldPrice"] !== "undefined") {
				data["$oldPrice"] = omnisend_product["variants"][variantID]["oldPrice"];
			}
			omnisend.push(["track", "$productViewed", data]);
		} catch (err) {}
	}
}

jQuery(document).ready(function () {
	if (jQuery('input[name=variation_id]').length) {
		jQuery('input[name=variation_id]').change(function () {
			if (jQuery(this).val() != "") omnisend_pp_push(jQuery(this).val());
		});
	}
});