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
        <form method="post">
            <input type="hidden" name="action" value="flush" />
            <input type="hidden" name="plugin" value="<?php echo $this->get_plugin_slug(); ?>" />
            <?php wp_nonce_field($this->get_settings_nonce_key()) ?>
            <?php do_action('c3cf_form_hidden_fields'); ?>

            <table class="form-table">
                <?php $distribution_id = $this->get_setting('distribution_id'); ?>
                <tr class="">
                    <td>
                        <?php if($distribution_id){ ?>
                        <h4><?php _e('CloudFront Distribution ID:', 'wp-amazon-c3-cloudfront-clear-cache') ?></h4>
                        <?php echo $distribution_id; ?>
                        <?php } else { ?>
                            <h4><?php _e('CloudFront Distribution ID needs to be defined in wp-config.php', 'wp-amazon-c3-cloudfront-clear-cache') ?></h4>
                        <?php } ?>

                    </td>
                </tr>
                <tr>
                    <td>
                        <?php if ($distribution_id){ ?>
                        <p>
                            <button type="submit" class="button button-primary" ><?php _e('Flush all', 'wp-amazon-c3-cloudfront-clear-cache'); ?></button>
                        </p>
                        <?php } ?>

                    </td>
                </tr>
            </table>

        </form>
    </div>
    <?php if ($distribution_id){ ?>
    <?php $this->render_view('list-invalidations') ?>
    <?php } ?>


</div>


<?php do_action('c3cf_after_settings'); ?>
