<?php

if( VHOST == 'no' ) {
	die( 'Sorry, domain mapping only works on virtual host installs.' );
}

$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';

$wpdb->suppress_errors();
$dm_domain = $wpdb->escape( preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ) );
$domain_mapping_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = '{$dm_domain}' LIMIT 1" );
$wpdb->suppress_errors( false );
if( $domain_mapping_id ) {
	$current_blog->blog_id = $domain_mapping_id;
	$current_blog->site_id = 1;
	$current_blog->domain = $_SERVER[ 'HTTP_HOST' ];
	$current_blog->path = '/';
	$current_blog->public = 1;

	define( 'COOKIE_DOMAIN', $_SERVER[ 'HTTP_HOST' ] );

	$current_site = $wpdb->get_row( "SELECT * from wp_site LIMIT 0,1" );
	define( 'DOMAIN_MAPPING', 1 );
}
?>
