<?php
/*
Plugin Name: WordPress MU Domain Mapping
Plugin URI: http://ocaoimh.ie/wordpress-mu-domain-mapping/
Description: Map any blog on a WordPress MU website to another domain.
Version: 0.4.3
Author: Donncha O Caoimh
Author URI: http://ocaoimh.ie/
*/
/*  Copyright Donncha O Caoimh (http://ocaoimh.ie/)
    With contributions by Ron Rennick(http://wpmututorials.com/), Greg Sidberry and others.

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
	add_management_page( 'Domain Mapping', 'Domain Mapping', 'manage_options', 'domainmapping', 'dm_manage_page' );
	if( is_site_admin() ) {
		add_submenu_page('wpmu-admin.php', 'Domain Mapping', 'Domain Mapping', 'manage_options', 'dm_admin_page', 'dm_admin_page');
	}
}
add_action( 'admin_menu', 'dm_add_pages' );

function maybe_create_db() {
	global $wpdb;

	$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
	if ( is_site_admin() ) {
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

}

function dm_admin_page() {
	global $wpdb;
	maybe_create_db();
	if ( false == is_site_admin() ) { // paranoid? moi?
		return false;
	}

	if ( !empty( $_POST[ 'action' ] ) ) {
		check_admin_referer( 'domain_mapping' );
		if ( $_POST[ 'action' ] == 'update' ) {
			add_site_option( 'dm_ipaddress', $_POST[ 'ipaddress' ] );
			add_site_option( 'dm_301_redirect', intval( $_POST[ 'permanent_redirect' ] ) );
		}
	}

	echo '<h3>' . __( 'Domain Mapping Configuration' ) . '</h3>';
	echo "<p>" . __( "As a site admin on this site you can set the IP address users need to point their DNS A records at. If you don't know what it is, ping this blog to get the IP address." ) . "</p>";
	echo "<p>" . __( "If you use round robin DNS or another load balancing technique with more than one IP, enter each address, separating them by commas." ) . "</p>";
	echo '<form method="POST">';
	echo '<input type="hidden" name="action" value="update" />';
	_e( "Server IP Address: " );
	echo "<input type='text' name='ipaddress' value='" . get_site_option( 'dm_ipaddress' ) . "' /><br />";
	echo "<input type='checkbox' name='permanent_redirect' value='1' ";
	echo get_site_option( 'dm_301_redirect' ) == 1 ? "checked='checked'" : "";
	echo "' /> Permanent redirect. (better for your blogger's pagerank)<br />";
	wp_nonce_field( 'domain_mapping' );
	echo "<input type='submit' value='Save' />";
	echo "</form><br />";
	_e( 'The information you enter here will be shown to your users so they can configure their DNS correctly.' );
}

function dm_manage_page() {
	global $wpdb;
	maybe_create_db();

	if ( !empty( $_POST[ 'action' ] ) ) {
		$domain = $wpdb->escape( preg_replace( "/^www\./", "", $_POST[ 'domain' ] ) );
		if ( $domain == '' ) {
			wp_die( "You must enter a domain" );
		}
		check_admin_referer( 'domain_mapping' );
		switch( $_POST[ 'action' ] ) {
			case "add":
				if( null == $wpdb->get_row( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '$domain'" ) && null == $wpdb->get_row( "SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = '$domain'" ) ) {
					if ( $_POST[ 'primary' ] ) {
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid ) );
					}
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->dmtable} ( `id` , `blog_id` , `domain` , `active` ) VALUES ( NULL, %d, %s, %d )", $wpdb->blogid, $domain, $_POST[ 'primary' ] ) );
				}
			break;
			case "delete":
				$wpdb->query( "DELETE FROM {$wpdb->dmtable} WHERE domain = '$domain'" );
			break;
			case "primary":
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid ) );
				if( $domain != 'original' ) {
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 1 WHERE domain = %s", $domain ) );
				}
			break;
		}
	}
	echo "<div class='wrap'><h2>Domain Mapping</h2>";
	if ( !file_exists( ABSPATH . '/wp-content/sunrise.php' ) ) {
		if ( is_site_admin() ) {
			echo "Please copy sunrise.php to " . ABSPATH . "/wp-content/sunrise.php and uncomment the SUNRISE definition in " . ABSPATH . "wp-config.php";
		} else {
			echo "This plugin has not been configured correctly yet.";
		}
		echo "</div>";
		return false;
	}

	if ( !defined( 'SUNRISE' ) ) {
		if ( is_site_admin() ) {
			echo "Please uncomment the line <em>//define( 'SUNRISE', 'on' );</em> in your " . ABSPATH . "wp-config.php";
		} else {
			echo "This plugin has not been configured correctly yet.";
		}
		echo "</div>";
		return false;
	}

	if ( false == get_site_option( 'dm_ipaddress' ) ) {
		if ( is_site_admin() ) {
			echo "Please set the IP address of your server in the <a href='wpmu-admin.php?page=dm_admin_page'>site admin page</a>.";
		} else {
			echo "This plugin has not been configured correctly yet.";
		}
		echo "</div>";
		return false;
	}

	$domains = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}'" );
	if ( is_array( $domains ) && !empty( $domains ) ) {
		?><h3><?php _e( 'Active domains on this blog' ); ?></h3>
		<table><tr><th>Domain</th><th>Primary</th><th>Delete</th><th>Make Primary</th></tr>
		<?php
		$primary_found = 0;
		foreach( $domains as $details ) {
			echo "<tr><td>{$details->domain}</td><td style='text-align: center'><strong>";
			echo $details->active == 0 ? "No" : "Yes";
			echo "</strong></td><td>";
			echo '<form method="POST">';
			echo '<input type="hidden" name="action" value="delete" />';
			echo "<input type='hidden' name='domain' value='{$details->domain}' />";
			echo "<input type='submit' value='Delete' />";
			wp_nonce_field( 'domain_mapping' );
			echo "</form></td><td>";
			if ( 0 == $primary_found && $details->active == 0 ) {
				echo '<form method="POST">';
				echo '<input type="hidden" name="action" value="primary" />';
				echo "<input type='hidden' name='domain' value='{$details->domain}' />";
				echo "<input type='submit' value='Make Primary' />";
				wp_nonce_field( 'domain_mapping' );
				echo "</form>";
			}
			echo "</td></tr>";
			if ( 0 == $primary_found )
				$primary_found = $details->active;
		}
		?></table><?php
		if ( $primary_found == 0 ) {
			echo "<h3>Primary Domain disabled</h3><p>Your blog will answer on every domain listed, including the original blog url.</p>";
		} else {
			echo '<form method="POST">';
			echo '<input type="hidden" name="action" value="primary" />';
			echo "<input type='hidden' name='domain' value='original' />";
			echo "<input type='submit' value='Disable Primary Domain Checking' />";
			wp_nonce_field( 'domain_mapping' );
			echo "</form>";
		}
	}
	echo "<h3>" . __( 'Add new domain' ) . "</h3>";
	echo '<form method="POST">';
	echo '<input type="hidden" name="action" value="add" />';
	echo "http://www.<input type='text' name='domain' value='' />/<br />";
	wp_nonce_field( 'domain_mapping' );
	echo "<input type='checkbox' name='primary' value='1' /> Primary domain for this blog<br />";
	echo "<input type='submit' value='Add' />";
	echo "</form><br />";
	echo "<p>" . __( 'If your domain name includes a hostname like "blog" or some other prefix before the actual domain name you will need to add a CNAME for that hostname in your DNS pointing at this blog URL. "www" does not count because it will be removed from the domain name.' ) . "</p>";
	$dm_ipaddress = get_site_option( 'dm_ipaddress', 'IP not set by admin yet.' );
	if ( strpos( $dm_ipaddress, ',' ) ) {
		echo "<p>" . __( 'If you want to redirect a domain you will need to add DNS "A" records pointing at the IP addresses of this server: ' ) . "<strong>" . $dm_ipaddress . "</strong></p>";
	} else {
		echo "<p>" . __( 'If you want to redirect a domain you will need to add a DNS "A" record pointing at the IP address of this server: ' ) . "<strong>" . $dm_ipaddress . "</strong></p>";
	}
	echo "</div>";
}

function domain_mapping_siteurl( $setting ) {
	global $wpdb, $current_blog;

	// To reduce the number of database queries, save the results the first time we encounter each blog ID.
	static $return_url = array();

	if ( !isset( $return_url[ $wpdb->blogid ] ) ) {
		$s = $wpdb->suppress_errors();

		// get primary domain 
		$domain = $wpdb->get_var( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}' AND active = 1 LIMIT 1" );
		if ( null == $domain ) {
			// Try matching on the current URL domain and blog.
			$domain = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM {$wpdb->dmtable} WHERE domain = %s AND blog_id = %d LIMIT 1", preg_replace( "/^www\./", "", $_SERVER[ 'HTTP_HOST' ] ), $wpdb->blogid ) );
		}

		$wpdb->suppress_errors( $s );
		$protocol = ( 'on' == strtolower( $_SERVER['HTTPS' ] ) ) ? 'https://' : 'http://';
		if ( $domain ) {
			$return_url[ $wpdb->blogid ] = untrailingslashit( $protocol . $domain  );
			$setting = $return_url[ $wpdb->blogid ];
		} else {
			$return_url[ $wpdb->blogid ] = false;
		}
	} elseif ( $return_url[ $wpdb->blogid ] !== FALSE) {
		$setting = $return_url[ $wpdb->blogid ];
	}

	return $setting;
}

function domain_mapping_post_content( $post_content ) {
	global $wpdb;

	static $orig_urls = array();
	if ( ! isset( $orig_urls[ $wpdb->blog_id ] ) ) {
		remove_filter( 'pre_option_siteurl', 'domain_mapping_siteurl' );
		$orig_url = get_option( 'siteurl' );
		$orig_urls[ $wpdb->blog_id ] = $orig_url;
		add_filter( 'pre_option_siteurl', 'domain_mapping_siteurl' );
	} else {
		$orig_url = $orig_urls[ $wpdb->blog_id ];
	}
	$url = domain_mapping_siteurl( 'NA' );
	if ( $url == 'NA' )
		return $post_content;
	return str_replace( $orig_url, $url, $post_content );
}

// fixes the plugins_url 
function domain_mapping_plugins_uri( $full_url, $path=NULL, $plugin=NULL ) {
	return get_option( 'siteurl' ) . substr( $full_url, stripos( $full_url, PLUGINDIR ) - 1 );
}

function domain_mapping_themes_uri( $full_url ) {
	return get_option( 'siteurl' ) . substr( $full_url, stripos( $full_url, "/wp-content/themes" ) );
}

if ( defined( 'DOMAIN_MAPPING' ) ) {
	add_filter( 'plugins_url', 'domain_mapping_plugins_uri', 1 );
	add_filter( 'theme_root_uri', 'domain_mapping_themes_uri', 1 );
	add_filter( 'pre_option_siteurl', 'domain_mapping_siteurl' );
	add_filter( 'pre_option_home', 'domain_mapping_siteurl' );
	add_filter( 'the_content', 'domain_mapping_post_content' );
}

function redirect_to_mapped_domain() {
	global $current_blog, $wpdb;
	$protocol = ( 'on' == strtolower($_SERVER['HTTPS']) ) ? 'https://' : 'http://';
	$url = domain_mapping_siteurl( false );
	if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_blog->path ) ) {
		$redirect = get_site_option( 'dm_301_redirect' ) ? '301' : '302';
		if ( constant( "VHOST" ) != 'yes' ) {
			$_SERVER[ 'REQUEST_URI' ] = str_replace( $current_blog->path, '/', $_SERVER[ 'REQUEST_URI' ] );
		}
		header( "Location: {$url}{$_SERVER[ 'REQUEST_URI' ]}", true, $redirect );
		exit;
	}
}
add_action( 'template_redirect', 'redirect_to_mapped_domain' );

// delete mapping if blog is deleted
function delete_blog_domain_mapping( $blog_id, $drop ) {
	global $wpdb;
	$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
	if ( $blog_id && $drop ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtable} WHERE blog_id  = %d", $blog_id ) );
	}
}
add_action( 'delete_blog', 'delete_blog_domain_mapping', 1, 2 );

// show mapping on site admin blogs screen
function ra_domain_mapping_columns( $columns ) {
	$columns[ 'map' ] = __( 'Mapping' );
	return $columns;
}
add_filter( 'wpmu_blogs_columns', 'ra_domain_mapping_columns' );

function ra_domain_mapping_field( $column, $blog_id ) {
	global $wpdb;
	static $maps = false;
	
	if ( $column == 'map' ) {
		if ( $maps === false ) {
			$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
			$work = $wpdb->get_results( "SELECT blog_id, domain FROM {$wpdb->dmtable} ORDER BY blog_id" );
			$maps = array();
			if($work) {
				foreach( $work as $blog ) {
					$maps[ $blog->blog_id ] = $blog->domain;
				}
			}
		}
		echo $maps[ $blog_id ];
	}
}
add_action( 'manage_blogs_custom_column', 'ra_domain_mapping_field', 1, 3 );

?>
