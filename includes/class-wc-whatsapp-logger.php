<?php
/**
 * Classe para gerenciamento de logs
 *
 * @package WC_WhatsApp_Notifications
 */

// Se este arquivo for chamado diretamente, aborta.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe WC_WhatsApp_Logger
 */
class WC_WhatsApp_Logger {

	/**
	 * Instância única (Singleton)
	 *
	 * @var WC_WhatsApp_Logger
	 */
	private static $instance = null;

	/**
	 * Logger do WooCommerce
	 *
	 * @var WC_Logger
	 */
	private $logger;

	/**
	 * Retorna instância única
	 *
	 * @return WC_WhatsApp_Logger
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
		if ( class_exists( 'WC_Logger' ) ) {
			$this->logger = wc_get_logger();
		}
	}

	/**
	 * Registra mensagem de log
	 *
	 * @param string $message Mensagem a ser logada.
	 * @param string $level Nível do log (info, error, warning, debug).
	 * @param array  $context Contexto adicional.
	 */
	public function log( $message, $level = 'info', $context = array() ) {
		if ( ! $this->logger ) {
			return;
		}

		$context['source'] = 'wc-whatsapp-notifications';
		$this->logger->log( $level, $message, $context );
	}

	/**
	 * Registra mensagem de informação
	 *
	 * @param string $message Mensagem.
	 * @param array  $context Contexto adicional.
	 */
	public function info( $message, $context = array() ) {
		$this->log( $message, 'info', $context );
	}

	/**
	 * Registra mensagem de erro
	 *
	 * @param string $message Mensagem.
	 * @param array  $context Contexto adicional.
	 */
	public function error( $message, $context = array() ) {
		$this->log( $message, 'error', $context );
	}

	/**
	 * Registra mensagem de aviso
	 *
	 * @param string $message Mensagem.
	 * @param array  $context Contexto adicional.
	 */
	public function warning( $message, $context = array() ) {
		$this->log( $message, 'warning', $context );
	}

	/**
	 * Registra mensagem de debug
	 *
	 * @param string $message Mensagem.
	 * @param array  $context Contexto adicional.
	 */
	public function debug( $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->log( $message, 'debug', $context );
		}
	}
}

