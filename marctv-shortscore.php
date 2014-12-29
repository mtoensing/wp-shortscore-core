<?php

/*
Plugin Name: MarcTV ShortScore
Plugin URI: http://marctv.de/blog/marctv-wordpress-plugins/
Description: Extends the comment fields by a review score field.
Version:  0.2
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
        load_plugin_textdomain('marctv-shortscore', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        $this->initComments();

        $this->initFrontend();

    }

    public function my_get_posts( $query ) {

        if ( is_home() && $query->is_main_query() )
            $query->set( 'post_type', array( 'post', 'page', 'game' ) );

        return $query;
    }

    public function initFrontend()
    {
        add_action('wp_print_styles', array($this, 'enqueScripts'));
        add_filter('pre_get_posts', array($this, 'filter_search'));
    }

    public function enqueScripts()
    {
        wp_enqueue_style($this->pluginPrefix . '_style', plugins_url(false, __FILE__) . "/marctv-shortscore.css", false, $this->version);
        wp_enqueue_script($this->pluginPrefix . '-js', plugins_url(false, __FILE__) . "/marctv-shortscore.js", array("jquery"), $this->version, true);

    }



    public function filter_search($query) {



        return $query;
    }




    public function initComments()
    {
        add_filter('pre_comment_content', 'esc_html');
        add_filter('comment_form_defaults', array($this, 'change_comment_form_defaults'));
        add_filter('the_content', array($this, 'append_content_to_post'));
        add_action('comment_post', array($this, 'save_comment_meta_data'));
        add_filter('preprocess_comment', array($this, 'verify_comment_meta_data'));

        add_filter('preprocess_comment', array($this, 'verify_comment_duplicate_email'));

        add_filter('comment_form_default_fields', array($this, 'alter_comment_form_fields'));
        add_filter('comment_text', array($this, 'append_score'), 99);
        add_filter('post_class', array($this, 'add_hreview_aggregate_class'));
        add_filter('the_title', array($this, 'add_hreview_title'));

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
       // global $post;

        //if (get_post_type($post->ID) == 'game' && is_single()) {
           // $title = '<span class="fn">' . $title . '</span>';
        //}

        return $title;
    }


    public function change_comment_form_defaults($default)
    {

        global $post;

        if (get_post_type($post->ID) == 'game') {

            $markup = '<p class="comment-form-score"><label for="score">' . __('ShortScore 1 to 10 (e.g. 7.5)', 'marctv-shortscore') . '<span class="required">*</span></label><select id="score" name="score">';

            for ($i = 1; $i <= 100; $i++) {
                if ($i == 50 ) {
                    $markup .= '<option size="4" selected="selected" value="' . $i / 10 . '">' . $i / 10 . '</option>';
                } else {
                    $markup .= '<option size="4" value="' . $i / 10 . '">' . $i / 10 . '</option>';
                }
            }

            $markup .= '</select>';

            $default['comment_field'] = $markup . $default['comment_field'];
        }


        return $default;
    }


    public function append_content_to_post($content)
    {
        $id = get_the_ID();

        $score_count = get_post_meta($id, 'score_count', true);

        if (get_post_type($id) == 'game') {

            if ($score_count > 0) {
                $score_sum = get_post_meta($id, 'score_sum', true);


                $aggregate_score = round($score_sum / $score_count, 1);

                $markup = '<p></p><span class="rating">';
                $markup .= sprintf(__('%s out of %s based on %s reviews', 'marctv-shortscore'),
                    '<span class="average shortscore">' . $aggregate_score . '</span>',
                    '<span class="best">10</span>',
                    '<span class="count">' . $score_count . '</span>'
                );
                $markup .= '</span></p>';
                $markup .= '<p>' . sprintf(__('<a href="%s">Submit your ShortScore</a>!', 'marctv-shortscore'), esc_url(get_permalink($id) . '#respond')) . '</p>';
            } else {
                $markup = '<p>' . sprintf(__('No ShortScore yet! Be the first to <a href="%s">submit your ShortScore</a>!', 'marctv-shortscore'), esc_url(get_permalink($id) . '#respond')) . '</p>';
            }


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

    public function verify_comment_duplicate_email($commentdata)
    {
        global $post;

        $email = $commentdata["comment_author_email"];

        $args = array(
            'status' => 'approve',
            'post_id' => $post->ID
        );

        $comments = get_comments($args);

        foreach ($comments as $comment) :
            if ($email == $comment->comment_author_email) {
                wp_die(__('Sorry, this email address already submitted a shortscore for this game.', 'marctv-shortscore'));
            }
        endforeach;


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
