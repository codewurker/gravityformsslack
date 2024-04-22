<?php

if ( ! class_exists( 'GFForms') ) {
	die();
}

GFForms::include_feed_addon_framework();

use Gravity_Forms\Gravity_Forms_Slack\Slack_Legacy_API;

/**
 * Gravity Forms Slack Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GFSlack extends GFFeedAddOn {
	/**
	 * Version of this add-on which requires reauthentication with the API.
	 *
	 * Anytime updates are made to this class that requires a site to reauthenticate Gravity Forms with Slack, this
	 * constant should be updated to the value of GFForms::$version.
	 *
	 * @since 1.13
	 *
	 * @see GFForms::$version
	 */
	const LAST_REAUTHENTICATION_VERSION = '2.0.1';

	/**
	 * Enabling background feed processing to prevent performance issues delaying form submission completion.
	 *
	 * @since 2.0.1
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = true;

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @access private
	 * @var    object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Slack Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_version Contains the version, defined from slack.php
	 */
	protected $_version = GF_SLACK_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '1.9.14.26';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsslack';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsslack/slack.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'http://www.gravityforms.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'Gravity Forms Slack Add-On';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Slack';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_slack';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_slack';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_slack_uninstall';

	/**
	 * Defines the capabilities needed for the Post Creation Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_slack', 'gravityforms_slack_uninstall' );

	/**
	 * Contains an instance of the Slack API library, if available.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    GF_Slack_API $api If available, contains an instance of the Slack API library.
	 */
	protected $api = null;

	/**
	 * Get instance of this class.
	 *
	 * @access public
	 * @static
	 *
	 * @return GFSlack
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;

	}

	/**
	 * Plugin starting point. Adds PayPal delayed payment support.
	 *
	 * @since  1.3
	 * @access public
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Send message to Slack only when payment is received.', 'gravityformsslack' ),
			)
		);

	}


	/**
	 * Run admin initialization processes.
	 *
	 * @since 1.13
	 */
	public function init_admin() {
		parent::init_admin();

		add_action( 'admin_notices', array( $this, 'display_notices' ) );
	}

	/**
	 * Add AJAX callbacks.
	 *
	 * @since  1.7
	 * @access public
	 */
	public function init_ajax() {

		parent::init_ajax();

		// Add AJAX callback for de-authorizing with Dropbox.
		add_action( 'wp_ajax_gfslack_deauthorize', array( $this, 'ajax_deauthorize' ) );

	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since  1.7
	 * @access public
	 *
	 * @return array
	 */
	public function scripts() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$scripts = array(
			array(
				'handle'  => 'gform_slack_pluginsettings',
				'deps'    => array( 'jquery' ),
				'src'     => $this->get_base_url() . "/js/plugin_settings{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
				'strings' => array(
					'disconnect'        => wp_strip_all_tags( __( 'Are you sure you want to disconnect from Slack?', 'gravityformsslack' ) ),
					'nonce_deauthorize' => wp_create_nonce( 'gfslack_deauthorize' ),
					'pluginSettingsURL' => admin_url( 'admin.php?page=gf_settings&subview=' . $this->get_slug() ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );

	}

	/**
	 * Enqueue needed stylesheets.
	 *
	 * @since  1.7
	 * @access public
	 *
	 * @return array
	 */
	public function styles() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$styles = array(
			array(
				'handle'  => 'gform_slack_pluginsettings',
				'src'     => $this->get_base_url() . "/css/plugin_settings{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.10
	 *
	 * @return string
	 */
	public function get_menu_icon() {

		return $this->is_gravityforms_supported( '2.5-beta-4' ) ? 'gform-icon--slack' : 'dashicons-admin-generic';

	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Maybe save access token.
	 *
	 * @since  1.7
	 * @access public
	 */
	public function plugin_settings_page() {

		// If access token is provided, save it.
		if ( rgpost( 'access_token' ) ) {

			// If state does not match, do not save.
			if ( ! wp_verify_nonce( rgpost( 'state' ), $this->get_authentication_state_action() ) ) {

				// Add error message.
				GFCommon::add_error_message( esc_html__( 'Unable to authenticate with Slack due to mismatched state.', 'gravityformsslack' ) );

				return parent::plugin_settings_page();

			}

			// Get current plugin settings.
			$settings = $this->get_plugin_settings();

			// $settings can be false here, convert to array if so.
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}

			// Add access token to plugin settings.
			$settings['auth_token'] = rgpost( 'access_token' );

			// Get team name.
			$settings['team_name'] = $this->get_team_name( $settings['auth_token'] );

			// Set the API authentication version.
			$settings['reauth_version'] = self::LAST_REAUTHENTICATION_VERSION;

			// Save plugin settings.
			$this->update_plugin_settings( $settings );

		}

		// If error is provided, display message.
		if ( rgpost( 'auth_error' ) || rgpost( 'error' ) ) {

			// Add error message.
			GFCommon::add_error_message( esc_html__( 'Unable to authenticate with Slack.', 'gravityformsslack' ) );

		}

		return parent::plugin_settings_page();

	}

	/**
	 * Setup plugin settings fields.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						esc_html__( 'Slack provides simple group chat for your team. Use Gravity Forms to alert your Slack channels of a new form submission. If you don\'t have a Slack account, you can %1$s sign up for one here.%2$s', 'gravityformsslack' ),
						'<a href="https://www.slack.com/" target="_blank">', '</a>'
					)
				),
				'fields'      => array(
					array(
						'name'              => 'auth_token',
						'type'              => 'auth_token',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
					array(
						'name'          => 'team_name',
						'type'          => 'hidden',
						'readonly'      => true,
						'save_callback' => array( $this, 'update_team_name' ),
					),
				),
			),
		);

	}

	/**
	 * Update team name when saving legacy token.
	 *
	 * @since  1.8
	 * @access public
	 *
	 * @param array  $field       Field settings.
	 * @param string $field_value Field value.
	 *
	 * @uses   GFAddOn::get_posted_settings()
	 * @uses   GFSlack::get_team_name()
	 *
	 * @return null|string
	 */
	public function update_team_name( $field, $field_value ) {

		// Get posted settings.
		$settings = $this->get_posted_settings();

		// If auth token was not provided, return.
		if ( ! rgar( $settings, 'auth_token' ) ) {
			return $field_value;
		}

		// Get team name.
		$team_name = $this->get_team_name( $settings['auth_token'] );

		// Update posted setting.
		$_gaddon_posted_settings[ $field['name'] ] = $team_name;

		return $team_name;

	}

	/**
	 * Create Generate Auth Token settings field.
	 *
	 * @since  1.7
	 * @access public
	 *
	 * @param array $field Field settings.
	 * @param bool  $echo  Display field. Defaults to true.
	 *
	 * @uses   GFAddOn::settings_text()
	 * @uses   GFSlack::initialize_api()
	 *
	 * @return string
	 */
	public function settings_auth_token( $field, $echo = true ) {

		// Initialize return HTML.
		$html = '';

		// If Slack is authenticated, display de-authorize button.
		if ( $this->initialize_api() ) {

			// Get account information.
			$account = $this->api->auth_test();

			$html .= '<p>' . esc_html__( 'Signed into Slack team: ', 'gravityformsslack' );
			$html .= sprintf( '%s (%s)', esc_html( $account['team'] ), esc_html( $account['user'] ) );
			$html .= '</p>';
			$html .= sprintf(
				' <a href="#" class="button primary" id="gform_slack_deauth_button">%1$s</a>',
				esc_html__( 'Disconnect Slack', 'gravityformsslack' )
			);

		} else {

			// Prepare authorization URL.
			$license_key  = GFCommon::get_key();
			$settings_url = urlencode( admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) );
			$auth_url     = add_query_arg( array(
				'redirect_to' => $settings_url,
				'license'     => $license_key,
				'state'       => wp_create_nonce( $this->get_authentication_state_action() ),
				'version'     => $this->_version,
			), $this->get_gravity_api_url( '/auth/slack' ) );

			$html .= sprintf(
				'<span id="gform_slack_auth_container"><a href="%2$s" class="button primary" id="gform_slack_auth_button">%1$s</a></span>',
				esc_html__( 'Connect to Slack', 'gravityformsslack' ),
				$auth_url
			);

		}

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Deauthorize with Slack.
	 *
	 * @since  1.7
	 * @access public
	 *
	 * @uses   GFAddOn::get_plugin_settings()
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAddOn::log_error()
	 * @uses   GFAddOn::update_plugin_settings()
	 * @uses   GFSlack::initialize_api()
	 * @uses   GF_Slack_API::auth_revoke()
	 */
	public function ajax_deauthorize() {

		// Verify nonce.
		if ( wp_verify_nonce( rgget( 'nonce' ), 'gfslack_deauthorize' ) === false ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformsslack' ) ) );
		}

		// If user is not authorized, exit.
		if ( ! GFCommon::current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformsslack' ) ) );
		}

		// Initialize Slack API.
		$this->initialize_api();

		try {

			// Revoke access token.
			$revoke = $this->api->auth_revoke();

			// Send JSON based on revoke response.
			if ( rgar( $revoke, 'ok' ) ) {

				// Log that we revoked the access token.
				$this->log_debug( __METHOD__ . '(): Access token revoked.' );

				// Save settings.
				$this->update_plugin_settings( array() );
				$this->clear_plugin_cache();

				// Return success response.
				wp_send_json_success();

			} else {

				// Log that we could not revoke the access token.
				$this->log_error( __METHOD__ . '(): Unable to revoke access token; ' . rgar( $revoke, 'error' ) );

				// Return error response.
				wp_send_json_error( array( 'message' => esc_html__( 'Unable to de-authorize with Slack.', 'gravityformsslack' ) ) );

			}

		} catch ( Exception $e ) {

			// Log that we could not revoke the access token.
			$this->log_error( __METHOD__ . '(): Unable to revoke access token; ' . $e->getMessage() );

			// Return error response.
			wp_send_json_error( array( 'message' => $e->getMessage() ) );

		}

	}

	/**
	 * Display a notification in the admin if Gravity Forms needs to reauthenticate with Slack.
	 *
	 * @since 1.13
	 * @since 2.1 updated with new reauth message.
	 */
	public function display_notices() {
		if ( ! $this->requires_api_reauthentication() ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: open <a> tag, 2: close </a> tag */
			esc_html__(
				'Slack is updating their API to be more granular in scope, which requires re-authentication. Please disconnect and reconnect to re-authenticate with Slack and ensure the continued functionality of your feeds.',
				'gravityformsslack'
			),
			'<a href="' . $this->get_plugin_settings_url() . '">',
			'</a>'
		)
		?>

		<div class="gf-notice notice notice-error">
			<p><?php echo wp_kses( $message, array( 'a' => array( 'href' => true ) ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Check whether this add-on needs to be reauthenticated with the Slack API.
	 *
	 * @since 1.13
	 *
	 * @return bool
	 */
	private function requires_api_reauthentication() {
		$settings = $this->get_plugin_settings();

		return ! empty( $settings ) && version_compare( rgar( $settings, 'reauth_version' ), self::LAST_REAUTHENTICATION_VERSION, '<' );
	}


	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Setup fields for feed settings.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFFeedAddOn::get_default_feed_name()
	 * @uses   GFSlack::channels_for_feed_setting()
	 * @uses   GFSlack::fileupload_fields_for_feed_setting()
	 * @uses   GFSlack::groups_for_feed_setting()
	 * @uses   GFSlack::send_to_for_feed_setting()
	 * @uses   GFSlack::tooltip_for_feed_setting()
	 * @uses   GFSlack::users_for_feed_setting()
	 *
	 * @return array $settings
	 */
	public function feed_settings_fields() {

		$settings = array(
			array(
				'fields' => array(
					array(
						'name'          => 'feed_name',
						'label'         => esc_html__( 'Name', 'gravityformsslack' ),
						'type'          => 'text',
						'class'         => 'medium',
						'required'      => true,
						'tooltip'       => $this->tooltip_for_feed_setting( 'feed_name' ),
						'default_value' => $this->get_default_feed_name(),
					),
					array(
						'name'     => 'action',
						'label'    => esc_html__( 'Action', 'gravityformsslack' ),
						'type'     => 'radio',
						'required' => true,
						'onchange' => "jQuery(this).parents('form').submit();",
						'choices'  => $this->action_for_feed_setting(),
					),
					array(
						'name'       => 'invite_action_notice',
						'type'       => 'invite_action_notice',
						'dependency' => array(
							'field'  => 'action',
							'values' => array( 'invite' ),
						),
						'callback'   => array( $this, 'feed_invite_action_notice' ),
					),
					array(
						'name'       => 'email',
						'label'      => esc_html__( 'Email Address', 'gravityformsslack' ),
						'type'       => 'field_select',
						'required'   => true,
						'tooltip'    => $this->tooltip_for_feed_setting( 'email' ),
						'dependency' => array( 'field' => 'action', 'values' => array( 'invite' ) ),
						'args'       => array( 'input_types' => array( 'email' ) ),
					),
					array(
						'name'       => 'first_name',
						'label'      => esc_html__( 'First Name', 'gravityformsslack' ),
						'type'       => 'field_select',
						'dependency' => array( 'field' => 'action', 'values' => array( 'invite' ) ),
					),
					array(
						'name'       => 'last_name',
						'label'      => esc_html__( 'Last Name', 'gravityformsslack' ),
						'type'       => 'field_select',
						'dependency' => array( 'field' => 'action', 'values' => array( 'invite' ) ),
					),
					array(
						'name'       => 'channels[]',
						'label'      => esc_html__( 'Slack Channels', 'gravityformsslack' ),
						'type'       => 'select',
						'class'      => 'medium',
						'choices'    => $this->channels_for_feed_setting( false ),
						'multiple'   => true,
						'dependency' => array( 'field' => 'action', 'values' => array( 'invite' ) ),
					),
					array(
						'name'       => 'send_to',
						'label'      => esc_html__( 'Send To', 'gravityformsslack' ),
						'type'       => 'radio',
						'required'   => true,
						'onchange'   => "jQuery(this).parents('form').submit();",
						'choices'    => $this->send_to_for_feed_setting(),
						'tooltip'    => $this->tooltip_for_feed_setting( 'send_to' ),
						'dependency' => array( 'field' => 'action', 'values' => array( 'message' ) ),
					),
					array(
						'name'       => 'channel',
						'label'      => esc_html__( 'Slack Channel', 'gravityformsslack' ),
						'type'       => 'select',
						'required'   => true,
						'choices'    => $this->channels_for_feed_setting(),
						'tooltip'    => $this->tooltip_for_feed_setting( 'channel' ),
						'dependency' => array( 'field' => 'send_to', 'values' => array( 'channel' ) ),
					),
					array(
						'name'       => 'group',
						'label'      => esc_html__( 'Slack Private Group', 'gravityformsslack' ),
						'type'       => 'select',
						'required'   => true,
						'choices'    => $this->groups_for_feed_setting(),
						'tooltip'    => $this->tooltip_for_feed_setting( 'group' ),
						'dependency' => array( 'field' => 'send_to', 'values' => array( 'group' ) ),
					),
					array(
						'name'       => 'user',
						'label'      => esc_html__( 'Slack User', 'gravityformsslack' ),
						'type'       => 'select',
						'required'   => true,
						'choices'    => $this->users_for_feed_setting(),
						'tooltip'    => $this->tooltip_for_feed_setting( 'user' ),
						'dependency' => array( 'field' => 'send_to', 'values' => array( 'user' ) ),
					),
					array(
						'name'       => 'message',
						'label'      => esc_html__( 'Message', 'gravityformsslack' ),
						'type'       => 'textarea',
						'required'   => true,
						'class'      => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'tooltip'    => $this->tooltip_for_feed_setting( 'message' ),
						'value'      => 'Entry #{entry_id} ({entry_url}) has been added.',
						'dependency' => array( 'field' => 'send_to', 'values' => array( '_notempty_' ) ),
						'allow_html' => true,
					),
				),
			),
		);

		$fileupload_fields_for_feed = $this->fileupload_fields_for_feed_setting();

		if ( ! empty( $fileupload_fields_for_feed ) ) {

			$settings[0]['fields'][] = array(
				'name'       => 'attachments',
				'type'       => 'checkbox',
				'label'      => __( 'Image Attachments', 'gravityformsslack' ),
				'choices'    => $fileupload_fields_for_feed,
				'tooltip'    => $this->tooltip_for_feed_setting( 'attachments' ),
				'dependency' => array( 'field' => 'send_to', 'values' => array( '_notempty_' ) ),
			);

		}

		$settings[0]['fields'][] = array(
			'name'           => 'feed_condition',
			'label'          => esc_html__( 'Conditional Logic', 'gravityformsslack' ),
			'type'           => 'feed_condition',
			'checkbox_label' => esc_html__( 'Enable', 'gravityformsslack' ),
			'instructions'   => esc_html__( 'Post to Slack if', 'gravityformsslack' ),
			'tooltip'        => $this->tooltip_for_feed_setting( 'feed_condition' ),
			'dependency'     => array( 'field' => 'action', 'values' => array( '_notempty_' ) ),
		);

		return $settings;

	}

	/**
	 * Callback to display a notice regarding Invite to Team functionality for existing feeds.
	 *
	 * @since 1.14
	 *
	 * @param array $field The field array.
	 * @param bool  $echo  Whether or not to print the field to the screen.
	 *
	 * @return string
	 */
	public function feed_invite_action_notice( $field, $echo = true ) {
		// Apply inline styles for GF 2.4.
		$styles = $this->is_gravityforms_supported( '2.5-beta' ) ? '' : ' style="padding: 1rem; max-width: 750px;"';

		ob_start();
		?>
		<div <?php printf( 'class="alert alert_red gforms_note_error"%s', $styles ); // @codingStandardsIgnoreLine ?>>
			<p><?php esc_html_e( 'Slack no longer supports Invite to Teams functionality for standard workspaces. You may safely delete this feed.', 'gravityformsslack' ); ?></p>
		</div>
		<?php
		$html = ob_get_clean();

		if ( $echo ) {
			echo wp_kses_post( $html ); // @codingStandardsIgnoreLine - HTML prepared in this method.
		}

		return $html;
	}

	/**
	 * Get feed tooltip.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $field Field name.
	 *
	 * @return string
	 */
	public function tooltip_for_feed_setting( $field ) {

		// Setup tooltip array.
		$tooltips = array();

		// Feed Name.
		$tooltips['feed_name'] = '<h6>' . esc_html__( 'Name', 'gravityformsslack' ) . '</h6>';
		$tooltips['feed_name'] .= esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsslack' );

		// Send To.
		$tooltips['send_to'] = '<h6>' . esc_html__( 'Send To', 'gravityformsslack' ) . '</h6>';
		$tooltips['send_to'] .= esc_html__( 'Select what type of channel Slack will send the message to: a public channel, a private group or an IM channel.', 'gravityformsslack' );

		// Email Address.
		$tooltips['email'] = '<h6>' . esc_html__( 'Email Address', 'gravityformsslack' ) . '</h6>';
		$tooltips['email'] .= esc_html__( 'Select what email field will be used to send the Slack invite.', 'gravityformsslack' );

		// Channel.
		$tooltips['channel'] = '<h6>' . esc_html__( 'Slack Channel', 'gravityformsslack' ) . '</h6>';
		$tooltips['channel'] .= esc_html__( 'Select which Slack channel this feed will post a message to.', 'gravityformsslack' );

		// Private Group.
		$tooltips['group'] = '<h6>' . esc_html__( 'Slack Private Group', 'gravityformsslack' ) . '</h6>';
		$tooltips['group'] .= esc_html__( 'Select which Slack private group this feed will post a message to.', 'gravityformsslack' );

		// User.
		$tooltips['user'] = '<h6>' . esc_html__( 'Slack User', 'gravityformsslack' ) . '</h6>';
		$tooltips['user'] .= esc_html__( 'Select which Slack user this feed will post a message to.', 'gravityformsslack' );

		// Message.
		$tooltips['message'] = '<h6>' . __( 'Message', 'gravityformsslack' ) . '</h6>';
		$tooltips['message'] .= esc_html__( 'Enter the message that will be posted to the room.', 'gravityformsslack' ) . '<br /><br />';
		$tooltips['message'] .= '<strong>' . __( 'Available formatting:', 'gravityformsslack' ) . '</strong><br />';
		$tooltips['message'] .= '<strong>*asterisks*</strong> to create bold text<br />';
		$tooltips['message'] .= '<em>_underscores_</em> to italicize text<br /><br />';
		$tooltips['message'] .= '<strong>></strong> to indent a single line<br />';
		$tooltips['message'] .= '<strong>>>></strong> to indent multiple paragraphs<br /><br />';
		$tooltips['message'] .= '<strong>`single backticks`</strong> display as inline fixed-width text<br />';
		$tooltips['message'] .= '<strong>```triple backticks```</strong> create a block of pre-formatted, fixed-width text';

		// Image Attachment.
		$tooltips['attachments'] = '<h6>' . esc_html__( 'Image Attachments', 'gravityformsslack' ) . '</h6>';
		$tooltips['attachments'] .= esc_html__( 'Select which file upload fields will be attached to the Slack message. Only image files will be attached.', 'gravityformsslack' );

		// Feed Condition.
		$tooltips['feed_condition'] = '<h6>' . esc_html__( 'Conditional Logic', 'gravityformsslack' ) . '</h6>';
		$tooltips['feed_condition'] .= esc_html__( 'When conditional logic is enabled, form submissions will only be posted to Slack when the condition is met. When disabled, all form submissions will be posted.', 'gravityformsslack' );

		// Return desired tooltip.
		return $tooltips[ $field ];

	}

	/**
	 * Get Slack action types for feed settings field.
	 *
	 * @since  1.4
	 * @access public
	 *
	 * @uses   GFSlack::can_invite_to_team()
	 *
	 * @return array
	 */
	public function action_for_feed_setting() {

		// Initialize actions.
		$actions = array(
			array(
				'label' => esc_html__( 'Send Message', 'gravityformsslack' ),
				'value' => 'message',
				'icon'  => 'fa-comment',
			),
		);

		if ( $this->has_invite_action() ) {
			$actions[] = array(
				'label' => esc_html__( 'Invite to Team', 'gravityformsslack' ),
				'value' => 'invite',
				'icon'  => 'fa-users',
			);
		}

		return $actions;

	}

	/**
	 * Checks whether an existing feed has invite settings saved.
	 *
	 * @since 1.14
	 *
	 * @return bool
	 */
	private function has_invite_action() {
		$feed = $this->get_current_feed();

		return rgars( $feed, 'meta/action' ) === 'invite';
	}

	/**
	 * Get Slack channel types for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function send_to_for_feed_setting() {

		return array(
			array(
				'label' => esc_html__( 'Public Channel', 'gravityformsslack' ),
				'value' => 'channel',
				'icon'  => 'fa-hashtag',
			),
			array(
				'label' => esc_html__( 'Private Group', 'gravityformsslack' ),
				'value' => 'group',
				'icon'  => 'fa-users',
			),
			array(
				'label' => esc_html__( 'Direct Message', 'gravityformsslack' ),
				'value' => 'user',
				'icon'  => 'fa-user-secret',
			),
		);

	}

	/**
	 * Get Slack channels for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param bool $include_initial Include initial "Select a Channel" choice.
	 *
	 * @uses   GFSlack::initialize_api()
	 * @uses   GF_Slack_API::get_channels()
	 *
	 * @return array
	 */
	public function channels_for_feed_setting( $include_initial = true ) {
		$choices = array();

		if ( $include_initial ) {
			$choices[] = array(
				'label' => esc_html__( 'Select a Channel', 'gravityformsslack' ),
				'value' => '',
			);
		}

		if ( ! $this->initialize_api() ) {
			return $choices;
		}

		// Check for cached channels.
		$cache = get_transient( "{$this->_slug}_channels_list" );

		if ( $cache ) {
			return $cache;
		}

		// Get fresh data from Slack and prepare choices for the feed settings.
		$channels = $this->request_all_channels( 'get_channels' );

		if ( empty( $channels ) ) {
			return $choices;
		}

		$result = $this->append_channels_to_choices( $channels, $choices );

		set_transient( "{$this->_slug}_channels_list", $result, 15 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Request all channels from the Slack API, paginating through the results, alphabetically sorted by name.
	 *
	 * @since  1.14
	 *
	 * @param string $api_callback_method The name of the method to call on the API class.
	 * @param string $channel_index       The index to use on the response to find the set of channels. Defaults to
	 *                                    channels.
	 *
	 * @return array
	 */
	private function request_all_channels( $api_callback_method, $channel_index = 'channels' ) {
		$channels     = array();
		$request_args = array( 'cursor' => '' );

		do {
			$response          = call_user_func( array( $this->api, $api_callback_method ), $request_args );
			$response_channels = rgar( $response, $channel_index );
			$next_cursor       = rgars( $response, 'response_metadata/next_cursor' );

			if ( empty( $response_channels ) ) {
				break;
			}

			foreach ( $response_channels as $channel ) {
				$channels[] = $channel;
			}

			if ( empty( $next_cursor ) ) {
				unset( $request_args['cursor'] );
				break;
			}

			// Set the updated cursor for the next API request.
			$request_args['cursor'] = $next_cursor;
		} while ( ! empty( $request_args['cursor'] ) );

		return $this->sort_elements_by_name( $channels );
	}


	/**
	 * Helper function to sort elements alphabetically by name.
	 *
	 * This makes long lists of channels or users a little easier to search/parse in the UI.
	 *
	 * @param array $elements Collection of elements to sort.
	 *
	 * @return array
	 */
	private function sort_elements_by_name( $elements ) {
		if ( empty( $elements ) ) {
			return $elements;
		}

		usort(
			$elements,
			function( $a, $b ) {
				return strcmp( rgar( $a, 'name' ), rgar( $b, 'name' ) );
			}
		);

		return $elements;
	}

	/**
	 * Get Slack groups for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFSlack::initialize_api()
	 * @uses   GF_Slack_API::get_groups()
	 *
	 * @return array
	 */
	public function groups_for_feed_setting() {
		$choices = array(
			array(
				'label' => esc_html__( 'Select a Group', 'gravityformsslack' ),
				'value' => '',
			),
		);

		if ( ! $this->initialize_api() ) {
			return $choices;
		}

		// Checked for cached channels.
		$cache = get_transient( "{$this->_slug}_groups_list" );

		if ( $cache ) {
			return $cache;
		}

		// Get fresh data from Slack and prepare choices for the feed settings.
		$channels = $this->request_all_channels( 'get_groups', 'channels' );

		if ( empty( $channels ) ) {
			return $choices;
		}

		$result = $this->append_channels_to_choices( $channels, $choices );

		set_transient( "{$this->_slug}_groups_list", $result, 15 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Loop through a set of channels and append them to a set of choices.
	 *
	 * @param array $channels Array of channels.
	 * @param array $choices Array of choices.
	 *
	 * @return array
	 */
	protected function append_channels_to_choices( array $channels, array $choices ) {
		foreach ( $channels as $channel ) {
			$choices[] = array(
				'label' => rgar( $channel, 'name' ),
				'value' => rgar( $channel, 'id' ),
			);
		}

		return $choices;
	}

	/**
	 * Get Slack users for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFSlack::initialize_api()
	 * @uses   GF_Slack_API::get_users()
	 *
	 * @return array
	 */
	public function users_for_feed_setting() {

		// Setup choices array.
		$choices = array(
			array(
				'label' => esc_html__( 'Select a User', 'gravityformsslack' ),
				'value' => '',
			),
		);

		// If Slack API instance is not initialized, return choices.
		if ( ! $this->initialize_api() ) {
			return $choices;
		}

		// Setup choices array.
		$choices = array();

		// Get the channels.
		$users = $this->api->get_users();

		// Add lists to the choices array.
		if ( rgar( $users, 'members' ) && ! empty( $users['members'] ) ) {

			foreach ( $this->sort_elements_by_name( $users['members'] ) as $user ) {

				if ( rgar( $user, 'deleted', false ) ) {
					continue;
				}

				$choices[] = array(
					'label' => $user['name'],
					'value' => $user['id'],
				);

			}

		}

		return $choices;

	}

	/**
	 * Get file upload fields for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFAPI::get_form()
	 * @uses   GFCommon::get_fields_by_type()
	 *
	 * @return array
	 */
	public function fileupload_fields_for_feed_setting() {

		// Setup choices array.
		$choices = array();

		// Get the form file fields.
		$form        = GFAPI::get_form( rgget( 'id' ) );
		$file_fields = GFCommon::get_fields_by_type( $form, array( 'fileupload' ) );

		if ( ! empty( $file_fields ) ) {

			foreach ( $file_fields as $field ) {

				$choices[] = array(
					'name'          => 'attachments[' . $field->id . ']',
					'label'         => $field->label,
					'default_value' => 0,
				);

			}

		}

		return $choices;

	}

	/**
	 * Set feed creation control.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFSlack::initialize_api()
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		return $this->initialize_api();

	}

	/**
	 * Enable feed duplication.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  int $feed_id Feed ID requesting duplication ability.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $feed_id ) {

		return true;

	}





	// # FEED LIST -----------------------------------------------------------------------------------------------------

	/**
	 * Setup columns for feed list table.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformsslack' ),
			'action'    => esc_html__( 'Action', 'gravityformsslack' ),
			'send_to'   => esc_html__( 'Send To', 'gravityformsslack' ),
		);

	}

	/**
	 * Get value for action feed list column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed Feed object.
	 *
	 * @return string
	 */
	public function get_column_value_action( $feed ) {

		switch ( rgars( $feed, 'meta/action' ) ) {

			case 'invite':
				return esc_html__( 'Invite to Team', 'gravityformsslack' );

			case 'message':
				return esc_html__( 'Send Message', 'gravityformsslack' );

		}

	}

	/**
	 * Get value for send to feed list column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed Feed object.
	 *
	 * @uses   GFSlack::initialize_api()
	 * @uses   GF_Slack_API::get_channel()
	 * @uses   GF_Slack_API::get_group()
	 * @uses   GF_Slack_API::get_user()
	 *
	 * @return string
	 */
	public function get_column_value_send_to( $feed ) {

		// If this is an invite feed, return null.
		if ( 'invite' === rgars( $feed, 'meta/action' ) ) {
			return null;
		}

		// If Slack instance is not initialized, return channel ID.
		if ( ! $this->initialize_api() ) {
			return ucfirst( $feed['meta']['send_to'] );
		}

		switch ( $feed['meta']['send_to'] ) {

			case 'group':
				$group       = $this->api->get_group( $feed['meta']['group'] );
				$destination = ( rgar( $group, 'group' ) ) ? $group['group']['name'] : $feed['meta']['group'];

				return sprintf( esc_html__( 'Private Group: %s', 'gravityformsslack' ), $destination );
				break;

			case 'user':
				$user        = $this->api->get_user( $feed['meta']['user'] );
				$destination = ( rgar( $user, 'user' ) ) ? $user['user']['name'] : $feed['meta']['user'];

				return sprintf( esc_html__( 'Direct message to user: %s', 'gravityformsslack' ), $destination );
				break;

			default:
				$channel     = $this->api->get_channel( $feed['meta']['channel'] );
				$destination = ( rgar( $channel, 'channel' ) ) ? $channel['channel']['name'] : $feed['meta']['channel'];

				return sprintf( esc_html__( 'Public Channel: %s', 'gravityformsslack' ), $destination );
				break;

		}

	}





	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process feed.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed  The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 *
	 * @uses   GFFeedAddOn::add_feed_error()
	 * @uses   GFSlack::initialize_api()
	 * @uses   GFSlack::send_invite()
	 * @uses   GFSlack::send_message()
	 */
	public function process_feed( $feed, $entry, $form ) {

		// If Slack instance is not initialized, exit.
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Feed was not processed because API was not initialized.', 'gravityformsslack' ), $feed, $entry, $form );

			return;
		}

		// Process feed based on action.
		switch ( rgars( $feed, 'meta/action' ) ) {

			case 'invite':
				$this->send_invite( $feed, $entry, $form );
				break;

			default:
				$this->send_message( $feed, $entry, $form );
				break;

		}

	}

	/**
	 * Send Slack invite.
	 *
	 * @since  1.4
	 * @access public
	 *
	 * @param array $feed  The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 *
	 * @uses   GFAddOn::get_field_value()
	 * @uses   GFAddOn::get_plugin_setting()
	 * @uses   GFCommon::is_invalid_or_empty_email()
	 * @uses   GFFeedAddOn::add_feed_error()
	 * @uses   GFSlack::can_invite_to_team()
	 * @uses   GF_Slack_API::invite_user()
	 *
	 * @return array
	 */
	public function send_invite( $feed, $entry, $form ) {

		// If we do not have permissions to invite user, exit.
		if ( ! $this->can_invite_to_team() ) {
			$this->add_feed_error( esc_html__( 'Unable to invite user because Slack API user does not have permission to invite users.', 'gravityformsslack' ), $feed, $entry, $form );
			return $entry;
		}

		// Get team name.
		$team_name = $this->get_plugin_setting( 'team_name' );

		// If no team name is provided, exit.
		if ( rgblank( $team_name ) ) {
			$this->add_feed_error( esc_html__( 'Unable to invite user because no team name is defined.', 'gravityformsslack' ), $feed, $entry, $form );
			return $entry;
		}

		// Get email address.
		$email_address = $this->get_field_value( $form, $entry, rgars( $feed, 'meta/email' ) );

		// If an invalid email address is provided, exit.
		if ( GFCommon::is_invalid_or_empty_email( $email_address ) ) {
			$this->add_feed_error( esc_html__( 'Unable to invite user because no email address was provided.', 'gravityformsslack' ), $feed, $entry, $form );
			return $entry;
		}

		// Prepare basic invite parameters.
		$params = array(
			'team'       => $team_name,
			'email'      => $email_address,
			'set_active' => true,
		);

		// Populate first name.
		if ( rgars( $feed, 'meta/first_name' ) ) {

			// Get first name.
			$first_name = $this->get_field_value( $form, $entry, $feed['meta']['first_name'] );

			// If a first name was found, add it to the invite parameters.
			if ( ! rgblank( $first_name ) ) {
				$params['first_name'] = $first_name;
			}

		}

		// Populate last name.
		if ( rgars( $feed, 'meta/last_name' ) ) {

			// Get last name.
			$last_name = $this->get_field_value( $form, $entry, $feed['meta']['last_name'] );

			// If a last name was found, add it to the invite parameters.
			if ( ! rgblank( $last_name ) ) {
				$params['last_name'] = $last_name;
			}

		}

		// Populate channels.
		if ( rgars( $feed, 'meta/channels' ) ) {

			// Get channels.
			$channels = $feed['meta']['channels'];

			// Remove empty selections.
			$channels = array_filter( $channels );

			// If channels were found, convert to string and add to invite parameters.
			if ( ! empty( $channels ) ) {

				// Convert channels to string.
				$channels = implode( ',', $channels );

				// Add channels as invite parameters.
				$params['channels'] = $channels;

			}

		}

		/**
		 * Modify the invite user parameters before the invite is sent to Slack.
		 *
		 * @param array $invite Invite parameters.
		 * @param array $feed   The current feed object.
		 * @param array $entry  The current entry object.
		 * @param array $form   The current form object.
		 */
		$params = gf_apply_filters( array( 'gform_slack_invite', $form['id'] ), $params, $feed, $entry, $form );

		// Log the invite to be sent.
		$this->log_debug( __METHOD__ . '(): Sending invite: ' . print_r( $params, true ) );

		// Send invite.
		$invite = $this->api->invite_user( $params );

		// Log result.
		if ( rgar( $invite, 'ok' ) ) {
			$this->log_debug( __METHOD__ . '(): User was invited.' );
		} else {
			$this->add_feed_error( esc_html__( 'User was not invited.', 'gravityformsslack' ), $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): Invite response: ' . print_r( $invite, true ) );
		}

		return $entry;

	}

	/**
	 * Send Slack message.
	 *
	 * @since  1.4
	 * @access public
	 *
	 * @param array $feed  The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFCommon::replace_variables()
	 * @uses   GFFeedAddOn::add_feed_error()
	 * @uses   GFSlack::open_im_channel()
	 * @uses   GFSlack::prepare_attachments()
	 * @uses   GF_Slack_API::post_message()
	 */
	public function send_message( $feed, $entry, $form ) {

		/**
		 * Change the icon being displayed with the message sent to Slack.
		 * The icon image size should be 48x48.
		 *
		 * @param string $icon_url The current icon being used for the Slack message.
		 * @param array  $feed     The current feed object.
		 * @param array  $entry    The current entry object.
		 * @param array  $form     The current form object.
		 */
		$message_icon = apply_filters( 'gform_slack_icon', $this->get_base_url() . '/images/icon.png', $feed, $entry, $form );

		/**
		 * Change the username the message is being sent by before it is sent to Slack.
		 *
		 * @param string $username The current username being used for the Slack message.
		 * @param array  $feed     The current feed object.
		 * @param array  $entry    The current entry object.
		 * @param array  $form     The current form object.
		 */
		$message_username = gf_apply_filters( array(
			'gform_slack_username',
			$form['id'],
		), 'Gravity Forms', $feed, $entry, $form );

		// Prepare notification array.
		$message = array(
			'icon_url' => $message_icon,
			'text'     => $feed['meta']['message'],
			'username' => $message_username,
		);

		// Add channel based on send_to.
		switch ( $feed['meta']['send_to'] ) {

			case 'group':
				$message['channel'] = $feed['meta']['group'];
				break;

			case 'user':
				$message['channel'] = $this->open_im_channel( $feed['meta']['user'] );
				break;

			default:
				$message['channel'] = $feed['meta']['channel'];
				break;

		}

		// Replace entry URL in message.
		$entry_url       = admin_url( 'admin.php?page=gf_entries&view=entry&id=' . rgar( $form, 'id' ) . '&lid=' . rgar( $entry, 'id' ) );
		$message['text'] = str_replace( '{entry_url}', esc_url_raw( $entry_url ), $message['text'] );

		// Replace merge tags on notification message.
		$message['text'] = GFCommon::replace_variables( $message['text'], $form, $entry, false, false, false, 'text' );

		/**
		 * Enable shortcode processing in the Slack message.
		 *
		 * @param bool  $process_shortcodes Is shortcode processing enabled?
		 * @param array $form               The current form object.
		 * @param array $feed               The current feed object.
		 */
		$process_shortcodes = gf_apply_filters( 'gform_slack_process_message_shortcodes', $form['id'], false, $form, $feed );

		// Process shortcodes.
		if ( $process_shortcodes ) {
			$message['text'] = do_shortcode( $message['text'] );
		}

		// If message is empty, exit.
		if ( rgblank( $message['text'] ) ) {
			$this->add_feed_error( esc_html__( 'Notification message is empty.', 'gravityformsslack' ), $feed, $entry, $form );
			return;
		}

		// Prepare attachments.
		$message = $this->prepare_attachments( $message, $feed, $entry, $form );

		// Post message to channel.
		$this->log_debug( __METHOD__ . '(): Posting message: ' . print_r( $message, true ) );
		$message_channel = $this->api->post_message( $message );

		// Log result.
		if ( rgar( $message_channel, 'ok' ) ) {
			$this->log_debug( __METHOD__ . '(): Message was posted to channel.' );
		} else {
			$this->add_feed_error( esc_html__( 'Message was not posted to channel.', 'gravityformsslack' ), $feed, $entry, $form );
		}

	}

	/**
	 * Prepare attachments for Slack message.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $message Slack message object.
	 * @param array $feed    The feed object to be processed.
	 * @param array $entry   The entry object currently being processed.
	 * @param array $form    The form object currently being processed.
	 *
	 * @uses   GFAddOn::get_field_value()
	 *
	 * @return array
	 */
	public function prepare_attachments( $message, $feed, $entry, $form ) {

		// Initialize files array.
		$files = array();

		// Get files for the selected fields.
		if ( rgars( $feed, 'meta/attachments' ) ) {

			// Loop through attachment fields.
			foreach ( $feed['meta']['attachments'] as $field => $enabled ) {

				// If this field is not enabled for this feed, skip it.
				if ( '0' === $enabled ) {
					continue;
				}

				// Get the field value.
				$field_value = $this->get_field_value( $form, $entry, $field );

				// If no files were uploaded for this field, skip it.
				if ( rgblank( $field_value ) ) {
					continue;
				}

				// Convert attachments string to array.
				$field_value = explode( ',', $field_value );
				$field_value = array_map( 'trim', $field_value );

				// Add field files to files array.
				$files = array_merge( $files, $field_value );

			}

		}

		// If no files were uploaded, return the message object.
		if ( empty( $files ) ) {
			return $message;
		}

		// Add images to attachments array.
		foreach ( $files as $file ) {

			// Get file path.
			$file_path = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $file );

			// Get file details.
			$file_details = wp_check_filetype_and_ext( $file_path, basename( $file_path ) );

			// If file is not an image, skip it.
			if ( strpos( $file_details['type'], 'image' ) === false ) {
				continue;
			}

			// Attach image to message.
			$message['attachments'][] = array(
				'fallback'  => $message['text'],
				'image_url' => $file,
				'text'      => basename( $file_path ),
			);

		}

		// Convert attachments to JSON string.
		if ( rgar( $message, 'attachments' ) ) {
			$message['attachments'] = json_encode( $message['attachments'] );
		}

		return $message;

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Initializes Slack API if credentials are valid.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $auth_token Authentication token.
	 *
	 * @uses   GFAddOn::get_plugin_setting()
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAddOn::log_error()
	 * @uses   GF_Slack_API::auth_test()
	 *
	 * @return bool|null
	 */
	public function initialize_api( $auth_token = null ) {

		// If API is alredy initialized and auth token is not provided, return true.
		if ( ! is_null( $this->api ) && is_null( $auth_token ) ) {
			return true;
		}

		// Load the API library.
		if ( ! class_exists( 'GF_Slack_API' ) ) {
			require_once gf_slack()->get_base_path() . '/includes/class-gf-slack-api.php';
		}

		// Get the OAuth token.
		if ( rgblank( $auth_token ) ) {
			$auth_token = $this->get_plugin_setting( 'auth_token' );
		}

		// If the OAuth token, do not run a validation check.
		if ( rgblank( $auth_token ) ) {
			return null;
		}

		// Log validation step.
		$this->log_debug( __METHOD__ . '(): Validating API Info.' );

		// Setup a new Slack object with the API credentials.
		$slack = new GF_Slack_API( $auth_token );

		// Run an authentication test.
		$auth_test = $slack->auth_test();

		// If authentication test passed, assign API library to instance.
		if ( rgar( $auth_test, 'ok' ) ) {

			// Assign API library to instance.
			$this->api = $slack;

			// Log that authentication test passed.
			$this->log_debug( __METHOD__ . '(): API credentials are valid.' );

			return true;

		} else {

			// Log that authentication test failed.
			$this->log_error( __METHOD__ . '(): API credentials are invalid; ' . $auth_test['error'] );

			return false;

		}

	}

	/**
	 * Open an IM channel with user.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $user User ID.
	 *
	 * @uses   GFSlack::initialize_api()
	 * @uses   GF_Slack_API::open_im()
	 *
	 * @return null|string
	 */
	public function open_im_channel( $user ) {

		// If API is not initialized, return null.
		if ( ! $this->initialize_api() ) {
			return null;
		}

		// Open IM channel.
		$im_channel = $this->api->open_im( $user );

		// Return IM channel ID or user ID.
		return rgar( $im_channel, 'channel' ) ? $im_channel['channel']['id'] : $user;

	}

	/**
	 * Determine if current API user can invite users to team.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @return bool
	 */
	public function can_invite_to_team() {
		return false;
	}

	/**
	 * Get action name for authentication state.
	 *
	 * @since 1.11
	 *
	 * @return string
	 */
	public function get_authentication_state_action() {

		return 'gform_slack_authentication_state';

	}

	/**
	 * Get and save team name when saving plugin settings.
	 *
	 * @since  1.4.1
	 * @access public
	 *
	 * @param string $auth_token Authentication token.
	 *
	 * @uses   GFAddOn::get_plugin_settings()
	 * @uses   GFAddOn::update_plugin_settings()
	 * @uses   GFSlack::initialize_api()
	 * @uses   GF_Slack_API::get_team_info()
	 *
	 * @return string|null
	 */
	public function get_team_name( $auth_token = '' ) {

		// If API is initialized, get team info and save domain.
		if ( $this->initialize_api( $auth_token ) ) {

			// Get team info.
			$team_info = $this->api->get_team_info();

			return rgars( $team_info, 'team/domain' );

		} else {

			return null;

		}

	}

	/**
	 * Clear caches created by this add-on.
	 *
	 * @since 1.14
	 */
	private function clear_plugin_cache() {
		delete_transient( "{$this->_slug}_channels_list" );
		delete_transient( "{$this->_slug}_groups_list" );
	}



	// # UPGRADES ------------------------------------------------------------------------------------------------------

	/**
	 * Run required routines when upgrading from previous versions of Add-On.
	 *
	 * @since  1.4
	 * @access public
	 *
	 * @param string $previous_version Previous version number.
	 *
	 * @uses   GFAddOn::get_plugin_settings()
	 * @uses   GFAddOn::update_plugin_settings()
	 * @uses   GFFeedAddOn::get_feeds()
	 * @uses   GFFeedAddOn::update_feed_meta()
	 * @uses   GFSlack::initialize_api()
	 * @uses   GF_Slack_API::get_team_info()
	 */
	public function upgrade( $previous_version ) {

		// Determine if previous version is before invite to team feature or auto team name detection.
		$previous_is_pre_invite    = ! empty( $previous_version ) && version_compare( $previous_version, '1.4', '<' );
		$previous_is_pre_auto_team = ! empty( $previous_version ) && version_compare( $previous_version, '1.4.1', '<' );

		// If previous version is before invite to team feature, add action to existing feeds.
		if ( $previous_is_pre_invite ) {

			// Get feeds.
			$feeds = $this->get_feeds();

			// If no feeds are found, exit.
			if ( empty( $feeds ) ) {
				return;
			}

			// Loop through feeds.
			foreach ( $feeds as $feed ) {

				// If action is defined, skip feed.
				if ( rgars( $feed, 'meta/action' ) ) {
					continue;
				}

				// Add action.
				$feed['meta']['action'] = 'message';

				// Update feed.
				$this->update_feed_meta( $feed['id'], $feed['meta'] );

			}

		}

		// If previous version is before auto team name detection was added, add team name to plugin settings.
		if ( $previous_is_pre_auto_team ) {

			// If API is not initialized, return.
			if ( ! $this->initialize_api() ) {
				return;
			}

			// Get plugin settings.
			$settings = $this->get_plugin_settings();

			// Get team info.
			$team_info = $this->api->get_team_info();

			// Set team name plugin setting.
			$settings['team_name'] = rgars( $team_info, 'team/domain' );

			// Update plugin settings.
			$this->update_plugin_settings( $settings );

		}

	}

	/**
	 * Get Gravity API URL.
	 *
	 * @since 1.9.2
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	public function get_gravity_api_url( $path = '' ) {
		return ( defined( 'GRAVITY_API_URL' ) ? GRAVITY_API_URL : 'https://gravityapi.com/wp-json/gravityapi/v1' ) . $path;
	}

}
