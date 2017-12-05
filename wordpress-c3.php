<?php
/*
 * Plugin Name: WP Amazon C3 Cloudfront Cache Controller
 * Version: 1.0.8
 * Plugin URI:https://github.com/JanneAalto/wp-amazon-c3-cloudfront-clear-cache
 * Description: Cloudfront cache management based on C3 Cloudfront Cache Controller by AMIMOTO and WP Offload S3 Lite by Delicious Brains
 * Author: Janne Aalto, Sanna Nygård
 * Author URI: https://frantic.com/
 * Text Domain: wp-amazon-c3-cloudfront-clear-cache
 */

$GLOBALS['aws_meta']['wp-amazon-c3-cloudfront-clear-cache']['version'] = '1.0.0';
$GLOBALS['aws_meta']['amazon-web-services']['supported_addon_versions']['wp-amazon-c3-cloudfront-clear-cache'] = '1.0.0';
$aws_plugin_version_required = '1.0.1';

require_once dirname(__FILE__) . '/classes/wp-aws-compatibility-check.php';

global $c3cf_compat_check;
$c3cf_compat_check = new WP_AWS_Compatibility_Check(
    'C3 Cloudfront Cache Controller',
    'wp-amazon-c3-cloudfront-clear-cache',
    __FILE__,
    'Amazon Web Services',
    'amazon-web-services',
    $aws_plugin_version_required
);

function c3cf_require_files(){
    $abspath = dirname(__FILE__);

    require_once $abspath . '/classes/wp-amazon-c3-cloudfront-clear-cache.php';

}


function c3cf_init( $aws ){
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
    $c3cf = new C3_CloudFront_Clear_Cache( __FILE__, $aws );
}

add_action('aws_init', 'c3cf_init');
