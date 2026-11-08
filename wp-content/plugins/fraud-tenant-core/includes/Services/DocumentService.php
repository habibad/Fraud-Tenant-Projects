<?php
/**
 * Secure Document Management and Private Storage Service.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\AuditLogger;
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
	 * Allows overriding storage path outside webroot via FT_PROTECTED_STORAGE_DIR.
	 *
	 * @return string Absolute directory path.
	 */
	public static function get_protected_dir(): string {
		if ( defined( 'FT_PROTECTED_STORAGE_DIR' ) && ! empty( constant( 'FT_PROTECTED_STORAGE_DIR' ) ) ) {
			$base_dir = rtrim( constant( 'FT_PROTECTED_STORAGE_DIR' ), '/\\' );
		} else {
			$upload_dir = wp_upload_dir();
			$base_dir   = $upload_dir['basedir'] . '/fraud-tenant-protected';
		}

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
	 * Document types: storage sub-directory, stored-name prefix, and whether tenant identity data
	 * protection (match key + encryption; ComplianceService::is_data_gate_open) must be ready.
	 */
	protected const STORAGE_TYPES = array(
		'property_ownership' => array( 'dir' => 'ownership_docs', 'prefix' => 'doc_', 'gated' => false, 'per_user' => false ),
		'incident_evidence'  => array( 'dir' => 'incident_evidence', 'prefix' => 'inc_doc_', 'gated' => true, 'per_user' => false ),
		'dispute_evidence'   => array( 'dir' => 'dispute_evidence', 'prefix' => 'dsp_doc_', 'gated' => true, 'per_user' => true ),
		'identity_claim'     => array( 'dir' => 'identity_claims', 'prefix' => 'idc_doc_', 'gated' => true, 'per_user' => true ),
	);

	/**
	 * Validate and store a private document (single implementation for every document type).
	 *
	 * Checks: upload error, size (0 < size <= 10 MB), extension allowlist, real MIME type via fileinfo,
	 * extension/MIME agreement. Stores under an unguessable name in protected storage (web access
	 * denied), chmod 0640, and records metadata in wp_ft_documents.
	 *
	 * @param int    $user_id     Uploading user.
	 * @param string $object_type One of STORAGE_TYPES.
	 * @param int    $object_id   Parent object ID.
	 * @param array  $file        Entry from $_FILES.
	 * @return array Result array with success status, document_id or error message.
	 */
	public static function store_private_document( int $user_id, string $object_type, int $object_id, array $file ): array {
		global $wpdb;

		$type = self::STORAGE_TYPES[ $object_type ] ?? null;
		if ( null === $type ) {
			return array( 'success' => false, 'error' => 'invalid_document_type', 'message' => __( 'Unsupported document type.', 'fraud-tenant-core' ) );
		}
		if ( $type['gated'] && ! ComplianceService::is_data_gate_open() ) {
			return array(
				'success' => false,
				'error'   => 'submission_locked_blk08',
				'message' => __( 'Document upload is unavailable until tenant data protection (match key and encryption key) is configured.', 'fraud-tenant-core' ),
			);
		}
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return array( 'success' => false, 'error' => 'invalid_upload_payload', 'message' => __( 'Invalid file upload payload.', 'fraud-tenant-core' ) );
		}
		switch ( $file['error'] ) {
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_NO_FILE:
				return array( 'success' => false, 'error' => 'missing_file', 'message' => __( 'Please select a document to upload.', 'fraud-tenant-core' ) );
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return array( 'success' => false, 'error' => 'file_too_large', 'message' => __( 'Uploaded document exceeds the maximum permitted file size.', 'fraud-tenant-core' ) );
			default:
				return array( 'success' => false, 'error' => 'upload_system_error', 'message' => __( 'Failed to receive file upload due to a server error.', 'fraud-tenant-core' ) );
		}

		$file_size = (int) $file['size'];
		if ( $file_size <= 0 || $file_size > self::MAX_FILE_SIZE ) {
			return array(
				'success' => false,
				'error'   => 'file_size_limit_exceeded',
				/* translators: %d: maximum file size in megabytes */
				'message' => sprintf( __( 'Document size must be greater than 0 and less than %d MB.', 'fraud-tenant-core' ), (int) ( self::MAX_FILE_SIZE / ( 1024 * 1024 ) ) ),
			);
		}

		$raw_name  = (string) $file['name'];
		$extension = strtolower( pathinfo( $raw_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) ) {
			return array( 'success' => false, 'error' => 'invalid_file_extension', 'message' => __( 'Invalid document file type. Only PDF, PNG, and JPEG documents are accepted.', 'fraud-tenant-core' ) );
		}

		$tmp_path = (string) $file['tmp_name'];
		if ( ! is_uploaded_file( $tmp_path ) && ! defined( 'FT_DOING_TESTS' ) ) {
			return array( 'success' => false, 'error' => 'invalid_temp_file', 'message' => __( 'Upload verification failed: file is not a valid upload.', 'fraud-tenant-core' ) );
		}

		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = $finfo ? (string) finfo_file( $finfo, $tmp_path ) : '';
		if ( $finfo ) {
			finfo_close( $finfo );
		}
		if ( ! array_key_exists( $mime_type, self::ALLOWED_MIME_TYPES ) ) {
			return array( 'success' => false, 'error' => 'disallowed_mime_type', 'message' => __( 'The uploaded file format is not supported. Please upload a genuine PDF, PNG, or JPEG file.', 'fraud-tenant-core' ) );
		}
		if ( ! in_array( $extension, self::ALLOWED_MIME_TYPES[ $mime_type ], true ) ) {
			return array( 'success' => false, 'error' => 'extension_mime_mismatch', 'message' => __( 'Document file extension does not match the actual file contents.', 'fraud-tenant-core' ) );
		}

		$sub_dir    = $type['dir'] . '/' . ( $type['per_user'] ? $user_id . '/' : '' ) . gmdate( 'Y/m' );
		$target_dir = rtrim( self::get_protected_dir(), '/\\' ) . '/' . $sub_dir;
		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}
		$stored_name    = $type['prefix'] . bin2hex( random_bytes( 16 ) ) . '_' . time() . '.' . $extension;
		$target_path    = $target_dir . '/' . $stored_name;
		$stored_relpath = $sub_dir . '/' . $stored_name;

		$moved = defined( 'FT_DOING_TESTS' ) ? @copy( $tmp_path, $target_path ) : @move_uploaded_file( $tmp_path, $target_path );
		if ( ! $moved ) {
			return array( 'success' => false, 'error' => 'storage_move_failed', 'message' => __( 'Failed to securely store document on server. Please try again.', 'fraud-tenant-core' ) );
		}
		@chmod( $target_path, 0640 );

		$clean_origname = sanitize_file_name( $raw_name );
		$inserted       = $wpdb->insert(
			$wpdb->prefix . Constants::TABLE_DOCUMENTS,
			array(
				'user_id'           => $user_id,
				'object_type'       => $object_type,
				'object_id'         => $object_id,
				'file_path'         => $stored_relpath,
				'original_name'     => $clean_origname,
				'file_size'         => $file_size,
				'mime_type'         => $mime_type,
				'access_permission' => 'private',
				'review_status'     => 'pending',
				'uploaded_at'       => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			@unlink( $target_path );
			return array( 'success' => false, 'error' => 'db_record_failed', 'message' => __( 'Could not record document in database.', 'fraud-tenant-core' ) );
		}

		return array(
			'success'       => true,
			'document_id'   => (int) $wpdb->insert_id,
			'original_name' => $clean_origname,
			'file_size'     => $file_size,
			'mime_type'     => $mime_type,
			'file_path'     => $stored_relpath,
		);
	}

	/**
	 * Store a property ownership verification document.
	 *
	 * @param int   $user_id     Owner User ID.
	 * @param int   $property_id Property ID.
	 * @param array $file        Entry from $_FILES.
	 * @return array
	 */
	public static function store_ownership_document( int $user_id, int $property_id, array $file ): array {
		return self::store_private_document( $user_id, 'property_ownership', $property_id, $file );
	}

	/**
	 * Store an incident evidence document.
	 *
	 * @param int   $user_id     Owner User ID.
	 * @param int   $incident_id Incident Report ID.
	 * @param array $file        Entry from $_FILES.
	 * @return array
	 */
	public static function store_incident_document( int $user_id, int $incident_id, array $file ): array {
		return self::store_private_document( $user_id, 'incident_evidence', $incident_id, $file );
	}

	/**
	 * Store a tenant dispute evidence document.
	 *
	 * @param int   $user_id    Tenant User ID.
	 * @param int   $dispute_id Dispute ID.
	 * @param array $file       Entry from $_FILES.
	 * @return array
	 */
	public static function store_dispute_document( int $user_id, int $dispute_id, array $file ): array {
		return self::store_private_document( $user_id, 'dispute_evidence', $dispute_id, $file );
	}

	/**
	 * Store a tenant identity-claim verification document (government ID or lease naming the tenant).
	 *
	 * @param int   $user_id  Tenant User ID.
	 * @param int   $claim_id Identity claim ID.
	 * @param array $file     Entry from $_FILES.
	 * @return array
	 */
	public static function store_claim_document( int $user_id, int $claim_id, array $file ): array {
		return self::store_private_document( $user_id, 'identity_claim', $claim_id, $file );
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

	/**
	 * Securely stream a private document to an authorized administrator or document owner.
	 *
	 * Enforces capability or ownership check, database lookup, path canonicalization,
	 * strict directory confinement, audit logging, and safe HTTP headers.
	 *
	 * @param int $document_id        Document ID in wp_ft_documents.
	 * @param int $requesting_user_id Current authenticated user ID.
	 * @return void
	 */
	public static function stream_protected_document( int $document_id, int $requesting_user_id ): void {
		if ( $requesting_user_id <= 0 ) {
			wp_die( esc_html__( 'Unauthorized access. Authentication required.', 'fraud-tenant-core' ), 403 );
		}

		// 1. Resolve document record from database
		$doc = self::get_document( $document_id );
		if ( ! $doc || 'private' !== $doc->access_permission ) {
			AuditLogger::log(
				'document.access_denied',
				'document',
				$document_id,
				array( 'reason' => 'document_not_found_or_not_private' ),
				$requesting_user_id
			);
			wp_die( esc_html__( 'Document record not found or inaccessible.', 'fraud-tenant-core' ), 404 );
		}

		// 2. Authorization Check (Reviewer capability OR verified Document Owner)
		$is_reviewer = false;
		if ( 'property_ownership' === $doc->object_type ) {
			// For property ownership documents, require dedicated property review/verification capabilities or super-admin
			$is_reviewer = user_can( $requesting_user_id, Capabilities::REVIEW_PROPERTIES ) ||
						   user_can( $requesting_user_id, Capabilities::VERIFY_PROPERTIES ) ||
						   user_can( $requesting_user_id, 'manage_options' );
		} elseif ( 'incident_evidence' === $doc->object_type ) {
			// BLK-08 Security Gate: Incident evidence streaming strictly fails closed in production
			if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
				AuditLogger::log(
					'document.access_denied',
					'document',
					$document_id,
					array( 'reason' => 'incident_review_locked_blk08' ),
					$requesting_user_id
				);
				wp_die( esc_html__( 'Incident evidence access is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ), 423 );
			}

			// For incident evidence documents, require incident review/decision capabilities or super-admin
			$is_reviewer = user_can( $requesting_user_id, Capabilities::REVIEW_INCIDENTS ) ||
						   user_can( $requesting_user_id, Capabilities::DECIDE_INCIDENTS ) ||
						   user_can( $requesting_user_id, Capabilities::REVIEW_INCIDENT_REPORTS ) ||
						   user_can( $requesting_user_id, Capabilities::APPROVE_INCIDENT_REPORTS ) ||
						   user_can( $requesting_user_id, 'manage_options' );
		} elseif ( 'dispute_evidence' === $doc->object_type ) {
			// BLK-08 Security Gate: Dispute evidence streaming strictly fails closed in production
			if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
				AuditLogger::log(
					'document.access_denied',
					'document',
					$document_id,
					array( 'reason' => 'dispute_review_locked_blk08' ),
					$requesting_user_id
				);
				wp_die( esc_html__( 'Dispute evidence access is locked in production mode pending sensitive PII encryption infrastructure (BLK-08).', 'fraud-tenant-core' ), 423 );
			}

			// For dispute evidence documents, require dispute review/resolution capabilities or super-admin
			$is_reviewer = user_can( $requesting_user_id, Capabilities::REVIEW_TENANT_DISPUTES ) ||
						   user_can( $requesting_user_id, Capabilities::RESOLVE_TENANT_DISPUTES ) ||
						   user_can( $requesting_user_id, 'manage_options' );
		} elseif ( 'identity_claim' === $doc->object_type ) {
			// Identity-claim verification documents: identity reviewers only (plus the uploader below).
			if ( ! \FraudTenant\Services\ComplianceService::is_data_gate_open() ) {
				wp_die( esc_html__( 'Identity documents are unavailable until tenant data protection is configured.', 'fraud-tenant-core' ), 423 );
			}
			$is_reviewer = user_can( $requesting_user_id, Capabilities::MANAGE_TENANT_IDENTITIES ) ||
						   user_can( $requesting_user_id, 'manage_options' );
		} else {
			// For other documents (e.g. user registration evidence), require registration review capabilities or super-admin
			$is_reviewer = user_can( $requesting_user_id, Capabilities::REVIEW_USER_REGISTRATIONS ) ||
						   user_can( $requesting_user_id, Capabilities::APPROVE_USER_REGISTRATIONS ) ||
						   user_can( $requesting_user_id, 'manage_options' );
		}

		$is_owner = ( (int) $doc->user_id === (int) $requesting_user_id );

		// Strictly cross-verify property ownership linkage for property documents
		if ( $is_owner && 'property_ownership' === $doc->object_type && $doc->object_id > 0 ) {
			$property = PropertyService::get_property( (int) $doc->object_id );
			if ( ! $property || (int) $property->owner_id !== (int) $requesting_user_id ) {
				$is_owner = false;
			}
		}

		// Strictly cross-verify incident report ownership linkage for incident evidence documents
		if ( $is_owner && 'incident_evidence' === $doc->object_type && $doc->object_id > 0 ) {
			$incident = IncidentService::get_incident_report( (int) $doc->object_id, $requesting_user_id );
			if ( ! $incident || (int) $incident->owner_id !== (int) $requesting_user_id ) {
				$is_owner = false;
			}
		}

		// Strictly cross-verify dispute ownership linkage for dispute evidence documents
		if ( 'dispute_evidence' === $doc->object_type ) {
			if ( $is_owner ) {
				if ( (int) $doc->object_id <= 0 ) {
					// Unlinked dispute evidence documents cannot be streamed
					$is_owner = false;
				} else {
					global $wpdb;
					$dsp_table = $wpdb->prefix . Constants::TABLE_TENANT_DISPUTES;
					$dispute   = $wpdb->get_row( $wpdb->prepare( "SELECT tenant_id FROM {$dsp_table} WHERE id = %d", (int) $doc->object_id ) );
					if ( ! $dispute || (int) $dispute->tenant_id !== (int) $requesting_user_id || (int) $doc->user_id !== (int) $dispute->tenant_id ) {
						$is_owner = false;
					}
				}
			}
		}

		// Identity-claim documents may be re-opened by the claimant who uploaded them.
		if ( $is_owner && 'identity_claim' === $doc->object_type ) {
			global $wpdb;
			$claim_owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}" . Constants::TABLE_IDENTITY_CLAIMS . ' WHERE id = %d', (int) $doc->object_id ) );
			$is_owner    = $claim_owner === (int) $requesting_user_id;
		}

		// Owner-submitted incident evidence is private to the reporting owner and reviewers. It is never
		// streamed to the subject tenant or to screening owners (the report narrative is disclosed instead).
		$is_associated_tenant = false;

		if ( ! $is_reviewer && ! $is_owner ) {
			AuditLogger::log(
				'document.access_denied',
				'document',
				$document_id,
				array(
					'reason'   => 'unauthorized_actor',
					'owner_id' => (int) $doc->user_id,
				),
				$requesting_user_id
			);
			wp_die( esc_html__( 'Unauthorized access. You do not have permission to view this document.', 'fraud-tenant-core' ), 403 );
		}

		// 3. Strict Allowed MIME Types
		$allowed_mimes = array( 'application/pdf', 'image/jpeg', 'image/png' );
		if ( ! in_array( $doc->mime_type, $allowed_mimes, true ) ) {
			AuditLogger::log(
				'document.access_denied',
				'document',
				$document_id,
				array( 'reason' => 'disallowed_mime_type', 'mime' => $doc->mime_type ),
				$requesting_user_id
			);
			wp_die( esc_html__( 'Disallowed document format.', 'fraud-tenant-core' ), 403 );
		}

		// 4. Confinement and Canonicalization Check
		$base_dir        = self::get_protected_dir();
		$canonical_base  = realpath( $base_dir );
		if ( false === $canonical_base ) {
			wp_die( esc_html__( 'Protected storage directory is unavailable.', 'fraud-tenant-core' ), 500 );
		}

		$normalized_base = rtrim( $canonical_base, '/\\' ) . DIRECTORY_SEPARATOR;
		$relative_path   = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, ltrim( $doc->file_path, '/\\' ) );
		$target_path     = $normalized_base . $relative_path;
		$canonical_file  = realpath( $target_path );

		// Validate path exists, is a regular file, and is strictly within canonical base directory (path traversal & sibling-prefix guard)
		if (
			false === $canonical_file ||
			! is_file( $canonical_file ) ||
			strpos( $canonical_file, $normalized_base ) !== 0
		) {
			AuditLogger::log(
				'document.access_denied',
				'document',
				$document_id,
				array( 'reason' => 'path_traversal_or_missing_file' ),
				$requesting_user_id
			);
			wp_die( esc_html__( 'File does not exist or invalid path traversal detected.', 'fraud-tenant-core' ), 404 );
		}

		// 5. Audit Log Authorized Access
		$event_name = $is_reviewer ? 'admin.document_viewed' : ( $is_associated_tenant ? 'tenant.document_viewed' : ( 'dispute_evidence' === $doc->object_type ? 'tenant.dispute_evidence_viewed' : 'owner.document_viewed' ) );
		$actor_role = $is_reviewer ? 'reviewer' : ( $is_associated_tenant ? 'tenant' : ( 'dispute_evidence' === $doc->object_type ? 'disputant_tenant' : 'owner' ) );
		AuditLogger::log(
			$event_name,
			'document',
			$document_id,
			array(
				'document_id' => $document_id,
				'target_user' => (int) $doc->user_id,
				'object_type' => $doc->object_type,
				'mime_type'   => $doc->mime_type,
				'actor_role'  => $actor_role,
			),
			$requesting_user_id
		);

		// 6. Safe Streaming Output
		// If HTTP headers have already been sent, file streaming cannot enforce mandatory security headers.
		// Abort safely before readfile() to prevent exposing protected files without attachment and nosniff protection.
		$headers_already_sent = headers_sent() && ( ! defined( 'FT_DOING_TESTS' ) || ! empty( $GLOBALS['ft_simulate_headers_sent'] ) );

		if ( $headers_already_sent ) {
			AuditLogger::log(
				'document.streaming_aborted_headers_sent',
				'document',
				$document_id,
				array(
					'document_id' => $document_id,
					'reason'      => 'headers_already_sent',
				),
				$requesting_user_id
			);
			wp_die( esc_html__( 'Unable to securely stream document. Security headers cannot be applied.', 'fraud-tenant-core' ), 500 );
		}

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			while ( ob_get_level() ) {
				ob_end_clean();
			}
		}

		$filename = sanitize_file_name( $doc->original_name ?: 'document' );
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $doc->mime_type );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . (string) filesize( $canonical_file ) );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		readfile( $canonical_file );

		if ( ! defined( 'FT_DOING_TESTS' ) ) {
			exit;
		}
	}

	/**
	 * Retrieve all evidence documents attached to a specific tenant dispute.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return array Array of document rows.
	 */
	public static function get_dispute_documents( int $dispute_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
		$docs  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, original_name, file_size, mime_type, review_status, uploaded_at 
				 FROM {$table} 
				 WHERE object_type = 'dispute_evidence' AND object_id = %d 
				 ORDER BY id ASC",
				$dispute_id
			)
		) ?: array();

		foreach ( $docs as &$doc ) {
			$doc->id            = (int) $doc->id;
			$doc->file_size_fmt = size_format( (int) $doc->file_size );
			$doc->stream_nonce  = wp_create_nonce( 'ft_view_doc_' . $doc->id );
			$doc->view_url      = add_query_arg(
				array(
					'action'      => 'ft_view_document',
					'document_id' => $doc->id,
					'_wpnonce'    => $doc->stream_nonce,
				),
				admin_url( 'admin-post.php' )
			);
		}
		unset( $doc );

		return $docs;
	}
}

