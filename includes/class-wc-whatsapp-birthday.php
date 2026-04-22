<?php
/**
 * Notificações de aniversário com cupom e limpeza segura de cupons
 *
 * @package WC_WhatsApp_Notifications
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe WC_WhatsApp_Birthday
 */
class WC_WhatsApp_Birthday {

	const COUPON_META_FLAG = '_generated_by_birthday_plugin';

	const CRON_HOOK       = 'wc_whatsapp_birthday_daily_check';
	const CRON_CLEAN_HOOK = 'wc_whatsapp_birthday_cleanup_daily';

	/**
	 * @var WC_WhatsApp_Birthday|null
	 */
	private static $instance = null;

	/**
	 * @var WC_WhatsApp_Logger
	 */
	private $logger;

	/**
	 * @var WC_WhatsApp_API
	 */
	private $api;

	/**
	 * @return WC_WhatsApp_Birthday
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->logger = WC_WhatsApp_Logger::get_instance();
		$this->api    = WC_WhatsApp_API::get_instance();

		// Cria/atualiza tabela assim que o WooCommerce carrega (evita hook plugins_loaded 5 antes desta classe existir).
		WC_WhatsApp_Birthday_DB::ensure_installed();

		add_action( 'init', array( $this, 'maybe_upgrade_db' ), 1 );

		add_action( self::CRON_HOOK, array( $this, 'run_birthday_check' ) );
		add_action( self::CRON_CLEAN_HOOK, array( $this, 'run_coupon_cleanup' ) );

		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_mark_coupon_used' ), 20, 2 );

		add_action( 'init', array( $this, 'maybe_schedule_crons' ), 20 );
	}

	/**
	 * Reforço no init (cron, REST, etc.)
	 */
	public function maybe_upgrade_db() {
		WC_WhatsApp_Birthday_DB::ensure_installed();
	}

	/**
	 * Agenda crons se habilitado
	 */
	public function maybe_schedule_crons() {
		if ( ! get_option( 'wc_whatsapp_birthday_enabled', false ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			wp_clear_scheduled_hook( self::CRON_CLEAN_HOOK );
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$this->schedule_birthday_cron();
		}
		if ( ! wp_next_scheduled( self::CRON_CLEAN_HOOK ) ) {
			wp_schedule_event( $this->next_cleanup_timestamp(), 'daily', self::CRON_CLEAN_HOOK );
		}
	}

	/**
	 * Recria agendamentos (chamar ao salvar configurações)
	 */
	public static function reschedule_crons() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::CRON_CLEAN_HOOK );

		if ( get_option( 'wc_whatsapp_birthday_enabled', false ) ) {
			$inst = self::get_instance();
			$inst->schedule_birthday_cron();
			wp_schedule_event( $inst->next_cleanup_timestamp(), 'daily', self::CRON_CLEAN_HOOK );
		}
	}

	/**
	 * Próxima execução do cron de limpeza (03:00 site)
	 *
	 * @return int Unix timestamp
	 */
	private function next_cleanup_timestamp() {
		$tz = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );
		$run = $now->setTime( 3, 0, 0 );
		if ( $run <= $now ) {
			$run = $run->modify( '+1 day' );
		}
		return $run->getTimestamp();
	}

	/**
	 * Agenda verificação diária próxima ao horário configurado
	 */
	private function schedule_birthday_cron() {
		$ts = $this->next_send_window_timestamp();
		wp_schedule_event( $ts, 'daily', self::CRON_HOOK );
	}

	/**
	 * Próximo timestamp alinhado ao horário de envio (HH:MM) no fuso do site
	 *
	 * @return int
	 */
	private function next_send_window_timestamp() {
		$tz   = wp_timezone();
		$time = get_option( 'wc_whatsapp_birthday_send_time', '09:00' );
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $time, $m ) ) {
			$time = '09:00';
			preg_match( '/^(\d{1,2}):(\d{2})$/', $time, $m );
		}
		$h = min( 23, max( 0, (int) $m[1] ) );
		$i = min( 59, max( 0, (int) $m[2] ) );

		$now = new DateTimeImmutable( 'now', $tz );
		$run = $now->setTime( $h, $i, 0 );
		if ( $run <= $now ) {
			$run = $run->modify( '+1 day' );
		}
		return $run->getTimestamp();
	}

	/**
	 * Callback cron: verifica aniversariantes
	 */
	public function run_birthday_check() {
		if ( ! get_option( 'wc_whatsapp_birthday_enabled', false ) ) {
			return;
		}

		if ( ! $this->api->is_configured() ) {
			$this->logger->warning( 'Aniversário: API WhatsApp não configurada, cron ignorado.' );
			return;
		}

		$tz        = wp_timezone();
		$today     = new DateTimeImmutable( 'today', $tz );
		$today_str = $today->format( 'Y-m-d' );

		$offset_days = (int) get_option( 'wc_whatsapp_birthday_send_offset', 0 );
		$offset_days = max( 0, min( 30, $offset_days ) );

		$this->logger->debug(
			'Aniversário: início da verificação diária',
			array( 'today' => $today_str, 'offset_days' => $offset_days )
		);

		self::mark_expired_rows();

		$user_ids = $this->get_users_with_birthdate_meta();
		$year     = (int) $today->format( 'Y' );

		foreach ( $user_ids as $user_id ) {
			$bd = $this->get_user_birthdate_parsed( (int) $user_id );
			if ( ! $bd ) {
				continue;
			}

			$send_date = $this->compute_send_date_for_year( $bd['month'], $bd['day'], $year, $offset_days, $tz );
			if ( ! $send_date || $send_date->format( 'Y-m-d' ) !== $today_str ) {
				continue;
			}

			$bday_occurrence = $send_date->modify( '+' . (int) $offset_days . ' days' );

			if ( $this->already_sent_this_year( (int) $user_id, $year ) ) {
				continue;
			}

			$phone = $this->get_user_billing_phone( (int) $user_id );
			if ( empty( $phone ) ) {
				$this->logger->debug( 'Aniversário: usuário sem telefone', array( 'user_id' => $user_id ) );
				continue;
			}

			$formatted = $this->format_phone_br( $phone );
			if ( ! $formatted ) {
				continue;
			}

			$this->process_birthday_for_user( (int) $user_id, $formatted, $bd, $year, $bday_occurrence );
		}
	}

	/**
	 * IDs de usuários que possuem alguma meta de data de nascimento
	 *
	 * @return int[]
	 */
	private function get_users_with_birthdate_meta() {
		global $wpdb;

		$keys = array( 'billing_birthdate', '_billing_birthdate', 'birthdate' );
		$in   = implode(
			',',
			array_map(
				function ( $k ) {
					return "'" . esc_sql( $k ) . "'";
				},
				$keys
			)
		);

		$limit = (int) apply_filters( 'wc_whatsapp_birthday_user_query_limit', 500 );
		$limit = max( 50, min( 5000, $limit ) );

		$sql = "SELECT DISTINCT user_id FROM {$wpdb->usermeta}
			WHERE meta_key IN ({$in})
			AND meta_value != ''
			AND meta_value IS NOT NULL
			LIMIT {$limit}";

		$ids = $wpdb->get_col( $sql );
		return array_map( 'intval', $ids ? $ids : array() );
	}

	/**
	 * @param int $user_id User ID.
	 * @return array{month:int,day:int,year:int}|null
	 */
	private function get_user_birthdate_parsed( $user_id ) {
		$raw = '';
		foreach ( array( 'billing_birthdate', '_billing_birthdate', 'birthdate' ) as $key ) {
			$v = get_user_meta( $user_id, $key, true );
			if ( ! empty( $v ) ) {
				$raw = $v;
				break;
			}
		}
		if ( '' === $raw ) {
			return null;
		}
		return $this->parse_birthdate_string( $raw );
	}

	/**
	 * Parse público da data (admin / relatórios)
	 *
	 * @param string $raw Raw.
	 * @return array{month:int,day:int,year:int}|null
	 */
	public function parse_birthdate_public( $raw ) {
		return $this->parse_birthdate_string( $raw );
	}

	/**
	 * @param string $raw Raw date string.
	 * @return array{month:int,day:int,year:int}|null
	 */
	private function parse_birthdate_string( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}

		if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m ) ) {
			$day   = (int) $m[1];
			$month = (int) $m[2];
			$year  = (int) $m[3];
		} elseif ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) ) {
			$year  = (int) $m[1];
			$month = (int) $m[2];
			$day   = (int) $m[3];
		} else {
			$ts = strtotime( $raw );
			if ( ! $ts ) {
				return null;
			}
			$month = (int) gmdate( 'n', $ts );
			$day   = (int) gmdate( 'j', $ts );
			$year  = (int) gmdate( 'Y', $ts );
		}

		if ( $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
			return null;
		}
		if ( $year > (int) gmdate( 'Y' ) ) {
			return null;
		}

		return compact( 'month', 'day', 'year' );
	}

	/**
	 * Data de envio (apenas data) para o aniversário no ano corrente ou ajuste
	 *
	 * @param int                   $month Birth month.
	 * @param int                   $day Birth day.
	 * @param int                   $year_ref Reference year (current).
	 * @param int                   $offset_days Days before birthday.
	 * @param DateTimeZone          $tz Timezone.
	 * @return DateTimeImmutable|null
	 */
	private function compute_send_date_for_year( $month, $day, $year_ref, $offset_days, $tz ) {
		try {
			$bday = $this->safe_birthday_this_year( $month, $day, $year_ref, $tz );
		} catch ( Exception $e ) {
			return null;
		}

		$today = new DateTimeImmutable( 'today', $tz );
		if ( $bday < $today ) {
			try {
				$bday = $this->safe_birthday_this_year( $month, $day, $year_ref + 1, $tz );
			} catch ( Exception $e ) {
				return null;
			}
		}

		$send = $bday->modify( '-' . (int) $offset_days . ' days' );
		return $send;
	}

	/**
	 * @param int $month Month.
	 * @param int $day Day.
	 * @param int $year Year.
	 * @param DateTimeZone $tz TZ.
	 * @return DateTimeImmutable
	 * @throws Exception Invalid date.
	 */
	private function safe_birthday_this_year( $month, $day, $year, $tz ) {
		if ( 2 === (int) $month && 29 === (int) $day && ! $this->is_leap_year( $year ) ) {
			$day = 28;
		}
		return new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ), $tz );
	}

	/**
	 * @param int $year Year.
	 * @return bool
	 */
	private function is_leap_year( $year ) {
		return ( ( $year % 4 === 0 ) && ( $year % 100 !== 0 ) ) || ( $year % 400 === 0 );
	}

	/**
	 * @param int $user_id User.
	 * @param int $year Year.
	 * @return bool
	 */
	private function already_sent_this_year( $user_id, $year ) {
		global $wpdb;
		$table = WC_WhatsApp_Birthday_DB::get_table_name();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND coupon_year = %d AND status != %s",
				$user_id,
				$year,
				'deletado'
			)
		);
		return $count > 0;
	}

	/**
	 * @param int $user_id User.
	 * @return string
	 */
	private function get_user_billing_phone( $user_id ) {
		$p = get_user_meta( $user_id, 'billing_phone', true );
		return $p ? (string) $p : '';
	}

	/**
	 * Formata telefone BR (mesma regra do handler)
	 *
	 * @param string $phone Phone.
	 * @return string|false
	 */
	private function format_phone_br( $phone ) {
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		if ( substr( $phone, 0, 2 ) === '55' ) {
			if ( strlen( $phone ) >= 13 && strlen( $phone ) <= 14 ) {
				return $phone;
			}
			return false;
		}
		if ( substr( $phone, 0, 1 ) === '0' ) {
			$phone = substr( $phone, 1 );
		}
		if ( strlen( $phone ) >= 10 && strlen( $phone ) <= 11 ) {
			return '55' . $phone;
		}
		return false;
	}

	/**
	 * Gera cupom, envia WhatsApp, grava tabela
	 *
	 * @param int    $user_id User ID.
	 * @param string $formatted_phone Phone.
	 * @param array  $bd Parsed birthdate.
	 * @param int                 $year Coupon year.
	 * @param DateTimeImmutable   $bday_occurrence Data do aniversário (início da validade do cupom).
	 */
	private function process_birthday_for_user( $user_id, $formatted_phone, $bd, $year, $bday_occurrence = null ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$tz = wp_timezone();
		if ( ! $bday_occurrence instanceof DateTimeImmutable ) {
			$y = (int) wp_date( 'Y' );
			try {
				$bday_occurrence = $this->safe_birthday_this_year( (int) $bd['month'], (int) $bd['day'], $y, $tz );
			} catch ( Exception $e ) {
				return;
			}
		}

		$coupon_code = $this->build_unique_coupon_code( $user );
		if ( is_wp_error( $coupon_code ) ) {
			$this->logger->error( 'Aniversário: falha ao gerar código de cupom', array( 'user_id' => $user_id, 'error' => $coupon_code->get_error_message() ) );
			return;
		}

		$coupon_id = $this->create_wc_coupon( $coupon_code, $user, $bd, $year, $bday_occurrence );
		if ( is_wp_error( $coupon_id ) ) {
			$this->logger->error( 'Aniversário: falha ao criar cupom', array( 'user_id' => $user_id, 'error' => $coupon_id->get_error_message() ) );
			return;
		}

		$message = $this->build_message( $user, $coupon_code, $coupon_id );
		$result  = $this->api->send_message( $formatted_phone, $message, null, null, false, null );

		$wa_status = is_wp_error( $result ) ? 'falhou' : 'enviado';
		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Aniversário: erro WhatsApp',
				array(
					'user_id' => $user_id,
					'error'   => $result->get_error_message(),
				)
			);
		}

		$this->insert_tracking_row(
			$user_id,
			$user,
			$bd,
			$coupon_code,
			$coupon_id,
			$year,
			$wa_status,
			$formatted_phone
		);
	}

	/**
	 * @param WP_User $user User.
	 * @return string|WP_Error
	 */
	private function build_unique_coupon_code( $user ) {
		$prefix = strtoupper( sanitize_title( get_option( 'wc_whatsapp_birthday_coupon_prefix', 'ANIVER' ) ) );
		$prefix = preg_replace( '/[^A-Z0-9]/', '', $prefix );
		if ( '' === $prefix ) {
			$prefix = 'ANIVER';
		}

		$name_part = sanitize_title( $user->first_name ? $user->first_name : $user->display_name );
		$name_part = strtoupper( preg_replace( '/[^A-Z0-9]/', '', $name_part ) );
		if ( '' === $name_part ) {
			$name_part = 'CLIENTE';
		}
		$name_part = substr( $name_part, 0, 12 );

		$year = (int) wp_date( 'Y' );
		$base = $prefix . '-' . $name_part . '-' . $year;

		$code = $base;
		$n    = 0;
		while ( wc_get_coupon_id_by_code( $code ) ) {
			$n++;
			$code = $base . '-' . $n;
			if ( $n > 50 ) {
				return new WP_Error( 'coupon_code', __( 'Não foi possível gerar código único.', 'wc-whatsapp-notifications' ) );
			}
		}
		return $code;
	}

	/**
	 * @param string   $code Code.
	 * @param WP_User  $user User.
	 * @param array    $bd Birth parts.
	 * @param int                 $year Year.
	 * @param DateTimeImmutable   $bday_occurrence Início da validade (dia do aniversário 00:00).
	 * @return int|WP_Error Post ID
	 */
	private function create_wc_coupon( $code, $user, $bd, $year, $bday_occurrence ) {
		$type   = get_option( 'wc_whatsapp_birthday_discount_type', 'percent' );
		$amount = (float) get_option( 'wc_whatsapp_birthday_discount_amount', 15 );
		$valid  = max( 1, (int) get_option( 'wc_whatsapp_birthday_valid_days', 3 ) );
		$min    = (float) get_option( 'wc_whatsapp_birthday_min_amount', 0 );

		$tz    = wp_timezone();
		$start = $bday_occurrence->setTime( 0, 0, 0 );
		$end   = $start->modify( '+' . ( $valid - 1 ) . ' days' )->setTime( 23, 59, 59 );

		$wc_type = 'percent';
		if ( 'fixed' === $type ) {
			$wc_type = 'fixed_cart';
		} elseif ( 'free_shipping' === $type ) {
			$wc_type = 'free_shipping';
			$amount  = 0;
		}

		try {
			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( $wc_type );
			$coupon->set_amount( $amount );
			$expires_str = $end->format( 'Y-m-d H:i:s' );
			if ( function_exists( 'wc_string_to_datetime' ) ) {
				$coupon->set_date_expires( wc_string_to_datetime( $expires_str ) );
			} else {
				$coupon->set_date_expires( $expires_str );
			}
			$coupon->set_usage_limit( 1 );
			if ( get_option( 'wc_whatsapp_birthday_single_use', true ) ) {
				$coupon->set_usage_limit_per_user( 1 );
			}
			$coupon->set_individual_use( (bool) get_option( 'wc_whatsapp_birthday_individual_use', true ) );
			if ( $min > 0 ) {
				$coupon->set_minimum_amount( $min );
			}
			if ( is_email( $user->user_email ) ) {
				$coupon->set_email_restrictions( array( $user->user_email ) );
			}
			$coupon->set_description(
				sprintf(
					/* translators: %s: customer name */
					__( 'Cupom de aniversário para %s', 'wc-whatsapp-notifications' ),
					$user->display_name
				)
			);
			$coupon->update_meta_data( self::COUPON_META_FLAG, '1' );
			$coupon->save();
		} catch ( Exception $e ) {
			return new WP_Error( 'coupon', $e->getMessage() );
		}

		return $coupon->get_id();
	}

	/**
	 * @param WP_User $user User.
	 * @param string  $coupon_code Code.
	 * @param int     $coupon_id Coupon post ID.
	 * @return string
	 */
	private function build_message( $user, $coupon_code, $coupon_id ) {
		$tpl = get_option( 'wc_whatsapp_birthday_message_template', '' );
		if ( '' === trim( $tpl ) ) {
			$tpl = $this->default_template();
		}

		$type   = get_option( 'wc_whatsapp_birthday_discount_type', 'percent' );
		$amount = (float) get_option( 'wc_whatsapp_birthday_discount_amount', 15 );
		$min    = (float) get_option( 'wc_whatsapp_birthday_min_amount', 0 );

		$tipo_txt = __( 'percentual', 'wc-whatsapp-notifications' );
		$val_txt  = (string) $amount . '%';
		if ( 'fixed' === $type ) {
			$tipo_txt = __( 'valor fixo', 'wc-whatsapp-notifications' );
			$val_txt  = wp_strip_all_tags( wc_price( $amount ) );
		} elseif ( 'free_shipping' === $type ) {
			$tipo_txt = __( 'frete grátis', 'wc-whatsapp-notifications' );
			$val_txt  = __( 'Frete grátis', 'wc-whatsapp-notifications' );
		}

		$c = new WC_Coupon( $coupon_id );
		$exp = $c->get_date_expires();
		$data_validade = $exp ? $exp->date_i18n( 'd/m/Y' ) : '';

		$repl = array(
			'{nome_cliente}'    => $user->first_name ? $user->first_name : $user->display_name,
			'{codigo_cupom}'    => $coupon_code,
			'{valor_desconto}'  => $val_txt,
			'{tipo_desconto}'   => $tipo_txt,
			'{data_validade}'   => $data_validade,
			'{valor_minimo}'    => wc_format_decimal( $min, 2 ),
			'{loja_nome}'       => get_bloginfo( 'name' ),
		);

		return str_replace( array_keys( $repl ), array_values( $repl ), $tpl );
	}

	/**
	 * @return string
	 */
	private function default_template() {
		return "🎉 *Feliz Aniversário, {nome_cliente}!* 🎂\n\n"
			. "A equipe {loja_nome} preparou um presente especial para você:\n\n"
			. "🎁 *{valor_desconto} ({tipo_desconto})!*\n\n"
			. "Use o cupom: *{codigo_cupom}*\n"
			. "📅 Válido até: {data_validade}\n"
			. "💰 Pedido mínimo: R$ {valor_minimo}\n\n"
			. "Aproveite seu dia especial com a gente! 🛍️";
	}

	/**
	 * @param int     $user_id User ID.
	 * @param WP_User $user User.
	 * @param array   $bd Birth.
	 * @param string  $coupon_code Code.
	 * @param int     $coupon_id Coupon ID.
	 * @param int     $year Year.
	 * @param string  $wa_status Status.
	 * @param string  $phone Phone.
	 */
	private function insert_tracking_row( $user_id, $user, $bd, $coupon_code, $coupon_id, $year, $wa_status, $phone ) {
		global $wpdb;
		$table = WC_WhatsApp_Birthday_DB::get_table_name();

		$c = new WC_Coupon( $coupon_id );
		$exp = $c->get_date_expires();

		$wpdb->insert(
			$table,
			array(
				'customer_id'       => $user_id,
				'customer_email'    => $user->user_email,
				'customer_phone'    => $phone,
				'birthdate'         => sprintf( '%04d-%02d-%02d', $bd['year'], $bd['month'], $bd['day'] ),
				'coupon_code'       => $coupon_code,
				'coupon_post_id'    => $coupon_id,
				'coupon_year'       => $year,
				'sent_date'         => current_time( 'mysql' ),
				'expiration_date'   => $exp ? $exp->format( 'Y-m-d H:i:s' ) : null,
				'whatsapp_status'   => $wa_status,
				'status'            => 'enviado',
				'used_date'         => null,
				'deleted_date'      => null,
				'order_id'          => 0,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Marca cupom como usado quando pedido contém cupom do plugin
	 *
	 * @param int            $order_id Order ID.
	 * @param WC_Order|false $order Order.
	 */
	public function maybe_mark_coupon_used( $order_id, $order = null ) {
		if ( ! $order_id ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$coupons = $order->get_coupon_codes();
		if ( empty( $coupons ) ) {
			return;
		}

		global $wpdb;
		$table = WC_WhatsApp_Birthday_DB::get_table_name();

		foreach ( $coupons as $code ) {
			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( ! $coupon_id ) {
				continue;
			}
			$flag = get_post_meta( $coupon_id, self::COUPON_META_FLAG, true );
			if ( '1' !== (string) $flag ) {
				continue;
			}

			$wpdb->update(
				$table,
				array(
					'status'     => 'usado',
					'used_date'  => current_time( 'mysql' ),
					'order_id'   => $order->get_id(),
				),
				array( 'coupon_post_id' => $coupon_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);

			$this->logger->info(
				'Aniversário: cupom marcado como usado',
				array( 'coupon' => $code, 'order_id' => $order->get_id() )
			);
		}
	}

	/**
	 * Limpeza diária de cupons expirados não usados (somente gerados pelo plugin)
	 */
	public function run_coupon_cleanup() {
		self::mark_expired_rows();

		$cleanup_after = max( 1, (int) get_option( 'wc_whatsapp_birthday_cleanup_days', 7 ) );
		$tz            = wp_timezone();
		$now           = new DateTimeImmutable( 'now', $tz );
		$threshold     = $now->modify( '-' . $cleanup_after . ' days' )->setTime( 23, 59, 59 );

		$this->logger->debug(
			'Aniversário: início limpeza de cupons',
			array( 'cleanup_after_days' => $cleanup_after, 'threshold' => $threshold->format( 'Y-m-d H:i:s' ) )
		);

		$coupon_ids = $this->find_eligible_coupons_for_deletion( $threshold );
		foreach ( $coupon_ids as $row ) {
			$post_id = (int) $row['coupon_post_id'];
			$code    = $row['coupon_code'];

			if ( ! $post_id || get_post_type( $post_id ) !== 'shop_coupon' ) {
				continue;
			}

			$flag = get_post_meta( $post_id, self::COUPON_META_FLAG, true );
			if ( '1' !== (string) $flag ) {
				continue;
			}

			$c = new WC_Coupon( $post_id );
			if ( $c->get_usage_count() > 0 ) {
				continue;
			}

			wp_delete_post( $post_id, true );

			global $wpdb;
			$table = WC_WhatsApp_Birthday_DB::get_table_name();
			$wpdb->update(
				$table,
				array(
					'status'        => 'deletado',
					'deleted_date'  => current_time( 'mysql' ),
				),
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$this->logger->info(
				'Aniversário: cupom expirado removido (somente plugin)',
				array( 'coupon' => $code, 'post_id' => $post_id )
			);
		}
	}

	/**
	 * Linhas na tabela: expirados, não usados, elegíveis
	 *
	 * @param DateTimeImmutable $threshold Date.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_eligible_coupons_for_deletion( $threshold ) {
		global $wpdb;
		$table = WC_WhatsApp_Birthday_DB::get_table_name();

		$sql = $wpdb->prepare(
			"SELECT id, coupon_post_id, coupon_code FROM {$table}
			WHERE status IN ('enviado', 'expirado')
			AND (used_date IS NULL OR used_date = '0000-00-00 00:00:00')
			AND expiration_date IS NOT NULL
			AND expiration_date != ''
			AND expiration_date < %s",
			$threshold->format( 'Y-m-d H:i:s' )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return $rows ? $rows : array();
	}

	/**
	 * Atualiza status expirado para linhas antigas (sem deletar ainda)
	 */
	public static function mark_expired_rows() {
		global $wpdb;
		$table = WC_WhatsApp_Birthday_DB::get_table_name();
		$now   = current_time( 'mysql' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s
				WHERE status = %s
				AND expiration_date IS NOT NULL
				AND expiration_date != ''
				AND expiration_date < %s",
				'expirado',
				'enviado',
				$now
			)
		);
	}

	/**
	 * Envio manual (admin / AJAX)
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
	public function send_manual_for_user( $user_id ) {
		if ( ! get_option( 'wc_whatsapp_birthday_enabled', false ) ) {
			return new WP_Error( 'disabled', __( 'Recurso desativado.', 'wc-whatsapp-notifications' ) );
		}
		if ( ! $this->api->is_configured() ) {
			return new WP_Error( 'api', __( 'API não configurada.', 'wc-whatsapp-notifications' ) );
		}

		$bd = $this->get_user_birthdate_parsed( $user_id );
		if ( ! $bd ) {
			return new WP_Error( 'nodate', __( 'Data de nascimento não encontrada.', 'wc-whatsapp-notifications' ) );
		}

		$phone = $this->get_user_billing_phone( $user_id );
		if ( ! $phone ) {
			return new WP_Error( 'nophone', __( 'Telefone não cadastrado.', 'wc-whatsapp-notifications' ) );
		}
		$formatted = $this->format_phone_br( $phone );
		if ( ! $formatted ) {
			return new WP_Error( 'badphone', __( 'Telefone inválido.', 'wc-whatsapp-notifications' ) );
		}

		$year = (int) wp_date( 'Y' );
		if ( $this->already_sent_this_year( $user_id, $year ) ) {
			return new WP_Error( 'duplicate', __( 'Já existe cupom enviado para este cliente neste ano.', 'wc-whatsapp-notifications' ) );
		}

		$bday = $this->compute_birthday_occurrence_for_manual( $bd, (int) wp_date( 'Y' ) );
		if ( ! $bday ) {
			return new WP_Error( 'nodate2', __( 'Não foi possível calcular a data do aniversário.', 'wc-whatsapp-notifications' ) );
		}

		$this->process_birthday_for_user( $user_id, $formatted, $bd, $year, $bday );
		return true;
	}

	/**
	 * Próxima ocorrência do aniversário a partir do ano de referência
	 *
	 * @param array $bd Parsed birthdate.
	 * @param int   $year_ref Year.
	 * @return DateTimeImmutable|null
	 */
	private function compute_birthday_occurrence_for_manual( $bd, $year_ref ) {
		$tz = wp_timezone();
		try {
			$bday = $this->safe_birthday_this_year( (int) $bd['month'], (int) $bd['day'], $year_ref, $tz );
		} catch ( Exception $e ) {
			return null;
		}
		$today = new DateTimeImmutable( 'today', $tz );
		if ( $bday < $today ) {
			try {
				$bday = $this->safe_birthday_this_year( (int) $bd['month'], (int) $bd['day'], $year_ref + 1, $tz );
			} catch ( Exception $e ) {
				return null;
			}
		}
		return $bday;
	}
}
