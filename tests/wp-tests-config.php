<?php

/**
 * WordPress tests configuration for Icon Library.
 *
 * Environment variables override the defaults.
 *
 * @package sf-icon-manager
 */

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', getenv('WP_TESTS_DOMAIN') ?: 'sf-icon-manager.test');
define('WP_TESTS_EMAIL', 'admin@example.test');
define('WP_TESTS_TITLE', 'Icon Library Tests');
define('WP_TESTS_NETWORK_TITLE', 'Icon Library Tests Network');
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
    // WordPress core the test suite boots against (set WP_TESTS_WP_ROOT).
    if (! getenv('WP_TESTS_WP_ROOT')) {
        die('WP_TESTS_WP_ROOT must point to the WordPress core checkout.');
    }
    define('ABSPATH', getenv('WP_TESTS_WP_ROOT'));
}
