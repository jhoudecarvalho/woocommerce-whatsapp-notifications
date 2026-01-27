<?php
/**
 * Template da página de configurações
 *
 * @package WC_WhatsApp_Notifications
 */

// Se este arquivo for chamado diretamente, aborta.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap wc-whatsapp-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'wc_whatsapp_settings' ); ?>

	<form method="post" action="" id="wc-whatsapp-settings-form">
		<?php wp_nonce_field( 'wc_whatsapp_settings', 'wc_whatsapp_nonce' ); ?>

		<div class="wc-whatsapp-tabs">
			<nav class="nav-tab-wrapper">
				<a href="#api-settings" class="nav-tab nav-tab-active"><?php esc_html_e( 'Configurações da API', 'wc-whatsapp-notifications' ); ?></a>
				<a href="#status-settings" class="nav-tab"><?php esc_html_e( 'Status de Pedidos', 'wc-whatsapp-notifications' ); ?></a>
				<a href="#message-templates" class="nav-tab"><?php esc_html_e( 'Mensagens', 'wc-whatsapp-notifications' ); ?></a>
				<a href="#image-settings" class="nav-tab"><?php esc_html_e( 'Imagens', 'wc-whatsapp-notifications' ); ?></a>
			</nav>

			<!-- Aba: Configurações da API -->
			<div id="api-settings" class="tab-content active">
				<h2><?php esc_html_e( 'Credenciais da API WhatsApp', 'wc-whatsapp-notifications' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure a URL base e o token de autenticação da API WhatsApp.', 'wc-whatsapp-notifications' ); ?>
				</p>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wc_whatsapp_api_url"><?php esc_html_e( 'URL Base da API', 'wc-whatsapp-notifications' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="wc_whatsapp_api_url"
								name="wc_whatsapp_api_url"
								value="<?php echo esc_attr( $api_url ); ?>"
								class="regular-text"
								placeholder="https://apiwhatsapp.cdwchat.com.br/v1/api/external/..."
								required
							/>
							<p class="description">
								<?php esc_html_e( 'URL completa da API (sem barra final).', 'wc-whatsapp-notifications' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wc_whatsapp_api_token"><?php esc_html_e( 'Token de Autenticação', 'wc-whatsapp-notifications' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="wc_whatsapp_api_token"
								name="wc_whatsapp_api_token"
								value="<?php echo esc_attr( $api_token ); ?>"
								class="regular-text"
								placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
								required
							/>
							<p class="description">
								<?php esc_html_e( 'Token JWT de autenticação da API.', 'wc-whatsapp-notifications' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wc_whatsapp_api_auth_type"><?php esc_html_e( 'Tipo de Autenticação', 'wc-whatsapp-notifications' ); ?></label>
						</th>
						<td>
							<select
								id="wc_whatsapp_api_auth_type"
								name="wc_whatsapp_api_auth_type"
								class="regular-text"
							>
								<option value="bearer" <?php selected( $auth_type, 'bearer' ); ?>>
									<?php esc_html_e( 'Bearer Token (Authorization: Bearer)', 'wc-whatsapp-notifications' ); ?>
								</option>
								<option value="token" <?php selected( $auth_type, 'token' ); ?>>
									<?php esc_html_e( 'Token (Authorization: Token)', 'wc-whatsapp-notifications' ); ?>
								</option>
								<option value="apikey" <?php selected( $auth_type, 'apikey' ); ?>>
									<?php esc_html_e( 'API Key (X-API-Key)', 'wc-whatsapp-notifications' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Formato de autenticação usado pela API. O plugin tentará descobrir automaticamente, mas você pode configurar manualmente se necessário.', 'wc-whatsapp-notifications' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Testar API', 'wc-whatsapp-notifications' ); ?></th>
						<td>
							<button type="button" id="test-api-btn" class="button button-secondary">
								<?php esc_html_e( 'Testar Conexão', 'wc-whatsapp-notifications' ); ?>
							</button>
							<div id="test-api-result" class="wc-whatsapp-test-result" style="margin-top: 10px;"></div>
							<p class="description">
								<?php esc_html_e( 'Testa a conexão com a API usando as credenciais configuradas acima.', 'wc-whatsapp-notifications' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enviar Mensagem de Teste', 'wc-whatsapp-notifications' ); ?></th>
						<td>
							<p>
								<label for="test-phone">
									<?php esc_html_e( 'Telefone:', 'wc-whatsapp-notifications' ); ?>
									<input
										type="tel"
										id="test-phone"
										class="regular-text"
										placeholder="(44) 99999-9999"
										style="margin-left: 10px;"
									/>
								</label>
							</p>
							<p>
								<label for="test-message">
									<?php esc_html_e( 'Mensagem:', 'wc-whatsapp-notifications' ); ?>
									<textarea
										id="test-message"
										class="large-text"
										rows="3"
										placeholder="<?php esc_attr_e( 'Digite sua mensagem de teste aqui...', 'wc-whatsapp-notifications' ); ?>"
										style="margin-top: 5px;"
									></textarea>
								</label>
							</p>
							<p>
								<button type="button" id="send-test-message-btn" class="button button-secondary">
									<?php esc_html_e( 'Enviar Mensagem de Teste', 'wc-whatsapp-notifications' ); ?>
								</button>
							</p>
							<div id="test-message-result" class="wc-whatsapp-test-result" style="margin-top: 10px;"></div>
							<p class="description">
								<?php esc_html_e( 'Envia uma mensagem de teste para o número informado usando as configurações salvas.', 'wc-whatsapp-notifications' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Aba: Status de Pedidos -->
			<div id="status-settings" class="tab-content">
				<h2><?php esc_html_e( 'Status de Pedidos para Notificação', 'wc-whatsapp-notifications' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Selecione quais status de pedido devem gerar notificações WhatsApp.', 'wc-whatsapp-notifications' ); ?>
				</p>

				<table class="form-table">
					<?php foreach ( $statuses as $status_key => $status_label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $status_label ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="wc_whatsapp_enable_<?php echo esc_attr( $status_key ); ?>"
										value="1"
										<?php checked( in_array( $status_key, $enabled_statuses, true ) ); ?>
									/>
									<?php esc_html_e( 'Enviar notificação quando pedido mudar para este status', 'wc-whatsapp-notifications' ); ?>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>

			<!-- Aba: Templates de Mensagem -->
			<div id="message-templates" class="tab-content">
				<h2><?php esc_html_e( 'Templates de Mensagem', 'wc-whatsapp-notifications' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Personalize as mensagens enviadas para cada status. Use os placeholders disponíveis:', 'wc-whatsapp-notifications' ); ?>
					<code>{customer_name}</code>, <code>{order_number}</code>, <code>{order_total}</code>, <code>{order_date}</code>, <code>{products_list}</code>, <code>{status}</code>, <code>{shipping_method}</code>, <code>{shipping_total}</code>
				</p>

				<?php foreach ( $statuses as $status_key => $status_label ) : ?>
					<h3><?php echo esc_html( $status_label ); ?></h3>
					<?php
					$template_key = 'wc_whatsapp_message_' . $status_key;
					$template     = get_option( $template_key, '' );
					?>
					<table class="form-table">
						<tr>
							<td>
								<textarea
									name="<?php echo esc_attr( $template_key ); ?>"
									id="<?php echo esc_attr( $template_key ); ?>"
									rows="8"
									class="large-text code"
									placeholder="<?php esc_attr_e( 'Deixe vazio para usar o template padrão', 'wc-whatsapp-notifications' ); ?>"
								><?php echo esc_textarea( $template ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Use formatação WhatsApp: *negrito*, _itálico_, ~riscado~', 'wc-whatsapp-notifications' ); ?>
								</p>
							</td>
						</tr>
					</table>
				<?php endforeach; ?>

				<h3><?php esc_html_e( 'Mensagem de Código de Rastreio', 'wc-whatsapp-notifications' ); ?></h3>
				<table class="form-table">
					<tr>
						<td>
							<textarea
								name="wc_whatsapp_message_tracking"
								id="wc_whatsapp_message_tracking"
								rows="8"
								class="large-text code"
								placeholder="<?php esc_attr_e( 'Seu pedido {order_number} foi enviado! Código de rastreio: {tracking_code}. Acompanhe aqui: {tracking_url}', 'wc-whatsapp-notifications' ); ?>"
							><?php echo esc_textarea( get_option( 'wc_whatsapp_message_tracking', '' ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Use placeholders: {customer_name}, {order_number}, {order_total}, {order_date}, {products_list}, {tracking_code}, {tracking_url}, {shipping_company}, {shipping_method}, {shipping_total}.', 'wc-whatsapp-notifications' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Mensagem de Observação para Cliente', 'wc-whatsapp-notifications' ); ?></h3>
				<table class="form-table">
					<tr>
						<td>
							<textarea
								name="wc_whatsapp_message_customer_note"
								id="wc_whatsapp_message_customer_note"
								rows="8"
								class="large-text code"
								placeholder="<?php esc_attr_e( 'Olá {customer_name}! Nova observação sobre seu pedido #{order_number}: {note_content}', 'wc-whatsapp-notifications' ); ?>"
							><?php echo esc_textarea( get_option( 'wc_whatsapp_message_customer_note', '' ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Use placeholders: {customer_name}, {order_number}, {order_total}, {order_date}, {note_content}, {shipping_method}, {shipping_total}.', 'wc-whatsapp-notifications' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Aba: Imagens -->
			<div id="image-settings" class="tab-content">
				<h2><?php esc_html_e( 'Imagens para Notificações', 'wc-whatsapp-notifications' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure imagens para enviar junto com as notificações. As imagens serão convertidas para base64 e enviadas diretamente na mensagem.', 'wc-whatsapp-notifications' ); ?>
				</p>

				<?php
				$image_statuses = array(
					'processing'    => __( 'Em processamento', 'wc-whatsapp-notifications' ),
					'on-hold'       => __( 'Aguardando pagamento', 'wc-whatsapp-notifications' ),
					'completed'     => __( 'Concluído', 'wc-whatsapp-notifications' ),
					'cancelled'     => __( 'Cancelado', 'wc-whatsapp-notifications' ),
					'refunded'      => __( 'Reembolsado', 'wc-whatsapp-notifications' ),
					'tracking'      => __( 'Código de Rastreio', 'wc-whatsapp-notifications' ),
					'customer_note' => __( 'Observação para Cliente', 'wc-whatsapp-notifications' ),
				);

				foreach ( $image_statuses as $status_key => $status_label ) :
					$image_id = get_option( 'wc_whatsapp_image_' . $status_key, 0 );
					$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
					?>
					<h3><?php echo esc_html( $status_label ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="wc_whatsapp_image_<?php echo esc_attr( $status_key ); ?>">
									<?php esc_html_e( 'Imagem', 'wc-whatsapp-notifications' ); ?>
								</label>
							</th>
							<td>
								<div class="wc-whatsapp-image-upload">
									<input
										type="hidden"
										id="wc_whatsapp_image_<?php echo esc_attr( $status_key ); ?>"
										name="wc_whatsapp_image_<?php echo esc_attr( $status_key ); ?>"
										value="<?php echo esc_attr( $image_id ); ?>"
									/>
									<div class="wc-whatsapp-image-preview" style="margin-bottom: 10px;">
										<?php if ( $image_url ) : ?>
											<img src="<?php echo esc_url( $image_url ); ?>" style="max-width: 150px; height: auto; display: block; margin-bottom: 10px;" />
										<?php endif; ?>
									</div>
									<button type="button" class="button wc-whatsapp-upload-image-btn" data-status="<?php echo esc_attr( $status_key ); ?>">
										<?php echo $image_id ? esc_html__( 'Alterar Imagem', 'wc-whatsapp-notifications' ) : esc_html__( 'Selecionar Imagem', 'wc-whatsapp-notifications' ); ?>
									</button>
									<?php if ( $image_id ) : ?>
										<button type="button" class="button wc-whatsapp-remove-image-btn" data-status="<?php echo esc_attr( $status_key ); ?>" style="margin-left: 10px;">
											<?php esc_html_e( 'Remover Imagem', 'wc-whatsapp-notifications' ); ?>
										</button>
									<?php endif; ?>
									<p class="description">
										<?php esc_html_e( 'Formatos suportados: JPG, PNG, WEBP. A imagem será convertida para base64 e enviada junto com a mensagem.', 'wc-whatsapp-notifications' ); ?>
									</p>
								</div>
							</td>
						</tr>
					</table>
				<?php endforeach; ?>
			</div>

		</div>

		<?php submit_button( __( 'Salvar Configurações', 'wc-whatsapp-notifications' ) ); ?>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	// Upload de imagem
	$('.wc-whatsapp-upload-image-btn').on('click', function(e) {
		e.preventDefault();
		
		var button = $(this);
		var status = button.data('status');
		var inputId = '#wc_whatsapp_image_' + status;
		var previewDiv = button.siblings('.wc-whatsapp-image-preview');

		// Cria novo media uploader para este botão específico
		var mediaUploader = wp.media({
			title: '<?php esc_html_e( 'Selecione uma imagem', 'wc-whatsapp-notifications' ); ?>',
			button: {
				text: '<?php esc_html_e( 'Usar esta imagem', 'wc-whatsapp-notifications' ); ?>'
			},
			multiple: false,
			library: {
				type: 'image'
			}
		});

		// Quando uma imagem é selecionada
		mediaUploader.on('select', function() {
			var attachment = mediaUploader.state().get('selection').first().toJSON();
			
			// Atualiza o campo hidden
			$(inputId).val(attachment.id);
			
			// Atualiza preview
			previewDiv.html('<img src="' + attachment.url + '" style="max-width: 150px; height: auto; display: block; margin-bottom: 10px;" />');
			
			// Atualiza texto do botão
			button.text('<?php esc_html_e( 'Alterar Imagem', 'wc-whatsapp-notifications' ); ?>');
			
			// Mostra botão de remover se não existir
			if (button.siblings('.wc-whatsapp-remove-image-btn').length === 0) {
				button.after('<button type="button" class="button wc-whatsapp-remove-image-btn" data-status="' + status + '" style="margin-left: 10px;"><?php esc_html_e( 'Remover Imagem', 'wc-whatsapp-notifications' ); ?></button>');
			}
		});

		// Abre o media uploader
		mediaUploader.open();
	});

	// Remover imagem
	$(document).on('click', '.wc-whatsapp-remove-image-btn', function(e) {
		e.preventDefault();
		
		var button = $(this);
		var status = button.data('status');
		var inputId = '#wc_whatsapp_image_' + status;
		var previewDiv = button.siblings('.wc-whatsapp-image-preview');
		var uploadBtn = button.siblings('.wc-whatsapp-upload-image-btn');

		// Limpa valores
		$(inputId).val('');
		previewDiv.html('');
		
		// Atualiza texto do botão
		uploadBtn.text('<?php esc_html_e( 'Selecionar Imagem', 'wc-whatsapp-notifications' ); ?>');
		
		// Remove botão de remover
		button.remove();
	});
});
</script>

