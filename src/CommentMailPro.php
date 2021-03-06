<?php


namespace Lt;


class CommentMailPro {
	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {

			//add_filter( 'WebSharks\CommentMail\Pro\Plugin::setup_options', [ self::class, 'remove_subs_for_subscriber' ] );
			add_action( 'Lt\PostCreated', [ self::class, 'subscribe_user' ], 10, 2 );
			add_action('wp_enqueue_scripts',[self::class, 'remove_comment_mail']);

		}

	}
	public static function remove_comment_mail(){
		$inline_js = array(
			'is_subscriber'=>(bool)Users::user_is('subscriber')
		);
		wp_enqueue_script('commentmail', LT_URL . 'assets/commentmail.js', ['jquery']);
		wp_localize_script( 'commentmail', 'cminline', $inline_js );

	}
//	 подписываем пользователя на только что созданный пост
	public static function subscribe_user( $user, $post_id ) {
		global $wpdb;
		if ( $user->exists() ) {
			$insert = $wpdb->prepare( "(%s, %d, %d, 0, 'asap', %s, %s, %s, 'subscribed', %d)", 'k' . strtolower( substr( md5( time() ), 0, 18 ) ), $user->ID, $post_id,
				$user->first_name, $user->last_name, $user->user_email, time() );
			$wpdb->query( "INSERT INTO {$wpdb->prefix}comment_mail_subs (`key`, user_id, post_id, comment_id, deliver, fname, lname, email, status,insertion_time) VALUES " . $insert );
		}
	}

	// отключаем для подписчиков возможность подписаться на комментарии к своей записи так как они уже подписаны
	public static function remove_subs_for_subscriber( $options ) {
		if ( isset( $options['comment_form_sub_template_enable'] ) ) {
			$user = wp_get_current_user();
			if ( $user->exists() && Users::user_is( 'subscriber' ) ) {
				$options['comment_form_sub_template_enable'] = '0';
			}
		}

		return $options;
	}

}