<?php

/*
   Plugin Name: LifeTutor
   Author: Dmitry Krasnikov <dmitry.krasnikov@gmail.com>
   License: GPLv2 or later
   Text Domain: pharma
   GitHub Plugin URI: https://github.com/mainpart/lt
   Primary Branch: main
   Domain Path: /languages
   Version: 1.0.3
   Description: Плагин для организации консультаций
*/

namespace Lt;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Const for path root
if ( ! defined( 'LT_PATH' ) ) {
	define( 'LT_PATH', __DIR__ );
}
// Const for URL root
if ( ! defined( 'LT_URL' ) ) {
	define( 'LT_URL', plugin_dir_url( __FILE__ ) );
}

include_once __DIR__ . '/vendor/autoload.php';
\WP_Dependency_Installer::instance( __DIR__ )->run();

class Plugin {
	public static bool $initiated = false;
    public static function init() {
	    if ( ! self::$initiated ) {
		    // Const for path root
		    if ( ! defined( 'LT_LOCALE' ) ) {
			    define( 'LT_LOCALE', get_locale() );
		    }
		    load_plugin_textdomain( 'lt', false, plugin_basename( __DIR__ ) . '/languages/' );
			add_action('wp_enqueue_scripts', array(self::class, 'enqueue_scripts_styles'));


		    add_action('plugins_loaded', array('Lt\Settings', 'init'));
		    add_action('plugins_loaded', array('Lt\PostType', 'init'));
		    add_action('plugins_loaded', array('Lt\Users', 'init'));
		    add_action('plugins_loaded', array('Lt\Amelia', 'init'));
		    add_action('plugins_loaded', ['Lt\CommentMailPro', 'init']);
		    add_action('plugins_loaded', ['Lt\ContactForm', 'init']);
		    add_action('plugins_loaded', ['Lt\Redirects', 'init']);
		    add_action('plugins_loaded', ['Lt\Payment', 'init']);
		    add_action('plugins_loaded', ['Lt\ShortCode', 'init']);

		    self::$initiated = true;
	    }
    }
	public static function enqueue_scripts_styles(){
		wp_register_script( 'jquerydaterange', LT_URL . 'vendor/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.js', [
			'jquery',
			'jquery-ui-button',
			'jquery-ui-menu',
			'jquery-ui-datepicker',
			'moment'
		] );
		wp_register_style( 'jquerydaterange', LT_URL . 'vendor/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.css' );

	}

}

/** Init the plugin */
Plugin::init();

