<?php

if ( ! class_exists( 'GFForms') ) {
	die();
}

/**
 * Gravity Forms Slack Add-On API library.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GF_Slack_API {
	/**
	 * Base Slack API URL.
	 *
	 * @since  1.0
	 * @var    string
	 * @access protected
	 */
	protected $api_url = 'https://slack.com/api/';

	/**
	 * The authentication token.
	 *
	 * @since 2.1
	 *
	 * @var string
	 */
	protected $auth_token;

	/**
	 * Initialize Slack API library.
	 *
	 * @since  1.0
	 * @access public
	 * @param  string $auth_token Authentication token.
	 */
	public function __construct( $auth_token ) {

		$this->auth_token = $auth_token;

	}

	/**
	 * Make API request.
	 *
	 * @since  1.0
	 * @access public
	 * @param  string $path Request path.
	 * @param  array  $options Request option.
	 * @param  string $method (default: 'GET') Request method.
	 *
	 * @return array
	 */
	public function make_request( $path, $options = array(), $method = 'GET' ) {

		// Get API URL.
		$api_url = $this->api_url;

		// If team name is defined in request options, add team name to API URL.
		if ( rgar( $options, 'team' ) ) {
			$api_url = str_replace( 'slack.com', $options['team'] . '.slack.com', $api_url );
			unset( $options['team'] );
		}

		// Build base request options string.
		$request_options = '?token='. $this->auth_token;

		// Add options if this is a GET request.
		$request_options .= ( 'GET' === $method && ! empty( $options ) ) ? '&'. http_build_query( $options ) : null;

		// Build request URL.
		$request_url = $api_url . $path . $request_options;

		// Build request arguments.
		$args = array(
			'body'   => 'GET' !== $method ? $options : null,
			'method' => $method,
		);

		// Execute request.
		$response = wp_remote_request( $request_url, $args );

		// If WP_Error, die. Otherwise, return decoded JSON.
		if ( is_wp_error( $response ) ) {

			die( 'Request failed. ' . $response->get_error_message() );

		} else {

			return json_decode( $response['body'], true );

		}

	}

	/**
	 * Revoke authentication token.
	 *
	 * @since  1.7
	 * @access public
	 *
	 * @return array
	 */
	public function auth_revoke() {

		return $this->make_request( 'auth.revoke' );

	}

	/**
	 * Test authentication token.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function auth_test() {

		return $this->make_request( 'auth.test' );

	}

	/**
	 * Get info about a conversation using its ID.
	 *
	 * @param string $id ID of the conversation to retrieve.
	 *
	 * @return array
	 */
	public function get_conversation( $id ) {
		return $this->make_request( 'conversations.info', array( 'channel' => $id ) );
	}

	/**
	 * Get a channel.
	 *
	 * @since  1.0
	 * @access public
	 * @param  string $channel Channel name.
	 *
	 * @return array
	 */
	public function get_channel( $channel ) {
		return $this->get_conversation( $channel );
	}

	/**
	 * Get all channels.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $additional_args Additional arguments to pass to the channels request.
	 *
	 * @return array
	 */
	public function get_channels( $additional_args = array() ) {
		return $this->make_request(
			'conversations.list',
			array_merge(
				array(
					'types' => 'public_channel',
				),
				$additional_args
			)
		);
	}

	/**
	 * Get a group.
	 *
	 * @since  1.0
	 * @access public
	 * @param  string $group Group ID.
	 *
	 * @return array
	 */
	public function get_group( $group ) {
		return $this->get_conversation( $group );
	}

	/**
	 * Get all groups.
	 *
	 * @param array $additional_args Additional arguments to pass to the request.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function get_groups( $additional_args = array() ) {
		return $this->make_request(
			'conversations.list',
			array_merge(
				array(
					'types' => 'mpim,private_channel',
				),
				$additional_args
			)
		);
	}

	/**
	 * Get current team info.
	 *
	 * @since  1.4.1
	 * @access public
	 *
	 * @return array
	 */
	public function get_team_info() {

		return $this->make_request( 'team.info' );

	}

	/**
	 * Get a user.
	 *
	 * @since  1.0
	 * @access public
	 * @param  string $user User ID.
	 *
	 * @return array
	 */
	public function get_user( $user ) {

		return $this->make_request( 'users.info', array( 'user' => $user ) );

	}

	/**
	 * Get all users.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function get_users() {

		return $this->make_request( 'users.list' );

	}

	/**
	 * Invite user to team.
	 *
	 * @since  1.4
	 * @access public
	 *
	 * @param array $params Invite parameters.
	 *
	 * @return array
	 */
	public function invite_user( $params ) {

		return $this->make_request( 'users.admin.invite', $params, 'POST' );

	}

	/**
	 * Open IM channel.
	 *
	 * @since  1.0
	 * @access public
	 * @param  string $user User ID.
	 *
	 * @return array
	 */
	public function open_im( $user ) {

		return $this->make_request( 'users.info', array( 'user' => $user ), 'POST' );

	}

	/**
	 * Post message to channel.
	 *
	 * @since  1.0
	 * @access public
	 * @param  array $message Message details.
	 *
	 * @return array
	 */
	public function post_message( $message ) {

		return $this->make_request( 'chat.postMessage', $message, 'POST' );

	}

}
