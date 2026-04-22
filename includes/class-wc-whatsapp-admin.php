<?php
/**
 * Classe para gerenciar painel administrativo
 *
 * @package WC_WhatsApp_Notifications
 */

// Se este arquivo for chamado diretamente, aborta.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe WC_WhatsApp_Admin
 */
class WC_WhatsApp_Admin {

	/**
	 * Instância única (Singleton)
	 *
	 * @var WC_WhatsApp_Admin
	 */
	private static $instance = null;

	/**
	 * API WhatsApp
	 *
	 * @var WC_WhatsApp_API
	 */
	private $api;

	/**
	 * Logger
	 *
	 * @var WC_WhatsApp_Logger
	 */
	private $logger;

	/**
	 * Retorna instância única
	 *
	 * @return WC_WhatsApp_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	private function __construct() {
		$this->api    = WC_WhatsApp_API::get_instance();
		$this->logger = WC_WhatsApp_Logger::get_instance();

		// Adiciona menu no admin
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Handlers AJAX para testes
		add_action( 'wp_ajax_wc_whatsapp_test_api', array( $this, 'ajax_test_api' ) );
		add_action( 'wp_ajax_wc_whatsapp_send_test_message', array( $this, 'ajax_send_test_message' ) );
		add_action( 'wp_ajax_wc_whatsapp_birthday_manual', array( $this, 'ajax_birthday_manual' ) );
	}

	/**
	 * Adiciona menu no admin
	 */
	public function add_admin_menu() {
		// Tenta adicionar como submenu do WooCommerce
		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page(
				'woocommerce',
				__( 'WhatsApp Notifications', 'wc-whatsapp-notifications' ),
				__( 'WhatsApp', 'wc-whatsapp-notifications' ),
				'manage_woocommerce',
				'wc-whatsapp-notifications',
				array( $this, 'render_settings_page' )
			);
			add_submenu_page(
				'woocommerce',
				__( 'Aniversários e cupons WhatsApp', 'wc-whatsapp-notifications' ),
				__( 'WhatsApp · Aniversários', 'wc-whatsapp-notifications' ),
				'manage_woocommerce',
				'wc-whatsapp-birthday-reports',
				array( $this, 'render_birthday_reports_page' )
			);
		} else {
			// Fallback: adiciona como menu separado se WooCommerce não estiver ativo
			add_menu_page(
				__( 'WhatsApp Notifications', 'wc-whatsapp-notifications' ),
				__( 'WhatsApp', 'wc-whatsapp-notifications' ),
				'manage_options',
				'wc-whatsapp-notifications',
				array( $this, 'render_settings_page' ),
				'dashicons-whatsapp',
				56
			);
		}
	}

	/**
	 * Registra configurações
	 */
	public function register_settings() {
		// API Settings
		register_setting( 'wc_whatsapp_settings', 'wc_whatsapp_api_url', array( $this, 'sanitize_url' ) );
		register_setting( 'wc_whatsapp_settings', 'wc_whatsapp_api_token', array( $this, 'sanitize_token' ) );
		register_setting( 'wc_whatsapp_settings', 'wc_whatsapp_api_auth_type', array( $this, 'sanitize_auth_type' ) );

		// Status Settings
		register_setting( 'wc_whatsapp_settings', 'wc_whatsapp_enabled_statuses', array( $this, 'sanitize_statuses' ) );

		// Message Templates
		$statuses = array( 'processing', 'on-hold', 'completed', 'cancelled', 'refunded' );
		foreach ( $statuses as $status ) {
			register_setting( 'wc_whatsapp_settings', 'wc_whatsapp_message_' . $status, array( $this, 'sanitize_message' ) );
		}
		// Template para rastreio
		register_setting( 'wc_whatsapp_settings', 'wc_whatsapp_message_tracking', array( $this, 'sanitize_message' ) );
		// Template para observações do cliente
		register_setting( 'wc_whatsapp_settings', 'wc_whatsapp_message_customer_note', array( $this, 'sanitize_message' ) );

		// Imagens por status
		$statuses = array( 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'tracking', 'customer_note' );
		foreach ( $statuses as $status ) {
			register_setting( 'wc_whatsapp_settings', 'wc_whatsapp_image_' . $status, array( $this, 'sanitize_image_id' ) );
		}
	}

	/**
	 * Sanitiza URL
	 *
	 * @param string $url URL a sanitizar.
	 * @return string URL sanitizada.
	 */
	public function sanitize_url( $url ) {
		$url = esc_url_raw( trim( $url ) );
		if ( ! empty( $url ) ) {
			// Remove barra final se houver
			$url = rtrim( $url, '/' );
		}
		return $url;
	}

	/**
	 * Sanitiza token
	 *
	 * @param string $token Token a sanitizar.
	 * @return string Token sanitizado.
	 */
	public function sanitize_token( $token ) {
		return sanitize_text_field( trim( $token ) );
	}

	/**
	 * Sanitiza array de status
	 *
	 * @param array $statuses Array de status.
	 * @return array Array sanitizado.
	 */
	public function sanitize_statuses( $statuses ) {
		if ( ! is_array( $statuses ) ) {
			return array();
		}

		$valid_statuses = array( 'processing', 'on-hold', 'completed', 'cancelled', 'refunded' );
		$sanitized      = array();

		foreach ( $statuses as $status ) {
			$status = sanitize_text_field( $status );
			if ( in_array( $status, $valid_statuses, true ) ) {
				$sanitized[] = $status;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitiza mensagem
	 *
	 * @param string $message Mensagem a sanitizar.
	 * @return string Mensagem sanitizada.
	 */
	public function sanitize_message( $message ) {
		return sanitize_textarea_field( $message );
	}

	/**
	 * Sanitiza tipo de autenticação
	 *
	 * @param string $auth_type Tipo de autenticação.
	 * @return string Tipo sanitizado.
	 */
	public function sanitize_auth_type( $auth_type ) {
		$valid_types = array( 'bearer', 'token', 'apikey' );
		$auth_type   = sanitize_text_field( $auth_type );
		return in_array( $auth_type, $valid_types, true ) ? $auth_type : 'bearer';
	}

	/**
	 * Sanitiza ID da imagem
	 *
	 * @param mixed $image_id ID da imagem.
	 * @return int ID sanitizado ou 0.
	 */
	public function sanitize_image_id( $image_id ) {
		$image_id = absint( $image_id );
		// Verifica se é um attachment válido
		if ( $image_id > 0 && wp_attachment_is_image( $image_id ) ) {
			return $image_id;
		}
		return 0;
	}

	/**
	 * Carrega scripts e estilos do admin
	 *
	 * @param string $hook Hook da página atual.
	 */
	public function enqueue_admin_scripts( $hook ) {
		$allowed = array(
			'woocommerce_page_wc-whatsapp-notifications',
			'woocommerce_page_wc-whatsapp-birthday-reports',
		);
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wc-whatsapp-admin',
			WC_WHATSAPP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_WHATSAPP_VERSION
		);

		wp_enqueue_script(
			'wc-whatsapp-admin',
			WC_WHATSAPP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WC_WHATSAPP_VERSION,
			true
		);

		// Enqueue media uploader
		wp_enqueue_media();

		wp_localize_script(
			'wc-whatsapp-admin',
			'wcWhatsApp',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wc_whatsapp_admin' ),
				'birthdayNonce' => wp_create_nonce( 'wc_whatsapp_birthday_admin' ),
				'strings' => array(
					'testing'        => __( 'Testando...', 'wc-whatsapp-notifications' ),
					'success'        => __( 'Sucesso!', 'wc-whatsapp-notifications' ),
					'error'          => __( 'Erro!', 'wc-whatsapp-notifications' ),
					'sending'        => __( 'Enviando...', 'wc-whatsapp-notifications' ),
					'sent'           => __( 'Mensagem enviada!', 'wc-whatsapp-notifications' ),
					'invalid_phone'  => __( 'Telefone inválido', 'wc-whatsapp-notifications' ),
					'empty_message'  => __( 'Mensagem vazia', 'wc-whatsapp-notifications' ),
				),
			)
		);
	}

	/**
	 * Renderiza página de configurações
	 */
	public function render_settings_page() {
		// Verifica permissões
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'wc-whatsapp-notifications' ) );
		}

		// Processa formulário
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'wc_whatsapp_settings', 'wc_whatsapp_nonce' ) ) {
			$this->save_settings();
			$this->api->reload_settings();
		}

		// Obtém valores atuais
		$api_url          = get_option( 'wc_whatsapp_api_url', '' );
		$api_token        = get_option( 'wc_whatsapp_api_token', '' );
		$auth_type        = get_option( 'wc_whatsapp_api_auth_type', 'bearer' );
		$enabled_statuses = get_option( 'wc_whatsapp_enabled_statuses', array() );

		$statuses = array(
			'processing' => __( 'Em processamento', 'wc-whatsapp-notifications' ),
			'on-hold'    => __( 'Aguardando pagamento', 'wc-whatsapp-notifications' ),
			'completed'  => __( 'Concluído', 'wc-whatsapp-notifications' ),
			'cancelled'  => __( 'Cancelado', 'wc-whatsapp-notifications' ),
			'refunded'   => __( 'Reembolsado', 'wc-whatsapp-notifications' ),
		);

		include WC_WHATSAPP_PLUGIN_DIR . 'templates/admin-settings.php';
	}

	/**
	 * Salva configurações
	 */
	private function save_settings() {
		// Salva URL e Token
		if ( isset( $_POST['wc_whatsapp_api_url'] ) ) {
			update_option( 'wc_whatsapp_api_url', $this->sanitize_url( $_POST['wc_whatsapp_api_url'] ) );
		}

		if ( isset( $_POST['wc_whatsapp_api_token'] ) ) {
			update_option( 'wc_whatsapp_api_token', $this->sanitize_token( $_POST['wc_whatsapp_api_token'] ) );
		}

		if ( isset( $_POST['wc_whatsapp_api_auth_type'] ) ) {
			update_option( 'wc_whatsapp_api_auth_type', $this->sanitize_auth_type( $_POST['wc_whatsapp_api_auth_type'] ) );
		}

		// Salva status ativados
		$enabled_statuses = array();
		$statuses         = array( 'processing', 'on-hold', 'completed', 'cancelled', 'refunded' );

		foreach ( $statuses as $status ) {
			if ( isset( $_POST[ 'wc_whatsapp_enable_' . $status ] ) && '1' === $_POST[ 'wc_whatsapp_enable_' . $status ] ) {
				$enabled_statuses[] = $status;
			}
		}

		update_option( 'wc_whatsapp_enabled_statuses', $enabled_statuses );

		// Salva templates de mensagem
		foreach ( $statuses as $status ) {
			$key = 'wc_whatsapp_message_' . $status;
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, $this->sanitize_message( $_POST[ $key ] ) );
			}
		}

		// Salva template de rastreio
		if ( isset( $_POST['wc_whatsapp_message_tracking'] ) ) {
			update_option( 'wc_whatsapp_message_tracking', $this->sanitize_message( $_POST['wc_whatsapp_message_tracking'] ) );
		}

		// Salva template de observação do cliente
		if ( isset( $_POST['wc_whatsapp_message_customer_note'] ) ) {
			update_option( 'wc_whatsapp_message_customer_note', $this->sanitize_message( $_POST['wc_whatsapp_message_customer_note'] ) );
		}

		// Salva imagens por status
		$image_statuses = array( 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'tracking', 'customer_note' );
		foreach ( $image_statuses as $status ) {
			$option_key = 'wc_whatsapp_image_' . $status;
			if ( isset( $_POST[ $option_key ] ) ) {
				update_option( $option_key, $this->sanitize_image_id( $_POST[ $option_key ] ) );
			}
		}

		// Aniversário
		update_option( 'wc_whatsapp_birthday_enabled', isset( $_POST['wc_whatsapp_birthday_enabled'] ) && '1' === $_POST['wc_whatsapp_birthday_enabled'] );
		if ( isset( $_POST['wc_whatsapp_birthday_discount_type'] ) ) {
			$t = sanitize_text_field( wp_unslash( $_POST['wc_whatsapp_birthday_discount_type'] ) );
			update_option( 'wc_whatsapp_birthday_discount_type', in_array( $t, array( 'percent', 'fixed', 'free_shipping' ), true ) ? $t : 'percent' );
		}
		if ( isset( $_POST['wc_whatsapp_birthday_discount_amount'] ) ) {
			update_option( 'wc_whatsapp_birthday_discount_amount', wc_format_decimal( wp_unslash( $_POST['wc_whatsapp_birthday_discount_amount'] ) ) );
		}
		if ( isset( $_POST['wc_whatsapp_birthday_coupon_prefix'] ) ) {
			update_option( 'wc_whatsapp_birthday_coupon_prefix', sanitize_text_field( wp_unslash( $_POST['wc_whatsapp_birthday_coupon_prefix'] ) ) );
		}
		if ( isset( $_POST['wc_whatsapp_birthday_valid_days'] ) ) {
			update_option( 'wc_whatsapp_birthday_valid_days', max( 1, absint( $_POST['wc_whatsapp_birthday_valid_days'] ) ) );
		}
		if ( isset( $_POST['wc_whatsapp_birthday_cleanup_days'] ) ) {
			update_option( 'wc_whatsapp_birthday_cleanup_days', max( 1, absint( $_POST['wc_whatsapp_birthday_cleanup_days'] ) ) );
		}
		if ( isset( $_POST['wc_whatsapp_birthday_min_amount'] ) ) {
			update_option( 'wc_whatsapp_birthday_min_amount', wc_format_decimal( wp_unslash( $_POST['wc_whatsapp_birthday_min_amount'] ) ) );
		}
		update_option( 'wc_whatsapp_birthday_single_use', isset( $_POST['wc_whatsapp_birthday_single_use'] ) && '1' === $_POST['wc_whatsapp_birthday_single_use'] );
		update_option( 'wc_whatsapp_birthday_individual_use', isset( $_POST['wc_whatsapp_birthday_individual_use'] ) && '1' === $_POST['wc_whatsapp_birthday_individual_use'] );
		if ( isset( $_POST['wc_whatsapp_birthday_send_offset'] ) ) {
			$o = absint( $_POST['wc_whatsapp_birthday_send_offset'] );
			update_option( 'wc_whatsapp_birthday_send_offset', in_array( $o, array( 0, 1, 2, 7 ), true ) ? $o : 0 );
		}
		if ( isset( $_POST['wc_whatsapp_birthday_send_time'] ) ) {
			$time = sanitize_text_field( wp_unslash( $_POST['wc_whatsapp_birthday_send_time'] ) );
			if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
				update_option( 'wc_whatsapp_birthday_send_time', $time );
			}
		}
		if ( isset( $_POST['wc_whatsapp_birthday_message_template'] ) ) {
			update_option( 'wc_whatsapp_birthday_message_template', $this->sanitize_message( wp_unslash( $_POST['wc_whatsapp_birthday_message_template'] ) ) );
		}

		WC_WhatsApp_Birthday::reschedule_crons();

		// Mensagem de sucesso
		add_settings_error(
			'wc_whatsapp_settings',
			'settings_saved',
			__( 'Configurações salvas com sucesso!', 'wc-whatsapp-notifications' ),
			'success'
		);
	}


	/**
	 * Formata telefone para padrão brasileiro
	 *
	 * @param string $phone Telefone a formatar.
	 * @return string|false Telefone formatado ou false se inválido.
	 */
	private function format_phone( $phone ) {
		// Remove caracteres não numéricos
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		// Se já começa com 55, verifica se tem pelo menos 13 dígitos
		if ( substr( $phone, 0, 2 ) === '55' ) {
			if ( strlen( $phone ) >= 13 && strlen( $phone ) <= 14 ) {
				return $phone;
			}
			return false;
		}

		// Se começa com 0, remove
		if ( substr( $phone, 0, 1 ) === '0' ) {
			$phone = substr( $phone, 1 );
		}

		// Verifica se tem DDD (2 dígitos) + número (8-9 dígitos)
		if ( strlen( $phone ) >= 10 && strlen( $phone ) <= 11 ) {
			return '55' . $phone;
		}

		return false;
	}

	/**
	 * Handler AJAX para testar conexão com API
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'wc_whatsapp_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'wc-whatsapp-notifications' ) ) );
		}

		$api_url   = isset( $_POST['api_url'] ) ? sanitize_text_field( $_POST['api_url'] ) : '';
		$api_token = isset( $_POST['api_token'] ) ? sanitize_text_field( $_POST['api_token'] ) : '';

		if ( empty( $api_url ) || empty( $api_token ) ) {
			wp_send_json_error( array( 'message' => __( 'URL e Token são obrigatórios.', 'wc-whatsapp-notifications' ) ) );
		}

		// Testa a conexão usando a API
		$test_result = $this->test_api_connection( $api_url, $api_token );

		if ( is_wp_error( $test_result ) ) {
			wp_send_json_error( array( 'message' => $test_result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Conexão com API estabelecida com sucesso!', 'wc-whatsapp-notifications' ) ) );
	}

	/**
	 * Handler AJAX para enviar mensagem de teste
	 */
	public function ajax_send_test_message() {
		check_ajax_referer( 'wc_whatsapp_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'wc-whatsapp-notifications' ) ) );
		}

		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

		if ( empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Telefone é obrigatório.', 'wc-whatsapp-notifications' ) ) );
		}

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Mensagem é obrigatória.', 'wc-whatsapp-notifications' ) ) );
		}

		// Formata telefone
		$formatted_phone = $this->format_phone( $phone );

		if ( ! $formatted_phone ) {
			wp_send_json_error( array( 'message' => __( 'Telefone inválido. Use o formato: (44) 99999-9999', 'wc-whatsapp-notifications' ) ) );
		}

		// Envia mensagem de teste (bypass rate limit)
		$result = $this->api->send_message( $formatted_phone, $message, null, null, true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Mensagem de teste enviada com sucesso!', 'wc-whatsapp-notifications' ) ) );
	}

	/**
	 * Testa conexão com API
	 *
	 * @param string $api_url URL da API.
	 * @param string $api_token Token da API.
	 * @return bool|WP_Error True se conectou, WP_Error em caso de erro.
	 */
	private function test_api_connection( $api_url, $api_token ) {
		// Tenta descobrir o endpoint correto
		$endpoints = array( '/message', '/send', '/send-message', '/messages' );

		foreach ( $endpoints as $endpoint ) {
			$test_url = rtrim( $api_url, '/' ) . $endpoint;

			$response = wp_remote_post(
				$test_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'number'  => '5511999999999', // Número de teste
							'message' => 'Teste de conexão',
						)
					),
					'timeout' => 10,
				)
			);

			if ( ! is_wp_error( $response ) ) {
				$status_code = wp_remote_retrieve_response_code( $response );
				// Se retornou 200, 201 ou 400 (bad request mas API respondeu), a conexão está OK
				if ( in_array( $status_code, array( 200, 201, 400 ), true ) ) {
					return true;
				}
			}
		}

		// Se nenhum endpoint funcionou, tenta a URL diretamente (caso seja endpoint completo)
		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'number'  => '5511999999999',
						'message' => 'Teste de conexão',
					)
				),
				'timeout' => 10,
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );
			if ( in_array( $status_code, array( 200, 201, 400 ), true ) ) {
				return true;
			}
		}

		return new WP_Error( 'api_connection_failed', __( 'Não foi possível conectar com a API. Verifique a URL e o Token.', 'wc-whatsapp-notifications' ) );
	}

	/**
	 * Página de relatórios de aniversário
	 */
	public function render_birthday_reports_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'wc-whatsapp-notifications' ) );
		}

		WC_WhatsApp_Birthday_DB::ensure_installed();

		global $wpdb;
		$table   = WC_WhatsApp_Birthday_DB::get_table_name();
		$history = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200", ARRAY_A );
		$history = $history ? $history : array();

		$upcoming = $this->get_upcoming_birthdays( 30 );

		$month_start = gmdate( 'Y-m-01' );
		$sent_month  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE sent_date >= %s AND whatsapp_status = %s",
				$month_start,
				'enviado'
			)
		);
		$used_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'usado'" );
		$all_sent   = max( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE whatsapp_status = 'enviado'" ) );
		$use_rate   = round( 100 * $used_total / $all_sent, 1 );

		include WC_WHATSAPP_PLUGIN_DIR . 'templates/admin-birthday-reports.php';
	}

	/**
	 * Próximos aniversários (usuários com meta de data)
	 *
	 * @param int $days Days ahead.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_upcoming_birthdays( $days ) {
		global $wpdb;
		$keys = array( 'billing_birthdate', '_billing_birthdate', 'birthdate' );
		$in   = "'" . implode( "','", array_map( 'esc_sql', $keys ) ) . "'";
		$uids = $wpdb->get_col(
			"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ({$in}) AND meta_value != '' LIMIT 300"
		);
		if ( ! $uids ) {
			return array();
		}

		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'today', $tz );
		$end   = $today->modify( '+' . (int) $days . ' days' );
		$out   = array();

		foreach ( $uids as $uid ) {
			$raw = '';
			foreach ( $keys as $key ) {
				$v = get_user_meta( (int) $uid, $key, true );
				if ( ! empty( $v ) ) {
					$raw = $v;
					break;
				}
			}
			if ( '' === $raw ) {
				continue;
			}
			$bd = WC_WhatsApp_Birthday::get_instance()->parse_birthdate_public( $raw );
			if ( ! $bd ) {
				continue;
			}

			$y      = (int) $today->format( 'Y' );
			$b_this = $this->safe_bday_year( (int) $bd['month'], (int) $bd['day'], $y, $tz );
			if ( $b_this < $today ) {
				$b_this = $this->safe_bday_year( (int) $bd['month'], (int) $bd['day'], $y + 1, $tz );
			}
			if ( $b_this > $end ) {
				continue;
			}

			$user = get_userdata( (int) $uid );
			if ( ! $user ) {
				continue;
			}

			$out[] = array(
				'user_id'   => (int) $uid,
				'name'      => $user->display_name,
				'phone'     => get_user_meta( (int) $uid, 'billing_phone', true ),
				'birthday'  => $b_this->format( 'd/m' ),
				'birthfull' => $b_this->format( 'Y-m-d' ),
			);
		}

		usort(
			$out,
			function ( $a, $b ) {
				return strcmp( $a['birthfull'], $b['birthfull'] );
			}
		);

		return $out;
	}

	/**
	 * @param int            $month Month.
	 * @param int            $day Day.
	 * @param int            $year Year.
	 * @param DateTimeZone   $tz TZ.
	 * @return DateTimeImmutable
	 */
	private function safe_bday_year( $month, $day, $year, $tz ) {
		if ( 2 === $month && 29 === $day && ! ( ( ( $year % 4 === 0 ) && ( $year % 100 !== 0 ) ) || ( $year % 400 === 0 ) ) ) {
			$day = 28;
		}
		return new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ), $tz );
	}

	/**
	 * AJAX envio manual de cupom de aniversário
	 */
	public function ajax_birthday_manual() {
		check_ajax_referer( 'wc_whatsapp_birthday_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sem permissão.', 'wc-whatsapp-notifications' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Usuário inválido.', 'wc-whatsapp-notifications' ) ) );
		}

		$result = WC_WhatsApp_Birthday::get_instance()->send_manual_for_user( $user_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Cupom gerado e mensagem enviada.', 'wc-whatsapp-notifications' ) ) );
	}
}

