<?php

/**
 * Resource Section Template
 *
 * @package rde01
 * @version 1.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Constants
define('RESOURCES_PER_PAGE', 10);
define('PAGINATION_END_SIZE', 2);
define('PAGINATION_MID_SIZE', 2);
define('MAX_CARD_TITLE_LENGTH', 44);

// Verify requirements
if (!function_exists('get_sub_field')) {
  return;
}

// Verify the security nonce when a search is submitted to prevent CSRF attacks
if (isset($_GET['keyword-search'])) {
  if (!isset($_GET['resources_nonce']) || !wp_verify_nonce($_GET['resources_nonce'], 'resources_filter')) {
    wp_die(__('Invalid security token sent.', 'rde01'));
  }
}

// Get all URL parameters (category, author, type, search, page)
// and sanitize for security
$params = get_sanitized_resource_params();

// Implement rate limiting for searches to prevent abuse
if (!empty($params['keyword-search'])) {
  $rate_limit_check = handle_search_rate_limit();
  if (is_wp_error($rate_limit_check)) {
    wp_die($rate_limit_check->get_error_message());
  }
}

/** Get and validate post types from ACF field */
$selected_types = get_sub_field('resource_types');
if (empty($selected_types)) {
  return;
}

// Get categories for these post types (with caching for performance)
$cache_key = 'resource_categories_' . md5(serialize($selected_types));
$categories_with_children = wp_cache_get($cache_key);
if (false === $categories_with_children) {
  $categories_with_children = get_categories_with_children(0, $selected_types);
  wp_cache_set($cache_key, $categories_with_children, '', HOUR_IN_SECONDS);
}

// Get authors who have written content of these types
$authors = get_all_authors($selected_types);

/**
 * Build query arguments based on:
 * - Selected post type (or all allowed types)
 * - Category filter (using tax_query)
 * - Author filter (using meta_query because authors are stored in a custom field)
 * - Search term
 * - Pagination parameters
 */
$query_args = array(
  'post_type' => $params['type'] ? array($params['type']) : $selected_types,
  'posts_per_page' => RESOURCES_PER_PAGE,
  'paged' => $params['paged'],
  'orderby' => 'date',
  'order' => 'DESC',
  'post_status' => 'publish',
  'no_found_rows' => false,  // Keep true if pagination is not needed
  'update_post_meta_cache' => true,
  'update_post_term_cache' => true
);

// Add category query if set
if (!empty($params['category'])) {
  $query_args['tax_query'] = array(
    array(
      'taxonomy' => 'category',
      'field' => 'slug',
      'terms' => $params['category']
    )
  );
}

// Add author query if set
if (!empty($params['auth'])) {
  $query_args['meta_query'] = array(
    array(
      'key' => 'authored_by',
      'value' => serialize(strval($params['auth'])),
      'compare' => 'LIKE'
    )
  );
}

// Add search if set
if (!empty($params['keyword-search'])) {
  $query_args['s'] = $params['keyword-search'];
}

// Debug output for admins
if (WP_DEBUG && current_user_can('manage_options')) {
  echo '<pre>';
  echo 'Query Args: ';
  print_r($query_args);
  echo '</pre>';
}

// Run the query
$query = new WP_Query($query_args);

// Check for errors
$error = handle_resource_query_errors($query);
?>

<div class="container">
    <section class="page-section resources">
        <?php
          // Render filters
          get_template_part('inc/resources/filters', null, array(
            'params' => $params,
            'selected_types' => $selected_types,
            'categories' => $categories_with_children,
            'authors' => $authors
          ));

          // If there's an error, display it
          if (is_wp_error($error)) {
            echo '<div class="results" data-loading="false">';
            echo '<div class="no-results">';
            echo esc_html($error->get_error_message());
            echo '</div>';
            echo '</div>';
          } else {
            // Render results
            get_template_part('inc/resources/results', null, array(
              'query' => $query,
              'params' => $params
            ));
          }
        ?>
    </section>
</div>

<?php
// Add any necessary scripts
wp_enqueue_script('resource-filters');
?>