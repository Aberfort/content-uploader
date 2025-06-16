<?php

namespace ContentUploader\Service;

use WP_Error;

class ArchiveExtractor {
	/**
	 *  Archive
	 *
	 * @param string $sourcePath
	 * @param string $destination
	 *
	 * @return string|WP_Error
	 */
	public function extract( $sourcePath, $destination ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_missing', __( 'PHP ZipArchive is not installed.', 'content-uploader' ) );
		}

		$zip = new \ZipArchive();
		if ( $zip->open( $sourcePath ) === true ) {
			$zip->extractTo( $destination );
			$zip->close();

			return $destination;
		}

		return new WP_Error( 'zip_open_error', __( 'Failed to open the ZIP file.', 'content-uploader' ) );
	}
}
