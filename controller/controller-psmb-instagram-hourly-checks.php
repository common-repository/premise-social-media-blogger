<?php
/**
 * Instagram Hourly Checks Controller
 * Proceed to check the Accounts for new items & post them.
 *
 * @package Premise Social Media Blogger
 */

/**
 * Models
 */
// Load Premise_Social_Media_Blogger_Instagram_CPT class.
require_once PSMB_PATH . 'model/model-psmb-instagram-cpt.php';

// Load Premise_Social_Media_Blogger_Instagram class.
require_once PSMB_PATH . 'model/model-psmb-instagram.php';


/**
 * Do logic
 *
 * @see  Premise_Social_Media_Blogger_Instagram_CPT class
 */
$instagram_account_ids = array();

// Proceed to check as many Instagram accounts we have for new photos & post them.
if ( function_exists( 'premise_get_value' ) ) {
	$instagram = premise_get_value( 'psmb_instagram' );

	foreach ( (array) $instagram as $key => $values ) {
		if ( strpos( $key, 'account' ) === 0 ) {
			$instagram_account_ids[] = (string) substr( $key, 7 );
		}
	}
}

foreach ( (array) $instagram_account_ids as $account_id ) {

	$instagram_api_options = @$instagram[ 'api_options' . $account_id ];

	if ( ! $instagram_api_options ) {

		continue;
	}

	$instagram_client = new Premise_Social_Media_Blogger_Instagram( $instagram_api_options );

	// Get saved account.
	$account = $instagram[ 'account' . $account_id ];

	$photos = $instagram_client->get_account_photos();

	$import_photo_ids = array();

	foreach ( (array) $photos as $photo ) {

		$import_photo_ids[] = $photo->id;
	}

	$imported_photo_ids = $import_photo_ids;

	if ( $account['imported_photo_ids'] ) {

		// Eliminate already imported photos!
		$import_photo_ids = array_diff( $import_photo_ids, $account['imported_photo_ids'] );

		// Add photos to imported array.
		$imported_photo_ids = array_merge( $account['imported_photo_ids'], $import_photo_ids );
	}

	// Eliminate old photos!
	$import_photo_ids = array_diff( $import_photo_ids, (array) $account['photo_ids'] );

	if ( $import_photo_ids ) {

		$instagram_cpt = Premise_Social_Media_Blogger_Instagram_CPT::get_instance( (int) $account_id, $account['title'] );

		foreach ( (array) $photos as $photo ) {

			if ( ! in_array( $photo->id, $import_photo_ids ) ) {

				continue;
			}

			// Get photo details.
			$photo_details = $instagram_client->get_photo_details( $photo );

			$post_type = 'post';

			if ( 'psmb_instagram' === $account['post_type'] ) {

				$post_type = 'psmb_instagram_' . (int) $account_id;
			}

			// Insert Instagram post.
			$instagram_cpt->insert_instagram_post( $photo_details, $post_type );
		}

		if ( ! $instagram_client->errors ) {

			$instagram_updated = $instagram;

			// Save photo IDs!
			$instagram_updated[ 'account' . $account_id ]['imported_photo_ids'] = $imported_photo_ids;

			$instagram_updated[ 'account' . $account_id ]['photo_ids'] = array_merge(
				$account['photo_ids'],
				$import_photo_ids
			);

			update_option( 'psmb_instagram', $instagram_updated );
		}
	}
}
