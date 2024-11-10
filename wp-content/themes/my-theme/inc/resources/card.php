<?php

/**
 * Template part for displaying a single resource card
 *
 * @package rde01
 */

// Exit if accessed directly
defined('ABSPATH') || exit;
?>

<li>
  <div class="resource-card">
    <div class="resource-type">
      <?php
        $card_type = esc_html(get_post_type_object(get_post_type())->labels->singular_name);
        $card_type = $card_type === 'Post' ? 'Blog' : $card_type;
        echo $card_type;
      ?>
    </div>
    <div class="card-thumbnail">
      <?php if (has_post_thumbnail()): ?>
        <?php the_post_thumbnail('medium'); ?>
      <?php endif; ?>
    </div>
    <div class="card-content">
      <?php
        $title = get_the_title();
        $truncated_title = trunctate_text(esc_html($title), MAX_CARD_TITLE_LENGTH);
      ?>
      <h3><?php echo $truncated_title; ?></h3>
      <div class="author">
        <?php
          $authors = get_field('authored_by');
          if ($authors) {
            $author_names = array_map(function ($author) {
              return get_the_title($author);
            }, $authors);
            echo esc_html(implode(', ', $author_names));
          }
        ?>
      </div>
      <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
        <?php echo esc_html(get_the_date()); ?>
      </time>

      <a class="read-more" href="<?php echo esc_url(get_permalink()); ?>">
        <?php esc_html_e('Learn More', 'rde01'); ?>
      </a>
    </div>
  </div>
</li>