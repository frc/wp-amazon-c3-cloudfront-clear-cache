<?php

if (defined('WP_CLI') && WP_CLI) {

    class CloudfrontCacheCLI extends WP_CLI_Command {

        public function flush() {
            global $amazon_web_services;
            $c3cf   = new C3_CloudFront_Clear_Cache(__FILE__, $amazon_web_services);
            $return = $c3cf->flush_all();
            if ($return != false) {
                WP_CLI::success('Invalidation requested');
            } else {
                WP_CLI::error('Invalidation request failed');
            }
        }
    }

    WP_CLI::add_command('cloudfront-cache', 'CloudfrontCacheCLI');
}

