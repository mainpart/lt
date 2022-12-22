<?php


namespace Lt\Settings;


use Lt\Settings;

class Payments {

	public static function dbi_register_settings() {
		register_setting( Settings::$option_prefix . '_payments_match_group', Settings::$option_prefix . '_payments_options' );
		add_settings_section( 'payment_options_section', 'Общие настройки платежей', null, 'payment' );
		add_settings_field( Settings::$option_prefix . '_payments_array', 'Настройки оплаты', [
			self::class,
			'dbi_plugin_setting_payment'
		], 'payment', 'payment_options_section' );

		add_settings_field( Settings::$option_prefix . '_payments_wallet', 'Настройки Кошелька', [
			self::class,
			'dbi_plugin_setting_wallet'
		], 'payment', 'payment_options_section' );

		add_settings_field( Settings::$option_prefix . '_payments_convertation', 'Настройки конвертации', [
			self::class,
			'dbi_plugin_setting_convertation'
		], 'payment', 'payment_options_section' );

	}

	public static function dbi_plugin_setting_wallet() {
		echo "<p><a href=https://yoomoney.ru/transfer/myservices/http-notification>В кошельке</a> требуется установить callback ".get_option('home')."/?yoo_callback=true</p>";
		$options = get_option( Settings::$option_prefix . '_payments_options' );
		echo "<input type='text' name='lt_payments_options[wallet]' value='" . esc_attr( isset( $options['wallet'] ) ? $options['wallet'] : '' ) . "' placeholder=4100..... />";
	}

	private static function make_select( $optionkey, $key ) {
		$options = get_option( $optionkey );
		echo "<input name='{$optionkey}[match][{$key}][timevalue]' placeholder='Продолжительность доступа' type='text' value='" . esc_attr( isset( $options['match'][$key]['timevalue'] ) ? $options['match'][$key]['timevalue'] : '' ) . "' />";
		echo "<select name='{$optionkey}[match][{$key}][timespan]' >";
		$items = [ 'PTH' => 'Час', 'PD' => 'Дн', 'PM' => 'Мес' ];
		foreach ( $items as $k => $value ) {
			echo "<option" . ($options['match'][$key]['timespan'] == $k ? ' selected=selected' : '' ) . " value='" . esc_attr( $k ) . "'>{$value}</option>";
		}
		echo "</select>";
	}

	public static function dbi_plugin_setting_convertation() {
		?>
		<p>Скольки рублям равна условная единица.</p>
		<?php
		$options = get_option( Settings::$option_prefix . '_payments_options' );
		echo "<input type='text' name='lt_payments_options[convertation]' value='" . esc_attr( isset( $options['convertation'] ) ? $options['convertation'] : '' ) . "' />";
	}

	public static function dbi_plugin_setting_payment() {
		$options = get_option( Settings::$option_prefix . '_payments_options' );
		?>
		<p>Стомость в условных единицах продления доступа</p>
		<?php
		echo "<div class=dupeable_outer>";
		echo "<div class=dupeable_container>";
		if (   is_array( $options['match']      ) ) {
			foreach ( (array) $options['match'] as $key => $value ) {
				echo "<div class='dupeable'>";
				echo "<input type='text' placeholder='Сумма поступления: например 1' name='lt_payments_options[match][$key][amount]' value='" . esc_attr( isset( $value['amount'] ) ? $value['amount'] : '' ) . "' />";
				//echo "<input type='text' name='lt_payments_options[match][$key][quantity]' value='" . esc_attr( isset( $value['quantity'] ) ? $value['quantity'] : '' ) . "' />";
				self::make_select( Settings::$option_prefix . '_payments_options', $key);
				echo "<button class='delete'>Delete</button>";
				echo "</div>";
			}
		} else {
			echo "
		<div class=dupeable>
			<input type='text' placeholder='Сумма поступления: например 1' name='lt_payments_options[match][0][amount]' value='' />";
			echo self::make_select( Settings::$option_prefix . '_payments_options', 0);
			echo "</div>";
		}

		echo "</div>
		<button id=addrow>Add row</button>
		</div>";

	}

}
