<?php
/*
Plugin Name: WordPress MU Domain Mapping
Plugin URI: http://ocaoimh.ie/wordpress-mu-domain-mapping/
Description: Map any blog on a WordPress MU website to another domain.
Version: 0.4.1
Author: Donncha O Caoimh
Author URI: http://ocaoimh.ie/
*/
/*  Copyright Donncha O Caoimh (http://ocaoimh.ie/)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

function dm_add_pages() {
	add_management_page('Domain Mapping', 'Domain Mapping', 'manage_options', 'domainmapping', 'dm_manage_page');
}
add_action('admin_menu', 'dm_add_pages');

function dm_manage_page() {
	global $wpdb;
	$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';

	if( VHOST == 'no' ) {
		die( 'Sorry, domain mapping only works on virtual host installs.' );
	}
	if( is_site_admin() ) {
		if($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtable}'") != $wpdb->dmtable) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->dmtable}` (
				`id` bigint(20) NOT NULL auto_increment,
				`blog_id` bigint(20) NOT NULL,
				`domain` varchar(255) NOT NULL,
				`active` tinyint(4) default '1',
				PRIMARY KEY  (`id`),
				KEY `blog_id` (`blog_id`,`domain`,`active`)
			);" );
			?> <div id="message" class="updated fade"><p><strong><?php _e('Domain mapping database table created.') ?></strong></p></div> <?php
		}
	}


	if( !empty( $_POST[ 'action' ] ) ) {
		$domain = $wpdb->escape( preg_replace( "/^www\./", "", $_POST[ 'domain' ] ) );
		check_admin_referer( 'domain_mapping' );
		switch( $_POST[ 'action' ] ) {
			case "add":
				if( null == $wpdb->get_row( "SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = '$domain'" ) )
					$wpdb->query( "INSERT INTO {$wpdb->dmtable} ( `id` , `blog_id` , `domain` , `active` ) VALUES ( NULL, '" . intval( $wpdb->blogid ) . "', '$domain', '1')" );
			break;
			case "delete":
				$wpdb->query( "DELETE FROM {$wpdb->dmtable} WHERE domain = '$domain'" );
			break;
			case "ipaddress":
				if( is_site_admin() )
					add_site_option( 'dm_ipaddress', $_POST[ 'ipaddress' ] );
			break;
		}
	}
	echo "<div class='wrap'><h2>Domain Mapping</h2>";
	if( !file_exists( ABSPATH . '/wp-content/sunrise.php' ) ) {
		echo "Please copy sunrise.php to " . ABSPATH . "/wp-content/sunrise.php and uncomment the SUNRISE definition in " . ABSPATH . "wp-config.php";
		echo "</div>";
		die();
	}

	if( !defined( 'SUNRISE' ) ) {
		echo "Please uncomment the line <em>//define( 'SUNRISE', 'on' );</em> in your " . ABSPATH . "/wp-config.php";
		echo "</div>";
		die();
	}

	if( is_site_admin() ) {
		echo '<h3>' . __( 'Site Admin Configuration' ) . '</h3>';
		echo "<p>" . __( "As a site admin on this site you can set the IP address users need to point their DNS A records at. If you don't know what it is, ping this blog to get the IP address." ) . "</p>";
		echo "<p>" . __( "If you use round robin DNS or another load balancing technique with more than one IP, enter each address, separating them by commas." ) . "</p>";
		echo '<form method="POST">';
		echo '<input type="hidden" name="action" value="ipaddress" />';
		_e( "Server IP Address: " );
		echo "<input type='text' name='ipaddress' value='" . get_site_option( 'dm_ipaddress' ) . "' />";
		wp_nonce_field( 'domain_mapping' );
		echo "<input type='submit' value='Save' />";
		echo "</form><br />";
	}
	$domains = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}'" );
	if( is_array( $domains ) && !empty( $domains ) ) {
		?><h3><?php _e( 'Active domains on this blog' ); ?></h3><?php
		foreach( $domains as $details ) {
			echo '<form method="POST">';
			echo $details->domain . " ";
			echo '<input type="hidden" name="action" value="delete" />';
			echo "<input type='hidden' name='domain' value='{$details->domain}' />";
			echo "<input type='submit' value='Delete' />";
			wp_nonce_field( 'domain_mapping' );
			echo "</form><br />";
		}
		?><br /><?php
	}
	echo "<h3>" . __( 'Add new domain' ) . "</h3>";
	echo '<form method="POST">';
	echo '<input type="hidden" name="action" value="add" />';
	echo "http://www.<input type='text' name='domain' value='' />/";
	wp_nonce_field( 'domain_mapping' );
	echo "<input type='submit' value='Add' />";
	echo "</form><br />";
	echo "<p>" . __( 'If your domain name includes a hostname like "blog" or some other prefix before the actual domain name you will need to add a CNAME for that hostname in your DNS pointing at this blog URL. "www" does not count because it will be removed from the domain name.' ) . "</p>";
	$dm_ipaddress = get_site_option( 'dm_ipaddress', 'IP not set by admin yet.' );
	if( strpos( $dm_ipaddress, ',' ) ) {
		echo "<p>" . __( 'If you want to redirect a domain you will need to add DNS "A" records pointing at the IP addresses of this server: ' ) . "<strong>" . $dm_ipaddress . "</strong></p>";
	} else {
		echo "<p>" . __( 'If you want to redirect a domain you will need to add a DNS "A" record pointing at the IP address of this server: ' ) . "<strong>" . $dm_ipaddress . "</strong></p>";
	}
	echo "</div>";
}

function domain_mapping_siteurl( $setting ) {
	global $wpdb, $current_blog;
	$s = $wpdb->suppress_errors();
	$domain = $wpdb->get_var( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}'" );
	$wpdb->suppress_errors( $s );
	$protocol = ( 'on' == strtolower($_SERVER['HTTPS']) ) ? 'https://' : 'http://';
	if( $domain )
		return untrailingslashit( $protocol . $domain . $current_blog->path );

	return $setting;
}
add_action( 'pre_option_siteurl', 'domain_mapping_siteurl' );
add_action( 'pre_option_home', 'domain_mapping_siteurl' );

function redirect_admin_page() {
	global $current_blog;
	$url = domain_mapping_siteurl( 'NA' );
	if ( $url == 'NA' )
		return false;
	$protocol = ( 'on' == strtolower($_SERVER['HTTPS']) ) ? 'https://' : 'http://';
	if ( strpos( $protocol . $_SERVER[ 'HTTP_HOST' ], $url ) === false ) {
		wp_safe_redirect( $url . $_SERVER[ 'REQUEST_URI' ] );
	}
}
add_action( 'admin_init', 'redirect_admin_page' );

function redirect_to_mapped_domain() {
	global $current_blog;
	$protocol = ( 'on' == strtolower($_SERVER['HTTPS']) ) ? 'https://' : 'http://';
	$url = domain_mapping_siteurl( false );
	if( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_blog->path ) ) {
		wp_redirect( $url . $_SERVER[ 'REQUEST_URI' ] );
		exit;
	}
}
add_action( 'template_redirect', 'redirect_to_mapped_domain' );
?>
