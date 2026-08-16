<?php
/**
 * Template for the series archive page (/series/).
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="kwwd-series-archive">
    <h1 class="kwwd-series-archive-title"><?php esc_html_e('Series', 'kwwd-simple-series'); ?></h1>
    <?php echo KWWD_Series()->get_series_archive_html(); ?>
</div>
<?php
get_footer();
