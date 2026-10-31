<?php
/**
 * Property Management Service.
 *
 * Handles multi-property creation, editing, archival, document association,
 * and data-isolation for approved Property Owners.
 *
 * @package FraudTenant\Services
 */

namespace FraudTenant\Services;

use FraudTenant\Auth\Capabilities;
use FraudTenant\Services\AuditLogger;
use FraudTenant\Services\DocumentService;
use FraudTenant\Utils\Constants;
use FraudTenant\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PropertyService {

	/**
	 * Allowed property types.
	 */
	public const ALLOWED_PROPERTY_TYPES = array(
		'single_family' => 'Single Family',
		'multi_family'  => 'Multi-Family',
		'apartment'     => 'Apartment',
		'condo'         => 'Condominium',
		'townhouse'     => 'Townhouse',
		'commercial'    => 'Commercial',
		'other'         => 'Other Residential',
	);

	/**
	 * Create a new property for an approved property owner.
	 *
	 * @param int        $owner_id Owner user ID.
	 * @param array      $data     Submitted property data.
	 * @param array|null $doc_file Optional ownership document upload payload from $_FILES.
	 * @return array Result array with success status, property_id, and message.
	 */
	public static function create_property( int $owner_id, array $data, ?array $doc_file = null ): array {
		global $wpdb;

		// 1. Authorization check
		if ( ! self::can_manage_properties( $owner_id ) ) {
			return array(
				'success' => false,
				'error'   => 'unauthorized',
				'message' => __( 'You do not have permission to register properties.', 'fraud-tenant-core' ),
			);
		}

		// 2. Validate and sanitize fields
		$street = isset( $data['address_street'] ) ? Sanitizer::text( $data['address_street'] ) : '';
		$unit   = isset( $data['unit_number'] ) ? Sanitizer::text( $data['unit_number'] ) : '';
		$city   = isset( $data['city'] ) ? Sanitizer::text( $data['city'] ) : '';
		$state  = isset( $data['state'] ) ? strtoupper( Sanitizer::text( $data['state'] ) ) : '';
		$zip    = isset( $data['postal_code'] ) ? Sanitizer::zip( $data['postal_code'] ) : '';
		$title  = isset( $data['title'] ) ? Sanitizer::text( $data['title'] ) : '';
		$type   = isset( $data['property_type'] ) ? sanitize_key( $data['property_type'] ) : 'single_family';
		$units  = isset( $data['units_count'] ) ? max( 1, (int) $data['units_count'] ) : 1;

		if ( empty( $street ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_street',
				'message' => __( 'Street address is required.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $city ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_city',
				'message' => __( 'City is required.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $state ) || strlen( $state ) < 2 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_state',
				'message' => __( 'A valid 2-letter state code is required.', 'fraud-tenant-core' ),
			);
		}

		if ( empty( $zip ) || ! preg_match( '/^\d{5}(-\d{4})?$/', $zip ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_zip',
				'message' => __( 'A valid 5-digit ZIP code is required.', 'fraud-tenant-core' ),
			);
		}

		if ( ! array_key_exists( $type, self::ALLOWED_PROPERTY_TYPES ) ) {
			$type = 'single_family';
		}

		if ( empty( $title ) ) {
			$title = trim( $street . ( $unit ? ' #' . $unit : '' ) . ', ' . $city . ', ' . $state );
		}

		// 3. Optional Document pre-validation if provided
		$has_doc = $doc_file && isset( $doc_file['error'] ) && UPLOAD_ERR_NO_FILE !== $doc_file['error'];
		if ( $has_doc ) {
			$doc_ext = strtolower( pathinfo( (string) $doc_file['name'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $doc_ext, DocumentService::ALLOWED_EXTENSIONS, true ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_document_type',
					'message' => __( 'Invalid document file type. Only PDF, JPG, and PNG files are accepted.', 'fraud-tenant-core' ),
				);
			}
		}

		// 4. Insert into database
		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$now        = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$prop_table,
			array(
				'owner_id'       => $owner_id,
				'title'          => $title,
				'address_street' => $street,
				'unit_number'    => $unit ?: null,
				'city'           => $city,
				'state'          => $state,
				'postal_code'    => $zip,
				'property_type'  => $type,
				'units_count'    => $units,
				'status'         => Constants::PROP_STATUS_PENDING,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return array(
				'success' => false,
				'error'   => 'db_insert_failed',
				'message' => __( 'Failed to register property due to a database error.', 'fraud-tenant-core' ),
			);
		}

		$property_id = (int) $wpdb->insert_id;

		// 5. Store ownership document if uploaded
		$doc_id = null;
		if ( $has_doc ) {
			$doc_res = DocumentService::store_ownership_document( $owner_id, $property_id, $doc_file );
			if ( ! $doc_res['success'] ) {
				// Rollback property record
				$wpdb->delete( $prop_table, array( 'id' => $property_id ), array( '%d' ) );
				return array(
					'success' => false,
					'error'   => $doc_res['error'],
					'message' => $doc_res['message'],
				);
			}
			$doc_id = $doc_res['document_id'];
		}

		// 6. Log append-only application-level audit event
		AuditLogger::log(
			'owner.property_created',
			'property',
			$property_id,
			array(
				'property_id'   => $property_id,
				'owner_id'      => $owner_id,
				'title'         => $title,
				'address'       => $street,
				'city'          => $city,
				'state'         => $state,
				'zip'           => $zip,
				'property_type' => $type,
				'units_count'   => $units,
				'has_document'  => $has_doc,
				'document_id'   => $doc_id,
			),
			$owner_id
		);

		return array(
			'success'     => true,
			'property_id' => $property_id,
			'document_id' => $doc_id,
			'message'     => __( 'Property added successfully. It is now pending administrative ownership verification.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Retrieve all properties for an owner, strictly scoped to their user ID.
	 *
	 * @param int   $owner_id         Owner user ID.
	 * @param bool  $include_archived Whether to return archived properties.
	 * @param array $args             Optional filter args (e.g. status).
	 * @return array Array of property objects with document counts.
	 */
	public static function get_owner_properties( int $owner_id, bool $include_archived = false, array $args = array() ): array {
		global $wpdb;

		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;
		$doc_table  = $wpdb->prefix . Constants::TABLE_DOCUMENTS;

		$where_clauses = array( 'p.owner_id = %d' );
		$params        = array( $owner_id );

		$filter_status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';

		if ( 'archived' === $filter_status || true === $include_archived ) {
			if ( 'archived' === $filter_status ) {
				$where_clauses[] = "(p.status = 'archived' OR p.archived_at IS NOT NULL)";
			}
			// If include_archived is true and no specific filter, allow all
		} else {
			$where_clauses[] = "p.status != 'archived' AND p.archived_at IS NULL";
			if ( ! empty( $filter_status ) ) {
				$where_clauses[] = 'p.status = %s';
				$params[]        = $filter_status;
			}
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$sql = "SELECT p.*, 
					   COUNT(d.id) AS document_count,
					   MAX(d.uploaded_at) AS latest_doc_uploaded_at,
					   MAX(d.review_status) AS latest_doc_status
				FROM {$prop_table} p
				LEFT JOIN {$doc_table} d ON p.id = d.object_id AND d.object_type = 'property_ownership'
				WHERE {$where_sql}
				GROUP BY p.id
				ORDER BY p.id DESC";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as &$prop ) {
				$prop->id             = (int) $prop->id;
				$prop->owner_id       = (int) $prop->owner_id;
				$prop->units_count    = (int) $prop->units_count;
				$prop->document_count = (int) $prop->document_count;
			}
			unset( $prop );
		}

		return $results ?: array();
	}

	/**
	 * Retrieve a single property record, strictly validating ownership if owner_id is provided.
	 *
	 * @param int      $property_id Target property ID.
	 * @param int|null $owner_id    Optional owner ID for strict server-side ownership verification.
	 * @return object|null Property record or null if not found or unauthorized.
	 */
	public static function get_property( int $property_id, ?int $owner_id = null ): ?object {
		global $wpdb;

		$prop_table = $wpdb->prefix . Constants::TABLE_PROPERTIES;

		if ( null !== $owner_id && $owner_id > 0 ) {
			$sql = "SELECT * FROM {$prop_table} WHERE id = %d AND owner_id = %d LIMIT 1";
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $property_id, $owner_id ) );
		} else {
			$sql = "SELECT * FROM {$prop_table} WHERE id = %d LIMIT 1";
			$row = $wpdb->get_row( $wpdb->prepare( $sql, $property_id ) );
		}

		return $row ?: null;
	}

	/**
	 * Update property details for an owner.
	 *
	 * Protects internal review metadata and owner_id.
	 * Automatically resets verified status to 'pending_verification' if
	 * compliance-critical information (address, city, state, zip) is modified.
	 *
	 * @param int   $property_id Target property ID.
	 * @param int   $owner_id    Authenticated owner user ID.
	 * @param array $data        Submitted update fields.
	 * @return array
	 */
	public static function update_property( int $property_id, int $owner_id, array $data ): array {
		global $wpdb;

		// 1. Verify property exists and belongs to the owner
		$existing = self::get_property( $property_id, $owner_id );
		if ( ! $existing ) {
			return array(
				'success' => false,
				'error'   => 'not_found_or_forbidden',
				'message' => __( 'Property not found or you do not have permission to modify it.', 'fraud-tenant-core' ),
			);
		}

		// 2. Validate updated fields
		$street = isset( $data['address_street'] ) ? Sanitizer::text( $data['address_street'] ) : $existing->address_street;
		$unit   = isset( $data['unit_number'] ) ? Sanitizer::text( $data['unit_number'] ) : $existing->unit_number;
		$city   = isset( $data['city'] ) ? Sanitizer::text( $data['city'] ) : $existing->city;
		$state  = isset( $data['state'] ) ? strtoupper( Sanitizer::text( $data['state'] ) ) : $existing->state;
		$zip    = isset( $data['postal_code'] ) ? Sanitizer::zip( $data['postal_code'] ) : $existing->postal_code;
		$title  = isset( $data['title'] ) ? Sanitizer::text( $data['title'] ) : $existing->title;
		$type   = isset( $data['property_type'] ) ? sanitize_key( $data['property_type'] ) : $existing->property_type;
		$units  = isset( $data['units_count'] ) ? max( 1, (int) $data['units_count'] ) : (int) $existing->units_count;

		if ( empty( $street ) || empty( $city ) || empty( $state ) || empty( $zip ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_required_fields',
				'message' => __( 'Street address, city, state, and ZIP code are required.', 'fraud-tenant-core' ),
			);
		}

		if ( ! array_key_exists( $type, self::ALLOWED_PROPERTY_TYPES ) ) {
			$type = $existing->property_type;
		}

		// 3. Detect compliance-critical changes
		$critical_changed = (
			$street !== $existing->address_street ||
			$city   !== $existing->city ||
			$state  !== $existing->state ||
			$zip    !== $existing->postal_code
		);

		$new_status       = $existing->status;
		$reverify_needed  = false;

		if ( Constants::PROP_STATUS_VERIFIED === $existing->status && $critical_changed ) {
			$new_status      = Constants::PROP_STATUS_PENDING;
			$reverify_needed = true;
		}

		$now     = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$wpdb->prefix . Constants::TABLE_PROPERTIES,
			array(
				'title'          => $title,
				'address_street' => $street,
				'unit_number'    => $unit ?: null,
				'city'           => $city,
				'state'          => $state,
				'postal_code'    => $zip,
				'property_type'  => $type,
				'units_count'    => $units,
				'status'         => $new_status,
				'updated_at'     => $now,
			),
			array(
				'id'       => $property_id,
				'owner_id' => $owner_id,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'db_update_failed',
				'message' => __( 'Failed to update property details in database.', 'fraud-tenant-core' ),
			);
		}

		// 4. Log audit event
		AuditLogger::log(
			'owner.property_updated',
			'property',
			$property_id,
			array(
				'property_id'      => $property_id,
				'critical_changed' => $critical_changed,
				'reverify_needed'  => $reverify_needed,
				'previous_status'  => $existing->status,
				'new_status'       => $new_status,
				'owner_id'         => $owner_id,
			),
			$owner_id
		);

		if ( $reverify_needed ) {
			AuditLogger::log(
				'owner.property_reverification_triggered',
				'property',
				$property_id,
				array(
					'property_id' => $property_id,
					'reason'      => 'critical_address_modified',
					'owner_id'    => $owner_id,
				),
				$owner_id
			);
		}

		$msg = $reverify_needed
			? __( 'Property updated. Because compliance-critical address information changed, property verification has been reset to pending review.', 'fraud-tenant-core' )
			: __( 'Property details updated successfully.', 'fraud-tenant-core' );

		return array(
			'success'          => true,
			'property_id'      => $property_id,
			'status'           => $new_status,
			'critical_changed' => $critical_changed,
			'reverify_needed'  => $reverify_needed,
			'message'          => $msg,
		);
	}

	/**
	 * Soft-archive a property without destroying records, documents, or audit history.
	 *
	 * @param int $property_id Target property ID.
	 * @param int $owner_id    Authenticated owner user ID.
	 * @return array
	 */
	public static function archive_property( int $property_id, int $owner_id ): array {
		global $wpdb;

		$existing = self::get_property( $property_id, $owner_id );
		if ( ! $existing ) {
			return array(
				'success' => false,
				'error'   => 'not_found_or_forbidden',
				'message' => __( 'Property not found or unauthorized.', 'fraud-tenant-core' ),
			);
		}

		$now     = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$wpdb->prefix . Constants::TABLE_PROPERTIES,
			array(
				'status'      => Constants::PROP_STATUS_ARCHIVED,
				'archived_at' => $now,
				'updated_at'  => $now,
			),
			array(
				'id'       => $property_id,
				'owner_id' => $owner_id,
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'db_archive_failed',
				'message' => __( 'Failed to archive property.', 'fraud-tenant-core' ),
			);
		}

		AuditLogger::log(
			'owner.property_archived',
			'property',
			$property_id,
			array(
				'property_id' => $property_id,
				'owner_id'    => $owner_id,
			),
			$owner_id
		);

		return array(
			'success' => true,
			'message' => __( 'Property has been safely archived.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Unarchive / restore an archived property back to active status.
	 *
	 * @param int $property_id Target property ID.
	 * @param int $owner_id    Authenticated owner user ID.
	 * @return array
	 */
	public static function unarchive_property( int $property_id, int $owner_id ): array {
		global $wpdb;

		$existing = self::get_property( $property_id, $owner_id );
		if ( ! $existing ) {
			return array(
				'success' => false,
				'error'   => 'not_found_or_forbidden',
				'message' => __( 'Property not found or unauthorized.', 'fraud-tenant-core' ),
			);
		}

		$now     = current_time( 'mysql', true );
		$updated = $wpdb->update(
			$wpdb->prefix . Constants::TABLE_PROPERTIES,
			array(
				'status'      => Constants::PROP_STATUS_PENDING,
				'archived_at' => null,
				'updated_at'  => $now,
			),
			array(
				'id'       => $property_id,
				'owner_id' => $owner_id,
			),
			array( '%s', null, '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'error'   => 'db_restore_failed',
				'message' => __( 'Failed to restore property.', 'fraud-tenant-core' ),
			);
		}

		AuditLogger::log(
			'owner.property_unarchived',
			'property',
			$property_id,
			array(
				'property_id' => $property_id,
				'owner_id'    => $owner_id,
			),
			$owner_id
		);

		return array(
			'success' => true,
			'message' => __( 'Property restored. It has re-entered the verification queue.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Retrieve all ownership documents associated with a property.
	 *
	 * @param int      $property_id Target property ID.
	 * @param int|null $owner_id    Optional owner ID for authorization verification.
	 * @return array List of document records.
	 */
	public static function get_property_documents( int $property_id, ?int $owner_id = null ): array {
		global $wpdb;

		// If owner_id is specified, verify property ownership first
		if ( null !== $owner_id && $owner_id > 0 ) {
			$property = self::get_property( $property_id, $owner_id );
			if ( ! $property ) {
				return array();
			}
		}

		$doc_table = $wpdb->prefix . Constants::TABLE_DOCUMENTS;
		$sql       = "SELECT * FROM {$doc_table} 
					  WHERE object_type = 'property_ownership' AND object_id = %d 
					  ORDER BY id DESC";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $property_id ) );

		return $rows ?: array();
	}

	/**
	 * Upload an additional or replacement ownership document for a property.
	 *
	 * Preserves all prior document records (never overwrites history).
	 * If property was rejected or info_requested, resets status back to pending_verification.
	 *
	 * @param int   $property_id Target property ID.
	 * @param int   $owner_id    Authenticated owner user ID.
	 * @param array $file        Entry from $_FILES.
	 * @return array
	 */
	public static function upload_property_document( int $property_id, int $owner_id, array $file ): array {
		global $wpdb;

		// 1. Verify property exists and belongs to the owner
		$property = self::get_property( $property_id, $owner_id );
		if ( ! $property ) {
			return array(
				'success' => false,
				'error'   => 'not_found_or_forbidden',
				'message' => __( 'Property not found or unauthorized.', 'fraud-tenant-core' ),
			);
		}

		// 2. Delegate secure storage to DocumentService
		$result = DocumentService::store_ownership_document( $owner_id, $property_id, $file );
		if ( ! $result['success'] ) {
			return $result;
		}

		$doc_id = $result['document_id'];

		// 3. If property was rejected or info_requested, reset back to pending verification
		$prior_status = $property->status;
		if ( in_array( $prior_status, array( Constants::PROP_STATUS_REJECTED, Constants::PROP_STATUS_INFO_REQUESTED ), true ) ) {
			$now = current_time( 'mysql', true );
			$wpdb->update(
				$wpdb->prefix . Constants::TABLE_PROPERTIES,
				array(
					'status'     => Constants::PROP_STATUS_PENDING,
					'updated_at' => $now,
				),
				array( 'id' => $property_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		// 4. Log append-only application-level audit event
		AuditLogger::log(
			'owner.property_document_uploaded',
			'document',
			$doc_id,
			array(
				'property_id'   => $property_id,
				'document_id'   => $doc_id,
				'owner_id'      => $owner_id,
				'original_name' => $result['original_name'],
				'file_size'     => $result['file_size'],
				'prior_status'  => $prior_status,
			),
			$owner_id
		);

		return array(
			'success'     => true,
			'document_id' => $doc_id,
			'message'     => __( 'Ownership document uploaded successfully and queued for compliance review.', 'fraud-tenant-core' ),
		);
	}

	/**
	 * Check if an actor has permission to manage properties.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_manage_properties( int $user_id ): bool {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		if ( ! user_can( $user_id, Capabilities::CREATE_PROPERTIES ) && ! user_can( $user_id, Capabilities::MANAGE_PROPERTIES ) ) {
			return false;
		}

		// Check profile approval status
		$profile = \FraudTenant\Auth\AuthService::get_user_profile( $user_id );
		if ( ! $profile || Constants::STATUS_APPROVED !== $profile->approval_status ) {
			return false;
		}

		return true;
	}
}
