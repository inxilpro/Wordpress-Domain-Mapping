<?php
/*
Plugin Name: WordPress MU Domain Mapping
Plugin URI: http://ocaoimh.ie/wordpress-mu-domain-mapping/
Description: Map any blog on a WordPress MU website to another domain.
Version: 0.5.1
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
	global $current_site, $wpdb;

	if ( $current_site->path != "/" ) {
		wp_die( __( "The domain mapping plugin only works if the site is installed in /. This is a limitation of how virtual servers work and is very difficult to work around.", 'wordpress-mu-domain-mapping' ) );
	}

	maybe_create_db();

	if ( get_site_option( 'dm_user_settings' ) && $current_site->blog_id != $wpdb->blogid && !dm_sunrise_warning( false ) ) {
		add_management_page( 'Domain Mapping', 'Domain Mapping', 'manage_options', 'domainmapping', 'dm_manage_page' );
	}
	if( is_site_admin() ) {
		add_submenu_page('wpmu-admin.php', 'Domain Mapping', 'Domain Mapping', 'manage_options', 'dm_admin_page', 'dm_admin_page');
		add_submenu_page('wpmu-admin.php', 'Domains', 'Domains', 'manage_options', 'dm_domains_admin', 'dm_domains_admin');
	}
}
add_action( 'admin_menu', 'dm_add_pages' );

// Default Messages for the users Domain Mapping management page
// This can now be replaced by using:
// remove_action('dm_echo_updated_msg','dm_echo_default_updated_msg');
// add_action('dm_echo_updated_msg','my_custom_updated_msg_function');
function dm_echo_default_updated_msg() {
	switch( $_GET[ 'updated' ] ) {
		case "add":
			$msg = __( 'New domain added.', 'wordpress-mu-domain-mapping' );
			break;
		case "exists":
			$msg = __( 'New domain already exists.', 'wordpress-mu-domain-mapping' );
			break;
		case "primary":
			$msg = __( 'New primary domain.', 'wordpress-mu-domain-mapping' );
			break;
		case "del":
			$msg = __( 'Domain deleted.', 'wordpress-mu-domain-mapping' );
			break;
	}
	echo "<div class='updated fade'><p>$msg</p></div>";
}
add_action('dm_echo_updated_msg','dm_echo_default_updated_msg');

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

function dm_domains_admin() {
	global $wpdb, $current_site;
	if ( false == is_site_admin() ) { // paranoid? moi?
		return false;
	}

	dm_sunrise_warning();

	if ( $current_site->path != "/" ) {
		wp_die( sprintf( __( "<strong>Warning!</strong> This plugin will only work if WordPress MU is installed in the root directory of your webserver. It is currently installed in &#8217;%s&#8217;.", "wordpress-mu-domain-mapping" ), $current_site->path ) );
	}

	switch( $_POST[ 'action' ] ) {
		default:
	}
	echo '<h2>' . __( 'Domain Mapping: Domains' ) . '</h2>';
	if ( !empty( $_POST[ 'action' ] ) ) {
		check_admin_referer( 'domain_mapping' );
		switch( $_POST[ 'action' ] ) {
			case "edit":
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtable} WHERE domain = %s", $_POST[ 'domain' ] ) );
				if ( $row ) {
					dm_edit_domain( $row );
				} else {
					echo "<h3>" . __( 'Domain not found', 'wordpress-mu-domain-mapping' ) . "</h3>";
				}
			break;
			case "save":
				if ( $_POST[ 'blog_id' ] != 0 AND 
					$_POST[ 'blog_id' ] != 1 AND 
					null == $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id != %d AND domain = %s", $_POST[ 'blog_id' ], $_POST[ 'domain' ] ) ) 
				) {
					if ( $_POST[ 'orig_domain' ] == '' ) {
						$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->dmtable} ( `blog_id`, `domain`, `active` ) VALUES ( %d, %s, %d )", $_POST[ 'blog_id' ], $_POST[ 'domain' ], $_POST[ 'active' ] ) );
						echo "<p><strong>" . __( 'Domain Add', 'wordpress-mu-domain-mapping' ) . "</strong></p>";
					} else {
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET blog_id = %d, domain = %s, active = %d WHERE domain = %s", $_POST[ 'blog_id' ], $_POST[ 'domain' ], $_POST[ 'active' ], $_POST[ 'orig_domain' ] ) );
						echo "<p><strong>" . __( 'Domain Updated', 'wordpress-mu-domain-mapping' ) . "</strong></p>";
					}
				}
			break;
			case "del":
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtable} WHERE domain = %s", $_POST[ 'domain' ] ) );
				echo "<p><strong>" . __( 'Domain Deleted', 'wordpress-mu-domain-mapping' ) . "</strong></p>";
			break;
			case "search":
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtable} WHERE domain LIKE %s", $_POST[ 'domain' ] ) );
				dm_domain_listing( $rows, sprintf( __( "Searching for %s", 'wordpress-mu-domain-mapping' ), $_POST[ 'domain' ] ) );
			break;
		}
		if ( $_POST[ 'action' ] == 'update' ) {
			add_site_option( 'dm_ipaddress', $_POST[ 'ipaddress' ] );
			add_site_option( 'dm_cname', $_POST[ 'cname' ] );
			add_site_option( 'dm_301_redirect', intval( $_POST[ 'permanent_redirect' ] ) );
		}
	}

	echo "<h3>" . __( 'Search Domains', 'wordpress-mu-domain-mapping' ) . "</h3>";
	echo '<form method="POST">';
	wp_nonce_field( 'domain_mapping' );
	echo '<input type="hidden" name="action" value="search" />';
	echo _e( "Domain:", 'wordpress-mu-domain-mapping' );
	echo " <input type='text' name='domain' value='' /><br />";
	echo "<input type='submit' value='" . __( 'Search', 'wordpress-mu-domain-mapping' ) . "' />";
	echo "</form><br />";
	dm_edit_domain();
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} ORDER BY id DESC LIMIT 0,20" );
	dm_domain_listing( $rows );
}

function dm_edit_domain( $row = false ) {
	if ( is_object( $row ) ) {
		echo "<h3>" . __( 'Edit Domain', 'wordpress-mu-domain-mapping' ) . "</h3>";
	}  else {
		echo "<h3>" . __( 'New Domain', 'wordpress-mu-domain-mapping' ) . "</h3>";
		$row->blog_id = '';
		$row->domain = '';
		$_POST[ 'domain' ] = '';
		$row->active = 1;
	}

	echo "<form method='POST'><input type='hidden' name='action' value='save' /><input type='hidden' name='orig_domain' value='{$_POST[ 'domain' ]}' />";
	wp_nonce_field( 'domain_mapping' );
	echo "<table class='form-table'>\n";
	echo "<tr><th>Blog ID</th><td><input type='text' name='blog_id' value='{$row->blog_id}' /></td></tr>\n";
	echo "<tr><th>Domain</th><td><input type='text' name='domain' value='{$row->domain}' /></td></tr>\n";
	echo "<tr><th>Primary</th><td><input type='checkbox' name='active' value='1' ";
	echo $row->active == 1 ? 'checked=1 ' : ' ';
	echo "/></td></tr>\n";
	echo "</table>";
	echo "<input type='submit' value='" .__( 'Save', 'wordpress-mu-domain-mapping' ). "' /></form><br /><br />";
}

function dm_domain_listing( $rows, $heading = '' ) {
	if ( $rows ) {
		if ( $heading != '' )
			echo "<h3>$heading</h3>";
		echo '<table class="widefat" cellspacing="0"><thead><tr><th>'.__( 'Blog ID', 'wordpress-mu-domain-mapping' ).'</th><th>'.__( 'Domain', 'wordpress-mu-domain-mapping' ).'</th><th>'.__( 'Primary', 'wordpress-mu-domain-mapping' ).'</th><th>'.__( 'Edit', 'wordpress-mu-domain-mapping' ).'</th><th>'.__( 'Delete', 'wordpress-mu-domain-mapping' ).'</th></tr></thead><tbody>';
		foreach( $rows as $row ) {
			echo "<tr><td><a href='wpmu-blogs.php?action=editblog&id={$row->blog_id}'>{$row->blog_id}</a></td><td><a href='http://{$row->domain}/'>{$row->domain}</a></td><td>";
			echo $row->active == 1 ? __( 'Yes',  'wordpress-mu-domain-mapping' ) : __( 'No',  'wordpress-mu-domain-mapping' );
			echo "</td><td><form method='POST'><input type='hidden' name='action' value='edit' /><input type='hidden' name='domain' value='{$row->domain}' />";
			wp_nonce_field( 'domain_mapping' );
			echo "<input type='submit' value='" .__( 'Edit', 'wordpress-mu-domain-mapping' ). "' /></form></td><td><form method='POST'><input type='hidden' name='action' value='del' /><input type='hidden' name='domain' value='{$row->domain}' />";
			wp_nonce_field( 'domain_mapping' );
			echo "<input type='submit' value='" .__( 'Del', 'wordpress-mu-domain-mapping' ). "' /></form>";
			echo "</td></tr>";
		}
		echo '</table>';
	}
}

function dm_admin_page() {
	global $wpdb, $current_site;
	if ( false == is_site_admin() ) { // paranoid? moi?
		return false;
	}

	dm_sunrise_warning();

	if ( $current_site->path != "/" ) {
		wp_die( sprintf( __( "<strong>Warning!</strong> This plugin will only work if WordPress MU is installed in the root directory of your webserver. It is currently installed in &#8217;%s&#8217;.", "wordpress-mu-domain-mapping" ), $current_site->path ) );
	}

	if ( !empty( $_POST[ 'action' ] ) ) {
		check_admin_referer( 'domain_mapping' );
		if ( $_POST[ 'action' ] == 'update' ) {
			add_site_option( 'dm_ipaddress', $_POST[ 'ipaddress' ] );
			if ( intval( $_POST[ 'always_redirect_admin' ] ) == 0 )
				$_POST[ 'dm_remote_login' ] = 0; // disable remote login if redirecting to mapped domain
			add_site_option( 'dm_remote_login', intval( $_POST[ 'dm_remote_login' ] ) );
			add_site_option( 'dm_cname', $_POST[ 'cname' ] );
			add_site_option( 'dm_301_redirect', intval( $_POST[ 'permanent_redirect' ] ) );
			add_site_option( 'dm_redirect_admin', intval( $_POST[ 'always_redirect_admin' ] ) );
			add_site_option( 'dm_user_settings', intval( $_POST[ 'dm_user_settings' ] ) );
		}
	}

	// set up some defaults
	if ( get_site_option( 'dm_remote_login', 'NA' ) == 'NA' )
		add_site_option( 'dm_remote_login', 1 );
	if ( get_site_option( 'dm_redirect_admin', 'NA' ) == 'NA' )
		add_site_option( 'dm_redirect_admin', 1 );
	if ( get_site_option( 'dm_user_settings', 'NA' ) == 'NA' )
		add_site_option( 'dm_user_settings', 1 );
	echo '<h3>' . __( 'Domain Mapping Configuration' ) . '</h3>';
	echo '<form method="POST">';
	echo '<input type="hidden" name="action" value="update" />';
	echo "<p>" . __( "As a site admin on this site you can set the IP address users need to point their DNS A records at <em>or</em> the domain to point CNAME record at. If you don't know what the IP address is, ping this blog to get it." ) . "</p>";
	echo "<p>" . __( "If you use round robin DNS or another load balancing technique with more than one IP, enter each address, separating them by commas." ) . "</p>";
	_e( "Server IP Address: " );
	echo "<input type='text' name='ipaddress' value='" . get_site_option( 'dm_ipaddress' ) . "' /><br />";

	// Using a CNAME is a safer method than using IP adresses for some people (IMHO)
	echo "<p>" . __( "If you prefer the use of a CNAME record, you can set the domain here. This domain must be configured with an A record or ANAME pointing at an IP address. Visitors may experience problems if it is a CNAME of another domain." ) . "</p>";
	echo "<p>" . __( "NOTE, this voids the use of any IP address set above" ) . "</p>";
	_e( "Server CNAME domain: " );
	echo "<input type='text' name='cname' value='" . get_site_option( 'dm_cname' ) . "' /><br />";

	echo "<h3>" . __( 'Domain Options', 'wordpress-mu-domain-mapping' ) . "</h3>";
	echo "<ol><li><input type='checkbox' name='dm_remote_login' value='1' ";
	echo get_site_option( 'dm_remote_login' ) == 1 ? "checked='checked'" : "";
	echo " /> Remote Login</li>";
	echo "<li><input type='checkbox' name='permanent_redirect' value='1' ";
	echo get_site_option( 'dm_301_redirect' ) == 1 ? "checked='checked'" : "";
	echo " /> Permanent redirect (better for your blogger's pagerank)</li>";
	echo "<li><input type='checkbox' name='dm_user_settings' value='1' ";
	echo get_site_option( 'dm_user_settings' ) == 1 ? "checked='checked'" : "";
	echo " /> User domain mapping page</li> ";
	echo "<li><input type='checkbox' name='always_redirect_admin' value='1' ";
	echo get_site_option( 'dm_redirect_admin' ) == 1 ? "checked='checked'" : "";
	echo " /> Redirect administration pages to original blog's domain (remote login disabled if redirect disabled)</li></ol>";
	wp_nonce_field( 'domain_mapping' );
	echo "<input type='submit' value='Save' />";
	echo "</form><br />";
	_e( 'The information you enter here will be shown to your users so they can configure their DNS correctly.' );
}

function dm_handle_actions() {
	global $wpdb;
	if ( !empty( $_POST[ 'action' ] ) ) {
		$domain = $wpdb->escape( preg_replace( "/^www\./", "", $_POST[ 'domain' ] ) );
		if ( $domain == '' ) {
			wp_die( "You must enter a domain" );
		}
		check_admin_referer( 'domain_mapping' );
		do_action('dm_handle_actions_init', $domain);
		switch( $_POST[ 'action' ] ) {
			case "add":
				do_action('dm_handle_actions_add', $domain);
				if( null == $wpdb->get_row( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '$domain'" ) && null == $wpdb->get_row( "SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = '$domain'" ) ) {
					if ( $_POST[ 'primary' ] ) {
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid ) );
					}
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->dmtable} ( `id` , `blog_id` , `domain` , `active` ) VALUES ( NULL, %d, %s, %d )", $wpdb->blogid, $domain, $_POST[ 'primary' ] ) );
					wp_redirect( '?page=domainmapping&updated=add' );
					exit;
				} else {
					wp_redirect( '?page=domainmapping&updated=exists' );
					exit;
				}
			break;
			case "primary":
				do_action('dm_handle_actions_primary', $domain);
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid ) );
				$orig_url = parse_url( get_original_url( 'siteurl' ) );
				if( $domain != $orig_url[ 'host' ] ) {
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 1 WHERE domain = %s", $domain ) );
				}
				wp_redirect( '?page=domainmapping&updated=primary' );
				exit;
			break;
		}
	} elseif( $_GET[ 'action' ] == 'delete' ) {
		$domain = $wpdb->escape( preg_replace( "/^www\./", "", $_GET[ 'domain' ] ) );
		if ( $domain == '' ) {
			wp_die( "You must enter a domain" );
		}
		check_admin_referer( "delete" . $_GET['domain'] );
		do_action('dm_handle_actions_del', $domain);
		$wpdb->query( "DELETE FROM {$wpdb->dmtable} WHERE domain = '$domain'" );
		wp_redirect( '?page=domainmapping&updated=del' );
		exit;
	}

}
if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'domainmapping' )
	add_action( 'admin_init', 'dm_handle_actions' );

function dm_sunrise_warning( $die = true ) {
	if ( !file_exists( ABSPATH . '/wp-content/sunrise.php' ) ) {
		if ( !$die )
			return true;

		if ( is_site_admin() ) {
			wp_die( "Please copy sunrise.php to " . ABSPATH . "/wp-content/sunrise.php and uncomment the SUNRISE definition in " . ABSPATH . "wp-config.php" );
		} else {
			wp_die( "This plugin has not been configured correctly yet." );
		}
		return true;
	} elseif ( !defined( 'SUNRISE' ) ) {
		if ( !$die )
			return true;

		if ( is_site_admin() ) {
			wp_die( "Please uncomment the line <em>//define( 'SUNRISE', 'on' );</em> in your " . ABSPATH . "wp-config.php" );
		} else {
			wp_die( "This plugin has not been configured correctly yet." );
		}
		return true;
	}
	return false;
}


function dm_manage_page() {
	global $wpdb;

	if ( isset( $_GET[ 'updated' ] ) ) {
		do_action('dm_echo_updated_msg');
	}

	dm_sunrise_warning();

	echo "<div class='wrap'><h2>Domain Mapping</h2>";

	if ( false == get_site_option( 'dm_ipaddress' ) && false == get_site_option( 'dm_cname' ) ) {
		if ( is_site_admin() ) {
			echo "Please set the IP address or CNAME of your server in the <a href='wpmu-admin.php?page=dm_admin_page'>site admin page</a>.";
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
		<table><tr><th>Primary</th><th>Domain</th><th>Delete</th></tr>
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
			$url = "{$protocol}{$details[ 'domain' ]}{$details[ 'path' ]}";
			echo "</td><td><a href='$url'>$url</a></td><td style='text-align: center'>";
			if ( $details[ 'domain' ] != $orig_url[ 'host' ] && $details[ 'active' ] != 1 ) {
				echo "<a href='" . wp_nonce_url( "?page=domainmapping&action=delete&domain={$details[ 'domain' ]}", "delete" . $details[ 'domain' ] ) . "'>Del</a>";
			}
			echo "</td></tr>";
			if ( 0 == $primary_found )
				$primary_found = $details[ 'active' ];
		}
		?></table><?php
		echo '<input type="hidden" name="action" value="primary" />';
		echo "<input type='submit' value='Set Primary Domain' />";
		wp_nonce_field( 'domain_mapping' );
		echo "</form>";
		echo "<p>" . __( "* The primary domain cannot be deleted." ) . "</p>";
	}
	echo "<h3>" . __( 'Add new domain' ) . "</h3>";
	echo '<form method="POST">';
	echo '<input type="hidden" name="action" value="add" />';
	echo "http://www.<input type='text' name='domain' value='' />/<br />";
	wp_nonce_field( 'domain_mapping' );
	echo "<input type='checkbox' name='primary' value='1' /> Primary domain for this blog<br />";
	echo "<input type='submit' value='Add' />";
	echo "</form><br />";
	
	if ( get_site_option( 'dm_cname' ) ) {
		$dm_cname = get_site_option( 'dm_cname');
		echo "<p>" . __( 'If you want to redirect a domain you will need to add a DNS "CNAME" record pointing to the following domain name for this server: ' ) . "<strong>" . $dm_cname . "</strong></p>";
		echo "<p>" . __( 'Google have published <a href="http://www.google.com/support/blogger/bin/answer.py?hl=en&answer=58317" target="_blank">instructions</a> for creating CNAME records on various hosting platforms such as GoDaddy and others.' ) . "</p>";
	} else {
		echo "<p>" . __( 'If your domain name includes a hostname like "blog" or some other prefix before the actual domain name you will need to add a CNAME for that hostname in your DNS pointing at this blog URL. "www" does not count because it will be removed from the domain name.' ) . "</p>";
		$dm_ipaddress = get_site_option( 'dm_ipaddress', 'IP not set by admin yet.' );
		if ( strpos( $dm_ipaddress, ',' ) ) {
			echo "<p>" . __( 'If you want to redirect a domain you will need to add DNS "A" records pointing at the IP addresses of this server: ' ) . "<strong>" . $dm_ipaddress . "</strong></p>";
		} else {
			echo "<p>" . __( 'If you want to redirect a domain you will need to add a DNS "A" record pointing at the IP address of this server: ' ) . "<strong>" . $dm_ipaddress . "</strong></p>";
		}
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
		if ( defined( 'DOMAIN_MAPPING' ) ) 
			remove_filter( 'pre_option_' . $url, 'domain_mapping_' . $url );
		$orig_url = get_option( $url );
		if ( isset( $_SERVER['HTTPS' ] ) && 'on' == strtolower( $_SERVER['HTTPS' ] ) ) {
			$orig_url = str_replace( "http://", "https://", $orig_url );
		} else {
			$orig_url = str_replace( "https://", "http://", $orig_url );
		}
		$orig_urls[ $wpdb->blogid ] = $orig_url;
		if ( defined( 'DOMAIN_MAPPING' ) ) 
			add_filter( 'pre_option_' . $url, 'domain_mapping_' . $url );
	}
	return $orig_urls[ $wpdb->blogid ];
}

function domain_mapping_adminurl( $url ) {
	$index = strpos( $url, '/wp-admin' );
	if( $index !== false )
		$url = get_original_url( 'siteurl' ) . substr( $url, $index );
	return $url;
}

function domain_mapping_post_content( $post_content ) {
	global $wpdb;

	$orig_url = get_original_url( 'siteurl' );

	$url = domain_mapping_siteurl( 'NA' );
	if ( $url == 'NA' )
		return $post_content;
	return str_replace( $orig_url, $url, $post_content );
}

function dm_redirect_admin() {
	if ( get_site_option( 'dm_redirect_admin' ) ) {
		// redirect mapped domain admin page to original url
		$url = get_original_url( 'siteurl' );
		if ( false === strpos( $url, $_SERVER[ 'HTTP_HOST' ] ) ) {
			wp_redirect( untrailingslashit( $url ) . $_SERVER[ 'REQUEST_URI' ] );
			exit;
		}
	} else {
		global $current_blog;
		// redirect original url to primary domain wp-admin/ - remote login is disabled!
		$url = domain_mapping_siteurl( false );
		$request_uri = str_replace( $current_blog->path, '/', $_SERVER[ 'REQUEST_URI' ] );
		if ( false === strpos( $url, $_SERVER[ 'HTTP_HOST' ] ) ) {
			wp_redirect( str_replace( '//wp-admin', '/wp-admin', trailingslashit( $url ) . $request_uri ) );
			exit;
		}
	}
}

function redirect_login_to_orig() {
	if ( !get_site_option( 'dm_remote_login' ) || $_GET[ 'action' ] == 'logout' || isset( $_GET[ 'loggedout' ] ) ) {
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
	return str_replace( get_original_url ( 'siteurl' ), get_option( 'siteurl' ), $full_url );
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

	add_filter( 'stylesheet_uri', 'domain_mapping_post_content' );
	add_filter( 'stylesheet_directory', 'domain_mapping_post_content' );
	add_filter( 'stylesheet_directory_uri', 'domain_mapping_post_content' );
	add_filter( 'template_directory', 'domain_mapping_post_content' );
	add_filter( 'template_directory_uri', 'domain_mapping_post_content' );
	add_filter( 'plugins_url', 'domain_mapping_post_content' );
} else {
	add_filter( 'admin_url', 'domain_mapping_adminurl' );
}	
add_action( 'admin_init', 'dm_redirect_admin' );
if ( isset( $_GET[ 'dm' ] ) )
	add_action( 'template_redirect', 'remote_login_js' );

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
	if ( !isset( $_SERVER[ 'HTTPS' ] ) )
		$_SERVER[ 'HTTPS' ] = "off";
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
	echo "<script src='{$protocol}{$current_site->domain}{$current_site->path}?dm={$hash}&amp;action=load&amp;blogid={$current_blog->blog_id}&amp;siteid={$current_blog->site_id}&amp;t=" . mt_rand() . "&amp;back=" . urlencode( $protocol . $current_blog->domain . $_SERVER[ 'REQUEST_URI' ] ) . "' type='text/javascript'></script>";
}

// delete mapping if blog is deleted
function delete_blog_domain_mapping( $blog_id, $drop ) {
	global $wpdb;
	$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
	if ( $blog_id && $drop ) {
		// Get an array of domain names to pass onto any delete_blog_domain_mapping actions
		$domains = $wpdb->get_col( $wpdb->prepare( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id  = %d", $blog_id ) );
		do_action('dm_delete_blog_domain_mappings', $domains);
		
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
					$maps[ $blog->blog_id ][] = $blog->domain;
				}
			}
		}
		if(is_array( $maps[ $blog_id ] ) && count( $maps[ $blog_id ] )) {
			foreach( $maps[ $blog_id ] as $blog ) {
				echo $blog . '<br />';
			}
		}
	}
}
add_action( 'manage_blogs_custom_column', 'ra_domain_mapping_field', 1, 3 );

?>
