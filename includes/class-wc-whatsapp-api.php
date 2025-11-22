<?php
/**
 * Classe para comunicação com API WhatsApp
 *
 * @package WC_WhatsApp_Notifications
 */

// Se este arquivo for chamado diretamente, aborta.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe WC_WhatsApp_API
 */
class WC_WhatsApp_API {

	/**
	 * Instância única (Singleton)
	 *
	 * @var WC_WhatsApp_API
	 */
	private static $instance = null;

	/**
	 * URL base da API
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Token de autenticação
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Logger
	 *
	 * @var WC_WhatsApp_Logger
	 */
	private $logger;

	/**
	 * Retorna instância única
	 *
	 * @return WC_WhatsApp_API
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
		$this->logger = WC_WhatsApp_Logger::get_instance();
		$this->load_settings();
	}

	/**
	 * Verifica rate limiting antes de enviar mensagem
	 *
	 * @return bool|WP_Error True se pode enviar, WP_Error se excedeu limite.
	 */
	private function check_rate_limit() {
		$transient_key = 'wc_whatsapp_rate_limit';
		$rate_data = get_transient( $transient_key );
		
		if ( false === $rate_data ) {
			// Primeira requisição no período
			$rate_data = array(
				'count' => 1,
				'start_time' => time(),
			);
		} else {
			$rate_data['count']++;
		}
		
		// Limite: 100 requisições por minuto
		$max_requests = apply_filters( 'wc_whatsapp_rate_limit_max', 100 );
		$time_window = apply_filters( 'wc_whatsapp_rate_limit_window', 60 ); // 60 segundos
		
		// Se passou o período, reseta
		if ( ( time() - $rate_data['start_time'] ) > $time_window ) {
			$rate_data = array(
				'count' => 1,
				'start_time' => time(),
			);
		}
		
		// Verifica se excedeu o limite
		if ( $rate_data['count'] > $max_requests ) {
			$this->logger->warning(
				'Rate limit excedido',
				array(
					'count' => $rate_data['count'],
					'max' => $max_requests,
					'window' => $time_window,
				)
			);
			
			// Salva dados atualizados
			set_transient( $transient_key, $rate_data, $time_window );
			
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: número máximo de requisições, %d: janela de tempo em segundos */
					esc_html__( 'Limite de requisições excedido. Máximo de %d requisições por %d segundos. Tente novamente em alguns instantes.', 'wc-whatsapp-notifications' ),
					$max_requests,
					$time_window
				)
			);
		}
		
		// Salva dados atualizados
		set_transient( $transient_key, $rate_data, $time_window );
		
		return true;
	}

	/**
	 * Carrega configurações da API
	 */
	private function load_settings() {
		$this->api_url = get_option( 'wc_whatsapp_api_url', '' );
		$this->token   = get_option( 'wc_whatsapp_api_token', '' );
	}

	/**
	 * Atualiza configurações
	 */
	public function reload_settings() {
		$this->load_settings();
	}

	/**
	 * Envia mensagem via WhatsApp
	 *
	/**
	 * Envia mensagem via WhatsApp API
	 *
	 * @param string      $number Número do telefone (formato: 5544999999999).
	 * @param string      $message Mensagem a ser enviada.
	 * @param string|null $custom_endpoint Endpoint customizado (opcional).
	 * @param array|null  $body_format Formato do body (opcional).
	 * @return bool|WP_Error True se enviado com sucesso, WP_Error em caso de erro.
	 */
	public function send_message( $number, $message, $custom_endpoint = null, $body_format = null ) {
		// Validação de tipos para compatibilidade
		if ( ! is_string( $number ) || ! is_string( $message ) ) {
			return new WP_Error( 'invalid_params', __( 'Número e mensagem devem ser strings.', 'woocommerce-whatsapp-notifications' ) );
		}
		// Verifica rate limiting
		$rate_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}
		// Validações
		if ( empty( $this->api_url ) || empty( $this->token ) ) {
			$error = new WP_Error(
				'api_not_configured',
				'API não configurada. Configure a URL e o Token nas configurações.'
			);
			$this->logger->error( 'Tentativa de envio sem configuração da API' );
			return $error;
		}

		if ( empty( $number ) || empty( $message ) ) {
			$error = new WP_Error(
				'invalid_params',
				'Número ou mensagem vazios.'
			);
			$this->logger->error( 'Tentativa de envio com parâmetros inválidos', array( 'number' => $number ) );
			return $error;
		}

		// Determina endpoint
		// Se a URL já contém o endpoint completo (como no caso da API CDW), usa diretamente
		// Caso contrário, adiciona o endpoint configurado
		if ( $custom_endpoint ) {
			// Se custom_endpoint começa com http, é uma URL completa
			if ( strpos( $custom_endpoint, 'http' ) === 0 ) {
				$endpoint = $custom_endpoint;
			} else {
				$base_url = rtrim( $this->api_url, '/' );
				$endpoint_path = '/' . ltrim( $custom_endpoint, '/' );
				$endpoint = $base_url . $endpoint_path;
			}
		} else {
			$saved_endpoint = get_option( 'wc_whatsapp_api_endpoint', '' );
			
			// Se não há endpoint salvo ou está vazio, a URL base já é o endpoint completo
			if ( empty( $saved_endpoint ) ) {
				$endpoint = $this->api_url;
			} else {
				// Se saved_endpoint começa com http, é uma URL completa
				if ( strpos( $saved_endpoint, 'http' ) === 0 ) {
					$endpoint = $saved_endpoint;
				} else {
					$base_url = rtrim( $this->api_url, '/' );
					$endpoint_path = '/' . ltrim( $saved_endpoint, '/' );
					$endpoint = $base_url . $endpoint_path;
				}
			}
		}

		// Determina formato do body
		if ( $body_format && isset( $body_format['number'] ) && isset( $body_format['message'] ) ) {
			$number_key = $body_format['number'];
			$message_key = $body_format['message'];
		} else {
			$saved_format = get_option( 'wc_whatsapp_api_body_format', array( 'number' => 'number', 'message' => 'body' ) );
			$number_key   = isset( $saved_format['number'] ) ? $saved_format['number'] : 'number';
			$message_key  = isset( $saved_format['message'] ) ? $saved_format['message'] : 'body';
		}

		// Prepara dados
		$data = array(
			$number_key  => sanitize_text_field( $number ),
			$message_key => sanitize_textarea_field( $message ),
		);

		// Prepara headers
		$headers = array(
			'Content-Type' => 'application/json',
		);

		// Determina formato de autenticação
		$auth_type = get_option( 'wc_whatsapp_api_auth_type', 'bearer' );
		switch ( $auth_type ) {
			case 'token':
				$headers['Authorization'] = 'Token ' . sanitize_text_field( $this->token );
				break;
			case 'apikey':
				$headers['X-API-Key'] = sanitize_text_field( $this->token );
				break;
			case 'bearer':
			default:
				$headers['Authorization'] = 'Bearer ' . sanitize_text_field( $this->token );
				break;
		}

		// Prepara requisição
		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => wp_json_encode( $data ),
			'timeout' => 30,
		);

		$this->logger->debug(
			'Enviando mensagem WhatsApp',
			array(
				'endpoint'    => $endpoint,
				'number'      => $number,
				'message'     => substr( $message, 0, 50 ) . '...',
				'body_format' => array( $number_key => $number, $message_key => '...' ),
			)
		);

		// Faz requisição
		$response = wp_remote_post( $endpoint, $args );

		// Verifica erros HTTP
		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'Erro ao enviar mensagem WhatsApp',
				array(
					'error'    => $response->get_error_message(),
					'code'     => $response->get_error_code(),
					'endpoint' => $endpoint,
				)
			);
			return $response;
		}

		// Verifica código de resposta
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$this->logger->debug(
			'Resposta da API WhatsApp',
			array(
				'code' => $response_code,
				'body' => $response_body,
			)
		);

		// Se sucesso (200-299)
		if ( $response_code >= 200 && $response_code < 300 ) {
			$this->logger->info(
				'Mensagem enviada com sucesso',
				array( 'number' => $number, 'code' => $response_code )
			);

			return true;
		}

		// Tenta extrair mensagem de erro da resposta
		$error_message = sprintf( 'Erro na API: %d', $response_code );
		$decoded_body = json_decode( $response_body, true );
		if ( is_array( $decoded_body ) ) {
			if ( isset( $decoded_body['message'] ) ) {
				$error_message .= ' - ' . $decoded_body['message'];
			} elseif ( isset( $decoded_body['error'] ) ) {
				$error_message .= ' - ' . ( is_string( $decoded_body['error'] ) ? $decoded_body['error'] : wp_json_encode( $decoded_body['error'] ) );
			} else {
				$error_message .= ' - ' . substr( $response_body, 0, 200 );
			}
		} else {
			$error_message .= ' - ' . substr( $response_body, 0, 200 );
		}

		// Erro na API
		$error = new WP_Error(
			'api_error',
			$error_message
		);

		$this->logger->error(
			'Erro na resposta da API WhatsApp',
			array(
				'code' => $response_code,
				'body' => $response_body,
			)
		);

		return $error;
	}

	/**
	 * Verifica se a API está configurada
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->api_url ) && ! empty( $this->token );
	}
}

