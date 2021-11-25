<?php

/*
   Plugin Name: LifeTutor
   Author: Dmitry Krasnikov <dmitry.krasnikov@gmail.com>
   License: GPLv2 or later
   Text Domain: pharma
   GitHub Plugin URI: https://github.com/mainpart/lt
   Primary Branch: main
   Domain Path: /languages
   Version: 1.0.1
   Description: Плагин для организации консультаций
*/

namespace Lt;


//use Lt\Settings;

// No direct access
//use Admin\Settings;

defined('ABSPATH') or die('No script kiddies please!');

// Const for path root
if (!defined('LT_PATH')) {
    define('LT_PATH', __DIR__);
}
// Const for URL root
if (!defined('LT_URL')) {
    define('LT_URL', plugin_dir_url(__FILE__));
}


include_once __DIR__ . '/vendor/autoload.php';
//include_once __DIR__."/src/Settings.php";
//include_once __DIR__."/src/PostType.php";
//include_once __DIR__."/src/Users.php";
class Plugin
{

    public static function init()
    {
        // Const for path root
        if (!defined('LT_LOCALE')) {
            define('LT_LOCALE', get_locale());
        }
        load_plugin_textdomain('lt', false, plugin_basename(__DIR__) . '/languages/');

	    wp_register_script('jquerydaterange', LT_URL.'vendor/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.js', [ 'jquery', 'jquery-ui-button', 'jquery-ui-menu', 'jquery-ui-datepicker', 'moment']);
	    wp_register_style('jquerydaterange', LT_URL.'vendor/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.css');

	    add_action('init', array('Lt\Settings', 'init'));
	    add_action('init', array('Lt\PostType', 'init'));
	    add_action('init', array('Lt\Users', 'init'));
		add_action('init', array('Lt\Amelia', 'init'));

   	    add_action('init', ['Lt\ContactForm', 'init']);
   	    add_action('init', ['Lt\Redirects', 'init']);
	    add_action('init', ['Lt\Payment', 'init']);
	    add_action('init', ['Lt\ShortCode', 'init']);

    }


}




/** Init the plugin */
add_action('plugin_loaded', array('LT\Plugin', 'init'));

