<?php

/*
Plugin Name: MarcTV ShortScore
Plugin URI: http://marctv.de/blog/marctv-wordpress-plugins/
Description: Extends the comment fields by a review score field.
Version:  0.8
Author:  Marc Tönsing
Author URI: marctv.de
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class MarcTVShortScore
{

    private $version = '0.1';
    private $pluginPrefix = 'marctv-shortscore';

    public function __construct()
    {
        load_plugin_textdomain('marctv-shortscore', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        $this->initComments();

        $this->initFrontend();

        $this->initSorting();
    }

    public function initSorting()
    {
        // Hook my above function to the pre_get_posts action
        add_action('pre_get_posts', array($this, 'my_modify_main_query'));
    }

    public function initFrontend()
    {
        add_action('wp_print_styles', array($this, 'enqueScripts'));
    }

    public function enqueScripts()
    {
        wp_enqueue_style($this->pluginPrefix . '_style', plugins_url(false, __FILE__) . "/marctv-shortscore.css", false, $this->version);
        wp_enqueue_script($this->pluginPrefix . '-js', plugins_url(false, __FILE__) . "/marctv-shortscore.js", array("jquery"), $this->version, true);

    }

    public function initComments()
    {
        add_filter('pre_comment_content', 'esc_html');
        add_filter('comment_form_defaults', array($this, 'change_comment_form_defaults'));
        add_action('comment_post', array($this, 'save_comment_meta_data'));

        add_action('comment_post', array($this, 'save_comment_meta_data'));
        add_action('edit_comment', array($this, 'save_comment_meta_data'));
        add_action('deleted_comment', array($this, 'save_comment_meta_data'));
        add_action('trashed_comment', array($this, 'save_comment_meta_data'));

        add_filter('preprocess_comment', array($this, 'verify_comment_meta_data'));

        add_filter('the_content', array($this, 'addShortScoreLink'));

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


    public static function getShortScore($id ='')
    {
        if($id == '') {
            $id = get_the_ID();
        }


        if (get_post_type($id) == 'game') {
            $score_count = get_post_meta($id, 'score_count', true);

            $markup = '<div class="rating">';

            if ($score_count > 0) {

                $score_sum = get_post_meta($id, 'score_sum', true);

                $aggregate_score = round($score_sum / $score_count, 1);

                $markup .= sprintf(__('%s out of %s based on %s user reviews', 'marctv-shortscore'),
                    '<div class="average shortscore">' . $aggregate_score . '</div>',
                    '<span class="best">10</span>',
                    '<span class="votes">' . $score_count . '</span>'
                );

            } else {
                $markup = '<div class="rating"><div class="average shortscore">?</div>';

            }

            $markup .= '</div>';

            return $markup;
        }

        return false;

    }


    public function addShortScoreLink($content){
        $id = get_the_ID();

        echo $this->getShortScore();

        if (get_post_type($id) == 'game') {

            $markup = '<p class="shortscore-submit ">' . sprintf(__('<a class="btn" href="%s">Submit your ShortScore</a>', 'marctv-shortscore'), esc_url(get_permalink($id) . '#respond')) . '</p>';

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

        $score_value = round($score_sum / $score_count, 1);

        add_post_meta($post_ID, 'score_value', $score_value, true) || update_post_meta($post_ID, 'score_value', $score_value);
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
                return $comment_text . '<div class="rating shortscore">' . $score . '</div>';
            }
        }

        return $comment_text;
    }

    public function my_modify_main_query($query)
{
    if ($query->is_home() && $query->is_main_query()) { // Run only on the homepage
        $query->set('post_type', array('game', 'post'));
    }

    if($query->is_archive() && $query->is_main_query()){
        $query->set('post_type', array('game', 'post'));
        $query->set('meta_key', 'score_value');
        $query->set('orderby', 'meta_value_num date');
        $query->set('order', 'DESC');
    }
}


}


/**
 * Initialize plugin.
 */
new MarcTVShortScore();
