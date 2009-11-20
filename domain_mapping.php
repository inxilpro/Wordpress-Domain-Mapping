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

	get_dm_hash(); // initialise the remote login hash

	$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
	$wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';
	if ( is_site_admin() ) {
		$created = 0;
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtable}'") != $wpdb->dmtable ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->dmtable}` (
				`id` bigint(20) NOT NULL auto_increment,
				`blog_id` bigint(20) NOT NULL,
				`domain` varchar(255) NOT NULL,
				`active` tinyint(4) default '1',
				PRIMARY KEY  (`id`),
				KEY `blog_id` (`blog_id`,`domain`,`active`)
			);" );
			$created = 1;
		}
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtablelogins}'") != $wpdb->dmtablelogins ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->dmtablelogins}` (
				`id` varchar(32) NOT NULL,
				`user_id` bigint(20) NOT NULL,
				`blog_id` bigint(20) NOT NULL,
				`t` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
				PRIMARY KEY  (`id`)
			);" );
			$created = 1;
		}
		if ( $created ) {
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
			add_site_option( 'dm_redirect_admin', intval( $_POST[ 'always_redirect_admin' ] ) );
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
	echo " /> Permanent redirect. (better for your blogger's pagerank)<br />";
	echo "<input type='checkbox' name='always_redirect_admin' value='1' ";
	echo get_site_option( 'dm_redirect_admin' ) == 1 ? "checked='checked'" : "";
	echo " /> Redirect administration pages to original blog's domain<br />";
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
			case "primary":
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid ) );
				$orig_url = parse_url( get_original_url( 'siteurl' ) );
				if( $domain != $orig_url[ 'host' ] ) {
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 1 WHERE domain = %s", $domain ) );
				}
			break;
		}
	} elseif( $_GET[ 'action' ] == 'delete' ) {
		$domain = $wpdb->escape( preg_replace( "/^www\./", "", $_GET[ 'domain' ] ) );
		if ( $domain == '' ) {
			wp_die( "You must enter a domain" );
		}
		check_admin_referer( "delete" . $_GET['domain'] );
		$wpdb->query( "DELETE FROM {$wpdb->dmtable} WHERE domain = '$domain'" );
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

	$protocol = ( 'on' == strtolower( $_SERVER['HTTPS' ] ) ) ? 'https://' : 'http://';
	$domains = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}'", ARRAY_A );
	if ( is_array( $domains ) && !empty( $domains ) ) {
		$orig_url = parse_url( get_original_url( 'siteurl' ) );
		$domains[] = array( 'domain' => $orig_url[ 'host' ], 'path' => $orig_url[ 'path' ], 'active' => 0 );
		?><h3><?php _e( 'Active domains on this blog' ); ?></h3>
		<table><tr><th></th><th>Domain</th><th>Primary</th><th>Delete</th></tr>
		<?php
		$primary_found = 0;
		echo '<form method="POST">';
		foreach( $domains as $details ) {
			if ( 0 == $primary_found && $details[ 'domain' ] == $orig_url[ 'host' ] ) {
				$details[ 'active' ] = 1;
			}
			echo "<tr><td>";
			echo "<input type='radio' name='domain' value='{$details[ 'domain' ]}' ";
			if ( $details[ 'active' ] == 1 )
				echo "checked='1' ";
			echo "/>";
			echo "</td><td>{$protocol}{$details[ 'domain' ]}{$details[ 'path' ]}</td><td style='text-align: center'><strong>";
			echo $details[ 'active' ] == 0 ? "No" : "Yes";
			echo "</strong></td><td>";
			if ( $details[ 'domain' ] != $orig_url[ 'host' ] )
				echo "<a href='" . wp_nonce_url( "?page=domainmapping&action=delete&domain={$details[ 'domain' ]}", "delete" . $details[ 'domain' ] ) . "'>Del</a>";
			echo "</td></tr>";
			if ( 0 == $primary_found )
				$primary_found = $details[ 'active' ];
		}
		?></table><?php
		echo '<input type="hidden" name="action" value="primary" />';
		echo "<input type='submit' value='Set Primary Domain' />";
		wp_nonce_field( 'domain_mapping' );
		echo "</form>";
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

		// get primary domain, if we don't have one then return original url.
		$domain = $wpdb->get_var( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}' AND active = 1 LIMIT 1" );
		if ( null == $domain ) {
			$return_url[ $wpdb->blogid ] = untrailingslashit( get_original_url( "siteurl" ) );
			return $return_url[ $wpdb->blogid ];
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

// url is siteurl or home
function get_original_url( $url ) {
	global $wpdb;

	static $orig_urls = array();
	if ( ! isset( $orig_urls[ $wpdb->blogid ] ) ) {
		remove_filter( 'pre_option_' . $url, 'domain_mapping_' . $url );
		$orig_urls[ $wpdb->blogid ] = get_option( $url );
		add_filter( 'pre_option_' . $url, 'domain_mapping_' . $url );
	}
	return $orig_urls[ $wpdb->blogid ];
}

function domain_mapping_post_content( $post_content ) {
	global $wpdb;

	$orig_url = get_original_url( 'siteurl' );

	$url = domain_mapping_siteurl( 'NA' );
	if ( $url == 'NA' )
		return $post_content;
	return str_replace( $orig_url, $url, $post_content );
}

function redirect_admin() {
	if ( get_site_option( 'dm_redirect_admin' ) ) {
		// redirect mapped domain admin page to original url
		$url = get_original_url( 'siteurl' );
		if ( false === strpos( $url, $_SERVER[ 'HTTP_HOST' ] ) ) {
			wp_redirect( trailingslashit( $url ) . 'wp-admin/' );
			exit;
		}
	} else {
		// redirect original url to mapped domain
		$url = domain_mapping_siteurl( false );
		if ( $url != site_url() ) {
			wp_redirect( trailingslashit( $url ) . '?dm_gotoadmin=1' );
			exit;
		}
	}
}

function redirect_login_to_orig() {
	if ( $_GET[ 'action' ] == 'logout' || isset( $_GET[ 'loggedout' ] ) ) {
		return false;
	}
	$url = get_original_url( 'siteurl' );
	if ( $url != site_url() ) {
		$url .= "/wp-login.php";
		echo "<script type='text/javascript'>\nwindow.location = '$url'</script>";
	}
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
	add_action( 'wp_head', 'remote_login_js_loader' );
	add_action( 'login_head', 'redirect_login_to_orig' );
	add_action( 'wp_logout', 'remote_logout_loader', 9999 );
	if ( isset( $_GET[ 'dm_gotoadmin' ] ) )
		add_action( 'init', 'redirect_to_admin' );
}
add_action( 'admin_init', 'redirect_admin' );
if ( isset( $_GET[ 'dm' ] ) )
	add_action( 'template_redirect', 'remote_login_js' );

function redirect_to_admin() {
	if ( isset( $_GET[ 'dm_gotoadmin' ] ) && is_user_logged_in() ) {
		wp_redirect( site_url( "wp-admin/" ) );
		exit;
	}
}

function remote_logout_loader() {
	global $current_site, $current_blog, $wpdb;
	$wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';
	$protocol = ( 'on' == strtolower( $_SERVER['HTTPS' ] ) ) ? 'https://' : 'http://';
	$hash = get_dm_hash();
	$wpdb->insert( $wpdb->dmtablelogins, array( 'user_id' => 0, 'blog_id' => $current_blog->blog_id, 't' => time() ) );
	$key = $wpdb->insert_id;
	wp_redirect( $protocol . $current_site->domain . $current_site->path . "?dm={$hash}&action=logout&blogid={$current_blog->blog_id}&k={$key}&t=" . mt_rand() );
	exit;
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

function get_dm_hash() {
	$remote_login_hash = get_site_option( 'dm_hash' );
	if ( null == $remote_login_hash ) {
		$remote_login_hash = md5( time() );
		update_site_option( 'dm_hash', $remote_login_hash );
	}
	return $remote_login_hash;
}

function remote_login_js() {
	global $current_blog, $current_user, $wpdb;
	$wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';
	$hash = get_dm_hash();
	$protocol = ( 'on' == strtolower( $_SERVER['HTTPS' ] ) ) ? 'https://' : 'http://';
	if ( $_GET[ 'dm' ] == $hash ) {
		if ( $_GET[ 'action' ] == 'load' ) {
			if ( !is_user_logged_in() )
				exit;
			$key = md5( time() . mt_rand() );
			$wpdb->insert( $wpdb->dmtablelogins, array( 'id' => $key, 'user_id' => $current_user->ID, 'blog_id' => $_GET[ 'blogid' ], 't' => time() ) );
			$url = add_query_arg( array( 'action' => 'login', 'dm' => $hash, 'k' => $key, 't' => mt_rand() ), $_GET[ 'back' ] );
			echo "window.location = '$url'";
			exit;
		} elseif ( $_GET[ 'action' ] == 'login' ) {
			if ( $details = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtablelogins} WHERE id = %s AND blog_id = %d", $_GET[ 'k' ], $wpdb->blogid ) ) ) {
				if ( $details->blog_id == $wpdb->blogid ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtablelogins} WHERE id = %s", $_GET[ 'k' ] ) );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtablelogins} WHERE t < %d", ( time() - 120 ) ) ); // remote logins survive for only 2 minutes if not used.
					wp_set_auth_cookie( $details->user_id );
					wp_redirect( remove_query_arg( array( 'dm', 'action', 'k', 't', $protocol . $current_blog->domain . $_SERVER[ 'REQUEST_URI' ] ) ) );
					exit;
				} else {
					wp_die( "Incorrect or out of date login key" );
				}
			} else {
				wp_die( "Unknown login key" );
			}
		} elseif ( $_GET[ 'action' ] == 'logout' ) {
			if ( $details = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtablelogins} WHERE id = %d AND blog_id = %d", $_GET[ 'k' ], $_GET[ 'blogid' ] ) ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtablelogins} WHERE id = %s", $_GET[ 'k' ] ) );
				$blog = get_blog_details( $_GET[ 'blogid' ] );
				wp_clear_auth_cookie();
				wp_redirect( trailingslashit( $blog->siteurl ) . "wp-login.php?loggedout=true" );
				exit;
			} else {
				wp_die( "Unknown logout key" );
			}
		}
	}
}

function remote_login_js_loader() {
	global $current_site, $current_blog;
	if ( is_user_logged_in() )
		return false;
	$protocol = ( 'on' == strtolower( $_SERVER['HTTPS' ] ) ) ? 'https://' : 'http://';
	$hash = get_dm_hash();
	echo "<script src='{$protocol}{$current_site->domain}{$current_site->path}?dm={$hash}&action=load&blogid={$current_blog->blog_id}&siteid={$current_blog->site_id}&t=" . mt_rand() . "&back=" . urlencode( $protocol . $current_blog->domain . $_SERVER[ 'REQUEST_URI' ] ) . "' type='text/javascript'></script>";
}

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
