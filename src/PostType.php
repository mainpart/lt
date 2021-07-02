<?php

namespace Lt;

class PostType {

	const POST_TYPE = 'discursion';
	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			add_action( 'wp_loaded', [ self::class, 'register_post_type' ] );
			add_filter( 'posts_results', [ self::class, 'posts_results' ] );
			add_filter( 'the_comments', [ self::class, 'the_comments' ] );
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
				if (Users::user_is('homeopath') ||  current_user_can( 'manage_options' )) {
					continue;
				}
				if ( ! Users::is_active( $user->ID ) || $post->post_author !=$user->ID  ) {
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
	public function the_comments( $comments ) {

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