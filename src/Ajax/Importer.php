<?php

namespace ContentUploader\Ajax;

use ContentUploader\Controller\ImportController;

class Importer {
	public function __construct() {
		add_action( 'wp_ajax_content_uploader_import_batch', [
			$this,
			'handle_import_batch'
		] );
		add_action( 'wp_ajax_nopriv_content_uploader_import_batch', [
			$this,
			'handle_import_batch'
		] );
	}

	/**
	 * Handle batch import via AJAX
	 */
	public function handle_import_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions.', 'content-uploader' ) );
		}

		if ( ! isset( $_POST['content_uploader_nonce'] ) || ! wp_verify_nonce( $_POST['content_uploader_nonce'], 'content_uploader_upload' ) ) {
			wp_send_json_error( __( 'Nonce verification failed.', 'content-uploader' ) );
		}

		$batch      = isset( $_POST['batch'] ) ? intval( $_POST['batch'] ) : 0;
		$batch_size = 1;

		$import_data = get_transient( 'content_uploader_import_data' );
		if ( ! $import_data ) {
			wp_send_json_error( __( 'No import data found!', 'content-uploader' ) );
		}

		$entries   = $import_data['entries'];
		$total     = count( $entries );
		$processed = $batch * $batch_size;

		if ( $processed >= $total ) {
			delete_transient( 'content_uploader_import_data' );
			wp_send_json_success( [
				'message'   => __( 'All batches have been processed.', 'content-uploader' ),
				'completed' => true,
			] );
		}

		$current_batch    = array_slice( $entries, $processed, $batch_size );
		$importController = new ImportController();
		$messages         = [];

		foreach ( $current_batch as $entry ) {
			$result = $importController->importSingleEntry( $import_data['extractedPath'], $entry );
			if ( $result['status'] === 'error' ) {
				wp_send_json_error( sprintf( __( 'Batch %d error - %s Total: %d/%d', 'content-uploader' ), $batch + 1, $result['message'], $processed + 1, $total ) );
			}
			$messages[] = sprintf( __( 'Batch %d processed. Total: %d/%d', 'content-uploader' ), $batch + 1, $processed + 1, $total );
		}

		$processed += count( $current_batch );
		$completed = ( $processed >= $total );

		set_transient( 'content_uploader_import_data', [
			'extractedPath' => $import_data['extractedPath'],
			'entries'       => $entries,
		], 3600 );

		wp_send_json_success( [
			'message'   => $messages,
			'processed' => $processed,
			'total'     => $total,
			'completed' => $completed,
		] );
	}
}
