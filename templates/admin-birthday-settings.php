<?php
/**
 * Aba: Notificação de Aniversário
 *
 * @package WC_WhatsApp_Notifications
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$birthday_enabled       = (bool) get_option( 'wc_whatsapp_birthday_enabled', false );
$birthday_discount_type = get_option( 'wc_whatsapp_birthday_discount_type', 'percent' );
$birthday_amount        = get_option( 'wc_whatsapp_birthday_discount_amount', '15' );
$birthday_prefix        = get_option( 'wc_whatsapp_birthday_coupon_prefix', 'ANIVER' );
$birthday_valid_days    = get_option( 'wc_whatsapp_birthday_valid_days', '3' );
$birthday_cleanup_days  = get_option( 'wc_whatsapp_birthday_cleanup_days', '7' );
$birthday_min_amount    = get_option( 'wc_whatsapp_birthday_min_amount', '0' );
$birthday_single_use    = (bool) get_option( 'wc_whatsapp_birthday_single_use', true );
$birthday_individual    = (bool) get_option( 'wc_whatsapp_birthday_individual_use', true );
$birthday_send_offset   = get_option( 'wc_whatsapp_birthday_send_offset', '0' );
$birthday_send_time     = get_option( 'wc_whatsapp_birthday_send_time', '09:00' );
$birthday_template      = get_option( 'wc_whatsapp_birthday_message_template', '' );
?>
<div id="birthday-settings" class="tab-content">
	<h2><?php esc_html_e( 'Notificações de Aniversário', 'wc-whatsapp-notifications' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Integração com data de nascimento (ex.: WooCommerce Extra Checkout Fields for Brazil). Usa a mesma API WhatsApp configurada acima.', 'wc-whatsapp-notifications' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Ativar', 'wc-whatsapp-notifications' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="wc_whatsapp_birthday_enabled" value="1" <?php checked( $birthday_enabled ); ?> />
					<?php esc_html_e( 'Ativar notificações de aniversário via WhatsApp', 'wc-whatsapp-notifications' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Configurações do cupom', 'wc-whatsapp-notifications' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="wc_whatsapp_birthday_discount_type"><?php esc_html_e( 'Tipo de desconto', 'wc-whatsapp-notifications' ); ?></label></th>
			<td>
				<select name="wc_whatsapp_birthday_discount_type" id="wc_whatsapp_birthday_discount_type">
					<option value="percent" <?php selected( $birthday_discount_type, 'percent' ); ?>><?php esc_html_e( 'Porcentagem', 'wc-whatsapp-notifications' ); ?></option>
					<option value="fixed" <?php selected( $birthday_discount_type, 'fixed' ); ?>><?php esc_html_e( 'Valor fixo (carrinho)', 'wc-whatsapp-notifications' ); ?></option>
					<option value="free_shipping" <?php selected( $birthday_discount_type, 'free_shipping' ); ?>><?php esc_html_e( 'Frete grátis', 'wc-whatsapp-notifications' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wc_whatsapp_birthday_discount_amount"><?php esc_html_e( 'Valor do desconto', 'wc-whatsapp-notifications' ); ?></label></th>
			<td>
				<input type="number" step="0.01" min="0" name="wc_whatsapp_birthday_discount_amount" id="wc_whatsapp_birthday_discount_amount" value="<?php echo esc_attr( $birthday_amount ); ?>" class="small-text" />
				<p class="description"><?php esc_html_e( 'Para porcentagem: ex. 15. Para valor fixo: valor em moeda da loja. Ignorado em frete grátis.', 'wc-whatsapp-notifications' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wc_whatsapp_birthday_coupon_prefix"><?php esc_html_e( 'Prefixo do cupom', 'wc-whatsapp-notifications' ); ?></label></th>
			<td>
				<input type="text" name="wc_whatsapp_birthday_coupon_prefix" id="wc_whatsapp_birthday_coupon_prefix" value="<?php echo esc_attr( $birthday_prefix ); ?>" class="regular-text" maxlength="20" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wc_whatsapp_birthday_valid_days"><?php esc_html_e( 'Validade do cupom (dias)', 'wc-whatsapp-notifications' ); ?></label></th>
			<td>
				<input type="number" min="1" max="365" name="wc_whatsapp_birthday_valid_days" id="wc_whatsapp_birthday_valid_days" value="<?php echo esc_attr( $birthday_valid_days ); ?>" class="small-text" />
				<p class="description"><?php esc_html_e( 'A partir do dia do aniversário (00:00) até este número de dias.', 'wc-whatsapp-notifications' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wc_whatsapp_birthday_cleanup_days"><?php esc_html_e( 'Limpeza automática (dias após expiração)', 'wc-whatsapp-notifications' ); ?></label></th>
			<td>
				<input type="number" min="1" max="365" name="wc_whatsapp_birthday_cleanup_days" id="wc_whatsapp_birthday_cleanup_days" value="<?php echo esc_attr( $birthday_cleanup_days ); ?>" class="small-text" />
				<p class="description"><?php esc_html_e( 'Cupons não usados, gerados por este recurso e já expirados, serão excluídos após este período. Cupons manuais nunca são afetados.', 'wc-whatsapp-notifications' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wc_whatsapp_birthday_min_amount"><?php esc_html_e( 'Valor mínimo do pedido', 'wc-whatsapp-notifications' ); ?></label></th>
			<td>
				<input type="number" step="0.01" min="0" name="wc_whatsapp_birthday_min_amount" id="wc_whatsapp_birthday_min_amount" value="<?php echo esc_attr( $birthday_min_amount ); ?>" class="small-text" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Regras de uso', 'wc-whatsapp-notifications' ); ?></th>
			<td>
				<label><input type="checkbox" name="wc_whatsapp_birthday_single_use" value="1" <?php checked( $birthday_single_use ); ?> /> <?php esc_html_e( 'Uso único por cliente', 'wc-whatsapp-notifications' ); ?></label><br />
				<label><input type="checkbox" name="wc_whatsapp_birthday_individual_use" value="1" <?php checked( $birthday_individual ); ?> /> <?php esc_html_e( 'Não pode ser combinado com outros cupons', 'wc-whatsapp-notifications' ); ?></label>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Envio', 'wc-whatsapp-notifications' ); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="wc_whatsapp_birthday_send_offset"><?php esc_html_e( 'Quando enviar', 'wc-whatsapp-notifications' ); ?></label></th>
			<td>
				<select name="wc_whatsapp_birthday_send_offset" id="wc_whatsapp_birthday_send_offset">
					<option value="0" <?php selected( (string) $birthday_send_offset, '0' ); ?>><?php esc_html_e( 'No dia do aniversário', 'wc-whatsapp-notifications' ); ?></option>
					<option value="1" <?php selected( (string) $birthday_send_offset, '1' ); ?>><?php esc_html_e( '1 dia antes', 'wc-whatsapp-notifications' ); ?></option>
					<option value="2" <?php selected( (string) $birthday_send_offset, '2' ); ?>><?php esc_html_e( '2 dias antes', 'wc-whatsapp-notifications' ); ?></option>
					<option value="7" <?php selected( (string) $birthday_send_offset, '7' ); ?>><?php esc_html_e( '7 dias antes', 'wc-whatsapp-notifications' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'O agendamento WordPress (WP-Cron) é aproximado; mantenha tráfego no site ou configure cron real do servidor.', 'wc-whatsapp-notifications' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wc_whatsapp_birthday_send_time"><?php esc_html_e( 'Horário de referência do cron', 'wc-whatsapp-notifications' ); ?></label></th>
			<td>
				<input type="time" name="wc_whatsapp_birthday_send_time" id="wc_whatsapp_birthday_send_time" value="<?php echo esc_attr( $birthday_send_time ); ?>" />
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Template WhatsApp', 'wc-whatsapp-notifications' ); ?></h3>
	<table class="form-table">
		<tr>
			<td>
				<textarea name="wc_whatsapp_birthday_message_template" rows="12" class="large-text code"><?php echo esc_textarea( $birthday_template ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Variáveis:', 'wc-whatsapp-notifications' ); ?>
					<code>{nome_cliente}</code>, <code>{codigo_cupom}</code>, <code>{valor_desconto}</code>, <code>{tipo_desconto}</code>, <code>{data_validade}</code>, <code>{valor_minimo}</code>, <code>{loja_nome}</code>
				</p>
			</td>
		</tr>
	</table>
</div>
