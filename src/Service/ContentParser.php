<?php

namespace ContentUploader\Service;

use WP_Error;

class ContentParser {
	/**
	 * csv, json, xml
	 */
	public function parse( $filePath ) {
		$extension = pathinfo( $filePath, PATHINFO_EXTENSION );
		switch ( $extension ) {
			case 'csv':
				return $this->parseCsv( $filePath );
			case 'json':
				return $this->parseJson( $filePath );
			case 'xml':
				return $this->parseXml( $filePath );
			default:
				return new WP_Error( 'unsupported_filetype', __( 'Unsupported data file type.', 'content-uploader' ) );
		}
	}

	private function parseCsv( $filePath ) {
		$rows = [];
		if ( ( $handle = fopen( $filePath, 'r' ) ) !== false ) {
			$headers = fgetcsv( $handle, 1000, ';' );
			while ( ( $data = fgetcsv( $handle, 1000, ';' ) ) !== false ) {
				$row = [];
				foreach ( $headers as $index => $header ) {
					$row[ $header ] = $data[ $index ] ?? '';
				}
				$rows[] = $row;
			}
			fclose( $handle );
		} else {
			return new WP_Error( 'csv_open_error', __( 'Failed to open CSV file.', 'content-uploader' ) );
		}

		return $rows;
	}

	private function parseJson( $filePath ) {
		$content = file_get_contents( $filePath );
		$decoded = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_decode_error', __( 'Invalid JSON file.', 'content-uploader' ) );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'json_structure_error', __( 'JSON file does not contain an array.', 'content-uploader' ) );
		}

		return $decoded;
	}

	private function parseXml( $filePath ) {
		libxml_use_internal_errors( true );
		$content = file_get_contents( $filePath );
		$xml     = simplexml_load_string( $content, 'SimpleXMLElement', LIBXML_NOCDATA );
		if ( ! $xml ) {
			return new WP_Error( 'xml_parse_error', __( 'Invalid XML file.', 'content-uploader' ) );
		}

		$json  = json_encode( $xml );
		$array = json_decode( $json, true );

		if ( isset( $array['item'] ) ) {
			if ( isset( $array['item']['title'] ) ) {
				$array['item'] = [ $array['item'] ];
			}

			return $array['item'];
		}

		return new WP_Error( 'xml_structure_error', __( 'XML structure not recognized.', 'content-uploader' ) );
	}
}
