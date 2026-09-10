<?php

/**
 * WordPress tests configuration for WP Iconizer.
 *
 * Environment variables override the ddev-compatible defaults.
 *
 * @package wp-iconizer
 */

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', getenv('WP_TESTS_DOMAIN') ?: 'wp-iconizer.test');
define('WP_TESTS_EMAIL', 'admin@example.test');
define('WP_TESTS_TITLE', 'WP Iconizer Tests');
define('WP_TESTS_NETWORK_TITLE', 'WP Iconizer Tests Network');
define('WP_TESTS_SUBDOMAIN_INSTALL', true);
define('WP_PHP_BINARY', getenv('WP_PHP_BINARY') ?: PHP_BINARY);
$base = '/';

define('DB_NAME', getenv('WP_TESTS_DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('WP_TESTS_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WP_TESTS_DB_PASSWORD') ?: 'root');
define('DB_HOST', getenv('WP_TESTS_DB_HOST') ?: 'db');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

define('WP_DEBUG', true);

if (! defined('ABSPATH')) {
    // WordPress core the test suite boots against (ddev site root by default).
    define('ABSPATH', getenv('WP_TESTS_WP_ROOT') ?: '/var/www/html/web/wp/');
}
