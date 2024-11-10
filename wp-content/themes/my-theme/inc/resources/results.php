<?php

/**
 * Template part for displaying resource results
 *
 * @package rde01
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

$query = $args['query'] ?? null;
$params = $args['params'] ?? array();

if (!$query) {
  return;
}

$paged = $params['paged'] ?? 1;
?>

<div class="results" data-loading="false">
  <!-- Results count -->
  <div class="results-count">
    <?php
      $total_results = $query->found_posts;
      $start_count = (($paged - 1) * RESOURCES_PER_PAGE) + 1;
      $end_count = min($start_count + RESOURCES_PER_PAGE - 1, $total_results);

      printf(
        esc_html__('Showing %1$s-%2$s of %3$s resources', 'rde01'),
        number_format_i18n($start_count),
        number_format_i18n($end_count),
        number_format_i18n($total_results)
      );
    ?>
  </div>

  <!-- Resource list -->
  <ul class="resources-list">
    <?php if ($query->have_posts()): ?>
        <?php while ($query->have_posts()):
          $query->the_post();
          get_template_part('inc/resources/card');
        endwhile; ?>
        <?php wp_reset_postdata(); ?>
    <?php else: ?>
        <li class="no-results">
            <?php esc_html_e('No resources found.', 'rde01'); ?>
        </li>
    <?php endif; ?>
  </ul>

  <!-- Pagination -->
  <?php if ($query->max_num_pages > 1): ?>
    <nav class="pagination" aria-label="<?php esc_attr_e('Resources navigation', 'rde01'); ?>">
      <div class="pagination-links">
        <?php
        // Previous page
        if ($paged > 1) {
          $prev_link = get_filtered_url(array('paged' => $paged - 1));
          printf(
            '<a href="%s" class="prev page-numbers">%s</a>',
            esc_url($prev_link),
            esc_html__('Previous', 'rde01')
          );
        }

        // Page numbers
        $total_pages = $query->max_num_pages;
        $current_page = $paged;

        // Start pages
        for ($i = 1; $i <= min(PAGINATION_END_SIZE, $total_pages); $i++) {
          $link = get_filtered_url(array('paged' => $i));
          printf(
            '<a href="%s" class="page-numbers%s">%s</a>',
            esc_url($link),
            $current_page === $i ? ' current' : '',
            number_format_i18n($i)
          );
        }

        // Middle pages
        for ($i = max(PAGINATION_END_SIZE + 1, $current_page - PAGINATION_MID_SIZE);
          $i <= min($current_page + PAGINATION_MID_SIZE, $total_pages - PAGINATION_END_SIZE);
          $i++) {
          if ($i > PAGINATION_END_SIZE && $i < $current_page - PAGINATION_MID_SIZE) {
            echo '<span class="page-numbers dots">&hellip;</span>';
            $i = $current_page - PAGINATION_MID_SIZE;
            continue;
          }
          $link = get_filtered_url(array('paged' => $i));
          printf(
            '<a href="%s" class="page-numbers%s">%s</a>',
            esc_url($link),
            $current_page === $i ? ' current' : '',
            number_format_i18n($i)
          );
        }

        // End pages
        for ($i = max($total_pages - PAGINATION_END_SIZE + 1, $current_page + PAGINATION_MID_SIZE + 1);
          $i <= $total_pages;
          $i++) {
          if ($i < $total_pages - PAGINATION_END_SIZE && $i > $current_page + PAGINATION_MID_SIZE) {
            echo '<span class="page-numbers dots">&hellip;</span>';
            $i = $total_pages - PAGINATION_END_SIZE;
            continue;
          }
          $link = get_filtered_url(array('paged' => $i));
          printf(
            '<a href="%s" class="page-numbers%s">%s</a>',
            esc_url($link),
            $current_page === $i ? ' current' : '',
            number_format_i18n($i)
          );
        }

        // Next page
        if ($paged < $query->max_num_pages) {
          $next_link = get_filtered_url(array('paged' => $paged + 1));
          printf(
            '<a href="%s" class="next page-numbers">%s</a>',
            esc_url($next_link),
            esc_html__('Next', 'rde01')
          );
        }
        ?>
      </div>
    </nav>
  <?php endif; ?>
</div>