<?php
/**
 * Agent Photo Settings Class
 *
 * Handles the admin settings page for the Agent Photo plugin.
 *
 * @package Agent_Photo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Agent_Photo_Settings {

	/**
	 * Registers the settings page under the Settings menu.
	 */
	public static function register_settings_page() {
		add_options_page(
			__( 'Agent Photo Settings', 'agent-photo' ),
			__( 'Agent Photo', 'agent-photo' ),
			'manage_options',
			'agent-photo-settings',
			array( __CLASS__, 'display_settings_page' )
		);
	}

	/**
	 * Renders the settings page.
	 */
	public static function display_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Agent Photo Settings', 'agent-photo' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'agent_photo_options' );
				do_settings_sections( 'agent-photo-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Registers plugin settings.
	 */
	public static function register_settings() {
		register_setting(
			'agent_photo_options',
			'agent_photo_api_key',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '',
			)
		);

		add_settings_section(
			'agent_photo_main_section',
			__( 'API Settings', 'agent-photo' ),
			null,
			'agent-photo-settings'
		);

		add_settings_field(
			'agent_photo_api_key',
			__( 'OpenAI API Key', 'agent-photo' ),
			array( __CLASS__, 'render_api_key_field' ),
			'agent-photo-settings',
			'agent_photo_main_section'
		);
	}

	/**
	 * Renders the API key field.
	 */
	public static function render_api_key_field() {
		$key = get_option( 'agent_photo_api_key', '' );
		?>
		<input type="text" name="agent_photo_api_key" value="<?php echo esc_attr( $key ); ?>" class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'Enter your OpenAI API Key.', 'agent-photo' ); ?>
		</p>
		<?php
	}
}

// Hook into admin_init to register settings.
add_action( 'admin_init', array( 'Agent_Photo_Settings', 'register_settings' ) ); 