<?php

/**
 * Template part for displaying resource filters
 *
 * @package rde01
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

$params = $args['params'] ?? array();
$selected_types = $args['selected_types'] ?? array();
$categories = $args['categories'] ?? array();
$authors = $args['authors'] ?? array();
?>

<aside class="resources-filters">
    <!-- Search form -->
    <form action="<?php echo esc_url(get_permalink()); ?>" method="get" class="keyword-search">
        <label for="keyword-search"><?php esc_html_e('Search Keywords', 'rde01'); ?></label>
        <div class="input-wrapper">
            <input type="text" 
                id="keyword-search" 
                name="keyword-search" 
                class="keyword-search"
                value="<?php echo esc_attr($params['keyword-search'] ?? ''); ?>"
                placeholder="<?php esc_attr_e('Search resources...', 'rde01'); ?>"
            >

            <?php
              // Always include the nonce
              printf(
                '<input type="hidden" name="resources_nonce" value="%s">',
                wp_create_nonce('resources_filter')
              );

              // Add hidden inputs for any active filters
              $filter_params = array('category', 'auth', 'type', 'paged');
              foreach ($filter_params as $param) {
                if (!empty($params[$param])) {
                  printf(
                    '<input type="hidden" name="%s" value="%s">',
                    esc_attr($param),
                    esc_attr($params[$param])
                  );
                }
              }
            ?>

            <button type="submit" class="button inverted">
                <?php
                  $icon_path = get_template_directory() . '/icons/search.svg';
                  if (file_exists($icon_path)) {
                    include $icon_path;
                  }
                ?>
                <span class="screen-reader-text">
                    <?php echo esc_html_x('Search Keywords', 'search keywords button', 'rde01'); ?>
                </span>
            </button>
        </div>
    </form>

    <!-- Categories filter -->
    <div class="categories filter-item">
        <label><?php esc_html_e('Select a category', 'rde01'); ?></label>
        <div class="current-filter-item">
            <?php
              if (!empty($params['category'])) {
                $category = get_category_by_slug($params['category']);
                echo $category ? esc_html($category->name) : esc_html__('All Categories', 'rde01');
              } else {
                esc_html_e('All Categories', 'rde01');
              }
            ?>
        </div>
        <ul class="filter-list">
            <?php print_categories_list($categories); ?>
        </ul>
    </div>

    <!-- Authors filter -->
    <div class="authors filter-item">
        <label><?php esc_html_e('Select an author', 'rde01'); ?></label>
        <div class="current-filter-item">
            <?php
              $author_name = '';
              if (!empty($params['auth'])) {
                $author_name = get_field('person_name', $params['auth']);
              }
              echo esc_html($author_name ?: __('All Authors', 'rde01'));
            ?>
        </div>
        <ul class="filter-list">
            <?php print_authors_list($authors); ?>
        </ul>
    </div>

    <!-- Resource types -->
    <div class="types filter-item">
        <label><?php esc_html_e('Filter by type', 'rde01'); ?></label>
        <div class="current-filter-item">
            <?php
              if (!empty($params['type'])) {
                $post_type_obj = get_post_type_object($params['type']);
                echo $post_type_obj ? esc_html($post_type_obj->labels->name) : esc_html__('Unknown Type', 'rde01');
              } else {
                esc_html_e('All Types', 'rde01');
              }
            ?>
        </div>
        <ul class="filter-list">
            <?php print_types_list($selected_types); ?>
        </ul>
    </div>

    <!-- Clear filters button -->
    <?php if (!empty($params['category']) || !empty($params['auth']) || !empty($params['keyword-search']) || !empty($params['type'])): ?>
        <div class="clear-filters">
            <a href="<?php echo esc_url(get_permalink()); ?>" class="button">
                <?php esc_html_e('Clear Filters', 'rde01'); ?>
            </a>
        </div>
    <?php endif; ?>
</aside>