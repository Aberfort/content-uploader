<?php

namespace ContentUploader\Ajax;

use ContentUploader\Controller\ImportController;

class Uploader {
	public function __construct() {
		add_action( 'wp_ajax_content_uploader_upload', [
			$this,
			'handle_upload'
		] );
		add_action( 'wp_ajax_nopriv_content_uploader_upload', [
			$this,
			'handle_upload'
		] );
	}

	/**
	 * Handle file upload via AJAX
	 */
	public function handle_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions.', 'content-uploader' ) );
		}

		if ( ! isset( $_POST['content_uploader_nonce'] ) || ! wp_verify_nonce( $_POST['content_uploader_nonce'], 'content_uploader_upload' ) ) {
			wp_send_json_error( __( 'Nonce verification failed.', 'content-uploader' ) );
		}

		if ( ! isset( $_FILES['zip_file'] ) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( __( 'Error uploading file.', 'content-uploader' ) );
		}

		$importController = new ImportController();
		$importResult     = $importController->processUpload( $_FILES['zip_file'] );

		if ( isset( $importResult['status'] ) && $importResult['status'] === 'error' ) {
			wp_send_json_error( $importResult['message'] );
		}

		if ( isset( $importResult['status'] ) && $importResult['status'] === 'warning' ) {
			wp_send_json_success( [
				'status'        => 'warning',
				'message'       => $importResult['message'],
				'total'         => isset( $importResult['total_entries'] ) ? $importResult['total_entries'] : 0,
				'need_reupload' => true
			] );
		}

		wp_send_json_success( [
			'status'        => 'success',
			'message'       => __( 'File uploaded successfully. Starting import...', 'content-uploader' ),
			'total'         => isset( $importResult['total_entries'] ) ? $importResult['total_entries'] : 0,
			'need_reupload' => false,
		] );
	}
}
