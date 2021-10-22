<?php
/*
 * Plugin Name: WP Amazon C3 Cloudfront Cache Controller
 * Version: 3.0
 * Plugin URI:https://github.com/frc/wp-amazon-c3-cloudfront-clear-cache
 * Description: Cloudfront cache management based on C3 Cloudfront Cache Controller by AMIMOTO and WP Offload S3 Lite by Delicious Brains
 * Author: Janne Aalto, Sanna Nygård, Ahti Nurminen, Lauri Kallioniemi
 * Author URI: https://frantic.com/
 * Text Domain: wp-amazon-c3-cloudfront-clear-cache
 */

$GLOBALS['aws_meta']['wp-amazon-c3-cloudfront-clear-cache']['version'] = '3.0';
$GLOBALS['aws_meta']['amazon-web-services']['supported_addon_versions']['wp-amazon-c3-cloudfront-clear-cache'] = '3.0';

require_once dirname(__FILE__) . '/classes/wp-aws-compatibility-check.php';

global $c3cf_compat_check;
$c3cf_compat_check = new WP_AWS_Compatibility_Check(
    'C3 Cloudfront Cache Controller',
    'wp-amazon-c3-cloudfront-clear-cache',
    __FILE__
);

function c3cf_require_files(){
    $abspath = dirname(__FILE__);
    include_once $abspath . '/classes/wp-aws-plugin-base.php';
    include_once $abspath . '/classes/wp-amazon-c3-cloudfront-clear-cache.php';
    include_once $abspath . '/cli.php';
}

function c3cf_init( $aws ){
    if ( class_exists( 'C3_CloudFront_Clear_Cache' ) ) {
        return;
    }

    global $c3cf_compat_check;

    if (method_exists('WP_AWS_Compatibility_Check', 'is_plugin_active') && $c3cf_compat_check->is_plugin_active('amazon-s3-and-cloudfront-pro/amazon-s3-and-cloudfront-pro.php')) {
        // Don't load if pro plugin installed
        return;
    }


    if (!$c3cf_compat_check->is_compatible()) {
        return;
    }

    c3cf_require_files();
    global $c3cf;
    $c3cf = new C3_CloudFront_Clear_Cache(__FILE__, $aws);
}

add_action( 'init', 'c3cf_init' );

// If AWS still active need to be around to satisfy addon version checks until upgraded.
add_action( 'aws_init', 'c3cf_init', 11 );
