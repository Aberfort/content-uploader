<?php

namespace ContentUploader\Service;

use WP_Error;

class FileValidator {
	private $dataFiles = [];

	public function validate( $extractedPath ) {
		$extractedPath = rtrim($extractedPath, '/\\');
		$imagesDir = $extractedPath . '/images';
		$filesDir  = $extractedPath . '/files';

		if ( ! is_dir( $imagesDir ) ) {
			return new WP_Error( 'missing_images_dir', __( 'Missing images folder in the archive.', 'content-uploader' ) );
		}

		if ( ! is_dir( $filesDir ) ) {
			return new WP_Error( 'missing_files_dir', __( 'Missing files folder in the archive.', 'content-uploader' ) );
		}

		$rootFiles = glob( $extractedPath . '/*.{csv,json,xml}', GLOB_BRACE );
		if ( empty( $rootFiles ) ) {
			return new WP_Error( 'missing_data_files', __( 'No CSV/JSON/XML files found in the archive root.', 'content-uploader' ) );
		}

		$this->dataFiles = $rootFiles;

		return true;
	}

	public function getDataFiles() {
		return $this->dataFiles;
	}
}
