<?php

namespace ContentUploader\Controller;

use ContentUploader\Service\ArchiveExtractor;
use ContentUploader\Service\FileValidator;
use ContentUploader\Service\ContentParser;
use ContentUploader\Service\WpmlService;
use ContentUploader\Service\Logger;
use WP_Error;

class ImportController {

	/**
	 * Process the uploaded archive.
	 *
	 * @param array $file
	 *
	 * @return array
	 */
	public function processUpload( $file ) {
		$tmpName  = $file['tmp_name'];
		$fileName = $file['name'];

		// Check if the file is a ZIP archive.
		if ( pathinfo( $fileName, PATHINFO_EXTENSION ) !== 'zip' ) {
			Logger::logError( "Файл не є ZIP: $fileName" );

			return [
				'status'  => 'error',
				'message' => __( 'File is not a ZIP archive.', 'content-uploader' ),
			];
		}

		$uploadDir       = wp_upload_dir();
		$destinationPath = trailingslashit( $uploadDir['basedir'] ) . 'content-uploader/' . uniqid( 'archive_', false );

		if ( ! wp_mkdir_p( $destinationPath ) ) {
			Logger::logError( "Не вдалося створити директорію: $destinationPath" );

			return [
				'status'  => 'error',
				'message' => __( 'Failed to create extraction directory.', 'content-uploader' ),
			];
		}

		// Unpack the archive.
		$extractor     = new ArchiveExtractor();
		$extractedPath = $extractor->extract( $tmpName, $destinationPath );
		if ( is_wp_error( $extractedPath ) ) {
			Logger::logError( "Помилка розпакування: " . $extractedPath->get_error_message() );

			return [
				'status'  => 'error',
				'message' => $extractedPath->get_error_message(),
			];
		}

		// Validate the extracted data.
		$validator        = new FileValidator();
		$validationResult = $validator->validate( $extractedPath );
		if ( is_wp_error( $validationResult ) ) {
			Logger::logError( "Помилка валідації: " . $validationResult->get_error_message() );

			return [
				'status'  => 'error',
				'message' => $validationResult->get_error_message(),
			];
		}

		// Get data files from the archive root.
		$dataFiles = $validator->getDataFiles( $extractedPath );
		if ( empty( $dataFiles ) ) {
			Logger::logError( "Не знайдено файлів з даними" );

			return [
				'status'  => 'error',
				'message' => __( 'No data files found in archive.', 'content-uploader' ),
			];
		}

		// Parse data from files.
		$parser              = new ContentParser();
		$entries             = [];
		$postTypesToRegister = [];
		foreach ( $dataFiles as $dataFile ) {
			$parsedData = $parser->parse( $dataFile );
			if ( ! is_wp_error( $parsedData ) ) {
				foreach ( $parsedData as $row ) {
					$entries[] = $row;
					// Normalize the post_type value.
					$ptRaw = sanitize_text_field( $row['post_type'] ?? '' );
					if ( $ptRaw ) {
						if ( in_array( strtolower( $ptRaw ), [
							'post',
							'page'
						] ) ) {
							$pt = strtolower( $ptRaw );
						} else {
							$pt = sanitize_title( $ptRaw );
						}
						// If the post type is not registered, add it.
						if ( ! post_type_exists( $pt ) ) {
							$postTypesToRegister[] = $pt;
						}
					}
				}
			}
		}

		// Check maximum entries limit.
		$max_entries   = 1000;
		$total_entries = count( $entries );
		if ( $total_entries > $max_entries ) {
			Logger::logError( "Кількість записів ($total_entries) перевищує допустиму межу ($max_entries)." );

			return [
				'status'  => 'error',
				'message' => sprintf(
					__( 'Too many entries (%d). Maximum allowed is %d.', 'content-uploader' ),
					$total_entries,
					$max_entries
				),
			];
		}

		// Process custom post types.
		$postTypesToRegister = array_unique( $postTypesToRegister );
		$currentCPT          = get_option( 'content_uploader_cpt_types', [] );
		$newCPT              = [];
		foreach ( $postTypesToRegister as $pt ) {
			if ( ! in_array( $pt, $currentCPT ) && ! post_type_exists( $pt ) ) {
				$newCPT[] = $pt;
			}
		}

		// Filter new CPT: if for a given CPT there is more than one language,
		// then it's considered multilingual and a warning is needed.
		$finalNewCPT = [];
		foreach ( $newCPT as $pt ) {
			$languages = [];
			foreach ( $entries as $entry ) {
				if ( ! in_array( strtolower( $entry['post_type'] ), [
					'post',
					'page'
				] ) ) {
					$entryPt = sanitize_title( $entry['post_type'] );
					if ( $entryPt === $pt ) {
						$languages[] = sanitize_text_field( $entry['lang'] );
					}
				}
			}
			$languages = array_unique( $languages );
			if ( count( $languages ) > 1 ) {
				$finalNewCPT[] = $pt;
			}
		}

		if ( ! empty( $finalNewCPT ) ) {
			// Multilingual new CPT found – return warning.
			$mergedCPT = array_unique( array_merge( $currentCPT, $finalNewCPT ) );
			update_option( 'content_uploader_cpt_types', $mergedCPT );

			$cpt_list = implode( ', ', $finalNewCPT );
			$message  = sprintf(
				__( 'New Custom Post Types registered: %s. Please enable translation for these types by navigating to <a href="%s" target="_blank">WPML → Settings → Post Types Translation</a>, and then re-upload the archive to complete the import.', 'content-uploader' ),
				esc_html( $cpt_list ),
				admin_url( 'admin.php?page=wpml-settings-translate-types' )
			);

			add_settings_error( 'content_uploader', 'cpt_registered', $message, 'warning' );

			return [
				'status'        => 'warning',
				'message'       => $message,
				'total_entries' => $total_entries,
			];
		} elseif ( ! empty( $newCPT ) ) {
			// For new CPT that are monolingual, automatically register them.
			foreach ( $newCPT as $pt ) {
				if ( ! post_type_exists( $pt ) ) {
					register_post_type( $pt, [
						'label'              => ucfirst( str_replace( '_', ' ', $pt ) ),
						'public'             => true,
						'show_ui'            => true,
						'has_archive'        => true,
						'supports'           => [
							'title',
							'editor',
							'thumbnail'
						],
						'publicly_queryable' => true,
					] );
				}
			}
			$mergedCPT = array_unique( array_merge( $currentCPT, $newCPT ) );
			update_option( 'content_uploader_cpt_types', $mergedCPT );
		}

		// Save import data in a transient if everything is OK.
		set_transient( 'content_uploader_import_data', [
			'extractedPath' => $extractedPath,
			'entries'       => $entries,
		], 3600 );

		return [
			'status'        => 'success',
			'message'       => __( 'File uploaded successfully. Starting import...', 'content-uploader' ),
			'total_entries' => $total_entries,
		];
	}

	/**
	 * Import a single entry.
	 *
	 * @param string $extractedPath
	 * @param array  $entry
	 *
	 * @return array
	 */
	public function importSingleEntry( $extractedPath, $entry ) {
		$required = [ 'post_type', 'file' ];
		$missing  = [];
		foreach ( $required as $field ) {
			if ( empty( $entry[ $field ] ) ) {
				$missing[] = $field;
			}
		}
		if ( ! empty( $missing ) ) {
			$message = "Missing required fields: " . implode( ', ', $missing ) . " in entry: " . print_r( $entry, true );
			Logger::logError( $message );

			return [
				'status'  => 'error',
				'message' => sprintf( __( 'Missing required fields: %s.', 'content-uploader' ), implode( ', ', $missing ) )
			];
		}

		$textFilePath = trailingslashit( $extractedPath ) . 'files/' . sanitize_file_name( $entry['file'] );
		$parsedText   = $this->parseTextFileByMarkers( $textFilePath );

		if ( empty( $entry['title'] ) && ! empty( $parsedText['TITLE'] ) ) {
			$entry['title'] = $parsedText['TITLE'];
		}
		if ( empty( $entry['meta_title'] ) && ! empty( $parsedText['META_TITLE'] ) ) {
			$entry['meta_title'] = $parsedText['META_TITLE'];
		}
		if ( empty( $entry['meta_description'] ) && ! empty( $parsedText['META_DESCRIPTION'] ) ) {
			$entry['meta_description'] = $parsedText['META_DESCRIPTION'];
		}
		$entry['fallback_content'] = isset( $parsedText['CONTENT'] ) ? $parsedText['CONTENT'] : '';

		if ( empty( $entry['title'] ) ) {
			$message = "Title is missing even after fallback parsing in entry: " . print_r( $entry, true );
			Logger::logError( $message );

			return [
				'status'  => 'error',
				'message' => __( 'Title is required.', 'content-uploader' )
			];
		}

		if ( $entry['title'] === '/' ) {
			$frontPageId = get_option( 'page_on_front' );

			if ( $frontPageId ) {
				$page = get_post( $frontPageId );
				if ( $page ) {
					$entry['title'] = $page->post_title;
					$slug = $page->post_name;
					$postType = 'page';
					Logger::logInfo( "Оновлюємо існуючу головну сторінку (ID: $frontPageId, slug: $slug)" );
				} else {
					$entry['title'] = 'Головна';
					$slug           = sanitize_title( 'Головна' );
					$postType       = 'page';
					$entry['set_as_front'] = true;
					Logger::logInfo( "Створюємо нову головну сторінку, оскільки page_on_front некоректний." );
				}
			} else {
				$entry['title'] = 'Головна';
				$slug           = sanitize_title( 'Головна' );
				$postType       = 'page';
				$entry['set_as_front'] = true;
				Logger::logInfo( "Створюємо нову сторінку з назвою 'Головна'." );
			}
		} else {
			$slug = sanitize_title( $entry['title'] );

			if ( in_array( strtolower( $entry['post_type'] ), [ 'post', 'page' ] ) ) {
				$postType = strtolower( $entry['post_type'] );
			} else {
				$postType = sanitize_title( $entry['post_type'] );
			}
		}

		$lang        = sanitize_text_field( $entry['lang'] );
		$title       = sanitize_text_field( $entry['title'] );
		$description = sanitize_textarea_field( $entry['meta_description'] );

		$postId = $this->twoStepImport( $slug, $lang, $title, $description, $postType, $extractedPath, $entry );
		if ( ! $postId ) {
			$message = "Failed to create or update post for entry: " . print_r( $entry, true );
			Logger::logError( $message );

			return [
				'status'  => 'error',
				'message' => __( 'Failed to create or update post.', 'content-uploader' )
			];
		}

		if ( ! empty( $entry['fallback_content'] ) ) {
			wp_update_post( [
				'ID'           => $postId,
				'post_content' => wp_kses_post( $entry['fallback_content'] ),
			] );
		}

		if ( ! empty( $entry['set_as_front'] ) && $postType === 'page' ) {
			update_option( 'page_on_front', $postId );
			update_option( 'show_on_front', 'page' );
			Logger::logInfo( "Новостворену сторінку (ID: $postId) призначено як головну (page_on_front)." );
		}

		$message = sprintf( __( 'Successfully imported post (ID: %d).', 'content-uploader' ), $postId );
		Logger::logInfo( $message );

		return [
			'status'  => 'success',
			'message' => $message
		];
	}

	/**
	 * Create or update a post.
	 *
	 * @param string $slug
	 * @param string $lang
	 * @param string $title
	 * @param string $description
	 * @param string $postType
	 * @param string $extractedPath
	 * @param array  $entry
	 *
	 * @return int|false
	 */
	private function twoStepImport( $slug, $lang, $title, $description, $postType, $extractedPath, $entry ) {
		// If this is a non-standard post type and it doesn't exist, try to register it dynamically.
		if ( ! in_array( $postType, [
				'post',
				'page'
			] ) && ! post_type_exists( $postType ) ) {
			register_post_type( $postType, [
				'label'              => ucfirst( str_replace( '_', ' ', $postType ) ),
				'public'             => true,
				'show_ui'            => true,
				'has_archive'        => true,
				'supports'           => [ 'title', 'editor', 'thumbnail' ],
				'publicly_queryable' => true,
			] );
			flush_rewrite_rules( false );
			if ( ! post_type_exists( $postType ) ) {
				Logger::logError( "Custom post type $postType not registered even after dynamic registration. Please enable translation for this CPT." );

				return false;
			}
		}

		if ( function_exists( 'icl_object_id' ) && $lang !== 'uk' ) {
			do_action( 'wpml_switch_language', $lang );
		}

		$existingPostId = $this->getPostIdBySlugAndLanguage( $slug, $lang, $postType );
		if ( $existingPostId ) {
			$postArr = [
				'ID'           => $existingPostId,
				'post_title'   => wp_strip_all_tags( $title ),
				'post_content' => '',
				'post_type'    => $postType,
				'post_status'  => 'publish',
			];
			wp_update_post( $postArr );
			Logger::logInfo( "Оновлено існуючий пост (ID: $existingPostId)" );
			$postId = $existingPostId;
		} else {
			$tempSlug = $slug . '-' . $lang . '-temp-' . wp_rand();
			$postArr  = [
				'post_title'  => wp_strip_all_tags( $title ),
				'post_name'   => sanitize_title( $tempSlug ),
				'post_status' => 'publish',
				'post_type'   => $postType,
			];
			$postId   = wp_insert_post( $postArr );
			if ( is_wp_error( $postId ) ) {
				Logger::logError( "Помилка створення поста: " . $postId->get_error_message() );

				return false;
			}
			Logger::logInfo( "Створено пост із tempSlug (ID: $postId)" );
		}

		if ( $postId && ! is_wp_error( $postId ) ) {
			$wpmlService = new WpmlService();
			$wpmlService->linkTranslations( $postId, $lang, $slug, $postType );
		}

		$this->updateContentAndImage( $postId, $extractedPath, $entry );

		if ( empty( $existingPostId ) && $postId && ! is_wp_error( $postId ) ) {
			wp_update_post( [
				'ID'        => $postId,
				'post_name' => sanitize_title( $slug ),
			] );
			Logger::logInfo( "Оновлено slug => $slug (ID: $postId)" );
			clean_post_cache( $postId );
		}

		if ( function_exists( 'icl_object_id' ) && $lang !== 'uk' ) {
			do_action( 'wpml_switch_language', 'uk' );
		}

		return $postId;
	}

	// Parsing text file
	private function parseTextFileByMarkers( $textFilePath ) {
		if ( ! file_exists( $textFilePath ) ) {
			Logger::logError( "Text file not found: $textFilePath" );

			return [];
		}
		$rawText = file_get_contents( $textFilePath );

		$pattern = '/\[(TITLE|META_TITLE|META_DESCRIPTION|CONTENT)\]/';
		$parts   = preg_split( $pattern, $rawText, - 1, PREG_SPLIT_DELIM_CAPTURE );

		$sections = [];
		for ( $i = 1; $i < count( $parts ); $i += 2 ) {
			$key              = strtoupper( trim( $parts[ $i ] ) );
			$value            = isset( $parts[ $i + 1 ] ) ? trim( $parts[ $i + 1 ] ) : '';
			$sections[ $key ] = $value;
		}

		return $sections;
	}

	/**
	 * Update content and featured image, and set Yoast meta data.
	 *
	 * @param int    $postId
	 * @param string $extractedPath
	 * @param array  $entry
	 *
	 * @return void
	 */
	private function updateContentAndImage( $postId, $extractedPath, $entry ) {
		// Update post content.
		if ( ! empty( $entry['file'] ) ) {
			$filePath = trailingslashit( $extractedPath ) . 'files/' . sanitize_file_name( $entry['file'] );
			if ( file_exists( $filePath ) ) {
				// Якщо текстовий файл існує, використовуємо його вміст як контент.
				$content = file_get_contents( $filePath );
			} elseif ( ! empty( $entry['fallback_content'] ) ) {
				// Інакше використовуємо fallback content, отриманий раніше.
				$content = $entry['fallback_content'];
			} else {
				$content = '';
			}
			wp_update_post( [
				'ID'           => $postId,
				'post_content' => wp_kses_post( $content ),
			] );
		}

		// Update featured image.
		if ( ! empty( $entry['image'] ) ) {
			$imagePath = trailingslashit( $extractedPath ) . 'images/' . $entry['image'];
			if ( file_exists( $imagePath ) ) {
				$attachId = $this->importFeaturedImage( $imagePath, $postId );
				if ( is_wp_error( $attachId ) ) {
					Logger::logError( "Помилка імпорту зображення: " . $attachId->get_error_message() );
				}
			} else {
				Logger::logError( "Зображення не знайдено: $imagePath" );
			}
		}

		// Update Yoast meta fields.
		if ( ! empty( $entry['meta_title'] ) ) {
			update_post_meta( $postId, '_yoast_wpseo_title', sanitize_text_field( $entry['meta_title'] ) );
		} else {
			update_post_meta( $postId, '_yoast_wpseo_title', sanitize_text_field( $entry['title'] ) );
		}
		if ( ! empty( $entry['meta_description'] ) ) {
			update_post_meta( $postId, '_yoast_wpseo_metadesc', sanitize_textarea_field( $entry['meta_description'] ) );
		}
	}

	/**
	 * Get post ID by slug and language.
	 *
	 * @param string $slug
	 * @param string $lang
	 * @param string $postType
	 *
	 * @return int|null
	 */
	private function getPostIdBySlugAndLanguage( $slug, $lang, $postType ) {
		$args  = [
			'post_type'        => $postType,
			'name'             => $slug,
			'posts_per_page'   => 1,
			'post_status'      => [
				'publish',
				'draft',
				'pending',
				'future',
				'private'
			],
			'suppress_filters' => false,
			'lang'             => $lang,
		];
		$posts = get_posts( $args );
		if ( ! empty( $posts ) ) {
			return $posts[0]->ID;
		}

		return null;
	}

	/**
	 * Import featured image for a post.
	 *
	 * @param string $filePath
	 * @param int    $postId
	 *
	 * @return int|WP_Error
	 */
	private function importFeaturedImage( $filePath, $postId ) {
		$filetype     = wp_check_filetype( basename( $filePath ), null );
		$uploadDir    = wp_upload_dir();
		$destFileName = trailingslashit( $uploadDir['path'] ) . basename( $filePath );

		if ( ! @copy( $filePath, $destFileName ) ) {
			return new WP_Error( 'copy_failed', __( 'Failed to copy image to uploads directory.', 'content-uploader' ) );
		}

		$attachment = [
			'guid'           => trailingslashit( $uploadDir['url'] ) . basename( $filePath ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( basename( $filePath ) ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		];

		$attachId = wp_insert_attachment( $attachment, $destFileName, $postId );
		if ( is_wp_error( $attachId ) ) {
			return new WP_Error( 'attachment_failed', __( 'Failed to create attachment.', 'content-uploader' ) );
		}

		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attachData = wp_generate_attachment_metadata( $attachId, $destFileName );
		wp_update_attachment_metadata( $attachId, $attachData );

		set_post_thumbnail( $postId, $attachId );

		return $attachId;
	}
}
