<?php

/**
 * Created by PhpStorm.
 * User: janneaalto
 * Date: 23/01/2017
 * Time: 20.45
 */
class C3_CloudFront_Clear_Cache extends AWS_Plugin_Base {

    /**
     * @var Amazon_Web_Services
     */
    private $aws;

    /**
     * @var Aws\CloudFront\CloudFrontClient
     */
    private $c3client;

    /**
     * @var string
     */
    protected $plugin_title;

    /**
     * @var string
     */
    protected $plugin_menu_title;

    /**
     * @var array
     */
    protected static $admin_notices = [];

    /**
     * @var
     */
    protected static $plugin_page;

    /**
     * @var string
     */
    protected $plugin_prefix = 'c3cf';

    /**
     * @var string
     */
    protected $default_tab = '';

    /**
     * @var string
     */
    public $hook_suffix;

    const SETTINGS_KEY = 'tantan_wordpress_s3';
    const SETTINGS_CONSTANT = 'WPOS3_SETTINGS';

    /**
     * @param string $plugin_file_path
     * @param Amazon_Web_Services $aws
     * @param string|null $slug
     */
    function __construct($plugin_file_path, $aws, $slug = null) {
        $this->plugin_slug = (is_null($slug)) ? 'c3-cloudfront-clear-cache' : $slug;

        parent::__construct($plugin_file_path);

        $this->aws = $aws;
        $this->init($plugin_file_path);
    }

    /**
     * Abstract class constructor
     *
     * @param string $plugin_file_path
     */
    function init($plugin_file_path) {
        self::$plugin_page = $this->plugin_slug;
        $this->plugin_title = __('C3 Cloudfront Cache Controller', 'c3-cloudfront-clear-cache');
        $this->plugin_menu_title = __('CloudFront Cache Controller', 'c3-cloudfront-clear-cache');

        // Plugin setup
        add_action('aws_admin_menu', [$this, 'admin_menu']);
        add_filter('plugin_action_links', [$this, 'plugin_actions_settings_link'], 10, 2);

        //cron hook
        add_action('c3cf_cron_invalidation', [$this, 'cron_invalidation']);

        //update hook
        add_action('save_post', [$this, 'save_post_invalidation'], 25, 3);
        add_action('wp_update_nav_menu', [$this, 'navigation_invalidation'], 25, 2);

        //fixes
        add_action('template_redirect', [$this, 'template_redirect']);
        add_filter('post_link', [$this, 'post_link_fix'], 10, 3);
        add_filter('preview_post_link', [$this, 'preview_post_link_fix'], 10, 2);
        add_filter('the_guid', [$this, 'the_guid']);

        load_plugin_textdomain('c3-cloudfront-clear-cache', false, dirname(plugin_basename($plugin_file_path)) . '/languages/');

        // Register modal scripts and styles

    }

    function update_last_invalidation_time(){
        return update_option('c3cf_last_invalidation_time', time());
    }

    function invalidate_now(){

        $last_ran = get_option('c3cf_last_invalidation_time', 0);

        if($last_ran + MINUTE_IN_SECONDS * 10 < time() && !get_option('c3cf_cron_scheduled')){
            return true;
        }

        return false;

    }

    function set_cron_scheduled($value = true){

        return update_option('c3cf_cron_scheduled', $value);

    }

    function set_cron_items($items = []){

        $curr_items = get_option('c3cf_cron_items', []);
        $items = array_unique(array_merge($items, $curr_items));

        return update_option('c3cf_cron_items', $items);

    }

    function get_cron_items(){

        $items = get_option('c3cf_cron_items', []);

        return $items;

    }

    function clear_cron_data(){

        update_option('c3cf_cron_scheduled', false);
        update_option('c3cf_cron_items', []);
        $this->update_last_invalidation_time();

        return true;

    }

    /**
     * Get the plugin title to be used in page headings
     *
     * @return string
     */
    function get_plugin_page_title() {
        return apply_filters('c3cf_settings_page_title', $this->plugin_title);
    }

    /**
     * Get the plugin prefix in slug format, ie. replace underscores with hyphens
     *
     * @return string
     */
    function get_plugin_prefix_slug() {
        return str_replace('_', '-', $this->plugin_prefix);
    }

    /**
     * Get the nonce key for the settings form of the plugin
     *
     * @return string
     */
    function get_settings_nonce_key() {
        return $this->get_plugin_prefix_slug() . '-save-settings';
    }

    /**
     * Accessor for a plugin setting with conditions to defaults and upgrades
     *
     * @param string $key
     * @param mixed $default
     *
     * @return int|mixed|string|WP_Error
     */
    function get_setting($key, $default = '') {

        $settings = $this->get_settings();

        $value = parent::get_setting($key, $default);

        // Bucket
        if (false !== ($distribution_id = $this->get_setting_distribution_id($key, $value))) {
            return $distribution_id;
        }

        return apply_filters('c3cf_setting_' . $key, $value);
    }

    /**
     * Get the bucket and if a constant save to database and clear region
     *
     * @param string $key
     * @param  string $value
     * @param string $constant
     *
     * @return string|false
     */
    public function get_setting_distribution_id($key, $value, $constant = 'DISTRIBUTION_ID') {

        if ('distribution_id' === $key && defined($constant)) {
            $distribution_id = constant($constant);

            if (!empty($value)) {
                // Clear bucket
                $this->remove_setting('distribution_id');
                $this->save_settings();
            }

            return $distribution_id;
        }

        return false;
    }

    /**
     * Get the C3 client
     *
     * @param bool $force force return of new C3 client when swapping regions
     *
     * @return Aws\CloudFront\CloudFrontClient
     */
    function get_c3client($force = false) {

        if (is_null($this->c3client) || $force) {

            $args = [];

            $client = $this->aws->get_client()->get('CloudFront', $args);

            $this->set_client($client);

        }

        return $this->c3client;

    }

    /**
     * Setter for C3 client
     *
     * @param Aws\CloudFront\CloudFrontClient $client
     */
    public function set_client($client) {
        $this->c3client = $client;
    }

    public function list_invalidations() {

        $lists = [];

        if (!$this->get_setting('distribution_id')) {
            return $lists;
        }

        $c3client = $this->get_c3client();
        $items = $c3client->listInvalidations([
            'DistributionId' => $this->get_setting('distribution_id'),
            'MaxItems' => apply_filters('c3_max_invalidation_logs', 25),
        ]);

        if ($items->get('Quantity')) {
            return $items->get('Items');
        }

        return false;

    }

    public function create_invalidation_array($items) {

        if (!$this->get_setting('distribution_id')) {
            return false;
        }

        return [
            'DistributionId' => esc_attr($this->get_setting('distribution_id')),
            'Paths' => [
                'Quantity' => count($items),
                'Items' => $items,
            ],
            'CallerReference' => uniqid(),
        ];
    }

    public function flush_all() {

        $items = ['/*'];
        $items = apply_filters('c3cf_modify_flush_all_items', $items);

        $invalidation_array = $this->create_invalidation_array($items);

        if ($invalidation_array) {

            $c3client = $this->get_c3client();

            //everything flushed
            $this->clear_cron_data();
            $this->clear_scheduled_event();

            return $c3client->createInvalidation($invalidation_array);

        }

        return false;

    }

    /**
     * Wrapper for scheduling  cron jobs
     *
     * @param string $hook
     * @param array $args
     */
    public function schedule_single_event($hook = 'c3cf_cron_invalidation', $args = []) {

        // Always schedule events on primary blog
        $this->switch_to_blog();

        if (!wp_next_scheduled($hook)) {

            $timestamp = time() * MINUTE_IN_SECONDS * 5;
            wp_schedule_single_event($timestamp, $hook, $args);

        }

        $this->restore_current_blog();
    }

    /**
     * Wrapper for scheduling  cron jobs
     *
     * @param string $hook
     * @param null|string $interval Defaults to hook if not supplied
     * @param array $args
     */
    public function schedule_event($interval = null, $hook = 'c3cf_cron_invalidation', $args = []) {

        if (is_null($interval)) {
            $interval = $hook;
        }

        // Always schedule events on primary blog
        $this->switch_to_blog();

        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $interval, $hook, $args);
        }

        $this->restore_current_blog();

    }

    /**
     * Wrapper for clearing scheduled events for a specific cron job
     *
     * @param string $hook
     */
    public function clear_scheduled_event($hook = 'c3cf_cron_invalidation') {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }

        if (is_multisite()) {
            // Always clear schedule events on primary blog
            $this->switch_to_blog();

            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }

            $this->restore_current_blog();
        }
    }

    /**
     * Helper to switch to a Multisite blog
     *  - If the site is MS
     *  - If the blog is not the current blog defined
     *
     * @param int|bool $blog_id
     */
    public function switch_to_blog($blog_id = false) {
        if (!is_multisite()) {
            return;
        }

        if (!$blog_id) {
            $blog_id = defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : 1;
        }

        if ($blog_id !== get_current_blog_id()) {
            switch_to_blog($blog_id);
        }
    }

    /**
     * Helper to restore to the current Multisite blog
     */
    public function restore_current_blog() {
        if (is_multisite()) {
            restore_current_blog();
        }
    }

    public function get_path($url){
        $parse_url = parse_url($url);
        return isset($parse_url['path']) ? $parse_url['path'] : '';
    }

    public function save_post_invalidation($post_id, $post, $update){

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$update || get_post_type($post_id) == 'nav_menu_item') {
            return;
        }

        $items = [];

        $path = trailingslashit($this->get_path(get_the_permalink($post_id))) . '*';

        $parents = get_post_ancestors($post_id);
        $id = ($parents) ? $parents[ count($parents) - 1 ] : $post_id;

        if($id != $post_id){
            $path = trailingslashit($this->get_path(get_the_permalink($id))) . '*';
        }

        $items = [$path];

        if (!is_int($post_id)) {
            $items = [];
        }

        $items = apply_filters('c3cf_modify_post_save_items', $items, $post_id, $post, $update);

        //@todo validate paths
        if (!empty($items)) {

            $this->invalidate($items);

        }

        return true;

    }

    public function navigation_invalidation($menu_id, $menu_data = false) {

        if (!empty($menu_data)) {

            //assume that navigation is on every page
            $items = ['/*'];
            $items = apply_filters('c3cf_modify_navigation_items', $items, $menu_id, $menu_data);

            //@todo validate paths
            if (!empty($items)) {

                $this->invalidate($items);

            }

            return true;

        }

        return;

    }

    public function invalidate($items = []){

        if (!empty($items)) {

            if ($this->invalidate_now()) {

                $items = array_unique(array_merge($items,  $this->get_cron_items()));
                $invalidation_array = $this->create_invalidation_array($items);

                if ($invalidation_array) {

                    $c3client = $this->get_c3client();

                    //everything flushed
                    $this->clear_cron_data();

                    return $c3client->createInvalidation($invalidation_array);

                }

            } else {

                $this->set_cron_items($items);
                $this->set_cron_scheduled();
                $this->schedule_single_event();

            }

        }

        return false;

    }

    public function cron_invalidation(){

        $items = $this->get_cron_items();

        $items = apply_filters('c3cf_modify_cron_items', $items);

        if(empty($items)){
            return;
        }
        $invalidation_array = $this->create_invalidation_array($items);

        if ($invalidation_array) {

            $c3client = $this->get_c3client();
            $this->clear_cron_data();

            return $c3client->createInvalidation($invalidation_array);

        }

        return false;

    }

    /**
     * Add the settings menu item
     *
     * @param Amazon_Web_Services $aws
     */
    function admin_menu($aws) {
        $hook_suffix = $aws->add_page($this->get_plugin_page_title(), $this->plugin_menu_title, 'manage_options', $this->plugin_slug, [
            $this,
            'render_page'
        ]);

        if (false !== $hook_suffix) {
            $this->hook_suffix = $hook_suffix;
            add_action('load-' . $this->hook_suffix, [$this, 'plugin_load']);
        }
    }

    function plugin_load() {
        $version = $this->get_asset_version();
        $suffix = $this->get_asset_suffix();

        $this->handle_post_request();

        do_action('c3cf_plugin_load');
    }

    /**
     * Handle the saving of the settings page
     */
    function handle_post_request() {
        if (empty($_POST['plugin']) || $this->get_plugin_slug() != sanitize_key($_POST['plugin'])) { // input var okay
            return;
        }

        if (empty($_POST['action']) || 'flush' != sanitize_key($_POST['action'])) { // input var okay
            return;
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), $this->get_settings_nonce_key())) { // input var okay
            die(__("Cheatin' eh?", 'c3-cloudfront-clear-cache'));
        }

        do_action('c3cf_pre_flush');
        $response = $this->flush_all();

        $url = $this->get_plugin_page_url(['flushed' => '1']);
        wp_redirect($url);
        exit;
    }

    /**
     * Display the main settings page for the plugin
     */
    function render_page() {
        $this->aws->render_view('header', ['page_title' => $this->get_plugin_page_title(), 'page' => 'c3cf']);

        $aws_client = $this->aws->get_client();

        if (is_wp_error($aws_client)) {
            $this->render_view('error-fatal', ['message' => $aws_client->get_error_message()]);
        } else {
            do_action('c3cf_pre_settings_render');
            $this->render_view('settings');
            do_action('c3cf_post_settings_render');
        }

        $this->aws->render_view('footer');
    }

    /**
     * Helper to display plugin details
     *
     * @param string $plugin_path
     * @param string $suffix
     *
     * @return string
     */
    function get_plugin_details($plugin_path, $suffix = '') {
        $plugin_data = get_plugin_data($plugin_path);
        if (empty($plugin_data['Name'])) {
            return basename($plugin_path);
        }

        return sprintf("%s%s (v%s) by %s\r\n", $plugin_data['Name'], $suffix, $plugin_data['Version'], strip_tags($plugin_data['AuthorName']));
    }

    /**
     * Helper to remove the plugin directory from the plugin path
     *
     * @param string $path
     *
     * @return string
     */
    function remove_wp_plugin_dir($path) {

        $plugin = str_replace(WP_PLUGIN_DIR, '', $path);

        return substr($plugin, 1);

    }

    public function template_redirect() {
        if (is_user_logged_in()) {
            nocache_headers();
        }
    }

    public function post_link_fix($permalink, $post, $leavename) {
        if (!is_user_logged_in() || !is_admin() || is_feed()) {
            return $permalink;
        }
        $post = get_post($post);
        $post_time = isset($post->post_modified) ? date('YmdHis', strtotime($post->post_modified)) : current_time('YmdHis');
        $permalink = add_query_arg('post_date', $post_time, $permalink);

        return $permalink;
    }

    public function preview_post_link_fix($permalink, $post) {
        if (is_feed()) {
            return $permalink;
        }
        $post = get_post($post);
        $preview_time = current_time('YmdHis');
        $permalink = add_query_arg('preview_time', $preview_time, $permalink);

        return $permalink;
    }

    public function the_guid($guid) {
        $guid = preg_replace('#\?post_date=[\d]+#', '', $guid);

        return $guid;
    }

}