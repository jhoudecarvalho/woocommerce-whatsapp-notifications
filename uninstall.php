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
	'wc_whatsapp_birthday_db_version',
	'wc_whatsapp_birthday_enabled',
	'wc_whatsapp_birthday_discount_type',
	'wc_whatsapp_birthday_discount_amount',
	'wc_whatsapp_birthday_coupon_prefix',
	'wc_whatsapp_birthday_valid_days',
	'wc_whatsapp_birthday_cleanup_days',
	'wc_whatsapp_birthday_min_amount',
	'wc_whatsapp_birthday_single_use',
	'wc_whatsapp_birthday_individual_use',
	'wc_whatsapp_birthday_send_offset',
	'wc_whatsapp_birthday_send_time',
	'wc_whatsapp_birthday_message_template',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'wc_whatsapp_birthday_daily_check' );
	wp_clear_scheduled_hook( 'wc_whatsapp_birthday_cleanup_daily' );
}

global $wpdb;
$table = $wpdb->prefix . 'wc_whatsapp_birthday_coupons';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

// Limpa logs relacionados (se houver)
if ( class_exists( 'WC_Logger' ) ) {
	$logger = wc_get_logger();
	// Os logs do WooCommerce são mantidos, mas podemos adicionar uma nota se necessário
}

