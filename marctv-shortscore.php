<?php

/*
Plugin Name: MarcTV ShortScore
Plugin URI: http://marctv.de/blog/marctv-wordpress-plugins/
Description: Extends the comment fields by a review score field.
Version:  0.1
Author:  Marc Tönsing
Author URI: marctv.de
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class MarcTVShortScore
{

    private $version = '0.1';
    private $pluginPrefix = 'marctv-shortscore';
    private $strings;

    public function __construct()
    {

        $this->initComments();

        $this->initDataStructures();

        load_plugin_textdomain('marctv-shortscore', false, dirname(plugin_basename(__FILE__)) . '/language/');
        
    }


    public function initFrontend()
    {
        wp_enqueue_style($this->pluginPrefix . '_style', plugins_url(false, __FILE__) . "/marctv-shortscore.css", false, $this->version);
    }

    public function initDataStructures()
    {
        add_action('init', array($this, 'create_post_type_game'));
        add_action('init', array($this, 'create_plattform_taxonomy'));
        add_action('init', array($this, 'create_genre_taxonomy'));
    }

    public function initComments()
    {
        add_filter('pre_comment_content', 'wp_specialchars');
        add_filter('comment_form_defaults', array($this, 'change_comment_form_defaults'));
        add_filter('the_content', array($this, 'append_content_to_post'));
        add_action('comment_post', array($this, 'save_comment_meta_data'));
        add_filter('preprocess_comment', array($this, 'verify_comment_meta_data'));
        add_filter('comment_form_default_fields', array($this, 'alter_comment_form_fields'));
        add_filter('comment_text', array($this, 'append_score'), 99);
        add_filter('post_class', array($this, 'add_hreview_aggregate_class'));
        add_filter('the_title', array($this, 'add_hreview_title'));

    }

    public function create_post_type_game()
    {
        register_post_type('game',
            array(
                'labels' => array(
                    'name' => __('Games'),
                    'singular_name' => __('Game')
                ),
                'public' => true,
                'taxonomies' => array(),
                'has_archive' => true,
                'supports' => array('title', 'editor', 'thumbnail', 'comments', 'custom-fields')
            )
        );
    }


    public function create_genre_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'genre',
            'game',
            array(
                'label' => __('Genre'),
                'rewrite' => array(
                    'slug' => 'genre'
                ),
            )
        );
    }

    public function create_plattform_taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'plattform',
            'game',
            array(
                'label' => __('Plattform'),
                'rewrite' => array(
                    'slug' => 'plattform',
                    'hierarchical' => true
                ),

            )
        );
    }

    public function create__taxonomy()
    {
        // create a new taxonomy
        register_taxonomy(
            'plattform',
            'shortscore_game',
            array(
                'label' => __('Plattform'),
                'rewrite' => array('slug' => 'plattform'),

            )
        );
    }


    public function add_hreview_aggregate_class($classes)
    {
        global $post;

        if (get_post_type($post->ID) == 'game') {
            $classes[] = 'hreview-aggregate';
        }

        return $classes;
    }


    function add_hreview_title($title)
    {
        global $post;

        if (get_post_type($post->ID) == 'game' && is_single()) {
            $title = '<span class="fn">' . $title . '</span>';
        }

        return $title;
    }


    public function change_comment_form_defaults($default)
    {

        global $post;

        if (get_post_type($post->ID) == 'game') {
            $default['comment_field'] .= '<p class="comment-form-score"><label for="score">' . __('Score (e.g. 7.5)', 'marctv-shortscore') . '<span class="required">*</span></label>
            <input id="score" name="score" min="1" step="0.5" max="10" size="2" type="number" /></p>';
        }


        return $default;
    }


    public function append_content_to_post($content)
    {
        global $post;

        if (get_post_type($post->ID) == 'game') {

            $score_sum = get_post_meta($post->ID, 'score_sum', true);
            $score_count = get_post_meta($post->ID, 'score_count', true);

            $aggregate_score = round($score_sum / $score_count, 1);

            $markup = '<span class="rating"><span class="average shortscore">' . $aggregate_score . '</span> out of <span class="best">10</span>
 based on <span class="count">' . $score_count . '</span> reviews</span>';

            $markup = '<span class="rating">';
            $markup .= sprintf(__('%s out of %s based on %s reviews', 'marctv-shortscore'),
                '<span class="average shortscore">' . $aggregate_score . '</span>',
                '<span class="best">10</span>',
                '<span class="count">' . $score_count . '</span>'
            );
            $markup .= '</span>';

            return $content . $markup;
        }

        return $content;

    }


    public function save_comment_meta_data($comment_id)
    {

        global $post;

        if (get_post_type($post->ID) == 'game') {

            add_comment_meta($comment_id, 'score', $_POST['score']);

            $comment = get_comment($comment_id);
            $post_ID = $comment->comment_post_ID;

            $this->save_ratings_to_post($post_ID);
        }
    }


    public function save_ratings_to_post($post_ID)
    {

        $args = array(
            'status' => 'approve',
            'post_id' => $post_ID,
        );

        $score_sum = 0;
        $score_count = 0;

        $comments = get_comments($args);
        foreach ($comments as $comment) :
            $comment->comment_ID;
            $meta_score = get_comment_meta($comment->comment_ID, 'score', true);

            if ($meta_score) {
                $score_count++;
                $score_sum = $score_sum + $meta_score;
            }

        endforeach;

        add_post_meta($post_ID, 'score_sum', $score_sum, true) || update_post_meta($post_ID, 'score_sum', $score_sum);
        add_post_meta($post_ID, 'score_count', $score_count, true) || update_post_meta($post_ID, 'score_count', $score_count);
    }


    public function verify_comment_meta_data($commentdata)
    {
        global $post;

        if (get_post_type($post->ID) == 'game') {
            $score = $_POST['score'];

            if (empty($score)) {
                wp_die(__('Error: please fill the required field (score).', 'marctv-shortscore'));
            }

            if ($score < 1 || $score > 10) {
                wp_die(__('Error: enter a rating smaller than 10 and greater than 0 for (score).', 'marctv-shortscore'));
            }

        }

        return $commentdata;
    }


    public function alter_comment_form_fields($default)
    {

        global $post;

        if (get_post_type($post->ID) == 'game') {
            $default['url'] = '';  //removes website field
        }

        return $default;
    }


    public function append_score($comment_text)
    {
        global $post;

        if (get_post_type($post->ID) == 'game') {

            $score = get_comment_meta(get_comment_ID(), 'score', true);

            if (!empty($score)) {
                return $comment_text . '<span class="rating shortscore">' . $score . '</span>';
            }
        }

        return $comment_text;
    }


}


/**
 * Initialize plugin.
 */
new MarcTVShortScore();
