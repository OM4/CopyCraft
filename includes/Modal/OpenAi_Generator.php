<?php

namespace OM4\CopyCraft\Modal;

use Exception;
use OM4\CopyCraft\Settings\Data;
use OM4\CopyCraft\Vendor\Tectalic\OpenAi\Client as OpenAiClient;
use OM4\CopyCraft\Vendor\Tectalic\OpenAi\ClientException;
use OM4\CopyCraft\Vendor\Tectalic\OpenAi\Models\Completions\CreateRequest as CompletionsRequest;
use OM4\CopyCraft\Vendor\Tectalic\OpenAi\Models\Moderations\CreateRequest as ModerationsRequest;
use OM4\CopyCraft\Vendor\Tectalic\OpenAi\Models\Completions\CreateResponse as CompletionsResponse;
use OM4\CopyCraft\Vendor\Tectalic\OpenAi\Models\Moderations\CreateResponse as ModerationsResponse;
use WC_Product;

/**
 * Generate a WooCommerce product description using OpenAI's GPT-3.
 */
class OpenAi_Generator {

	/**
	 * Settings Data instance.
	 *
	 * @var Data
	 */
	protected Data $settings;

	/**
	 * OpenAI Client instance.
	 *
	 * @var OpenAiClient
	 */
	protected OpenAiClient $client;

	/**
	 * Constructor.
	 *
	 * @param Data         $settings Settings Data instance.
	 * @param OpenAiClient $client OpenAI Client instance.
	 */
	public function __construct( Data $settings, OpenAiClient $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	/**
	 * Generate a WooCommerce product description using OpenAI's GPT-3.
	 *
	 * @param WC_Product $product The WooCommerce product to generate a description for.
	 *
	 * @return string The generated product description.
	 * @throws Exception When an error occurs.
	 */
	public function generate( WC_Product $product ) {
		$settings = $this->settings->get_settings();
		if ( ! is_array( $settings ) || ! isset( $settings['openai_api_key'] ) ) {
			throw new Exception( __( 'Please enter your OpenAI API key in the CopyCraft settings.', 'copycraft' ) );
		}

		$prompt = $this->build_prompt( $product );

		try {

			// Moderate the prompt to ensure it's safe to use.
			// The moderation result is cached to improve performance.
			$key = 'copycraft_prompt_flagged_' . md5( $prompt );
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
			if ( false === ( $flagged = get_transient( $key ) ) ) {
				// Moderation result not cached.
				// Moderate the prompt, and store the result flag in a transient.

				/**
				 * The Moderations API Call Response model.
				 *
				 * @var ModerationsResponse $result
				 */
				$result = $this->client->moderations()->create(
					new ModerationsRequest( array( 'input' => $prompt ) )
				)->toModel();

				$flagged = (int) $result->results[0]->flagged;
				set_transient( $key, $flagged, DAY_IN_SECONDS );
			}

			if ( $flagged ) {
				throw new Exception(
					__(
						'This product contains content that does not comply with OpenAI\'s content policy. Please edit the product description manually.',
						'copycraft'
					)
				);
			}

			// Generate the product description using the OpenAI completions API.
			$completions = $this->client->completions();
			/**
			 * The Completions API Call Response Model instance.
			 *
			 * @var CompletionsResponse $result
			 */
			$result          = $completions->create(
				new CompletionsRequest(
					array(
						'model'       => 'text-davinci-003',
						'prompt'      => $prompt,
						'temperature' => 0.7,
						'max_tokens'  => 2000,
					)
				)
			)->toModel();
			$new_description = $result->choices[0]->text;

			// Moderate the result.

			/**
			 * The Moderation API Call Response Model instance.
			 *
			 * @var ModerationsResponse $result
			 */
			$result = $this->client->moderations()->create(
				new ModerationsRequest( array( 'input' => $new_description ) )
			)->toModel();

			if ( $result->results[0]->flagged ) {
				throw new Exception(
					__(
						'This generated description contains content that does not comply with OpenAI\'s content policy. Please edit the product description manually.',
						'copycraft'
					)
				);
			}

			return $new_description;
		} catch ( ClientException $e ) {
			// Translators: %s The error message returned by the OpenAI API.
			throw new Exception( sprintf( __( 'OpenAI API Error: %s', 'copycraft' ), $e->getMessage() ), 0, $e );
		} catch ( Exception $e ) {
			throw new Exception( __( 'An unexpected error occurred. Please try again.', 'copycraft' ), 0, $e );
		}
	}

	/**
	 * Build the prompt to use for the OpenAI API.
	 * This is the text that the API will use to generate the product description.
	 *
	 * @param WC_Product $product The WooCommerce Product.
	 *
	 * @return string
	 */
	protected function build_prompt( WC_Product $product ) {
		// TODO: Distinguish between short or long description (based on the button clicked).
		$prompt = "Write a description for a product that has the following:\n\n";

		$prompt .= '- Name: ' . $product->get_name( 'edit' ) . "\n";

		$cats = wc_get_product_category_list( $product->get_id() );
		if ( strlen( $cats ) ) {
			$prompt .= '- Categories: ' . $this->clean_string( $cats ) . "\n";
		}

		if ( ! empty( $product->get_attributes() ) ) {
			$prompt .= '- Attributes: ';
			foreach ( $product->get_attributes() as $attribute ) {
				$prompt .= $attribute->get_name() . ': ' . implode( ', ', $attribute->get_options() ) . '. ';
			}
			$prompt = rtrim( $prompt ) . "\n";
		}

		if ( strlen( $product->get_description( 'edit' ) ) > 0 ) {
			$prompt .= '- Existing Description: ' . $this->clean_string( $product->get_description( 'edit' ) ) . "\n";
		}

		if ( strlen( $product->get_short_description( 'edit' ) ) > 0 ) {
			$prompt .= '- Existing Short Description: ' . $this->clean_string( $product->get_short_description( 'edit' ) ) . "\n";
		}

		return $prompt;
	}

	/**
	 * Clean the string, removing all newlines and HTML characters.
	 *
	 * @param string $input Input string.
	 *
	 * @return string
	 */
	protected function clean_string( $input ) {
		return trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $input ) ) );
	}
}