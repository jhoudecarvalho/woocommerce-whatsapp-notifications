<?php
/**
 * Tabela de controle de cupons de aniversário
 *
 * @package WC_WhatsApp_Notifications
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instalação e nome da tabela
 */
class WC_WhatsApp_Birthday_DB {

	const DB_VERSION = '1.0.0';

	/**
	 * Nome da tabela com prefixo
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wc_whatsapp_birthday_coupons';
	}

	/**
	 * Garante que a tabela exista (instalação, atualização sem reativar, primeira visita)
	 */
	public static function ensure_installed() {
		global $wpdb;

		$table = self::get_table_name();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( empty( $found ) ) {
			self::create_tables();
			return;
		}

		if ( version_compare( (string) get_option( 'wc_whatsapp_birthday_db_version', '0' ), self::DB_VERSION, '<' ) ) {
			self::create_tables();
		}
	}

	/**
	 * Cria ou atualiza a tabela
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_email varchar(100) NOT NULL DEFAULT '',
			customer_phone varchar(40) NOT NULL DEFAULT '',
			birthdate date DEFAULT NULL,
			coupon_code varchar(100) NOT NULL DEFAULT '',
			coupon_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			coupon_year smallint(4) unsigned NOT NULL DEFAULT 0,
			sent_date datetime DEFAULT NULL,
			expiration_date datetime DEFAULT NULL,
			whatsapp_status varchar(20) NOT NULL DEFAULT 'pendente',
			status varchar(20) NOT NULL DEFAULT 'enviado',
			used_date datetime DEFAULT NULL,
			deleted_date datetime DEFAULT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY customer_year (customer_id, coupon_year),
			KEY coupon_code (coupon_code(50)),
			KEY status_exp (status, expiration_date)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'wc_whatsapp_birthday_db_version', self::DB_VERSION );
	}
}
