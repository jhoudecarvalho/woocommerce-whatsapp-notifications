<?php
/**
 * Plugin Name: WooCommerce WhatsApp Notifications
 * Plugin URI: https://cdwtech.com.br
 * Description: Envia notificações automáticas via WhatsApp quando pedidos WooCommerce mudam de status
 * Version: 1.0.0
 * Author: Jhou de Carvalho
 * Author URI: https://cdwtech.com.br
 * Text Domain: wc-whatsapp-notifications
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package WC_WhatsApp_Notifications
 */

// Se este arquivo for chamado diretamente, aborta.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constantes do plugin
define( 'WC_WHATSAPP_VERSION', '1.0.0' );
define( 'WC_WHATSAPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_WHATSAPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_WHATSAPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Classe principal do plugin
 */
class WC_WhatsApp_Notifications {

	/**
	 * Instância única do plugin (Singleton)
	 *
	 * @var WC_WhatsApp_Notifications
	 */
	private static $instance = null;

	/**
	 * Retorna instância única do plugin
	 *
	 * @return WC_WhatsApp_Notifications
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor privado (Singleton)
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Inicializa o plugin
	 */
	private function init() {
		// Verifica se WooCommerce está ativo
		add_action( 'plugins_loaded', array( $this, 'check_woocommerce' ) );
	}

	/**
	 * Verifica se WooCommerce está ativo
	 */
	public function check_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Carrega classes do plugin
		$this->load_dependencies();

		// Inicializa componentes
		$this->init_components();
	}

	/**
	 * Carrega dependências do plugin
	 */
	private function load_dependencies() {
		require_once WC_WHATSAPP_PLUGIN_DIR . 'includes/class-wc-whatsapp-api.php';
		require_once WC_WHATSAPP_PLUGIN_DIR . 'includes/class-wc-whatsapp-logger.php';
		require_once WC_WHATSAPP_PLUGIN_DIR . 'includes/class-wc-whatsapp-admin.php';
		require_once WC_WHATSAPP_PLUGIN_DIR . 'includes/class-wc-whatsapp-handler.php';
	}

	/**
	 * Inicializa componentes do plugin
	 */
	private function init_components() {
		// Inicializa handler de pedidos
		WC_WhatsApp_Handler::get_instance();

		// Inicializa admin se estiver no admin
		if ( is_admin() ) {
			WC_WhatsApp_Admin::get_instance();
		}
	}

	/**
	 * Exibe aviso se WooCommerce não estiver ativo
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'WooCommerce WhatsApp Notifications', 'wc-whatsapp-notifications' ); ?></strong>
				<?php esc_html_e( 'requer que o WooCommerce esteja instalado e ativo.', 'wc-whatsapp-notifications' ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * Inicializa o plugin
 */
function wc_whatsapp_notifications_init() {
	return WC_WhatsApp_Notifications::get_instance();
}

// Inicia o plugin
wc_whatsapp_notifications_init();

