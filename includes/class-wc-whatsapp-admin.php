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
	 * Carrega scripts e estilos do admin
	 *
	 * @param string $hook Hook da página atual.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-whatsapp-notifications' !== $hook ) {
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

		wp_localize_script(
			'wc-whatsapp-admin',
			'wcWhatsApp',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wc_whatsapp_admin' ),
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
}

