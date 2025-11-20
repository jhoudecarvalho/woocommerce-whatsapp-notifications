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
	 * Testa conexão com a API tentando diferentes endpoints e formatos
	 *
	 * @return array Resultado do teste com endpoint encontrado ou erro.
	 */
	public function test_api_connection() {
		$test_number = '5544999999999'; // Número de teste
		$test_message = 'Teste de conexão - ' . date( 'Y-m-d H:i:s' );

		// Primeiro, tenta usar a URL diretamente (caso seja endpoint completo)
		$result = $this->send_message( $test_number, $test_message, null, true, array( 'number' => 'number', 'message' => 'body' ) );

		if ( ! is_wp_error( $result ) ) {
			// Salva o formato que funcionou
			update_option( 'wc_whatsapp_api_body_format', array( 'number' => 'number', 'message' => 'body' ) );
			update_option( 'wc_whatsapp_api_endpoint', '' ); // Vazio indica que URL base é o endpoint completo
			$this->logger->info( 'Conexão bem-sucedida usando URL base como endpoint completo' );
			return array(
				'success' => true,
				'endpoint' => 'URL base (endpoint completo)',
				'format'   => array( 'number' => 'number', 'message' => 'body' ),
				'message'  => 'Conexão bem-sucedida!',
			);
		}

		// Se não funcionou, tenta diferentes endpoints
		$endpoints = array(
			'/send-message',
			'/messages/send',
			'/sendText',
			'/message/send',
			'/send',
			'/api/send',
			'/v1/send',
			'/whatsapp/send',
		);

		// Tenta diferentes formatos de body
		$body_formats = array(
			array( 'number' => 'number', 'message' => 'body' ),
			array( 'number' => 'phone', 'message' => 'message' ),
			array( 'number' => 'phoneNumber', 'message' => 'text' ),
			array( 'number' => 'to', 'message' => 'message' ),
			array( 'number' => 'recipient', 'message' => 'body' ),
		);

		foreach ( $endpoints as $endpoint ) {
			foreach ( $body_formats as $format ) {
				$result = $this->send_message( $test_number, $test_message, $endpoint, true, $format );

				if ( ! is_wp_error( $result ) ) {
					// Salva o formato que funcionou
					update_option( 'wc_whatsapp_api_body_format', $format );
					update_option( 'wc_whatsapp_api_endpoint', $endpoint );
					$this->logger->info(
						sprintf( 'Endpoint e formato encontrados: %s (number: %s, message: %s)', $endpoint, $format['number'], $format['message'] ),
						array( 'endpoint' => $endpoint, 'format' => $format )
					);
					return array(
						'success'  => true,
						'endpoint' => $endpoint,
						'format'   => $format,
						'message'  => 'Conexão bem-sucedida!',
					);
				}

				// Se não for erro de endpoint não encontrado, pode ser o endpoint correto
				$error_code = $result->get_error_code();
				if ( 'http_request_failed' !== $error_code && 'http_404' !== $error_code ) {
					// Pode ser o endpoint correto, mas com erro de autenticação ou validação
					$this->logger->debug(
						sprintf( 'Endpoint possível: %s com formato %s/%s (erro: %s)', $endpoint, $format['number'], $format['message'], $error_code ),
						array( 'endpoint' => $endpoint, 'format' => $format, 'error' => $result->get_error_message() )
					);
				}
			}
		}

		return array(
			'success' => false,
			'message' => 'Nenhum endpoint válido encontrado. Verifique a URL base e o token. Verifique também os logs para mais detalhes.',
		);
	}

	/**
	 * Envia mensagem via WhatsApp
	 *
	 * @param string $number Número do telefone (formato: 5544999999999).
	 * @param string $message Mensagem a ser enviada.
	 * @param string $custom_endpoint Endpoint customizado (opcional, para testes).
	 * @param bool   $is_test Se é um teste (não salva endpoint).
	 * @param array  $body_format Formato do body (opcional, para testes).
	 * @return WP_Error|array Resposta da API ou erro.
	 */
	public function send_message( $number, $message, $custom_endpoint = null, $is_test = false, $body_format = null ) {
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
			// Se for teste e endpoint funcionou, salva o endpoint e formato
			if ( $is_test && $custom_endpoint ) {
				update_option( 'wc_whatsapp_api_endpoint', $custom_endpoint );
				$this->logger->info( 'Endpoint salvo: ' . $custom_endpoint );
			}
			if ( $is_test && $body_format ) {
				update_option( 'wc_whatsapp_api_body_format', $body_format );
				$this->logger->info( 'Formato do body salvo', array( 'format' => $body_format ) );
			}

			$this->logger->info(
				'Mensagem enviada com sucesso',
				array( 'number' => $number, 'code' => $response_code )
			);

			$decoded_body = json_decode( $response_body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$decoded_body = $response_body;
			}

			return array(
				'success' => true,
				'code'    => $response_code,
				'body'    => $decoded_body,
			);
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

