<?php

namespace Lt;

class PostType {

	const POST_TYPE = 'discursion';
	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			add_action( 'init', [ self::class, 'register_post_type' ] );
			add_filter( 'posts_results', [ self::class, 'posts_results' ] );
			add_filter( 'the_comments', [ self::class, 'the_comments' ] );
			add_action( 'template_redirect', [self::class, 'template_redirect'] );

		}

	}
	public static function template_redirect(){
		$obj = get_queried_object();
		if ($obj instanceof \WP_Post && $obj->post_type == self::POST_TYPE) {
			$user = wp_get_current_user();
			if (!$user->exists()) return;
			if (Users::is_active($user->ID)) return;
			$options = get_option( 'lt_plugin_options_redirects', true );
			switch ( true ) {
				case Users::is_future( $user->ID ) && ! empty( $options['postview_future'] ) :
					if ( ! empty( $options['postview_future'] ) ) {
						wp_redirect($options['postview_future']);
						exit();
					}
					break;
				case Users::is_past( $user->ID ) && ! empty( $options['postview_past'] ):
					if ( ! empty( $options['postview_past'] ) ) {
						wp_redirect($options['postview_past']);
						exit();
					}
					break;
				case Users::is_noset( $user->ID ) && ! empty( $options['postview_noinfo'] ):
					if ( ! empty( $options['postview_noinfo'] ) ) {
						wp_redirect($options['postview_noinfo']);
						exit();
					}
					break;

			}
		}
	}
	public static function register_post_type() {
		register_post_type( self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Consultations', 'lt' ),
					'singular_name' => __( 'Consultation', 'lt' )
				),
				'rewrite'            => array( 'slug' => 'discursion' ),
				//'show_in_rest' => true,
				'public'             => true,
				'has_archive'        => true,
				'show_ui'            => true,
				'supports'           => [ 'author', 'title', 'comments', 'editor' ],
				'publicly_queryable' => true,
				'capability_type'    => 'post',
				'capabilities'       => array(
					//'create_posts' => 'do_not_allow',
					//'edit_posts' => true,
				),
				'map_meta_cap'       => true//current_user_can( 'activate_plugins' ) ? true : false,

			)
		);

	}

	/**
	 * Функция прячет записи из выборки если у текущего пользователя нет права их просмотра
	 *
	 * @param $posts
	 *
	 * @return array
	 */
	static function posts_results( $posts ) {
		foreach ( $posts as $idx => $post ) {
			if ( in_array( $post->post_type, [ self::POST_TYPE ] ) ) {
				$user = wp_get_current_user();
				if ( Users::user_is( 'homeopath' ) || current_user_can( 'manage_options' ) ) {
					continue;
				}
				if ( ! Users::is_active( $user->ID ) || $post->post_author != $user->ID ) {
					unset( $posts[ $idx ] );
				}
			}
		}
		$posts = array_values( $posts );

		return $posts;
	}


	/**
	 * Фильтрация комментариев чтобы никто не видел чужие
	 */
	public static function the_comments( $comments ) {

		foreach ( $comments as $idx => $comment ) {

			$post_id = $comment->comment_post_ID;
			if ( get_post_type( $post_id ) == self::POST_TYPE ) {
				// прячем комменты
				$user = wp_get_current_user();
				$post = get_post( $post_id );
				// пользователю с оплаченной подпиской
				if ( $post->post_author == $user->ID && Users::is_active( $user->ID ) ) {
					continue;
				}
				// гомеопату с оплаченной подпиской
				if ( Users::user_is( 'homeopath' ) && Users::is_active( $user->ID ) ) {
					continue;
				}
				// админу
				if ( current_user_can( 'activate_plugins' ) ) {
					continue;
				}
				// остальным нельзя
				unset( $comments[ $idx ] );
			}
		}

		return $comments;
	}


}