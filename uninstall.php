<?php
/**
 * Arquivo de desinstalação do plugin
 *
 * @package WC_WhatsApp_Notifications
 */

// Se este arquivo não foi chamado pelo WordPress, aborta.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove todas as opções do plugin
$options = array(
	'wc_whatsapp_api_url',
	'wc_whatsapp_api_token',
	'wc_whatsapp_api_endpoint',
	'wc_whatsapp_enabled_statuses',
	'wc_whatsapp_message_processing',
	'wc_whatsapp_message_on-hold',
	'wc_whatsapp_message_completed',
	'wc_whatsapp_message_cancelled',
	'wc_whatsapp_message_refunded',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Limpa logs relacionados (se houver)
if ( class_exists( 'WC_Logger' ) ) {
	$logger = wc_get_logger();
	// Os logs do WooCommerce são mantidos, mas podemos adicionar uma nota se necessário
}

