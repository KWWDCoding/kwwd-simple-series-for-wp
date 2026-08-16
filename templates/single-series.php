<?php
/**
 * Template for a single series page (/series/{series-slug}/).
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="kwwd-series-page">
    <?php echo KWWD_Series()->get_series_page_html(get_queried_object_id()); ?>
</div>
<?php
get_footer();
