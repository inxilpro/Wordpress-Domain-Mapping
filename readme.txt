=== WordPress MU Domain Mapping ===
Contributors: donncha
Tags: wordpressmu, domain-mapping
Tested up to: 2.8.6
Stable tag: 0.4.3
Requires at least: 1.5.1
Donate link: http://ocaoimh.ie/wordpress-plugins/gifts-and-donations/

Map any blog on a WordPress MU website to an external domain.

== Description ==
This plugin allows users of a WordPress MU site to map their blog to another domain.

Site administrators must configure the plugin in Site Admin->Domain Mapping. You must enter the IP or IP addresses (comma deliminated) of your server on this page. The addresses are purely for documentation purposes so the user knows what they are (so users can set up their DNS correctly). They do nothing special in the plugin, they're only printed for the user to see.

Your users should go to Tools->Domain Mapping where they can add or delete domains. One domain must be set as the primary domain for the blog. When mapping a domain, (like 'example.com') your users must create an A record in their DNS pointing at that IP address. They should use multiple A records if your server uses more than one IP address.
If your user is mapping a hostname of a domain (sometimes called a "subdomain") like www.example.com or blog.example.com it's sufficient to create a CNAME record pointing at their blog url (NOT IP address).

The login page will (almost) always redirect back to the original blog's domain for login to ensure the user is logged in on the original site as well as the domain mapped one.

== Changelog ==

= 0.5 =
* Works in VHOST or folder based installs now.
* Remote login added.
* Admin backend redirects to mapped domain by default but can redirect to original blog url.
* Domain redirect can be 301 or 302.
* List multiple mapped domains on site admin blogs page if mapped
* Bug fixes: set blog_id of the current site's main blog in $current_site
* Bug fixes: cache domain maps correctly, blogid, not blog_id in $wpdb.

= 0.4.3 =
* Fixed bug in content filtering, VHOST check done in admin page, not sunrise.php now.

= 0.4.2 =
* Some actions are actually filters
* Change blog url in posts to mapped domain
* Don't redirect the dashboard to the domain mapped url
* Handle multiple domain mappings correctly.
* Don't let someone map an existing blog
* Only redirect the siteurl and home options if DOMAIN MAPPING set
* Delete domain mapping record if blog deleted
* Show mapping on blog's admin page.

= 0.4.1 =
* The admin pagesnon domain mapped blogs were redirected to an invalid url

= 0.4 =
* Redirect admin pages to the domain mapped url. Avoids problems with writing posts and image urls showing at the wrong url. Updated documentation on IP addresses for site admins.

== Installation ==
1. Copy sunrise.php into wp-content/. If there is a sunrise.php there already, you'll just have to merge them as best you can.
2. Copy domain_mapping.php into wp-content/mu-plugins/.
3. Edit wp-config.php and uncomment the SUNRISE definition line:
    `define( 'SUNRISE', 'on' );`
4. As a "site admin", visit Site Admin->Domain Mapping to create the domain mapping database table and set the server IP address.
5. Make sure the default Apache virtual host points at your WordPress MU site so it will handle unknown domains correctly. (Need info on cpanel, etc. How do you get them to respond to any domain?)
