<?php
/**
 * Agent Photo Processor Class
 *
 * Processes images using OpenAI API and handles metadata updates.
 *
 * @package Agent_Photo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Agent_Photo_Processor {

	/**
	 * The OpenAI API endpoint for vision tasks.
	 */
	const OPENAI_API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * The system message for the AI agent.
	 */
	const SYSTEM_MESSAGE = "You are Agent Photo, an assistant designed for black and white street photography analysis.

Upon receiving a photo upload, automatically provide the following:

1) **ALT text description**: Focus on accuracy and conciseness for screen reader accessibility. Highlight key elements and the overall feel of the image.

2) **Reflective legend**: Craft a poetic narrative that captures the scene's essence, considering mood, setting, and subjects.

3) **Creative title options**: Generate three title suggestions that reflect the photo's themes and subjects.

Your expertise is in interpreting street scenes with an emphasis on mood, setting, and subjects. In cases where a photo deviates from this specialty, such as color photos or non-street scenes, strive to adapt and offer service based on available content.

Avoid requesting additional details or clarification; rely solely on the visual content of the uploaded photo.

# Output Format

Provide output as a JSON containing a list of key/value pairs with the following keys:

- \"altText\": [A concise ALT text description.]
- \"legend\": [A brief and engaging reflective legend.]
- \"title1\": [First creative title option.]
- \"title2\": [Second creative title option.]
- \"title3\": [Third creative title option.]

# Notes

- Focus on retaining the unique aspects of street photography, particularly mood and ambiance.
- Adapt responses for non-black and white or non-street photos as best as possible.";

	/**
	 * Process an image through OpenAI's API.
	 *
	 * @param string $image_url The URL of the image to process.
	 * @param string $api_key   The OpenAI API key.
	 * @return array|WP_Error   The processed data or error.
	 */
	public static function process_image( $image_url, $api_key ) {
		$headers = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		);

		$body = array(
			'model' => 'gpt-4o',
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => self::SYSTEM_MESSAGE
				),
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => "Please analyze this image and provide your response in JSON format as instructed."
						),
						array(
							'type' => 'image_url',
							'image_url' => array(
								'url' => $image_url,
								'detail' => 'high'
							)
						),
					),
				),
			),
			'response_format' => array('type' => 'json_object'),
			'temperature' => 1.2,
			'max_tokens' => 1000,
		);

		// Log the request to OpenAI (safely)
		error_log('Agent Photo: OpenAI request - ' . wp_json_encode([
			'url' => self::OPENAI_API_ENDPOINT,
			'headers' => array(
				'Content-Type' => $headers['Content-Type'],
				'Authorization' => 'Bearer sk-...', // Hide full key
			),
			'body' => array_merge($body, ['api_key' => 'sk-...']) // Hide full key
		]));

		$response = wp_remote_post(
			self::OPENAI_API_ENDPOINT,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log('Agent Photo: WordPress error - ' . $response->get_error_message());
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);
		
		// Log the OpenAI response
		error_log('Agent Photo: OpenAI response code - ' . $response_code);
		error_log('Agent Photo: OpenAI response body - ' . $response_body);

		if ($response_code !== 200) {
			error_log('Agent Photo: Non-200 response code from OpenAI');
			return new WP_Error(
				'openai_error',
				__( 'Error from OpenAI API: ' . $response_body, 'agent-photo' )
			);
		}

		return self::parse_ai_response( $response_body );
	}

	/**
	 * Parse the AI response into structured data.
	 *
	 * @param string $content The raw response content from OpenAI.
	 * @return array         The parsed response data.
	 */
	private static function parse_ai_response( $content ) {
		// First decode the entire response
		$response = json_decode($content, true);
		error_log('Agent Photo: Full response - ' . print_r($response, true));
		
		if (empty($response['choices'][0]['message']['content'])) {
			error_log('Agent Photo: Empty content in OpenAI response');
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response structure from OpenAI API', 'agent-photo' )
			);
		}

		// Then decode the JSON content from the message
		$message_content = $response['choices'][0]['message']['content'];
		error_log('Agent Photo: Message content before JSON decode - ' . $message_content);
		
		$data = json_decode($message_content, true);
		error_log('Agent Photo: JSON decode error - ' . json_last_error_msg());
		error_log('Agent Photo: Parsed data - ' . print_r($data, true));
		
		if (json_last_error() !== JSON_ERROR_NONE) {
			error_log('Agent Photo: JSON parsing error - ' . json_last_error_msg());
			return new WP_Error(
				'json_parse_error',
				__( 'Error parsing OpenAI response', 'agent-photo' )
			);
		}

		// Check for required keys
		$required_keys = ['altText', 'legend', 'title1', 'title2', 'title3'];
		foreach ($required_keys as $key) {
			if (!isset($data[$key])) {
				error_log('Agent Photo: Missing required key - ' . $key);
				return new WP_Error(
					'missing_key',
					__( 'Missing required key in response: ' . $key, 'agent-photo' )
				);
			}
		}

		return array(
			'success'  => true,
			'altText'  => $data['altText'],
			'legend'   => $data['legend'],
			'title1'   => $data['title1'],
			'title2'   => $data['title2'],
			'title3'   => $data['title3'],
		);
	}

	/**
	 * Sets the alt text and inserts the legend below the image.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $data          The data returned from the API.
	 */
	public static function process_metadata( $attachment_id, $data ) {
		if ( ! isset( $data['altText'], $data['legend'] ) ) {
			return;
		}

		// Update alt text in attachment meta.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $data['altText'] ) );

		// Store the legend as custom meta if needed, or inject it into content.
		// Here, we're storing legend as a custom field.
		update_post_meta( $attachment_id, 'agent_photo_legend', sanitize_text_field( $data['legend'] ) );
	}
} 