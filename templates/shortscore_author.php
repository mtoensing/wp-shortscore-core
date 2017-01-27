<?php
/**
 * The template for displaying profile pages
 *
 * @package WordPress
 * @subpackage ShortScore - Twenty_Fifteen Child
 * @since Twenty Fifteen 1.0
 */

get_header();

$curauth = (isset($_GET['author_name'])) ? get_user_by('slug', $author_name) : get_userdata(intval($author));


/* sort by score */
$args = array(
    'status' => 'approve',
    'user_id' => $curauth->data->ID,
    'meta_key' => 'score',
    'orderby' => 'meta_value_num',
);

$comments = get_comments($args);

?>
    <div class="wrap">
        <div id="primary" class="content-area">
            <main id="main" class="site-main" role="main">

                <article class="page type-page status-publish has-post-thumbnail hentry">

                    <header class="entry-header full-width">
                        <h1 class="page-title">
                            <?php printf(__("%s's SHORTSCORES", 'shortscore_theme'), $curauth->nickname); ?>
                        </h1>
                        <p><?php _e('Sorted by highest SHORTSCORE', 'shortscore_theme'); ?></p>
                    </header>

                    <div class="entry-content full-width">
                        <!-- .page-header -->

                        <?php if (!empty($comments)) : ?>

                            <h2><?php printf(__("%s's favourites", 'marctv-shortscore'), $curauth->nickname); ?>
                                <sup>beta</sup></h2>
                            <p><?php
                                printf(__("Genres</br>%s", 'marctv-shortscore'), MarcTVShortScore::get_favourite_terms($comments, 'genre', 3))
                                ?></p>

                            <p><?php
                                printf(__("Platforms</br> %s", 'marctv-shortscore'), MarcTVShortScore::get_favourite_terms($comments, 'platform', 3))
                                ?></p>
                            <p><?php
                                printf(__("Developers</br> %s", 'marctv-shortscore'), MarcTVShortScore::get_favourite_terms($comments, 'developer', 3))
                                ?></p>
                </article>

                        <?php endif; ?>
                        <div id="comments" class="comments-area">
                            <h2 class="comments-title">
                                <?php
                                if (count($comments) <= 0) {
                                    _e('No SHORTSCORES yet', 'shortscore_theme');
                                } else {
                                    printf(_nx('One SHORTSCORE by &ldquo;%2$s&rdquo;', '%1$s SHORTSCORES by &ldquo;%2$s&rdquo;', count($comments), 'comments title', 'shortscore_theme'),
                                        number_format_i18n(count($comments)), $curauth->nickname);
                                }
                                ?>
                            </h2>

                            <?php
                            if (count($comments) <= 0) {
                                echo '<p>' . sprintf(__('There are no SHORTSCORES by &ldquo;%s&ldquo;', 'shortscore_theme'), $curauth->nickname) . '</p>';
                                echo get_search_form();
                            }
                            ?>
                            </article>
                            <ol class="comment-list">
                                <?php

                                wp_list_comments(array(
                                    'style' => 'ol',
                                    'short_ping' => true,
                                    'avatar_size' => 56,
                                    'per_page' => 0,
                                ), $comments);

                                ?>
                            </ol>
                            <!-- .comment-list -->

                        </div>
                        <!-- .comments-area -->
                    </div>


            </main><!-- #main -->
        </div><!-- #primary -->
    </div><!-- .wrap -->

<?php get_footer();