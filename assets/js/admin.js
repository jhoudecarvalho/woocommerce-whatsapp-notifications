/**
 * Scripts do painel administrativo
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Tabs
		$('.wc-whatsapp-tabs .nav-tab').on('click', function(e) {
			e.preventDefault();
			
			var target = $(this).attr('href');
			
			// Remove active de todas as tabs
			$('.wc-whatsapp-tabs .nav-tab').removeClass('nav-tab-active');
			$('.wc-whatsapp-tabs .tab-content').removeClass('active');
			
			// Adiciona active na tab clicada
			$(this).addClass('nav-tab-active');
			$(target).addClass('active');
		});

		// Testar API
		$('#test-api-btn').on('click', function() {
			var $btn = $(this);
			var $result = $('#test-api-result');
			
			$btn.prop('disabled', true);
			$result.removeClass('success error').addClass('loading').text(wcWhatsApp.strings.testing);
			
			var apiUrl = $('#wc_whatsapp_api_url').val();
			var apiToken = $('#wc_whatsapp_api_token').val();
			
			if (!apiUrl || !apiToken) {
				$result.removeClass('loading').addClass('error').text('Preencha a URL e o Token primeiro.');
				$btn.prop('disabled', false);
				return;
			}
			
			$.ajax({
				url: wcWhatsApp.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wc_whatsapp_test_api',
					nonce: wcWhatsApp.nonce,
					api_url: apiUrl,
					api_token: apiToken
				},
				success: function(response) {
					$btn.prop('disabled', false);
					
					if (response.success) {
						$result.removeClass('loading error').addClass('success')
							.text(wcWhatsApp.strings.success + ' - ' + response.data.message);
					} else {
						$result.removeClass('loading success').addClass('error')
							.text(wcWhatsApp.strings.error + ' - ' + response.data.message);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$result.removeClass('loading success').addClass('error')
						.text(wcWhatsApp.strings.error + ' - Erro na requisição');
				}
			});
		});

		// Enviar mensagem de teste
		$('#send-test-message-btn').on('click', function() {
			var $btn = $(this);
			var $result = $('#test-message-result');
			var phone = $('#test-phone').val();
			var message = $('#test-message').val();
			
			if (!phone) {
				$result.removeClass('success').addClass('error').text(wcWhatsApp.strings.invalid_phone);
				return;
			}
			
			if (!message) {
				$result.removeClass('success').addClass('error').text(wcWhatsApp.strings.empty_message);
				return;
			}
			
			$btn.prop('disabled', true);
			$result.removeClass('success error').addClass('loading').text(wcWhatsApp.strings.sending);
			
			$.ajax({
				url: wcWhatsApp.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wc_whatsapp_send_test_message',
					nonce: wcWhatsApp.nonce,
					phone: phone,
					message: message
				},
				success: function(response) {
					$btn.prop('disabled', false);
					
					if (response.success) {
						$result.removeClass('loading error').addClass('success')
							.text(wcWhatsApp.strings.sent);
						$('#test-message').val('');
					} else {
						$result.removeClass('loading success').addClass('error')
							.text(wcWhatsApp.strings.error + ' - ' + response.data.message);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$result.removeClass('loading success').addClass('error')
						.text(wcWhatsApp.strings.error + ' - Erro na requisição');
				}
			});
		});
	});
})(jQuery);

