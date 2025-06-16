<?php

namespace ContentUploader\Service;

use ContentUploader\Plugin;

class Logger {
	/**
	 * Log file
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public static function logError( $message ) {
		$logFile          = CONTENT_UPLOADER_PATH . 'logs/import.log';
		$formattedMessage = "[" . date( 'Y-m-d H:i:s' ) . "] ERROR: " . $message . "\n";
		error_log( $formattedMessage, 3, $logFile );
	}

	/**
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public static function logInfo( $message ) {
		$logFile          = CONTENT_UPLOADER_PATH . 'logs/import.log';
		$formattedMessage = "[" . date( 'Y-m-d H:i:s' ) . "] INFO: " . $message . "\n";
		error_log( $formattedMessage, 3, $logFile );
	}
}
