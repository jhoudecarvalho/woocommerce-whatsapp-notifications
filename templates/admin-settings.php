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

		</div>

		<?php submit_button( __( 'Salvar Configurações', 'wc-whatsapp-notifications' ) ); ?>
	</form>
</div>

