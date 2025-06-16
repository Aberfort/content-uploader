<?php

namespace ContentUploader\Admin;

class AdminPage {
	/**
	 * Render the admin page
	 */
	public function render() {
		?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Content Uploader', 'content-uploader' ); ?></h1>
			<?php settings_errors( 'content_uploader' ); ?>
            <form id="content-uploader-form" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'content_uploader_upload', 'content_uploader_nonce' ); ?>
                <label for="zip_file"><?php esc_html_e( 'Select .zip archive:', 'content-uploader' ); ?></label>
                <input type="file" name="zip_file" id="zip_file" accept=".zip" required/>
                <br><br>
                <button type="submit" class="button button-primary">
					<?php esc_html_e( 'Upload', 'content-uploader' ); ?>
                </button>
            </form>

            <div id="import-progress" style="display:none; margin-top:20px;">
                <h2><?php esc_html_e( 'Import Progress', 'content-uploader' ); ?></h2>
                <div class="progress-container">
                    <div class="progress-bar" id="progress-bar"></div>
                </div>
                <span id="progress-text">0%</span>
                <p id="current-batch" style="font-weight:bold;"></p>
                <div id="cu-console" style="margin-top:20px; max-height:300px; overflow-y:auto; border:1px solid #ccc; padding:10px;">
					<?php esc_html_e( 'Import logs will appear here...', 'content-uploader' ); ?>
                </div>
                <div id="spinner" class="spinner" style="display:none; margin-top:10px;"></div>
            </div>
        </div>
		<?php

		// Enqueue scripts and styles
		wp_enqueue_script(
			'content-uploader-admin-js',
			CONTENT_UPLOADER_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			CONTENT_UPLOADER_VERSION,
			true
		);
		wp_localize_script( 'content-uploader-admin-js', 'ContentUploaderAjax', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'content_uploader_upload' ),
		] );
		wp_enqueue_style(
			'content-uploader-admin-css',
			CONTENT_UPLOADER_URL . 'assets/css/admin.css',
			[],
			CONTENT_UPLOADER_VERSION
		);
	}
}
