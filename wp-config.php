<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'drag_drop' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', 'Root@123' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

@ini_set('upload_max_size','256M');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '[omM3zrmX08qhpX^4+gi~]OlCbI-v5I8*JU6MN5K!?wF$yL2z4Ly7a/x7N_N j.e' );
define( 'SECURE_AUTH_KEY',  'inMZ4rYc!s.g%yKcWE_APWLXEO>G2{aAlU_h8^PO=021aMpy/k^te`>vU/E10n P' );
define( 'LOGGED_IN_KEY',    ')-7)Bq$T&2XUrR$xNSX+#<h?_yh>%?OhT OiD>[CX0r0{$;^C;MeIQ_/Jq~w9j$B' );
define( 'NONCE_KEY',        'XjJ~UiA NG^)aF1J<m<&%<x:#;Asfon}{>k;FFctTYQ6^yS`M5p{Lt,M8cB$.t}3' );
define( 'AUTH_SALT',        '%+p`.AUv?^N:yJvs3J8}euyMJ8M#e1](EuOgDF^<`n-;NxF-+Cg)vDpw]^t6+v#j' );
define( 'SECURE_AUTH_SALT', 'PUXK^#a`YG#:t7&@w+2{[h[VIaV`Cy;lC2JFC@3S!Df%_,J|UDX}OC=dSD;g|Gp#' );
define( 'LOGGED_IN_SALT',   '?zo]cT4r5|}c/_e2bJ05i2rPr`$p`x~s:Ek&o5d{> .NR}~!clSpni14W1#T>US$' );
define( 'NONCE_SALT',       'MoG`;NU3OL^8<>Fk:uTTZv$9#l<JW~u6>#nB@D)tNy#6k%jhMkY o87I3h)ZI2PL' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

define('FS_METHOD', 'direct');

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
