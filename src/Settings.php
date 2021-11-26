<?php

namespace Lt;


class Settings {
	public static $option_prefix = 'lt';
	private static $initiated = false;

    public static function enqueue_scripts_styles(){
	    wp_enqueue_script( 'lt_dupeable', LT_URL . 'assets/dupeable.js', array( 'jquery' ) );
    }
	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			add_action( 'admin_menu', [ self::class, 'add_page' ] );
			add_action( 'admin_init', [ self::class, 'dbi_register_settings' ] );
			add_action( 'admin_init', [ 'Lt\Settings\Payments', 'dbi_register_settings' ] );
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts_styles']);
		}

	}


	public static function add_page() {
		add_options_page( 'Настройки Lt', 'Настройки LT', 'manage_options', self::$option_prefix . '_options', [
			self::class,
			'dbi_render_plugin_settings_page'
		] );
	}

	function dbi_plugin_subscribe_text() {
		echo '<p>Здесь задаются настройки для подписок</p>';
	}

	function dbi_plugin_redirects_text() {
		echo '<p>Настройки переадресации</p>';
	}

	function dbi_plugin_redirect_text_active() {
		echo '<p>Настройки переадресации для пользователей, которые считаются активными</p>';
	}

	function dbi_plugin_redirect_text_future() {
		echo '<p>Настройки переадресации для пользователей, которые будут активными в дальнейшем</p>';
	}

	function dbi_plugin_redirect_text_past() {
		echo '<p>Настройки переадресации для пользователей, чья дата активности прошла</p>';
	}

	function dbi_plugin_redirect_text_noinfo() {
		echo '<p>Настройки переадресации для пользователей, у которых не проставлена дата активности</p>';
	}


	function dbi_plugin_template_text() {
		echo '<p>Здесь задаются различные опции шаблонов. В шаблонах в скобочках { } можно использовать любые поля или мета-поля пользователя, такие как user_login, user_pass, user_nicename, user_email, user_url, user_registered, user_activation_key, user_status, display_name и пр</p>';
	}

	public static function dbi_register_settings() {
		register_setting( self::$option_prefix . '_plugin_options_timing', self::$option_prefix . '_plugin_options_timing' );
		register_setting( self::$option_prefix . '_plugin_options_template', self::$option_prefix . '_plugin_options_template' );
		register_setting( self::$option_prefix . '_plugin_options_redirects', self::$option_prefix . '_plugin_options_redirects' );


		add_settings_section( 'redirects_options', 'Общие настройки переадресации', [
			self::class,
			'dbi_plugin_redirects_text'
		], 'redirect' );

		add_settings_section( 'redirects_options_active', 'Переадресация для активных пользователей', [
			self::class,
			'dbi_plugin_redirect_text_active'
		], 'redirect' );
		add_settings_section( 'redirects_options_future', 'Переадресация для будущих пользователей', [
			self::class,
			'dbi_plugin_redirect_text_future'
		], 'redirect' );
		add_settings_section( 'redirects_options_past', 'Переадресация для прошедших пользователей', [
			self::class,
			'dbi_plugin_redirect_text_past'
		], 'redirect' );
		add_settings_section( 'redirects_options_noinfo', 'Переадресация для неустановленных пользователей', [
			self::class,
			'dbi_plugin_redirect_text_noinfo'
		], 'redirect' );

		add_settings_field( self::$option_prefix . '_redirect_login_active', 'Переадресация после входа', [
			self::class,
			'dbi_plugin_setting_redirect_login_active'
		], 'redirect', 'redirects_options_active' );
		add_settings_field( self::$option_prefix . '_redirect_login_future', 'Переадресация после входа', [
			self::class,
			'dbi_plugin_setting_redirect_login_future'
		], 'redirect', 'redirects_options_future' );
		add_settings_field( self::$option_prefix . '_redirect_login_past', 'Переадресация после входа', [
			self::class,
			'dbi_plugin_setting_redirect_login_past'
		], 'redirect', 'redirects_options_past' );
		add_settings_field( self::$option_prefix . '_redirect_login_noinfo', 'Переадресация после входа', [
			self::class,
			'dbi_plugin_setting_redirect_login_noinfo'
		], 'redirect', 'redirects_options_noinfo' );


		add_settings_section( 'subscribe_options', null, [
			self::class,
			'dbi_plugin_subscribe_text'
		], 'timing' );
		add_settings_field( self::$option_prefix . '_register_time', 'Доступ при регистирации', [
			self::class,
			'dbi_plugin_setting_register_time'
		], 'timing', 'subscribe_options' );
		add_settings_field( self::$option_prefix . '_approve_time', 'Доступ при одобрении консультации', [
			self::class,
			'dbi_plugin_setting_approve_time'
		], 'timing', 'subscribe_options' );

		add_settings_section( 'template_options_start', 'Уведомления о начавшемся доступе', [
			self::class,
			'dbi_plugin_template_text'
		], 'template' );

		add_settings_section( 'template_options_soon', 'Уведомления о приближающемся окончании', [
			self::class,
			'dbi_plugin_template_text'
		], 'template' );
		add_settings_section( 'template_options_end', 'Уведомления о закончившемся доступе', [
			self::class,
			'dbi_plugin_template_text'
		], 'template' );

		add_settings_field( self::$option_prefix . '_subjendsoon', 'Заголовок ', [
			self::class,
			'dbi_plugin_setting_template_subject_soon'
		], 'template', 'template_options_soon' );
		add_settings_field( self::$option_prefix . '_endsoon', 'Шаблон ', [
			self::class,
			'dbi_plugin_setting_template_soon'
		], 'template', 'template_options_soon' );
		add_settings_field( self::$option_prefix . '_endsoon_select', 'Срок уведомления', [
			self::class,
			'dbi_plugin_setting_template_soon_time'
		], 'template', 'template_options_soon' );
		add_settings_field( self::$option_prefix . '_subjend', 'Заголовок ', [
			self::class,
			'dbi_plugin_setting_template_subject_end'
		], 'template', 'template_options_end' );
		add_settings_field( self::$option_prefix . '_end', 'Шаблон ', [
			self::class,
			'dbi_plugin_setting_template_end'
		], 'template', 'template_options_end' );
		add_settings_field( self::$option_prefix . '_subjstart', 'Заголовок ', [
			self::class,
			'dbi_plugin_setting_template_subject_start'
		], 'template', 'template_options_start' );
		add_settings_field( self::$option_prefix . '_start', 'Шаблон ', [
			self::class,
			'dbi_plugin_setting_template_start'
		], 'template', 'template_options_start' );

	}



	public static function dbi_plugin_setting_redirect_login_active() {
		$options = get_option( self::$option_prefix . '_plugin_options_redirects' );
		echo "<input type='text' name='lt_plugin_options_redirects[login_active]' value='" . esc_attr( isset( $options['login_active'] ) ? $options['login_active'] : '' ) . "' />";
	}

	public static function dbi_plugin_setting_redirect_login_future() {
		$options = get_option( self::$option_prefix . '_plugin_options_redirects' );
		echo "<input type='text' name='lt_plugin_options_redirects[login_future]' value='" . esc_attr( isset( $options['login_future'] ) ? $options['login_future'] : '' ) . "' />";
	}

	public static function dbi_plugin_setting_redirect_login_past() {
		$options = get_option( self::$option_prefix . '_plugin_options_redirects' );
		echo "<input type='text' name='lt_plugin_options_redirects[login_past]' value='" . esc_attr( isset( $options['login_past'] ) ? $options['login_past'] : '' ) . "' />";
	}

	public static function dbi_plugin_setting_redirect_login_noinfo() {
		$options = get_option( self::$option_prefix . '_plugin_options_redirects' );
		echo "<input type='text' name='lt_plugin_options_redirects[login_noinfo]' value='" . esc_attr( isset( $options['login_noinfo'] ) ? $options['login_noinfo'] : '' ) . "' />";
	}

	function dbi_plugin_setting_template_soon() {
		?>
        <p><label for="lt_plugin_options_template[template_soon]">Можно использовать макросы {user_name}</label></p>
		<?php
		$options = get_option( self::$option_prefix . '_plugin_options_template' );
		echo "<textarea name='lt_plugin_options_template[template_soon]' rows='7' cols='50' >" . esc_textarea( isset( $options['template_soon'] ) ? $options['template_soon'] : '' ) . "</textarea>";
	}

	function dbi_plugin_setting_template_subject_end() {
		$options = get_option( self::$option_prefix . '_plugin_options_template' );
		echo "<input type='text' name='lt_plugin_options_template[subject_end]' value='" . esc_attr( isset( $options['subject_end'] ) ? $options['subject_end'] : '' ) . "' />";
	}

	function dbi_plugin_setting_template_subject_soon() {
		$options = get_option( self::$option_prefix . '_plugin_options_template' );
		echo "<input type='text' name='lt_plugin_options_template[subject_soon]' value='" . esc_attr( isset( $options['subject_soon'] ) ? $options['subject_soon'] : '' ) . "' />";
	}

	function dbi_plugin_setting_template_end() {
		$options = get_option( self::$option_prefix . '_plugin_options_template' );
		echo "<textarea name='lt_plugin_options_template[template_end]' rows='7' cols='50'>" . esc_textarea( isset( $options['template_end'] ) ? $options['template_end'] : '' ) . "</textarea>";
	}

	function dbi_plugin_setting_template_subject_start() {
		$options = get_option( self::$option_prefix . '_plugin_options_template' );
		echo "<input type='text' name='lt_plugin_options_template[subject_start]' value='" . esc_attr( isset( $options['subject_start'] ) ? $options['subject_start'] : '' ) . "' />";
	}

	function dbi_plugin_setting_template_start() {
		$options = get_option( self::$option_prefix . '_plugin_options_template' );
		echo "<textarea name='lt_plugin_options_template[template_start]' rows='7' cols='50'>" . esc_textarea( isset( $options['template_start'] ) ? $options['template_start'] : '' ) . "</textarea>";
	}


	function dbi_plugin_setting_template_soon_time() {
		self::make_select( self::$option_prefix . '_plugin_options_template', 'endsoon_timevalue', 'endsoon_timespan' );
	}
//	function dbi_plugin_setting_template_end_time() {
//		self::make_select( self::$option_prefix . '_plugin_options_template', 'end_timevalue', 'end_timespan' );
//	}


	function dbi_plugin_setting_register_time() {
		self::make_select( self::$option_prefix . '_plugin_options_timing', 'registration_timevalue', 'registration_timespan' );
	}

	function dbi_plugin_setting_approve_time() {
		self::make_select( self::$option_prefix . '_plugin_options_timing', 'approve_timevalue', 'approve_timespan' );
	}

	private static function make_select( $optionkey, $quantitykey, $selectkey ) {
		$options = get_option( $optionkey );
		echo "<input name='{$optionkey}[{$quantitykey}]' type='text' value='" . esc_attr( isset( $options[ $quantitykey ] ) ? $options[ $quantitykey ] : '' ) . "' />";
		echo "<select name='{$optionkey}[{$selectkey}]' >";
		$items = [ 'PTH' => 'Час', 'PD' => 'Дн', 'PM' => 'Мес' ];
		foreach ( $items as $key => $value ) {
			echo "<option" . ( $options[ $selectkey ] == $key ? ' selected=selected' : '' ) . " value='" . esc_attr( $key ) . "'>{$value}</option>";
		}
		echo "</select>";
	}

	/**
	 * @param $quantity - ключ для количества дней/минут
	 * @param $select - ключ в опциях для поиска строки PTH (добавить часы), PD (добавить дни), PM (добавить месяцы)
	 * @param $default - если не установлен Selectkey / quantitykey
	 *
	 * @return string - строка, пригодная для использования в DateTime::add/sub методах
	 */
	public static function parse_date_time( $quantity, $select, $default = '' ) {

		if ( !empty(  $quantity  ) && !empty(  $select  ) ) {
			$di_text = preg_replace( '/^(.{1,2})(.)$/', '${1}' . $quantity . '${2}',  $select );
			try {
				$di = new \DateInterval( $di_text );

				return $di_text;
			} catch ( \Exception $e ) {
				return $default;
			}

		}

		return $default;
	}

	public static function dbi_render_plugin_settings_page() {
		?>
        <!--        <h2>Example Plugin Settings</h2>-->

        <div class="wrap">
            <div id="icon-themes" class="icon32"></div>
            <h2>Настройки LT</h2>
			<?php
			$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'timing';
			?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=lt_options&tab=timing"
                   class="nav-tab <?php echo $active_tab == 'timing' ? 'nav-tab-active' : ''; ?>">Время доступа</a>
                <a href="?page=lt_options&tab=template"
                   class="nav-tab <?php echo $active_tab == 'template' ? 'nav-tab-active' : ''; ?>">Уведомления</a>
                <a href="?page=lt_options&tab=redirect"
                   class="nav-tab <?php echo $active_tab == 'redirect' ? 'nav-tab-active' : ''; ?>">Переадресации</a>
                <a href="?page=lt_options&tab=payment"
                   class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">Платежи</a>

            </h2>

            <form action="options.php" method="post">
				<?php
				if ( $active_tab == 'timing' ) {
					settings_fields( self::$option_prefix . '_plugin_options_timing' );
					do_settings_sections( 'timing' );
				} else if ( $active_tab == 'template' ) {
					settings_fields( self::$option_prefix . '_plugin_options_template' );
					do_settings_sections( 'template' );
				} else if ( $active_tab == 'redirect' ) {
					settings_fields( self::$option_prefix . '_plugin_options_redirects' );
					do_settings_sections( 'redirect' );
				} else if ( $active_tab == 'payment' ) {
					settings_fields( self::$option_prefix . '_payments_match_group' );
					do_settings_sections( 'payment' );
				}

				?>
				<?php submit_button( 'Сохранить' ); ?>

            </form>
        </div>
		<?php
	}


}

