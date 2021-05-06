jQuery(document).ready(function ($) {

	$(document).on({
		ajaxStart: function () {
			$("body").addClass("omnisend-ajax-loading");
		},
		ajaxStop: function () {
			$("body").removeClass("omnisend-ajax-loading");
		}
	});

	$(".change_api_key").click(function () {
		$('.omnisend_dn').toggle();
	});

	$('#api-key-form').submit(function (e) {
		e.preventDefault();

		$('.api-key-form-wrapper .spinner').show();
		$('.api-key-form-wrapper .spinner').css('visibility', 'visible');
		$('#api-key').css("background-color", "#D9D8D8");

		$('.response-message').html('');
		$('.response-message').removeClass('omnisend-warning');
		$('.response-message').removeClass('omnisend-success');
		var api_key = $.trim($('#api-key').val());
		if (api_key !== '') {
			if (api_key.length != 75) {
				$('.response-message').html('API key format is incorrect. Please check if the key is the same as on your Omnisend account. ');
				$('.response-message').addClass('omnisend-warning');
				$('.api-key-form-wrapper .spinner').hide();
				$('.api-key-form-wrapper .spinner').css('visibility', 'hidden');
				$('#api-key').css("background-color", "#fff");
			} else {

				$.ajax({
					type: 'POST',
					url: ajaxurl,
					data: {
						action: 'save_omnisend_api_key',
						omnisend_api_key: api_key
					},
					beforeSend: function () {},
					success: function (response) {
						response = $.parseJSON(response);
						if (response['success']) {
							location.reload();
						} else {
							$('.response-message').addClass('omnisend-warning');
							$('.response-message').html(response['msg']);
							$('.api-key-form-wrapper .spinner').hide();
							$('.api-key-form-wrapper .spinner').css('visibility', 'hidden');
							$('#api-key').css("background-color", "#fff");
						}
					},
					error: function (errorThrown) {
						$('.response-message').addClass('omnisend-warning');
						$('.response-message').html("Error: please try again.");
						$('.api-key-form-wrapper .spinner').hide();
						$('.api-key-form-wrapper .spinner').css('visibility', 'hidden');
						$('#api-key').css("background-color", "#fff");
					},
					complete: function (textStatus) {

					}
				});
			}

		} else {
			$('.response-message').html('No API key. Please copy and paste it from your Omnisend account.');
			$('.response-message').addClass('omnisend-warning');
			$('.api-key-form-wrapper .spinner').hide();
			$('.api-key-form-wrapper .spinner').css('visibility', 'hidden');
			$('#api-key').css("background-color", "#fff");
		}
	});

});