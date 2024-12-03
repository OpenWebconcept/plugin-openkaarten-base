<?php
/**
 * Helper class with several functions.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Eyal Beker <eyal@acato.nl>
 */

namespace Openkaarten_Base_Plugin\Admin;

/**
 * Helper class with several functions.
 */
class Helper {

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Helper|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Helper The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Helper();
		}

		return self::$instance;
	}

	/**
	 * Add an error message to the admin.
	 *
	 * @param string $message The error message.
	 *
	 * @return void
	 */
	public static function add_error_message( $message ) {
		add_action(
			'admin_notices',
			function () use ( $message ) {
				?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
				<?php
			}
		);
	}
}
