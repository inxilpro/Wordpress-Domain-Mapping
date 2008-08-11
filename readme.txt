=== WordPress MU Domain Mapping ===
Contributors: donncha
Tags: wordpressmu, domain-mapping
Tested up to: 2.6
Stable tag: 0.2
Requires at least: 1.5.1

Map any blog on a WordPress MU website to an external domain.

== Description ==
This plugin allows users of a WordPress MU site to map their blog to another domain.

The user should go to Manage->Domain Mapping where they can add or delete domains.

Remote login is not included in this release. A user can be logged in on the main site but not logged in on the domain mapped one, or may even be logged in as another user.

== Installation ==
1. Copy sunrise.php into wp-content/. If there is a sunrise.php there already, you'll just have to merge them as best you can.
2. Copy domain_mapping.php into wp-content/mu-plugins/.
3. Edit wp-config.php and uncomment the SUNRISE definition line:
    `define( 'SUNRISE', 'on' );`
4. As a "site admin", visit Manage->Domain Mapping to create the domain mapping database table and set the server IP address.
5. Make sure the default Apache virtual host points at your WordPress MU site so it will handle unknown domains correctly. (Need info on cpanel, etc. How do you get them to respond to any domain?)
