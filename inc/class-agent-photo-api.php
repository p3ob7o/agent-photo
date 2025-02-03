<?php
/**
 * Agent Photo REST API Class
 *
 * Handles API requests to process images.
 *
 * @package Agent_Photo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Agent_Photo_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'agent-photo/v1',
			'/process',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'process_image' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	public function permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Processes the image.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function process_image( $request ) {
		// Log the incoming request
		error_log('Agent Photo: Incoming request parameters: ' . print_r($request->get_params(), true));

		$image_id = $request->get_param( 'imageId' );
		$image_url = $request->get_param( 'imageUrl' );

		if ( empty( $image_id ) || empty( $image_url ) ) {
			error_log('Agent Photo: Missing image ID or URL');
			return new WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Image ID or URL is missing', 'agent-photo' ),
				'debug' => array(
					'imageId' => $image_id,
					'imageUrl' => $image_url
				)
			), 400 );
		}

		// Retrieve the API key
		$api_key = get_option( 'agent_photo_api_key' );
		error_log('Agent Photo: API key length - ' . strlen($api_key)); // Don't log the actual key
		if ( empty( $api_key ) ) {
			error_log('Agent Photo: API key is not set');
			return new WP_REST_Response( array(
				'success' => false,
				'message' => __( 'API key is not set', 'agent-photo' ),
			), 400 );
		}

		// Process the image using OpenAI API
		$result = Agent_Photo_Processor::process_image( $image_url, $api_key );

		if ( is_wp_error( $result ) ) {
			error_log('Agent Photo: Processing error - ' . $result->get_error_message());
			return new WP_REST_Response( array(
				'success' => false,
				'message' => $result->get_error_message(),
				'debug' => $result->get_error_data()
			), 400 );
		}

		// Update metadata
		Agent_Photo_Processor::process_metadata( $image_id, $result );

		return new WP_REST_Response( $result, 200 );
	}
}

new Agent_Photo_API(); 