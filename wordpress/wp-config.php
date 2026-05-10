<?php
/**
 * The base configuration for WordPress
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress_db' );

/** Database username */
define( 'DB_USER', 'wp_user' );

/** Database password */
define( 'DB_PASSWORD', 'wp_password' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/**#@+
 * Authentication unique keys and salts.
 */
define( 'AUTH_KEY',         'v6u^@#@!_replace_this_with_unique_key_1' );
define( 'SECURE_AUTH_KEY',  'v6u^@#@!_replace_this_with_unique_key_2' );
define( 'LOGGED_IN_KEY',    'v6u^@#@!_replace_this_with_unique_key_3' );
define( 'NONCE_KEY',        'v6u^@#@!_replace_this_with_unique_key_4' );
define( 'AUTH_SALT',        'v6u^@#@!_replace_this_with_unique_key_5' );
define( 'SECURE_AUTH_SALT', 'v6u^@#@!_replace_this_with_unique_key_6' );
define( 'LOGGED_IN_SALT',   'v6u^@#@!_replace_this_with_unique_key_7' );
define( 'NONCE_SALT',       'v6u^@#@!_replace_this_with_unique_key_8' );

$table_prefix = 'wp_';

define( 'WP_DEBUG', false );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';