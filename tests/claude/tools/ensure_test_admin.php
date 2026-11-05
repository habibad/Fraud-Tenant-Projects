<?php
/**
 * QA tool: ensure a dedicated administrator for automated tests exists, separate from the
 * human QA admin account. Credentials go to the private QA secrets folder (outside web root).
 */
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/fraudtenant-qa/';
ob_start();
require 'C:/xampp/htdocs/fraudtenant-qa/wp-load.php';
ob_end_clean();
if ( DB_NAME !== 'fraudtenant_qa' ) {
	exit( 2 );
}
$file = 'C:/Users/Md Anikur Rahaman/ft_qa_secrets/qa_test_admin.json';
$cred = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
$user = get_user_by( 'login', 'qa_test_admin' );
if ( $user && is_array( $cred ) && ! is_wp_error( wp_authenticate( 'qa_test_admin', $cred['password'] ) ) ) {
	echo "qa_test_admin ok (#{$user->ID})\n";
	exit( 0 );
}
$pw = wp_generate_password( 24, false );
if ( $user ) {
	wp_set_password( $pw, $user->ID );
	$id = $user->ID;
} else {
	$id = wp_insert_user( array( 'user_login' => 'qa_test_admin', 'user_email' => 'qa-test-admin@example.test', 'user_pass' => $pw, 'role' => 'administrator', 'display_name' => 'QA Test Admin' ) );
}
\FraudTenant\Auth\RoleManager::register_roles();
file_put_contents( $file, wp_json_encode( array( 'login' => 'qa_test_admin', 'password' => $pw, 'id' => (int) $id ) ) );
echo "qa_test_admin ready (#{$id}); credentials written to QA secrets\n";
