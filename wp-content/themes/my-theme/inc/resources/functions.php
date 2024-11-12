<?php

/**
 * Function to generate a filtered URL maintaining existing parameters
 * @param array $new_params New parameters to add/update
 * @return string URL with all parameters
 */
function get_filtered_url($new_params = array())
{
  // Get current parameters
  $current_params = array();

  // Maintain category parameter
  if (isset($_GET['category'])) {
    $current_params['category'] = sanitize_text_field($_GET['category']);
  }

  // Maintain author parameter
  if (isset($_GET['auth'])) {
    $current_params['auth'] = sanitize_text_field($_GET['auth']);
  }

  // Maintain search parameter
  if (isset($_GET['keyword-search'])) {
    $current_params['keyword-search'] = sanitize_text_field($_GET['keyword-search']);
  }

  // Maintain type parameter
  if (isset($_GET['type'])) {
    $current_params['type'] = sanitize_text_field($_GET['type']);
  }

  // Always add nonce
  $current_params['resources_nonce'] = wp_create_nonce('resources_filter');

  // Merge with new parameters (new ones will override existing ones)
  $params = array_merge($current_params, $new_params);

  // Generate URL
  return add_query_arg($params, get_permalink());
}

/**
 * Validate and sanitize request parameters
 * @return array Sanitized parameters
 */
function get_sanitized_resource_params()
{
  return array(
    'category' => isset($_GET['category']) ? sanitize_text_field($_GET['category']) : null,
    'auth' => isset($_GET['auth']) ? absint($_GET['auth']) : null,
    'type' => isset($_GET['type']) ? sanitize_key($_GET['type']) : null,
    'keyword-search' => isset($_GET['keyword-search']) ? sanitize_text_field($_GET['keyword-search']) : null,
    'paged' => isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1
  );
}

/**
 * Handle rate limiting for searches
 * @return bool|WP_Error
 */
function handle_search_rate_limit()
{
  $user_ip = $_SERVER['REMOTE_ADDR'];
  $rate_key = 'search_rate_' . md5($user_ip);
  $search_count = get_transient($rate_key);

  // Allow 10 searches per minute
  $max_searches = 10;
  $time_window = MINUTE_IN_SECONDS;

  if ($search_count === false) {
    set_transient($rate_key, 1, $time_window);
    return true;
  }

  if ($search_count >= $max_searches) {
    return new WP_Error(
      'rate_limit_exceeded',
      sprintf(
        __('Search limit of %d requests per minute exceeded. Please try again later.', 'rde01'),
        $max_searches
      )
    );
  }

  set_transient($rate_key, $search_count + 1, $time_window);
  return true;
}

/**
 * Handle resource query errors
 * @param WP_Query $query Query object
 * @return void|WP_Error
 */
function handle_resource_query_errors($query)
{
  if (!$query->have_posts()) {
    return new WP_Error(
      'no_results',
      __('No resources found matching your criteria.', 'rde01')
    );
  }

  if ($query->found_posts > 1000) {
    error_log(sprintf(
      'Large resource query detected: %d posts. Query args: %s',
      $query->found_posts,
      json_encode($query->query_vars)
    ));
  }

  return null;
}

/**
 * Function to get categories with their children
 * @param int $parent_id
 * @return array
 */
function get_categories_with_children($parent_id = 0, $types)
{
  // Get published posts of our specific types
  $posts = get_posts(array(
    'post_type' => $types,
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'fields' => 'ids'  // Only get post IDs for better performance
  ));

  // Get all categories used by these posts
  $used_category_ids = array();
  foreach ($posts as $post_id) {
    $post_categories = wp_get_post_categories($post_id);
    $used_category_ids = array_merge($used_category_ids, $post_categories);
  }
  $used_category_ids = array_unique($used_category_ids);

  // If no categories are used, return empty array
  if (empty($used_category_ids)) {
    return array();
  }

  // Get categories for this level that are actually in use
  $categories = get_terms(array(
    'taxonomy' => 'category',
    'hide_empty' => true,
    'parent' => $parent_id,
    'include' => $used_category_ids
  ));

  $result = array();

  foreach ($categories as $category) {
    $children = get_categories_with_children($category->term_id, $types);

    if ($category->count > 0 || !empty($children)) {
      $category->children = $children;
      $result[] = $category;
    }
  }

  return $result;
}

/**
 * Function to get all resource authors
 * @return array Array of unique authors
 */
function get_all_authors($types)
{
  $authors = array();
  $unique_authors = array();

  // Get all published posts of specific types
  $posts = get_posts(array(
    'post_type' => $types,
    'posts_per_page' => -1,
    'post_status' => 'publish'
  ));

  foreach ($posts as $post) {
    $author_objects = get_field('authored_by', $post->ID);

    if (is_array($author_objects)) {
      foreach ($author_objects as $author) {
        if (is_object($author) && isset($author->ID)) {
          $author_id = $author->ID;

          if (!isset($unique_authors[$author_id])) {
            $author_name = get_field('person_name', $author_id);

            if ($author_name) {
              $authors[] = array(
                'authorID' => $author_id,
                'authorName' => $author_name
              );
              $unique_authors[$author_id] = true;
            }
          }
        }
      }
    }
  }

  // Sort authors by name
  usort($authors, function ($a, $b) {
    return strcmp($a['authorName'], $b['authorName']);
  });

  return $authors;
}

/**
 * Function to get all category slugs
 * @param array $categories
 * @return array
 */
function get_all_category_slugs($categories)
{
  $slugs = array();
  foreach ($categories as $category) {
    $slugs[] = strtolower($category->slug);
    if (!empty($category->children)) {
      $slugs = array_merge($slugs, get_all_category_slugs($category->children));
    }
  }
  return array_unique($slugs);
}

/**
 * Check which filters would return results
 */
function check_available_results($selected_types, $current_filters)
{
  // Create a cache key based on the current filters and selected types
  $cache_key = 'available_results_' . md5(serialize($selected_types) . serialize($current_filters));
  $available = wp_cache_get($cache_key);

  if (false === $available) {
    $available = array(
      'types' => array(),
      'authors' => array(),
      'categories' => array()
    );

    // Base query args without the filter we're checking
    $base_args = array(
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'fields' => 'ids'
    );

    // Add current filters except the one we're checking
    if (!empty($current_filters['category']) && isset($current_filters['checking']) && $current_filters['checking'] !== 'category') {
      $base_args['tax_query'] = array(
        array(
          'taxonomy' => 'category',
          'field' => 'slug',
          'terms' => $current_filters['category']
        )
      );
    }

    if (!empty($current_filters['auth']) && isset($current_filters['checking']) && $current_filters['checking'] !== 'author') {
      $base_args['meta_query'] = array(
        array(
          'key' => 'authored_by',
          'value' => serialize(strval($current_filters['auth'])),
          'compare' => 'LIKE'
        )
      );
    }

    if (!empty($current_filters['type']) && isset($current_filters['checking']) && $current_filters['checking'] !== 'type') {
      $base_args['post_type'] = $current_filters['type'];
    } else {
      $base_args['post_type'] = $selected_types;
    }

    // Get all relevant posts based on current filters
    $filtered_posts = get_posts($base_args);

    // Check available post types
    if (!isset($current_filters['checking']) || $current_filters['checking'] === 'type') {
      foreach ($selected_types as $post_type) {
        $type_posts = array_filter($filtered_posts, function ($post) use ($post_type) {
          return get_post_type($post) === $post_type;
        });

        if (!empty($type_posts)) {
          $available['types'][] = $post_type;
        }
      }
    }

    // Check available categories
    if (!isset($current_filters['checking']) || $current_filters['checking'] === 'category') {
      foreach ($filtered_posts as $post_id) {
        $post_categories = wp_get_post_categories($post_id, array('fields' => 'all'));
        foreach ($post_categories as $category) {
          if ($category->slug !== 'uncategorized' && !in_array($category->slug, $available['categories'])) {
            $available['categories'][] = $category->slug;
          }
        }
      }
    }

    // Check available authors
    if (!isset($current_filters['checking']) || $current_filters['checking'] === 'author') {
      foreach ($filtered_posts as $post_id) {
        $author_objects = get_field('authored_by', $post_id);
        if (is_array($author_objects)) {
          foreach ($author_objects as $author) {
            if (is_object($author) && isset($author->ID)) {
              if (!in_array($author->ID, $available['authors'])) {
                $available['authors'][] = $author->ID;
              }
            }
          }
        }
      }
    }

    // Debug output for admins
    if (WP_DEBUG && current_user_can('manage_options')) {
      echo '<pre>';
      echo 'Current Filters: ';
      print_r($current_filters);
      echo "\nAvailable Options: ";
      print_r($available);
      echo '</pre>';
    }

    wp_cache_set($cache_key, $available, '', HOUR_IN_SECONDS);
  }

  return $available;
}

/**
 * Function to print categories list with links
 * @param array $categories
 * @param int $level
 */
function print_categories_list($categories, $level = 0)
{
  // Get available filter options based on current selection
  $current_filters = array(
    'checking' => 'category',
    'type' => isset($_GET['type']) ? $_GET['type'] : null,
    'auth' => isset($_GET['auth']) ? $_GET['auth'] : null
  );

  $available = check_available_results(get_sub_field('resource_types'), $current_filters);

  if ($level === 0) {
    // "All Categories" link using get_filtered_url
    $url = get_filtered_url(array('category' => null));
    echo '<li><a href="' . esc_url($url) . '">'
      . esc_html__('All Categories', 'rde01') . '</a></li>';
  }

  foreach ($categories as $category) {
    if ($category->slug !== 'uncategorized') {
      $indent = str_repeat('&nbsp;', $level * 4);

      if (in_array($category->slug, $available['categories'])) {
        $url = get_filtered_url(array('category' => $category->slug));
        echo '<li>' . $indent . '<a href="' . esc_url($url) . '">'
          . esc_html($category->name) . '</a></li>';
      } else {
        echo '<li class="disabled">' . $indent . '<span>'
          . esc_html($category->name) . '</span></li>';
      }

      if (!empty($category->children)) {
        print_categories_list($category->children, $level + 1);
      }
    }
  }
}

/**
 * Function to print a list of all resource authors
 * @param array $authors
 */
function print_authors_list($authors)
{
  // Get available filter options based on current selection
  $current_filters = array(
    'checking' => 'author',
    'category' => isset($_GET['category']) ? $_GET['category'] : null,
    'type' => isset($_GET['type']) ? $_GET['type'] : null
  );

  $available = check_available_results(get_sub_field('resource_types'), $current_filters);

  // "All Authors" link using get_filtered_url
  $url = get_filtered_url(array('auth' => null));
  echo '<li><a href="' . esc_url($url) . '">'
    . esc_html__('All Authors', 'rde01') . '</a></li>';

  foreach ($authors as $author) {
    if ($author['authorName'] !== 'none') {
      if (in_array($author['authorID'], $available['authors'])) {
        $url = get_filtered_url(array('auth' => $author['authorID']));
        echo '<li><a href="' . esc_url($url) . '">'
          . esc_html($author['authorName']) . '</a></li>';
      } else {
        echo '<li class="disabled"><span>'
          . esc_html($author['authorName']) . '</span></li>';
      }
    }
  }
}

/**
 * Function to limit text length
 * @param string $string
 * @param int $length
 */
function trunctate_text($string, $length)
{
  $string = strip_tags($string);
  if (strlen($string) > $length) {
    // truncate string
    $stringCut = substr($string, 0, $length);
    $endPoint = strrpos($stringCut, ' ');

    // if the string doesn't contain any space then it will cut without word basis.
    $string = $endPoint ? substr($stringCut, 0, $endPoint) : substr($stringCut, 0);
    $string .= '...';
  }
  return $string;
}

/**
 * Function to print a list of all resource types
 * @param array $selected_types
 */
function print_types_list($selected_types)
{
  // Get available filter options based on current selection
  $current_filters = array(
    'checking' => 'type',
    'category' => isset($_GET['category']) ? $_GET['category'] : null,
    'auth' => isset($_GET['auth']) ? $_GET['auth'] : null
  );

  $available = check_available_results(get_sub_field('resource_types'), $current_filters);

  // "All Types" link using get_filtered_url
  $url = get_filtered_url(array('type' => null));
  echo '<li><a href="' . esc_url($url) . '">' . esc_html__('All Types', 'rde01') . '</a></li>';

  foreach ($selected_types as $post_type) {
    if (in_array($post_type, $available['types'])) {
      $url = get_filtered_url(array('type' => $post_type));
      $obj = get_post_type_object($post_type);
      if ($obj) {
        $label = $obj->labels->name === 'Posts' ? 'Blog Posts' : $obj->labels->name;
        printf(
          '<li><a href="%s">%s</a></li>',
          esc_url($url),
          esc_html($label)
        );
      }
    } else {
      $obj = get_post_type_object($post_type);
      if ($obj) {
        $label = $obj->labels->name === 'Posts' ? 'Blog Posts' : $obj->labels->name;
        echo '<li class="disabled"><span>' . esc_html($label) . '</span></li>';
      }
    }
  }
}
