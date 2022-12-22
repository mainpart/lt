<?php


namespace Lt;

/**
 * Интеграция с WPCF7 - обрабатывает пользовательские консультации
 * Class ContactForm
 * @package Lt
 */
class ContactForm {

	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated && class_exists( 'WPCF7_Submission' ) ) {
			self::$initiated = true;
			add_filter( 'wpcf7_verify_nonce', '__return_true' );
			add_filter( 'wpcf7_ajax_json_echo', [ self::class, 'inject_redirect' ], 10 );
			add_action( 'wp_footer', [ self::class, 'redirect_cf7' ] );
			add_action( 'wpcf7_before_send_mail', [ self::class, 'before_send_email' ], 10, 3 );


		}

	}

	/**
	 * Если заполненная форма содержит тэг is_consult то эта контактная форма - для консультации
	 * Соответственно заполняем пользовательскую консультацию в связи с этой формой
	 *
	 * @param $contact_form
	 */
	public static function before_send_email( $contact_form ) {
		$submission = \WPCF7_Submission::get_instance();
		// если не установлено поле is_consult или пользователь не зареган - это не консультация

		if ( ! ( isset( $submission->get_posted_data()['is_consult'] ) ) ) {
			return;
		}
		global $consultation_page_id;
		// присваиваем $author_id либо текущего пользователя либо админа
		if ( is_user_logged_in() ) {
			$author_id = get_current_user_id();
		} else {
			$ids = get_users( [ 'role' => 'Administrator' ] );
			if ( count( $ids ) ) {
				$author_id = $ids[0];
			} else {
				return;
			}
		}

		//$mailprop = $contact_form->get_properties();
		//$mailprop['mail']['recipient'] = 'dk-resume@yandex.ru' ;
		//$contact_form->set_properties($mailprop);
		$post = $contact_form->prop( 'mail' )['body'];

		foreach ( $submission->get_posted_data() as $k => $v ) {	
			if (is_string($v)) {
				$post = str_replace( "[$k]", $v, $post );
			}
		}
		global $consultation_page_id;

		$query = new \WP_Query( [
			'author'      => $author_id,
			'post_type'   => PostType::POST_TYPE,
			'post_status' => [ 'publish' ],
		] );
		$user  = new \WP_User( $author_id );
		// создаем запись либо берем существующую запись для клиента
		if ( $query->have_posts() ) {
			$consultation_page_id = $query->posts[0]->ID;
		} else {
			$postcontent = get_option( Settings::$option_prefix . '_plugin_options_template', true );
			$consultation_page_id = wp_insert_post( [
				'post_author' => $author_id,
				'post_title'  => $contact_form->title() . " " . $user->user_login,
				'post_type'   => PostType::POST_TYPE,
				'post_status' => 'publish',
                'post_content' => $postcontent['postcontent'],
			] );
			update_post_meta( $consultation_page_id, 'client_id', $author_id );
			// если подписчик создает свой первый пост, то дата его членства стартует с указанной даты
            if (Users::user_is('subscriber', $user)) {
                Users::set_initial_access($user->ID);
            }
			do_action( 'Lt\PostCreated', wp_get_current_user(), $consultation_page_id );
		}
		// пишем комментарий клиента в эту запись
		if ( $consultation_page_id ) {

			// устанавливаем редирект на поле формы либо на эту запись
			global $redirect_page;
			$redirect_page = isset( $submission->get_posted_data()['redirect'] ) ? $submission->get_posted_data()['redirect'] : get_permalink( $consultation_page_id );

			wp_insert_comment( [
				'user_id'         => get_current_user_id(),
				'comment_post_ID' => $consultation_page_id,
				'comment_content' => $post
			] );
			do_action( 'Lt\PostUpdated', wp_get_current_user(), $consultation_page_id );
		}
	}

	/**
	 * Срабатываем после заполнения формы - добавляет поле redirect в json ответа
	 * После этого идет переадресация на этот урл в ф-и redirect_cf7
	 *
	 * @param $response
	 *
	 * @return mixed|void
	 */
	static function inject_redirect( $response ) {
		global $redirect_page;
		if ( $redirect_page ) {
			$response['redirect'] = $redirect_page;
		}

		return apply_filters( 'Lt\Options\formfill', $response );
	}

	/**
	 * Добавляется в тело страницы
	 * Выполняет переадресацию по адресу из параметра redirect, возвращаемому после заполнении формы
	 * см. ф-ю inject_redirect
	 */
	static function redirect_cf7() {
		?>
        <script type="text/javascript">
            var fun = function (event) {
                if (typeof event.detail.apiResponse.redirect === 'undefined') return;
                window.location = event.detail.apiResponse.redirect;
            }
            document.addEventListener('wpcf7mailsent', fun, false);
            document.addEventListener('wpcf7mailfailed', fun, false);
        </script>
		<?php
	}


}