<?php
/**
 * Secure Document Management and Private Storage Service.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocumentService {

	/**
	 * Maximum allowed document size in bytes (10 Megabytes).
	 */
	public const MAX_FILE_SIZE = 10485760; // 10 * 1024 * 1024

	/**
	 * Whitelisted file extensions.
	 */
	public const ALLOWED_EXTENSIONS = array( 'pdf', 'png', 'jpg', 'jpeg' );

	/**
	 * Whitelisted MIME types and their associated standard extensions.
	 */
	public const ALLOWED_MIME_TYPES = array(
		'application/pdf' => array( 'pdf' ),
		'image/jpeg'      => array( 'jpg', 'jpeg' ),
		'image/png'       => array( 'png' ),
	);

	/**
	 * Get the absolute directory path for private, protected documents.
	 *
	 * @return string Absolute directory path.
	 */
	public static function get_protected_dir(): string {
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'] . '/fraud-tenant-protected';

		self::ensure_storage_security( $base_dir );

		return $base_dir;
	}

	/**
	 * Ensure that the protected storage directory exists and has strict server-level access blocks.
	 *
	 * @param string $dir Path to secure.
	 */
	public static function ensure_storage_security( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// 1. Apache .htaccess: Deny all direct web access
		$htaccess_file = rtrim( $dir, '/\\' ) . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# FRAUD TENANT: Strictly protect uploaded private documents\n" .
								"Order Deny,Allow\n" .
								"Deny from all\n" .
								"<IfModule mod_authz_core.c>\n" .
								"    Require all denied\n" .
								"</IfModule>\n" .
								"<Files ~ \"\\.(php|phtml|php3|php4|php5|phps|cgi|pl|exe|sh)$\">\n" .
								"    Require all denied\n" .
								"    Deny from all\n" .
								"</Files>\n";
			@file_put_contents( $htaccess_file, $htaccess_content );
		}

		// 2. Directory traversal prevention via empty index.php
		$index_file = rtrim( $dir, '/\\' ) . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, "<?php\n// Access Denied - Fraud Tenant Protected Storage\nhttp_response_code(403);\nexit('Access denied.');\n" );
		}

		// 3. Microsoft IIS web.config protection
		$web_config = rtrim( $dir, '/\\' ) . '/web.config';
		if ( ! file_exists( $web_config ) ) {
			$iis_content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
						   "<configuration>\n" .
						   "  <system.webServer>\n" .
						   "    <authorization>\n" .
						   "      <deny users=\"*\" />\n" .
						   "    </authorization>\n" .
						   "  </system.webServer>\n" .
						   "</configuration>";
			@file_put_contents( $web_config, $iis_content );
		}
	}

	/**
	 * Validate and store a property ownership verification document securely.
	 *
	 * @param int   $user_id     Owner User ID.
	 * @param int   $property_id Associated Property ID.
	 * @param array $file        Entry from $_FILES.
	 * @return array Result array with success status, document_id or error message.
	 */
	public static function store_ownership_document( int $user_id, int $property_id, array $file ): array {
		global $wpdb;

		// 1. Check for standard PHP upload errors
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_upload_payload',
				'message' => __( 'Invalid file upload payload.', 'fraud-tenant-core' ),
			);
		}

		switch ( $file['error'] ) {
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_NO_FILE:
				return array(
					'success' => false,
					'error'   => 'missing_file',
					'message' => __( 'Please select a property ownership document to upload.', 'fraud-tenant-core' ),
				);
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return array(
					'success' => false,
					'error'   => 'file_too_large',
					'message' => __( 'Uploaded document exceeds the maximum permitted file size.', 'fraud-tenant-core' ),
				);
			default:
				return array(
					'success' => false,
					'error'   => 'upload_system_error',
					'message' => __( 'Failed to receive file upload due to a server error.', 'fraud-tenant-core' ),
				);
		}

		// 2. Validate File Size
		$file_size = (int) $file['size'];
		if ( $file_size <= 0 || $file_size > self::MAX_FILE_SIZE ) {
			return array(
				'success' => false,
				'error'   => 'file_size_limit_exceeded',
				'message' => sprintf(
					/* translators: %d: maximum file size in megabytes */
					__( 'Document size must be greater than 0 and less than %d MB.', 'fraud-tenant-core' ),
					(int) ( self::MAX_FILE_SIZE / ( 1024 * 1024 ) )
				),
			);
		}

		// 3. Validate Original File Extension
		$raw_name  = (string) $file['name'];
		$extension = strtolower( pathinfo( $raw_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_file_extension',
				'message' => __( 'Invalid document file type. Only PDF, PNG, and JPEG documents are accepted.', 'fraud-tenant-core' ),
			);
		}

		// 4. Validate Actual MIME Type via PHP Fileinfo
		$tmp_path = (string) $file['tmp_name'];
		if ( ! is_uploaded_file( $tmp_path ) && ! defined( 'FT_DOING_TESTS' ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_temp_file',
				'message' => __( 'Upload verification failed: file is not a valid upload.', 'fraud-tenant-core' ),
			);
		}

		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = $finfo ? finfo_file( $finfo, $tmp_path ) : '';
		if ( $finfo ) {
			finfo_close( $finfo );
		}

		if ( ! array_key_exists( $mime_type, self::ALLOWED_MIME_TYPES ) ) {
			return array(
				'success' => false,
				'error'   => 'disallowed_mime_type',
				'message' => __( 'The uploaded file format is not supported. Please upload a genuine PDF, PNG, or JPEG file.', 'fraud-tenant-core' ),
			);
		}

		// Validate that extension matches mime type
		if ( ! in_array( $extension, self::ALLOWED_MIME_TYPES[ $mime_type ], true ) ) {
			return array(
				'success' => false,
				'error'   => 'extension_mime_mismatch',
				'message' => __( 'Document file extension does not match the actual file contents.', 'fraud-tenant-core' ),
			);
		}

		// 5. Generate Randomized, Unguessable Storage Destination
		$protected_base = self::get_protected_dir();
		$sub_dir        = 'ownership_docs/' . gmdate( 'Y/m' );
		$target_dir     = rtrim( $protected_base, '/\\' ) . '/' . $sub_dir;

		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$random_token   = bin2hex( random_bytes( 16 ) );
		$stored_name    = 'doc_' . $random_token . '_' . time() . '.' . $extension;
		$target_path    = $target_dir . '/' . $stored_name;
		$stored_relpath = $sub_dir . '/' . $stored_name;

		// 6. Move File to Protected Storage
		$moved = false;
		if ( defined( 'FT_DOING_TESTS' ) ) {
			$moved = @copy( $tmp_path, $target_path );
		} else {
			$moved = @move_uploaded_file( $tmp_path, $target_path );
		}

		if ( ! $moved ) {
			return array(
				'success' => false,
				'error'   => 'storage_move_failed',
				'message' => __( 'Failed to securely store document on server. Please try again.', 'fraud-tenant-core' ),
			);
		}

		// Ensure permissions prevent execution
		@chmod( $target_path, 0640 );

		// 7. Insert Record into wp_ft_documents
		$now            = current_time( 'mysql', true );
		$clean_origname = sanitize_file_name( $raw_name );

		$inserted = $wpdb->insert(
			$wpdb->prefix . Constants::TABLE_DOCUMENTS,
			array(
				'user_id'           => $user_id,
				'object_type'       => 'property_ownership',
				'object_id'         => $property_id,
				'file_path'         => $stored_relpath,
				'original_name'     => $clean_origname,
				'file_size'         => $file_size,
				'mime_type'         => $mime_type,
				'access_permission' => 'private',
				'uploaded_at'       => $now,
			),
			array( '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			// Cleanup stored file if database insertion failed
			@unlink( $target_path );
			return array(
				'success' => false,
				'error'   => 'db_record_failed',
				'message' => __( 'Could not record document in database.', 'fraud-tenant-core' ),
			);
		}

		$document_id = (int) $wpdb->insert_id;

		return array(
			'success'       => true,
			'document_id'   => $document_id,
			'original_name' => $clean_origname,
			'file_size'     => $file_size,
			'mime_type'     => $mime_type,
			'file_path'     => $stored_relpath,
		);
	}

	/**
	 * Delete a document and its stored file safely.
	 *
	 * @param int $document_id Document ID.
	 * @return bool True on success.
	 */
	public static function delete_document( int $document_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $document_id ) );

		if ( ! $row ) {
			return false;
		}

		$protected_base = self::get_protected_dir();
		$abs_path       = rtrim( $protected_base, '/\\' ) . '/' . ltrim( $row->file_path, '/\\' );

		if ( file_exists( $abs_path ) ) {
			@unlink( $abs_path );
		}

		$deleted = $wpdb->delete( $table, array( 'id' => $document_id ), array( '%d' ) );

		return false !== $deleted;
	}

	/**
	 * Retrieve document metadata by document ID.
	 *
	 * @param int $document_id Document ID.
	 * @return object|null Document record or null.
	 */
	public static function get_document( int $document_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $document_id ) );
		return $row ?: null;
	}
}
