<?php

/**
 * PHPUnit bootstrap: loads Composer, the PHPUnit polyfills, the WordPress
 * test suite (wp-phpunit) and then the plugin under test.
 *
 * @package icon-library
 */

if (! file_exists($autoload = dirname(__DIR__) . '/vendor/autoload.php')) {
    printf("Missing %s - run 'composer install' first.\n", $autoload);
    exit(1);
}

require_once $autoload;

// PHPUnit cross-version compatibility.
require_once dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$wp_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit/includes';

if (! file_exists("{$wp_tests_dir}/bootstrap.php")) {
    printf("Missing the WordPress test library at %s.\n", $wp_tests_dir);
    exit(1);
}

if (! defined('WP_TESTS_CONFIG_FILE_PATH')) {
    define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php');
}

// The WordPress test suite (gives tests access to tests_add_filter()).
require_once "{$wp_tests_dir}/functions.php";

/**
 * Manually loads the plugin being tested.
 */
function _icon_library_manually_load_plugin()
{
    require dirname(__DIR__) . '/icon-library.php';
}
tests_add_filter('muplugins_loaded', '_icon_library_manually_load_plugin');

// Starts up the WordPress testing environment.
require "{$wp_tests_dir}/bootstrap.php";
