<?php
$updated_class = false;
if (isset($_GET['flushed'])) { // input var okay
    $updated_class = 1;
}
$prefix = $this->get_plugin_prefix_slug();
?>
<div class="notice is-dismissible c3cf-updated updated inline " <?php echo (!$updated_class) ? 'style="display:none;"' : ''; ?>>
    <p>
        <?php _e('Cache flushed.', 'wp-amazon-c3-cloudfront-clear-cache'); ?>
    </p>
</div>
<?php
?>
<div id="tab-media" data-prefix="c3cf" class="c3cf-tab aws-content">

    <div class="c3cf-main-settings">
        <table class="form-table">
            <?php $distribution_ids = $this->get_all_distribution_ids(); ?>
            <tr class="">
                <td>
                    <?php if($distribution_ids){ ?>
                        <h4><?php _e('CloudFront Distribution IDs:', 'wp-amazon-c3-cloudfront-clear-cache') ?></h4>
                        <?php echo implode('<br>', $distribution_ids); ?>
                    <?php } elseif($this->has_multiple_language_domains()) { ?>
                        <h4><?php _e('You have multiple domains specified but no distribution IDs for all of them. Check your configuration.', 'wp-amazon-c3-cloudfront-clear-cache') ?></h4>
                    <?php } else { ?>
                        <h4><?php _e('CloudFront Distribution ID needs to be defined in wp-config.php', 'wp-amazon-c3-cloudfront-clear-cache') ?></h4>
                    <?php } ?>

                </td>
            </tr>
            <tr>
                <td>
                    <?php if ($distribution_ids){ ?>
                        <?php if (is_multisite()) {
                            $sites = get_sites();

                            foreach ($sites as $site) { ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="flush" />
                                    <input type="hidden" name="site" value="<?php echo $site->blog_id; ?>" />
                                    <input type="hidden" name="plugin" value="<?php echo $this->get_plugin_slug(); ?>" />
                                    <?php wp_nonce_field($this->get_settings_nonce_key()) ?>
                                    <?php do_action('c3cf_form_hidden_fields'); ?>
                                    <button type="submit" class="button button-primary" ><?php _e('Flush', 'wp-amazon-c3-cloudfront-clear-cache'); ?> <?php echo get_blog_details($site->blog_id)->blogname; ?></button><br><br>
                                </form>
                            <?php } ?>
                        <?php } else { ?>
                            <form method="post">
                                <input type="hidden" name="action" value="flush" />
                                <input type="hidden" name="plugin" value="<?php echo $this->get_plugin_slug(); ?>" />
                                <?php wp_nonce_field($this->get_settings_nonce_key()) ?>
                                <?php do_action('c3cf_form_hidden_fields'); ?>
                                <button type="submit" class="button button-primary" ><?php _e('Flush all', 'wp-amazon-c3-cloudfront-clear-cache'); ?></button>
                            </form>
                        <?php } ?>
                    <?php } ?>

                </td>
            </tr>
        </table>
    </div>
    <?php if ($distribution_ids){ ?>
    <?php $this->render_view('list-invalidations') ?>
    <?php } ?>


</div>


<?php do_action('c3cf_after_settings'); ?>
