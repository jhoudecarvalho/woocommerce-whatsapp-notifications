<?php
/**
 * Classe para gerenciar eventos de pedidos WooCommerce
 *
 * @package WC_WhatsApp_Notifications
 */

// Se este arquivo for chamado diretamente, aborta.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe WC_WhatsApp_Handler
 */
class WC_WhatsApp_Handler {

	/**
	 * Instância única (Singleton)
	 *
	 * @var WC_WhatsApp_Handler
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
	 * @return WC_WhatsApp_Handler
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

		// Hooks para mudanças de status de pedido
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
		
		// Hooks para pedidos recém-criados (múltiplos hooks para garantir captura)
		add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'handle_checkout_order_processed' ), 20, 1 );
		
		// Hook adicional para garantir que pedidos criados manualmente também sejam processados
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'handle_order_saved' ), 20, 2 );
		
		// Hook para detectar quando uma nota é adicionada ao pedido (pode conter código de rastreio)
		add_action( 'woocommerce_order_note_added', array( $this, 'handle_order_note_added' ), 10, 2 );
		
		// Hook específico para notas do cliente (observações para cliente)
		add_action( 'woocommerce_new_customer_note', array( $this, 'handle_customer_note' ), 10, 1 );
		
		// Hook para detectar quando pedido é atualizado/salvo (pode ter código de rastreio adicionado)
		add_action( 'woocommerce_update_order', array( $this, 'handle_order_updated' ), 20, 2 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'check_tracking_after_save' ), 30, 2 );
		
		// Hook do plugin wc-any-shipping-notify quando código de rastreio é adicionado
		add_action( 'wcasn_tracking_added', array( $this, 'handle_wcasn_tracking_added' ), 10, 3 );
	}

	/**
	 * Processa pedido recém-criado
	 *
	 * @param int    $order_id ID do pedido.
	 * @param object $order Objeto do pedido.
	 */
	public function handle_new_order( $order_id, $order ) {
		$this->logger->debug(
			'Hook woocommerce_new_order disparado',
			array( 'order_id' => $order_id )
		);
		
		// Aguarda um pouco para garantir que o pedido está totalmente salvo
		$this->schedule_order_processing( $order_id );
	}

	/**
	 * Processa pedido após checkout
	 *
	 * @param int $order_id ID do pedido.
	 */
	public function handle_checkout_order_processed( $order_id ) {
		$this->logger->debug(
			'Hook woocommerce_checkout_order_processed disparado',
			array( 'order_id' => $order_id )
		);
		
		$this->schedule_order_processing( $order_id );
	}

	/**
	 * Processa pedido quando salvo manualmente no admin
	 *
	 * @param int $order_id ID do pedido.
	 * @param object $order Objeto do pedido.
	 */
	public function handle_order_saved( $order_id, $order ) {
		$this->logger->debug(
			'Hook woocommerce_process_shop_order_meta disparado',
			array( 'order_id' => $order_id )
		);
		
		$this->schedule_order_processing( $order_id );
	}

	/**
	 * Agenda processamento do pedido (evita processar múltiplas vezes)
	 *
	 * @param int $order_id ID do pedido.
	 */
	private function schedule_order_processing( $order_id ) {
		static $scheduled_orders = array();
		
		// Evita processar o mesmo pedido múltiplas vezes
		if ( isset( $scheduled_orders[ $order_id ] ) ) {
			return;
		}
		
		$scheduled_orders[ $order_id ] = true;
		
		// Usa transiente para evitar processar em múltiplos hooks
		$transient_key = 'wc_whatsapp_processing_' . $order_id;
		if ( get_transient( $transient_key ) ) {
			return;
		}
		
		// Marca como processando por 30 segundos
		set_transient( $transient_key, true, 30 );
		
		// Processa após um pequeno delay para garantir que tudo está salvo
		add_action( 'shutdown', function() use ( $order_id ) {
			$this->process_new_order( $order_id );
		}, 20 );
	}

	/**
	 * Processa pedido recém-criado
	 *
	 * @param int $order_id ID do pedido.
	 */
	private function process_new_order( $order_id ) {
		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			$this->logger->warning( 'Pedido não encontrado ao processar', array( 'order_id' => $order_id ) );
			return;
		}
		
		$status = $order->get_status();
		$this->logger->debug(
			'Processando pedido recém-criado',
			array( 'order_id' => $order_id, 'status' => $status )
		);
		
		// Processa como se fosse uma mudança de status (de 'new' para o status atual)
		$this->process_notification( $order_id, 'new', $status, $order );
	}

	/**
	 * Processa mudança de status do pedido
	 *
	 * @param int    $order_id ID do pedido.
	 * @param string $old_status Status anterior.
	 * @param string $new_status Novo status.
	 * @param object $order Objeto do pedido.
	 */
	public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
		// Ignora se old_status é rascunho e já processamos via woocommerce_new_order
		// Isso evita duplicatas quando pedido é criado
		$draft_statuses = array( 'new', 'auto-draft', 'draft', 'checkout-draft' );
		if ( in_array( $old_status, $draft_statuses, true ) ) {
			// Verifica se já foi processado via woocommerce_new_order
			$notification_key = 'wc_whatsapp_notified_' . $order_id . '_' . $new_status;
			if ( get_transient( $notification_key ) ) {
				$this->logger->debug(
					'Ignorando status_changed para pedido recém-criado (já processado via new_order)',
					array(
						'order_id'   => $order_id,
						'old_status' => $old_status,
						'new_status' => $new_status,
					)
				);
				return;
			}
		}
		
		$this->process_notification( $order_id, $old_status, $new_status, $order );
	}

	/**
	 * Processa notificação (método compartilhado)
	 *
	 * @param int    $order_id ID do pedido.
	 * @param string $old_status Status anterior.
	 * @param string $new_status Novo status.
	 * @param object $order Objeto do pedido.
	 */
	private function process_notification( $order_id, $old_status, $new_status, $order ) {
		// Proteção contra duplicatas: verifica se já processou esta combinação
		$notification_key = 'wc_whatsapp_notified_' . $order_id . '_' . $new_status;
		$notified = get_transient( $notification_key );
		
		if ( $notified ) {
			$this->logger->debug(
				'Notificação já enviada para esta combinação de pedido/status, ignorando',
				array(
					'order_id'   => $order_id,
					'old_status' => $old_status,
					'new_status' => $new_status,
				)
			);
			return;
		}
		
		// Verifica se API está configurada
		if ( ! $this->api->is_configured() ) {
			$this->logger->debug( 'API não configurada, ignorando notificação', array( 'order_id' => $order_id ) );
			return;
		}

		// Ignora status de rascunho
		$draft_statuses = array( 'new', 'auto-draft', 'draft', 'checkout-draft' );
		if ( in_array( $new_status, $draft_statuses, true ) ) {
			$this->logger->debug( 'Status é rascunho, ignorando notificação', array( 'order_id' => $order_id, 'status' => $new_status ) );
			return;
		}

		// Verifica se notificação para este status está ativada
		if ( ! $this->is_status_enabled( $new_status ) ) {
			$this->logger->debug(
				'Notificação desativada para status',
				array( 'order_id' => $order_id, 'status' => $new_status )
			);
			return;
		}

		// Obtém telefone do cliente
		$phone = $this->get_customer_phone( $order );

		if ( empty( $phone ) ) {
			$this->logger->warning(
				'Telefone não encontrado para pedido',
				array( 'order_id' => $order_id )
			);
			return;
		}

		// Formata telefone
		$formatted_phone = $this->format_phone( $phone );

		if ( ! $formatted_phone ) {
			$this->logger->warning(
				'Telefone inválido para pedido',
				array( 'order_id' => $order_id, 'phone' => $phone, 'phone_clean' => preg_replace( '/[^0-9]/', '', $phone ) )
			);
			return;
		}

		// Gera mensagem
		$message = $this->generate_message( $order, $new_status );

		if ( empty( $message ) ) {
			$this->logger->warning(
				'Mensagem vazia gerada para pedido',
				array( 'order_id' => $order_id, 'status' => $new_status )
			);
			return;
		}

		// Log antes de enviar
		$this->logger->info(
			'Preparando envio de notificação WhatsApp',
			array(
				'order_id'        => $order_id,
				'status'          => $new_status,
				'phone_original'  => $phone,
				'phone_formatted' => $formatted_phone,
			)
		);

		// Envia mensagem (não bloqueia o processo se falhar)
		$result = $this->api->send_message( $formatted_phone, $message );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Erro ao enviar notificação WhatsApp',
				array(
					'order_id' => $order_id,
					'status'   => $new_status,
					'error'    => $result->get_error_message(),
					'code'     => $result->get_error_code(),
				)
			);
		} else {
			// Marca como notificado por 1 hora para evitar duplicatas
			set_transient( $notification_key, true, HOUR_IN_SECONDS );
			
			$this->logger->info(
				'Notificação WhatsApp enviada com sucesso',
				array( 'order_id' => $order_id, 'status' => $new_status, 'response' => $result )
			);
		}
	}

	/**
	 * Verifica se notificação está ativada para um status
	 *
	 * @param string $status Status do pedido.
	 * @return bool
	 */
	private function is_status_enabled( $status ) {
		$enabled_statuses = get_option( 'wc_whatsapp_enabled_statuses', array() );
		return in_array( $status, $enabled_statuses, true );
	}

	/**
	 * Obtém telefone do cliente do pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return string Telefone ou string vazia.
	 */
	private function get_customer_phone( $order ) {
		$phone = $order->get_billing_phone();
		return $phone ? $phone : '';
	}

	/**
	 * Formata telefone para padrão brasileiro (55 + DDD + número)
	 *
	 * @param string $phone Telefone a formatar.
	 * @return string|false Telefone formatado ou false se inválido.
	 */
	private function format_phone( $phone ) {
		// Remove caracteres não numéricos
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		// Se já começa com 55, verifica se tem pelo menos 13 dígitos (55 + 2 DDD + 9-10 dígitos)
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
			// Adiciona código do país
			return '55' . $phone;
		}

		return false;
	}

	/**
	 * Gera mensagem personalizada para o pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @param string   $status Status do pedido.
	 * @return string Mensagem formatada.
	 */
	private function generate_message( $order, $status ) {
		// Obtém template de mensagem para o status
		$template = $this->get_message_template( $status );

		if ( empty( $template ) ) {
			$template = $this->get_default_template( $status );
		}

		// Dados do pedido
		$customer_name = $order->get_billing_first_name();
		$order_number  = $order->get_order_number();
		// Converte HTML para texto simples (WhatsApp não suporta HTML)
		$order_total   = html_entity_decode( strip_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
		$order_date    = wc_format_datetime( $order->get_date_created(), 'd/m/Y' );

		// Lista de produtos
		$products_list = $this->get_products_list( $order );

		// Status em português
		$status_label = $this->get_status_label( $status );

		// Informações de entrega
		$shipping_method = $this->get_shipping_method( $order );
		$shipping_total  = $this->get_shipping_total( $order );

		// Substitui placeholders
		$message = str_replace(
			array(
				'{customer_name}',
				'{order_number}',
				'{order_total}',
				'{order_date}',
				'{products_list}',
				'{status}',
				'{shipping_method}',
				'{shipping_total}',
			),
			array(
				$customer_name,
				$order_number,
				$order_total,
				$order_date,
				$products_list,
				$status_label,
				$shipping_method,
				$shipping_total,
			),
			$template
		);

		return $message;
	}

	/**
	 * Obtém template de mensagem personalizado para um status
	 *
	 * @param string $status Status do pedido.
	 * @return string Template ou string vazia.
	 */
	private function get_message_template( $status ) {
		return get_option( 'wc_whatsapp_message_' . $status, '' );
	}

	/**
	 * Obtém template padrão para um status
	 *
	 * @param string $status Status do pedido.
	 * @return string Template padrão.
	 */
	private function get_default_template( $status ) {
		$templates = array(
			'processing' => "Olá *{customer_name}*! 👋\n\nSeu pedido *#{order_number}* está sendo processado!\n\n📦 *Produtos:*\n{products_list}\n\n🚚 *Entrega:* {shipping_method}\n💵 *Frete:* {shipping_total}\n\n💰 *Total:* {order_total}\n\n📅 *Data:* {order_date}\n\nEm breve você receberá mais atualizações!",
			'on-hold'    => "Olá *{customer_name}*! 👋\n\nSeu pedido *#{order_number}* está aguardando pagamento.\n\n📦 *Produtos:*\n{products_list}\n\n🚚 *Entrega:* {shipping_method}\n💵 *Frete:* {shipping_total}\n\n💰 *Total:* {order_total}\n\n📅 *Data:* {order_date}\n\nAssim que o pagamento for confirmado, processaremos seu pedido!",
			'completed'  => "Olá *{customer_name}*! 🎉\n\nSeu pedido *#{order_number}* foi concluído!\n\n📦 *Produtos:*\n{products_list}\n\n🚚 *Entrega:* {shipping_method}\n💵 *Frete:* {shipping_total}\n\n💰 *Total:* {order_total}\n\n📅 *Data:* {order_date}\n\nObrigado pela sua compra!",
			'cancelled'  => "Olá *{customer_name}*,\n\nInfelizmente seu pedido *#{order_number}* foi cancelado.\n\n📦 *Produtos:*\n{products_list}\n\n🚚 *Entrega:* {shipping_method}\n💵 *Frete:* {shipping_total}\n\n💰 *Total:* {order_total}\n\n📅 *Data:* {order_date}\n\nSe tiver dúvidas, entre em contato conosco.",
			'refunded'   => "Olá *{customer_name}*,\n\nSeu pedido *#{order_number}* foi reembolsado.\n\n📦 *Produtos:*\n{products_list}\n\n🚚 *Entrega:* {shipping_method}\n💵 *Frete:* {shipping_total}\n\n💰 *Total:* {order_total}\n\n📅 *Data:* {order_date}\n\nO valor será estornado conforme método de pagamento utilizado.",
		);

		return isset( $templates[ $status ] ) ? $templates[ $status ] : '';
	}

	/**
	 * Obtém lista formatada de produtos do pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return string Lista de produtos formatada.
	 */
	private function get_products_list( $order ) {
		$items = $order->get_items();
		$list  = array();

		foreach ( $items as $item ) {
			$product_name = $item->get_name();
			$quantity     = $item->get_quantity();
			// Converte HTML para texto simples (WhatsApp não suporta HTML)
			$line_total   = html_entity_decode( strip_tags( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );

			$list[] = sprintf( '• %s (Qtd: %d) - %s', $product_name, $quantity, $line_total );
		}

		return implode( "\n", $list );
	}

	/**
	 * Obtém método de entrega do pedido
	 *
	 * @param WC_Order $order Pedido.
	 * @return string Método de entrega formatado.
	 */
	private function get_shipping_method( $order ) {
		$shipping_methods = $order->get_shipping_methods();
		
		if ( empty( $shipping_methods ) ) {
			return 'Não informado';
		}
		
		$methods = array();
		foreach ( $shipping_methods as $shipping_method ) {
			$method_title = $shipping_method->get_method_title();
			$method_name  = $shipping_method->get_name();
			
			// Se method_title estiver vazio, usa o name
			if ( empty( $method_title ) ) {
				$methods[] = $method_name;
			} else {
				$methods[] = $method_title;
			}
		}
		
		return implode( ', ', $methods );
	}

	/**
	 * Obtém valor total do frete/entrega
	 *
	 * @param WC_Order $order Pedido.
	 * @return string Valor do frete formatado.
	 */
	private function get_shipping_total( $order ) {
		$shipping_total = $order->get_shipping_total();
		
		if ( empty( $shipping_total ) || 0 == $shipping_total ) {
			return 'Grátis';
		}
		
		// Converte HTML para texto simples (WhatsApp não suporta HTML)
		return html_entity_decode( strip_tags( wc_price( $shipping_total, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Obtém label em português para um status
	 *
	 * @param string $status Status do pedido.
	 * @return string Label em português.
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'processing' => 'Em processamento',
			'on-hold'    => 'Aguardando pagamento',
			'completed'  => 'Concluído',
			'cancelled'  => 'Cancelado',
			'refunded'   => 'Reembolsado',
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Processa quando uma nota é adicionada ao pedido
	 *
	 * @param int      $note_id ID da nota.
	 * @param WC_Order $order Objeto do pedido.
	 */
	public function handle_order_note_added( $note_id, $order ) {
		if ( ! $order ) {
			return;
		}
		
		// Se o plugin wc-any-shipping-notify está ativo, não processa notas
		// O hook wcasn_tracking_added já cuida disso
		if ( function_exists( 'wc_any_shipping_get_tracking_codes' ) ) {
			$tracking_codes = wc_any_shipping_get_tracking_codes( $order );
			if ( is_array( $tracking_codes ) && ! empty( $tracking_codes ) ) {
				// Verifica se a nota contém algum dos códigos já processados
				$note = get_comment( $note_id );
				if ( $note ) {
					$note_content = $note->comment_content;
					$tracking_code = $this->extract_tracking_code( $note_content );
					
					if ( $tracking_code && isset( $tracking_codes[ $tracking_code ] ) ) {
						// Este código já foi processado via wcasn_tracking_added
						$this->logger->debug(
							'Ignorando nota - código já processado via wcasn_tracking_added',
							array(
								'order_id'      => $order->get_id(),
								'tracking_code' => $tracking_code,
							)
						);
						return;
					}
				}
			}
		}
		
		// Obtém a nota
		$note = get_comment( $note_id );
		if ( ! $note ) {
			return;
		}
		
		// Verifica se é uma nota do cliente (customer note)
		$is_customer_note = get_comment_meta( $note_id, 'is_customer_note', true );
		
		// Verifica se a nota contém código de rastreio
		$note_content = $note->comment_content;
		$tracking_code = $this->extract_tracking_code( $note_content );
		
		if ( $tracking_code ) {
			$this->logger->debug(
				'Código de rastreio detectado em nota do pedido',
				array(
					'order_id'      => $order->get_id(),
					'note_id'       => $note_id,
					'tracking_code' => $tracking_code,
					'is_customer_note' => $is_customer_note,
				)
			);
			
			// O método send_tracking_notification já verifica duplicatas
			$this->send_tracking_notification( $order->get_id(), $tracking_code );
		}
	}

	/**
	 * Processa quando uma observação para o cliente é adicionada
	 *
	 * @param array $args Array com order_id e customer_note.
	 */
	public function handle_customer_note( $args ) {
		if ( ! isset( $args['order_id'] ) || ! isset( $args['customer_note'] ) ) {
			return;
		}
		
		$order_id = $args['order_id'];
		$note_content = $args['customer_note'];
		
		if ( empty( $note_content ) ) {
			return;
		}
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		
		$this->logger->debug(
			'Observação para cliente detectada',
			array(
				'order_id' => $order_id,
				'note_length' => strlen( $note_content ),
			)
		);
		
		// Verifica se não é um código de rastreio (já processado)
		$tracking_code = $this->extract_tracking_code( $note_content );
		if ( $tracking_code ) {
			// Se contém código de rastreio, não processa aqui (já foi processado)
			$this->logger->debug(
				'Ignorando observação - contém código de rastreio (já processado)',
				array( 'order_id' => $order_id, 'tracking_code' => $tracking_code )
			);
			return;
		}
		
		// Envia observação para o cliente via WhatsApp
		$this->send_customer_note_notification( $order_id, $note_content );
	}

	/**
	 * Verifica código de rastreio após salvar pedido
	 *
	 * @param int $order_id ID do pedido.
	 * @param object $order Objeto do pedido.
	 */
	public function check_tracking_after_save( $order_id, $order ) {
		// Aguarda um pouco para garantir que as notas foram salvas
		add_action( 'shutdown', function() use ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$this->handle_order_updated( $order_id, $order );
			}
		}, 30 );
	}

	/**
	 * Processa quando pedido é atualizado
	 *
	 * @param int    $order_id ID do pedido.
	 * @param object $order Objeto do pedido.
	 */
	public function handle_order_updated( $order_id, $order ) {
		// Se o plugin wc-any-shipping-notify está ativo, não processa aqui
		// O hook wcasn_tracking_added já cuida disso
		if ( function_exists( 'wc_any_shipping_get_tracking_codes' ) ) {
			// Verifica se há códigos, mas não processa (já foi processado pelo hook wcasn_tracking_added)
			$tracking_codes = wc_any_shipping_get_tracking_codes( $order );
			if ( is_array( $tracking_codes ) && ! empty( $tracking_codes ) ) {
				// Não processa aqui, o hook wcasn_tracking_added já cuidou
				$this->logger->debug(
					'Ignorando handle_order_updated - códigos já processados via wcasn_tracking_added',
					array( 'order_id' => $order_id )
				);
				return;
			}
		}
		
		// Fallback: Verifica se há código de rastreio nas notas recentes (apenas se plugin não estiver ativo)
		$notes = $order->get_customer_order_notes();
		
		// Pega a última nota
		if ( ! empty( $notes ) ) {
			$last_note = reset( $notes );
			$note_content = isset( $last_note->comment_content ) ? $last_note->comment_content : '';
			$tracking_code = $this->extract_tracking_code( $note_content );
			
			if ( $tracking_code ) {
				// O método send_tracking_notification já verifica duplicatas
				$this->send_tracking_notification( $order_id, $tracking_code );
			}
		}
		
		// Também verifica todas as notas do pedido
		$all_notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
		foreach ( $all_notes as $note ) {
			$note_content = isset( $note->comment_content ) ? $note->comment_content : '';
			$tracking_code = $this->extract_tracking_code( $note_content );
			
			if ( $tracking_code ) {
				// O método send_tracking_notification já verifica duplicatas
				$this->send_tracking_notification( $order_id, $tracking_code );
				break; // Envia apenas uma vez
			}
		}
	}

	/**
	 * Extrai código de rastreio de um texto
	 *
	 * @param string $text Texto a analisar.
	 * @return string|false Código de rastreio ou false.
	 */
	private function extract_tracking_code( $text ) {
		if ( empty( $text ) ) {
			return false;
		}
		
		// Padrões comuns de códigos de rastreio
		// Correios: 2 letras + 9 dígitos + 2 letras (ex: QN756689320BR)
		// Outros: códigos numéricos ou alfanuméricos de 6-20 caracteres
		$patterns = array(
			// Correios com palavras-chave
			'/(?:rastreio|tracking|código|code|correios)[\s:]*([A-Z]{2}[\s\-]?\d{9}[\s\-]?[A-Z]{2})/i',
			// Correios direto (formato padrão)
			'/([A-Z]{2}[\s\-]?\d{9}[\s\-]?[A-Z]{2})/',
			// Correios com hífens ou espaços
			'/([A-Z]{2})[\s\-]?(\d{9})[\s\-]?([A-Z]{2})/',
			// Genérico com palavras-chave (6-20 caracteres alfanuméricos)
			'/(?:rastreio|tracking|código|code)[\s:]*([A-Z0-9]{6,20})/i',
			// Código numérico simples (6-20 dígitos) após palavras-chave
			'/(?:rastreio|tracking|código|code)[\s:]*(\d{6,20})/i',
		);
		
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text, $matches ) ) {
				if ( isset( $matches[1] ) ) {
					$code = strtoupper( trim( $matches[1] ) );
					// Remove espaços e hífens
					$code = preg_replace( '/[\s\-]/', '', $code );
					
					// Valida formato Correios (2 letras + 9 dígitos + 2 letras = 13 caracteres)
					if ( preg_match( '/^[A-Z]{2}\d{9}[A-Z]{2}$/', $code ) ) {
						return $code;
					}
					
					// Se tiver 3 matches, monta o código
					if ( isset( $matches[3] ) && count( $matches ) >= 3 ) {
						$code = strtoupper( trim( $matches[1] ) . trim( $matches[2] ) . trim( $matches[3] ) );
						$code = preg_replace( '/[\s\-]/', '', $code );
						if ( preg_match( '/^[A-Z]{2}\d{9}[A-Z]{2}$/', $code ) ) {
							return $code;
						}
					}
					
					// Aceita códigos genéricos de 6-20 caracteres (numéricos ou alfanuméricos)
					if ( strlen( $code ) >= 6 && strlen( $code ) <= 20 ) {
						return $code;
					}
				}
			}
		}
		
		return false;
	}

	/**
	 * Processa quando código de rastreio é adicionado via wc-any-shipping-notify
	 *
	 * @param WC_Order $order Pedido.
	 * @param string   $tracking_code Código de rastreio.
	 * @param string   $shipping_company_slug Slug da transportadora.
	 */
	public function handle_wcasn_tracking_added( $order, $tracking_code, $shipping_company_slug ) {
		$this->logger->debug(
			'Código de rastreio adicionado via wc-any-shipping-notify',
			array(
				'order_id'              => $order->get_id(),
				'tracking_code'         => $tracking_code,
				'shipping_company_slug' => $shipping_company_slug,
			)
		);
		
		// Envia notificação com informações da transportadora
		// O método send_tracking_notification já tem proteção contra duplicatas
		$this->send_tracking_notification( $order->get_id(), $tracking_code, $shipping_company_slug );
	}

	/**
	 * Envia notificação de código de rastreio
	 *
	 * @param int    $order_id ID do pedido.
	 * @param string $tracking_code Código de rastreio.
	 * @param string $shipping_company_slug Slug da transportadora (opcional).
	 */
	private function send_tracking_notification( $order_id, $tracking_code, $shipping_company_slug = '' ) {
		// Proteção contra duplicatas: verifica se já foi notificado sobre este código
		$notification_key = 'wc_whatsapp_tracking_notified_' . $order_id . '_' . md5( $tracking_code );
		if ( get_transient( $notification_key ) ) {
			$this->logger->debug(
				'Notificação de rastreio já enviada, ignorando duplicata',
				array(
					'order_id'      => $order_id,
					'tracking_code' => $tracking_code,
				)
			);
			return;
		}
		
		// Verifica se API está configurada
		if ( ! $this->api->is_configured() ) {
			$this->logger->debug( 'API não configurada, ignorando notificação de rastreio', array( 'order_id' => $order_id ) );
			return;
		}
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		
		// Obtém telefone do cliente
		$phone = $this->get_customer_phone( $order );
		if ( empty( $phone ) ) {
			$this->logger->warning( 'Telefone não encontrado para notificação de rastreio', array( 'order_id' => $order_id ) );
			return;
		}
		
		// Formata telefone
		$formatted_phone = $this->format_phone( $phone );
		if ( ! $formatted_phone ) {
			$this->logger->warning( 'Telefone inválido para notificação de rastreio', array( 'order_id' => $order_id, 'phone' => $phone ) );
			return;
		}
		
		// Gera mensagem de rastreio
		$message = $this->generate_tracking_message( $order, $tracking_code, $shipping_company_slug );
		
		if ( empty( $message ) ) {
			$this->logger->warning( 'Mensagem de rastreio vazia', array( 'order_id' => $order_id ) );
			return;
		}
		
		// Envia mensagem
		$result = $this->api->send_message( $formatted_phone, $message );
		
		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Erro ao enviar notificação de rastreio WhatsApp',
				array(
					'order_id'      => $order_id,
					'tracking_code' => $tracking_code,
					'error'         => $result->get_error_message(),
				)
			);
		} else {
			// Marca como notificado por 1 hora para evitar duplicatas
			set_transient( $notification_key, true, HOUR_IN_SECONDS );
			
			$this->logger->info(
				'Notificação de rastreio WhatsApp enviada com sucesso',
				array(
					'order_id'      => $order_id,
					'tracking_code' => $tracking_code,
				)
			);
		}
	}

	/**
	 * Gera mensagem de código de rastreio
	 *
	 * @param WC_Order $order Pedido.
	 * @param string   $tracking_code Código de rastreio.
	 * @param string   $shipping_company_slug Slug da transportadora (opcional).
	 * @return string Mensagem formatada.
	 */
	private function generate_tracking_message( $order, $tracking_code, $shipping_company_slug = '' ) {
		// Obtém template personalizado ou usa padrão
		$template = get_option( 'wc_whatsapp_message_tracking', '' );
		
		if ( empty( $template ) ) {
			// Template padrão
			$template = "Olá *{customer_name}*! 📦\n\nSeu pedido *#{order_number}* foi enviado!\n\n🚚 *Código de Rastreio:*\n{tracking_code}\n\n🔗 *Rastrear:* {tracking_url}\n\n📦 *Produtos:*\n{products_list}\n\n💰 *Total:* {order_total}\n\n📅 *Data:* {order_date}\n\nAcompanhe seu pedido!";
		}
		
		// Dados do pedido
		$customer_name = $order->get_billing_first_name();
		$order_number  = $order->get_order_number();
		// Converte HTML para texto simples (WhatsApp não suporta HTML)
		$order_total   = html_entity_decode( strip_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
		$order_date    = wc_format_datetime( $order->get_date_created(), 'd/m/Y' );
		$products_list = $this->get_products_list( $order );
		
		// Informações de entrega
		$shipping_method = $this->get_shipping_method( $order );
		$shipping_total  = $this->get_shipping_total( $order );
		
		// Obtém URL de rastreio e nome da transportadora
		$tracking_url = '';
		$shipping_company_name = '';
		
		// Se o plugin wc-any-shipping-notify estiver ativo, usa suas configurações
		if ( function_exists( 'wc_any_shipping_notify_get_shipping_company_url' ) ) {
			// Se não tem slug, tenta obter do pedido
			if ( empty( $shipping_company_slug ) ) {
				if ( function_exists( 'wc_any_shipping_get_tracking_codes' ) ) {
					$tracking_codes = wc_any_shipping_get_tracking_codes( $order );
					if ( is_array( $tracking_codes ) && isset( $tracking_codes[ $tracking_code ] ) ) {
						$shipping_company_slug = $tracking_codes[ $tracking_code ];
					}
				}
			}
			
			if ( ! empty( $shipping_company_slug ) ) {
				$tracking_url = wc_any_shipping_notify_get_shipping_company_url( $shipping_company_slug, $tracking_code, $order );
				$shipping_company_name = function_exists( 'wc_any_shipping_notify_get_shipping_company_name' ) 
					? wc_any_shipping_notify_get_shipping_company_name( $shipping_company_slug ) 
					: '';
			}
		}
		
		// Se não encontrou URL, tenta detectar pelo código
		if ( empty( $tracking_url ) ) {
			$tracking_url = $this->get_tracking_url( $tracking_code, $order );
		}
		
		// Se não encontrou URL, usa padrão dos Correios
		if ( empty( $tracking_url ) ) {
			$tracking_url = 'https://www.correios.com.br/precisa-de-ajuda/rastreamento-de-objetos';
		}
		
		// Substitui placeholders
		$message = str_replace(
			array(
				'{customer_name}',
				'{order_number}',
				'{tracking_code}',
				'{tracking_url}',
				'{shipping_company}',
				'{shipping_method}',
				'{shipping_total}',
				'{products_list}',
				'{order_total}',
				'{order_date}',
			),
			array(
				$customer_name,
				$order_number,
				$tracking_code,
				$tracking_url,
				$shipping_company_name,
				$shipping_method,
				$shipping_total,
				$products_list,
				$order_total,
				$order_date,
			),
			$template
		);
		
		return $message;
	}

	/**
	 * Envia notificação de observação para o cliente
	 *
	 * @param int    $order_id ID do pedido.
	 * @param string $note_content Conteúdo da observação.
	 */
	private function send_customer_note_notification( $order_id, $note_content ) {
		// Proteção contra duplicatas: verifica se já foi notificado sobre esta observação
		$notification_key = 'wc_whatsapp_customer_note_notified_' . $order_id . '_' . md5( $note_content );
		if ( get_transient( $notification_key ) ) {
			$this->logger->debug(
				'Observação para cliente já enviada, ignorando duplicata',
				array( 'order_id' => $order_id )
			);
			return;
		}
		
		// Verifica se API está configurada
		if ( ! $this->api->is_configured() ) {
			$this->logger->debug( 'API não configurada, ignorando notificação de observação', array( 'order_id' => $order_id ) );
			return;
		}
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		
		// Obtém telefone do cliente
		$phone = $this->get_customer_phone( $order );
		if ( empty( $phone ) ) {
			$this->logger->warning( 'Telefone não encontrado para notificação de observação', array( 'order_id' => $order_id ) );
			return;
		}
		
		// Formata telefone
		$formatted_phone = $this->format_phone( $phone );
		if ( ! $formatted_phone ) {
			$this->logger->warning( 'Telefone inválido para notificação de observação', array( 'order_id' => $order_id, 'phone' => $phone ) );
			return;
		}
		
		// Gera mensagem de observação
		$message = $this->generate_customer_note_message( $order, $note_content );
		
		if ( empty( $message ) ) {
			$this->logger->warning( 'Mensagem de observação vazia', array( 'order_id' => $order_id ) );
			return;
		}
		
		// Envia mensagem
		$result = $this->api->send_message( $formatted_phone, $message );
		
		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Erro ao enviar notificação de observação WhatsApp',
				array(
					'order_id' => $order_id,
					'error'   => $result->get_error_message(),
				)
			);
		} else {
			// Marca como notificado por 1 hora para evitar duplicatas
			set_transient( $notification_key, true, HOUR_IN_SECONDS );
			
			$this->logger->info(
				'Notificação de observação WhatsApp enviada com sucesso',
				array( 'order_id' => $order_id )
			);
		}
	}

	/**
	 * Gera mensagem de observação para o cliente
	 *
	 * @param WC_Order $order Pedido.
	 * @param string   $note_content Conteúdo da observação.
	 * @return string Mensagem formatada.
	 */
	private function generate_customer_note_message( $order, $note_content ) {
		// Obtém template personalizado ou usa padrão
		$template = get_option( 'wc_whatsapp_message_customer_note', '' );
		
		if ( empty( $template ) ) {
			// Template padrão
			$template = "Olá *{customer_name}*! 📝\n\nNova observação sobre seu pedido *#{order_number}*:\n\n{note_content}\n\n📦 *Pedido:* #{order_number}\n💰 *Total:* {order_total}\n📅 *Data:* {order_date}\n\nQualquer dúvida, entre em contato conosco!";
		}
		
		// Dados do pedido
		$customer_name = $order->get_billing_first_name();
		$order_number  = $order->get_order_number();
		// Converte HTML para texto simples (WhatsApp não suporta HTML)
		$order_total   = html_entity_decode( strip_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
		$order_date    = wc_format_datetime( $order->get_date_created(), 'd/m/Y' );
		
		// Informações de entrega
		$shipping_method = $this->get_shipping_method( $order );
		$shipping_total  = $this->get_shipping_total( $order );
		
		// Remove tags HTML da observação e converte entidades
		$note_content_clean = html_entity_decode( strip_tags( $note_content ), ENT_QUOTES, 'UTF-8' );
		
		// Substitui placeholders
		$message = str_replace(
			array(
				'{customer_name}',
				'{order_number}',
				'{note_content}',
				'{shipping_method}',
				'{shipping_total}',
				'{order_total}',
				'{order_date}',
			),
			array(
				$customer_name,
				$order_number,
				$note_content_clean,
				$shipping_method,
				$shipping_total,
				$order_total,
				$order_date,
			),
			$template
		);
		
		return $message;
	}

	/**
	 * Obtém URL de rastreio baseado no código ou configurações
	 *
	 * @param string   $tracking_code Código de rastreio.
	 * @param WC_Order $order Pedido.
	 * @return string URL de rastreio.
	 */
	private function get_tracking_url( $tracking_code, $order ) {
		// Se o plugin wc-any-shipping-notify estiver ativo, tenta obter códigos do pedido
		if ( function_exists( 'wc_any_shipping_get_tracking_codes' ) ) {
			$tracking_codes = wc_any_shipping_get_tracking_codes( $order );
			
			if ( is_array( $tracking_codes ) && isset( $tracking_codes[ $tracking_code ] ) ) {
				$shipping_company_slug = $tracking_codes[ $tracking_code ];
				
				if ( function_exists( 'wc_any_shipping_notify_get_shipping_company_url' ) ) {
					return wc_any_shipping_notify_get_shipping_company_url( $shipping_company_slug, $tracking_code, $order );
				}
			}
		}
		
		// Se não encontrou, tenta detectar pelo formato do código
		// Correios: 2 letras + 9 dígitos + 2 letras
		if ( preg_match( '/^[A-Z]{2}\d{9}[A-Z]{2}$/', $tracking_code ) ) {
			return 'https://www.correios.com.br/precisa-de-ajuda/rastreamento-de-objetos?objetos=' . $tracking_code;
		}
		
		return '';
	}
}

