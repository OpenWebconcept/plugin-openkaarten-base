<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the public-facing side of the site and
 * the admin area.
 *
 * @link       https://www.openwebconcept.nl
 *
 * @package    Openkaarten_Base_Plugin
 */

namespace Openkaarten_Base_Plugin;

use Openkaarten_Base_Plugin\Admin\Admin;
use Openkaarten_Base_Plugin\Admin\Cmb2;
use Openkaarten_Base_Plugin\Admin\Datalayers;
use Openkaarten_Base_Plugin\Admin\Importer;
use Openkaarten_Base_Plugin\Admin\Locations;
use Openkaarten_Base_Plugin\Admin\Uploads;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and public-facing site hooks.
 *
 * @package    Openkaarten_Base_Plugin
 * @author     Acato <eyal@acato.nl>
 */
class Plugin {
	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		/**
		 * Enable internationalization.
		 */
		I18n::get_instance();

		/**
		 * Register admin specific functionality.
		 */
		Datalayers::get_instance();
		Admin::get_instance();
		Cmb2::get_instance();
		Locations::get_instance();
		Uploads::get_instance();
		Importer::get_instance();

		/**
		 * Register REST API specific functionality.
		 */
		Rest_Api\Openkaarten_Controller::get_instance();
	}
}
