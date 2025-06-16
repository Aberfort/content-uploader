<?php

namespace ContentUploader\Service;

class WpmlService {
	public function linkTranslations( $postId, $lang, $slug, $postType ) {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$trid = null;

		if ( $lang !== 'uk' ) {
			$originalPostId = $this->getPostIdBySlugAndLanguage( $slug, 'uk', $postType );
			if ( $originalPostId ) {
				$foundTrid = $this->getTridForElement( $originalPostId, 'post_' . $postType );
				if ( $foundTrid ) {
					$trid = $foundTrid;
				}
			}
		}

		do_action( 'wpml_set_element_language_details', [
			'element_id'           => $postId,
			'element_type'         => 'post_' . $postType,
			'trid'                 => $trid,
			'language_code'        => $lang,
			'source_language_code' => ( $lang === 'uk' ? null : 'uk' ),
		] );
	}

	/**
	 * Find slug
	 */
	private function getPostIdBySlugAndLanguage( $slug, $lang, $postType ) {
		global $wpdb;
		$postsTable        = $wpdb->posts;
		$translationsTable = $wpdb->prefix . 'icl_translations';
		$elementType       = 'post_' . $postType;

		$sql = $wpdb->prepare( "
            SELECT p.ID
            FROM $postsTable p
            INNER JOIN $translationsTable icl ON icl.element_id = p.ID
            WHERE p.post_name = %s
              AND p.post_type = %s
              AND p.post_status NOT IN ('trash','auto-draft')
              AND icl.language_code = %s
              AND icl.element_type = %s
            LIMIT 1
        ", $slug, $postType, $lang, $elementType );

		return $wpdb->get_var( $sql ) ?: null;
	}

	/**
	 * Get trid
	 */
	private function getTridForElement( $elementId, $elementType ) {
		global $wpdb;
		$translationsTable = $wpdb->prefix . 'icl_translations';

		$sql = $wpdb->prepare( "
            SELECT trid
            FROM $translationsTable
            WHERE element_id = %d
              AND element_type = %s
            LIMIT 1
        ", $elementId, $elementType );

		return $wpdb->get_var( $sql ) ?: null;
	}
}
