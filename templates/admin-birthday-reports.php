<?php
/**
 * Relatórios de aniversário
 *
 * @package WC_WhatsApp_Notifications
 *
 * @var array $history Histórico da tabela.
 * @var array $upcoming Próximos aniversários.
 * @var int   $sent_month Enviados no mês.
 * @var float $use_rate Taxa de uso %.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Aniversários e cupons WhatsApp', 'wc-whatsapp-notifications' ); ?></h1>

	<div class="wc-whatsapp-birthday-stats" style="display:flex;gap:24px;margin:20px 0;flex-wrap:wrap;">
		<div class="postbox" style="padding:12px 20px;min-width:200px;">
			<strong><?php esc_html_e( 'Enviados (WhatsApp) no mês', 'wc-whatsapp-notifications' ); ?></strong>
			<p style="font-size:1.5em;margin:8px 0 0;"><?php echo esc_html( (string) $sent_month ); ?></p>
		</div>
		<div class="postbox" style="padding:12px 20px;min-width:200px;">
			<strong><?php esc_html_e( 'Taxa de uso (cupons)', 'wc-whatsapp-notifications' ); ?></strong>
			<p style="font-size:1.5em;margin:8px 0 0;"><?php echo esc_html( (string) $use_rate ); ?>%</p>
		</div>
	</div>

	<h2><?php esc_html_e( 'Próximos aniversários (30 dias)', 'wc-whatsapp-notifications' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Cliente', 'wc-whatsapp-notifications' ); ?></th>
				<th><?php esc_html_e( 'Telefone', 'wc-whatsapp-notifications' ); ?></th>
				<th><?php esc_html_e( 'Data (próxima)', 'wc-whatsapp-notifications' ); ?></th>
				<th><?php esc_html_e( 'Ações', 'wc-whatsapp-notifications' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $upcoming ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'Nenhum registro encontrado (verifique meta de data de nascimento nos usuários).', 'wc-whatsapp-notifications' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $upcoming as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td><?php echo esc_html( (string) $row['phone'] ); ?></td>
						<td><?php echo esc_html( $row['birthday'] ); ?></td>
						<td>
							<button type="button" class="button wc-birthday-send-now" data-user-id="<?php echo esc_attr( (string) $row['user_id'] ); ?>">
								<?php esc_html_e( 'Gerar cupom e enviar agora', 'wc-whatsapp-notifications' ); ?>
							</button>
							<span class="wc-birthday-send-result" style="margin-left:8px;"></span>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<h2 style="margin-top:32px;"><?php esc_html_e( 'Histórico de cupons', 'wc-whatsapp-notifications' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Cliente', 'wc-whatsapp-notifications' ); ?></th>
				<th><?php esc_html_e( 'Cupom', 'wc-whatsapp-notifications' ); ?></th>
				<th><?php esc_html_e( 'Enviado', 'wc-whatsapp-notifications' ); ?></th>
				<th><?php esc_html_e( 'WhatsApp', 'wc-whatsapp-notifications' ); ?></th>
				<th><?php esc_html_e( 'Status cupom', 'wc-whatsapp-notifications' ); ?></th>
				<th><?php esc_html_e( 'Pedido', 'wc-whatsapp-notifications' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $history ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Nenhum cupom registrado ainda.', 'wc-whatsapp-notifications' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $history as $h ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $h['customer_email'] ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( (string) ( $h['coupon_code'] ?? '' ) ); ?></code></td>
						<td><?php echo esc_html( (string) ( $h['sent_date'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $h['whatsapp_status'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $h['status'] ?? '' ) ); ?></td>
						<td><?php echo ! empty( $h['order_id'] ) ? '#' . esc_html( (string) $h['order_id'] ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
<script>
jQuery(function($){
	$('.wc-birthday-send-now').on('click', function(){
		var btn = $(this);
		var uid = btn.data('user-id');
		var out = btn.siblings('.wc-birthday-send-result');
		btn.prop('disabled', true);
		out.text('<?php echo esc_js( __( 'Enviando…', 'wc-whatsapp-notifications' ) ); ?>');
		$.post(wcWhatsApp.ajaxUrl, {
			action: 'wc_whatsapp_birthday_manual',
			nonce: wcWhatsApp.birthdayNonce,
			user_id: uid
		}).done(function(r){
			btn.prop('disabled', false);
			if (r.success) { out.text(r.data.message); }
			else { out.text(r.data && r.data.message ? r.data.message : 'Erro'); }
		}).fail(function(){
			btn.prop('disabled', false);
			out.text('<?php echo esc_js( __( 'Erro de rede', 'wc-whatsapp-notifications' ) ); ?>');
		});
	});
});
</script>
