<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin so that it is ready for translation.
 *
 * @link       https://www.openwebconcept.nl
 *
 * @package    Openkaarten_Base_Plugin
 */

namespace Openkaarten_Base_Plugin;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin so that it is ready for translation.
 *
 * @package    Openkaarten_Base_Plugin
 * @author     Acato <eyal@acato.nl>
 */
class I18n {
	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    I18n|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return I18n The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new I18n();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ 'Openkaarten_Base_Plugin\I18n', 'load_plugin_textdomain' ] );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain(
			'openkaarten-base',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
