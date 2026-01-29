<?php
/**
 * Admin settings page.
 *
 * @package WkWooWebAr
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

settings_errors();
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Marketplace Split Order', 'mp-split-order' ); ?></h1>
	<form method="POST" action="options.php">
	<?php settings_fields( 'wkmpso-settings-group' ); ?>
		<table class="form-table">
			<tbody>

				<tr>
					<th scope="row" class="titledesc">
						<label for="wkmpso-plugin-status"><?php esc_html_e( 'Status', 'mp-split-order' ); ?></label>
					</th>
					<td>
						<?php echo wc_help_tip( esc_html__( 'Enable / Disable Marketplace Split Order Plugin.', 'mp-split-order' ) ); ?>
						<select name="_wkmpso_plugin_status" class="regular-text" id="wkmpso-plugin-status">
							<option value="enable" <?php selected( get_option( '_wkmpso_plugin_status' ), 'enable' ); ?>>
							<?php esc_html_e( 'Enable', 'mp-split-order' ); ?>
							</option>
							<option value="disable" <?php selected( get_option( '_wkmpso_plugin_status' ), 'disable' ); ?>>
							<?php esc_html_e( 'Disable', 'mp-split-order' ); ?>
							</option>
						</select>
					</td>
				</tr>

				<?php do_action( 'wkmpso_settings_field' ); ?>
			</tbody>
		</table>
		<?php submit_button( esc_html__( 'Save Changes', 'mp-split-order' ), 'primary' ); ?>
	</form>
	<hr/>
</div>
