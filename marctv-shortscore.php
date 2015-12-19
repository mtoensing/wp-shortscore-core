<?php

/*
Plugin Name: MarcTV ShortScore
Plugin URI: http://marctv.de/blog/marctv-wordpress-plugins/
Description: Extends the comment fields by a review score field and alters queries.
Version:  0.8
Author:  Marc Tönsing
Author URI: marctv.de
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class MarcTVShortScore
{

    private $version = '0.8';
    private $pluginPrefix = 'marctv-shortscore';
    private $shortscore_explained_url = 'http://shortscore.org/faq/#calculation';

    public function __construct()
    {
        load_plugin_textdomain('marctv-shortscore', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        $this->initComments();

        $this->initFrontend();

        $this->initSorting();
    }

    public function initSorting()
    {
        add_action('pre_get_posts', array($this, 'my_modify_main_query'));

    }

    public function initFrontend()
    {
        add_filter('get_comment_author_link', array($this, 'comment_author_profile_link'));

        add_shortcode('list_top_authors', array($this, 'list_top_authors'));
    }

    public function initComments()
    {
        add_filter('pre_comment_content', 'esc_html');
        add_filter('comment_form_defaults', array($this, 'changeCommentformDefaults'));

        add_action('comment_post', array($this, 'saveCommentMetadata'));
        add_action('comment_post', array($this, 'saveCommentMetadata'));
        add_action('edit_comment', array($this, 'saveCommentMetadata'));
        add_action('deleted_comment', array($this, 'saveCommentMetadata'));
        add_action('trashed_comment', array($this, 'saveCommentMetadata'));
        add_action('wp_insert_comment', array($this, 'saveCommentMetadata'));

        add_action('transition_comment_status', array($this, 'comment_approved_check'), 10, 3);

        add_filter('preprocess_comment', array($this, 'verify_comment_meta_data'));

        add_filter('the_content', array($this, 'addShortScoreLink'));

        add_filter('preprocess_comment', array($this, 'verify_comment_duplicate_email'));

        add_filter('comment_form_default_fields', array($this, 'alter_comment_form_fields'));
        add_filter('comment_text', array($this, 'append_score'), 99);
        add_filter('post_class', array($this, 'add_hreview_aggregate_class'));
        //add_filter('the_title', array($this, 'add_hreview_title'));

        add_action('add_meta_boxes_comment', array($this, 'comment_add_meta_box'));
        add_action('edit_comment', array($this, 'comment_edit_function'));


    }


    /*
     * http://www.mdj.us/web-development/php-programming/calculating-the-median-average-values-of-an-array-with-php/
     *
     * */
    public function calculateMedian($arr)
    {
        sort($arr);
        $count = count($arr); //total numbers in array
        $middleval = floor(($count - 1) / 2); // find the middle value, or the lowest middle value
        if ($count % 2) { // odd number, middle is the median
            $median = $arr[$middleval];
        } else { // even number, calculate avg of 2 medians
            $low = $arr[$middleval];
            $high = $arr[$middleval + 1];
            $median = (($low + $high) / 2);
        }
        return $median;
    }


    public function comment_approved_check($new_status, $old_status, $comment)
    {

        if ($old_status != $new_status) {
            $this->saveCommentMetadata($comment->comment_ID);
        }
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
        $id = get_the_ID();

        if ($id >= 0 && get_post_type($id) == 'game' && is_single()) {
            $title = '<span class="fn">' . $title . '</span>';
        }

        return $title;
    }


    public function list_top_authors($atts)
    {
        $query = "SELECT comment_author as name,user_id as user_id, COUNT(*) as count
		FROM wp_comments WHERE comment_approved ='1' AND comment_type ='' GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 10";

        global $wpdb;

        $result = $wpdb->get_results($query);
        $markup = '<ol>';

        foreach ($result as $author) {
            $uid = $author->user_id;
            if ($uid > 0) {
                $user = get_userdata($uid);
                $markup .= '<li>';
                $markup .= '<a href="' . get_author_posts_url($uid) . '">' . $user->nickname . '</a> ';
                $markup .= 'hat ' . $author->count . ' SHORTSCORES  geschrieben.';
                $markup .= '</li>';
            }
        }

        $markup .= '</ol>';

        return $markup;

    }

    public static function getReleaseDate()
    {
        $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';

        if (get_the_time('U') !== get_the_modified_time('U')) {
            $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
        }

        $time_string = sprintf($time_string,
            esc_attr(get_the_date('c')),
            get_the_date(),
            esc_attr(get_the_modified_date('c')),
            get_the_modified_date()
        );

        $releasedate = sprintf('<p class="posted-on"><span class="label">%1$s</span> <span class="screen-reader-text">%1$s </span>%2$s</p>',
            _x('Initial release date:', 'Used before publish date.', 'marctv-shortscore'),
            $time_string
        );

        return $releasedate;
    }

    public function changeCommentformDefaults($default)
    {

        $post_id = get_the_ID();



        if (get_post_type($post_id) == 'game') {

            $permalink = get_permalink($post_id);

            $markup = '<p class="comment-form-score"><label for="score">' . __('SHORTSCORE 1 to 10. The higher the better.', 'marctv-shortscore') . '<span class="required">*</span></label>';

            $markup .= '<select id="score" name="score">';
            for ($i = 1; $i <= 10; $i++) {
                if ($i == 5) {
                    $markup .= '<option size="4"  value="' . $i . '">' . $i . '</option>';
                    $markup .= '<option size="4" selected="selected" value="">?</option>';
                } else {
                    $markup .= '<option size="4" value="' . $i . '">' . $i . '</option>';
                }
            }
            $markup .= '</select>';

            $commenter = wp_get_current_commenter();
            $req = get_option('require_name_email');
            $aria_req = ($req ? " aria-required='true'" : '');
            $default['fields']['email'] = '<p class="comment-form-email"><label for="email">' . __('Email', 'marctv-shortscore') . ($req ? '<span class="required">*</span>' : '') . '</label> ' .
                '<input id="email" name="email" type="text" value="' . esc_attr($commenter['comment_author_email']) .
                '" size="30"' . $aria_req . ' /><span class="email-notice form-allowed-tags">' . __('<strong>Warning: </strong> Your email address needs to be verified!', 'marctv-shortscore') . '</span></p>';
            $default['label_submit'] = __('Submit SHORTSCORE', 'marctv-shortscore');

            $default['must_log_in'] = '<p class="must-log-in">' . sprintf(__('<a class ="btn" href="%1s">sign in</a> or <a class ="btn" href="%2s">register</a>', 'marctv-shortscore'), '/login/?redirect_to=' . $permalink .'#respond', '/register/?redirect_to=' . $permalink .'#respond') . '</p>';

            $default['comment_notes_after'] = '<p class="form-allowed-tags" id="form-allowed-tags">' . __('Each account is allow to post only once per game. You are not allowed to edit your SHORTSCORE afterwards.', 'marctv-shortscore') . '</p>';
            $default['title_reply'] = __('Select your SHORTSCORE', 'marctv-shortscore');
            $default['comment_field'] = '<p class="comment-form-comment"><label for="comment">' . __('Your short review text:', 'marctv-shortscore') . '<span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="4" aria-required="true"></textarea></p>';
            $default['comment_field'] = $default['comment_field'] . $markup;
        }

        return $default;
    }


    public static function getShortScoreCount($id = '')
    {
        if ($id == '') {
            $id = get_the_ID();
        }

        $markup = '';

        $score_count = get_post_meta($id, 'score_count', true);

        $submit_link = esc_url(get_permalink($id));

        $markup .= '<div class="score-notice">';

        if ($score_count > 0) {

            if (is_single()) {
                $markup .= '<a href="' . $submit_link . '#comments">' . sprintf(__('out of %s based on %s', 'marctv-shortscore') . '</strong></a>',
                        '<span class="best">10</span>',
                        '<strong><span class="votes">' . sprintf(_n('one user review', '%s user reviews', $score_count, 'marctv-shortscore'), $score_count) . '</span>'
                    );
            } else {
                $markup .= '<a href="' . $submit_link . '">' . sprintf(__('based on %s', 'marctv-shortscore') . '</strong></a>',
                        '<strong><span class="votes">' . sprintf(_n('one user review', '%s user reviews', $score_count, 'marctv-shortscore'), $score_count) . '</span>'
                    );
            }

        } else {
            if (is_single()) {
                $markup .= '<a href="' . $submit_link . '#comments">' . __('No reviews yet', 'marctv-shortscore') . '</a>';
            } else {
                $markup .= '<a href="' . $submit_link . '">' . __('No reviews yet', 'marctv-shortscore') . '</a>';
            }

        }

        $markup .= '</div>';

        return $markup;
    }

    public static function getShortScore($id = '')
    {
        if ($id == '') {
            $id = get_the_ID();
        }


        if (get_post_type($id) == 'game') {
            $score_count = get_post_meta($id, 'score_count', true);
            if (is_single()) {
                $markup = '<a class="score" href="' . get_permalink($id) . '">';
            } else {
                $markup = '<a class="score" href="' . get_permalink($id) . '">';
            }

            if ($score_count > 0) {

                $shortscore = get_post_meta($id, 'score_value', true);

                $score_int = floor($shortscore);

                $markup .= '<div class="average shortscore shortscore-' . $score_int . '">' . $shortscore . '</div>';

            } else {
                $markup .= '<div class="average shortscore shortscore-0">?</div>';

            }

            $markup .= '</a>';


            return $markup;

        }

        return false;

    }

    public function comment_add_meta_box()
    {
        add_meta_box('score', __('SHORTSCORE'), array($this, 'comment_meta_box_score'), 'comment', 'normal', 'high');
    }

    public function comment_meta_box_score($comment)
    {
        $score = get_comment_meta($comment->comment_ID, 'score', true);

        $markup = '<p><label for="score"><?php __( "value" ); ?></label>';
        $markup .= '<input type="text" name="score" value="' . esc_attr($score) . '"  class="widefat" /></p>';

        echo $markup;
    }

    public function comment_edit_function($comment_id)
    {
        if (isset($_POST['score']))
            update_comment_meta($comment_id, 'score', esc_attr($_POST['score']));
    }


    public function addShortScoreLink($content)
    {
        $id = get_the_ID();

        if (get_post_type($id) == 'game') {

            if (is_single()) {

                $markup = '<div class="rating">';

                $categories_list = get_the_term_list($id, 'platform', '', ', ');
                $markup .= sprintf('<p class="platform"><strong><span class="screen-reader-text">%1$s </span>%2$s</strong></p>',
                    _x('Categories', 'Used before category names.', 'marctv-shortscore'),
                    $categories_list
                );

                $markup .= $this->getShortScore();

                $markup .= $this->getShortScoreCount();

                $markup .= '<p class="shortscore-submit ">' . sprintf(__('<a class="btn" href="%s">Submit ShortScore</a>', 'marctv-shortscore'), esc_url(get_permalink($id) . '#respond')) . '</p>';


                $markup .= '</div>';

                $markup .= '<div class="game-meta rating">';


                $markup .= $this->renderBarChart($id);

                $markup .= $this->getReleaseDate();

                if ($genre_list = get_the_term_list($id, 'genre', '', ', ')) {
                    $markup .= sprintf('<p class="genre"><span class="label">%1$s </span>%2$s</p>',
                        _x('Genre', 'Used before category names.', 'marctv-shortscore') . ':',
                        $genre_list
                    );
                }

                $markup .= '<p>';
                if ($developer_list = get_the_term_list($id, 'developer', '', ', ')) {
                    $markup .= sprintf('<p class="developer"><span class="label">%1$s </span>%2$s</p>',
                        _x('Developer', 'Used before category names.', 'marctv-shortscore') . ':',
                        $developer_list
                    );
                }

                if ($publisher_list = get_the_term_list($id, 'publisher', '', ', ')) {
                    $markup .= sprintf('<p class=" publisher"><span class="label">%1$s </span>%2$s</p>',
                        _x('Publisher', 'Used before category names.', 'marctv-shortscore') . ':',
                        $publisher_list
                    );
                }
                $markup .= '</p>';


                if ($publisher_list = get_the_term_list($id, 'coop', '', ', ')) {
                    $markup .= sprintf('<p class="coop"><span class="label">%1$s </span>%2$s</p>',
                        _x('cooperation mode', 'Used before category names.', 'marctv-shortscore') . ':',
                        $publisher_list
                    );
                }

                if ($publisher_list = get_the_term_list($id, 'players', '', ', ')) {
                    $markup .= sprintf('<p class="players"><span class="label">%1$s </span>%2$s</p>',
                        _x('player count', 'Used before category names.', 'marctv-shortscore') . ':',
                        $publisher_list
                    );
                }

                if ($fps_list = get_the_term_list($id, 'fps', '', ', ')) {
                    $markup .= sprintf('<p class="coop"><span class="label">%1$s </span>%2$s</p>',
                        _x('frame rate', 'Used before category names.', 'marctv-shortscore') . ':',
                        $fps_list
                    );
                }

                $markup .= '</div>';

                $yturl = get_post_meta($id, 'Youtube', true);

                if ($yturl) {
                    $markup .= '<a href="' . $yturl . '" class="embedvideo">' . get_the_title($id) . ' - Trailer</a>';
                }

                $overview = get_post_meta($id, 'Overview', true);

                if(is_string($overview) && $overview != ''){
                    $overview = wpautop($overview);
                    $markup .= '<h2>About ' . get_the_title($id)  . '</h2>';
                    $markup .= '<div class="overview">' . $overview . '</div>';
                }

            } else {
                $markup = '';
            }
            return $content . $markup;
        }

        return $content;
    }

    public function convertScoreDistribution($post_ID)
    {
        $score_distribution = get_post_meta($post_ID, 'score_distribution', true);

        if(is_array($score_distribution)){
            $score_distribution_sum = array_sum($score_distribution);
        }

        if (!empty($score_distribution) && $score_distribution_sum != 0) {

            $score_distribution_percent = array(
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0,
                6 => 0,
                7 => 0,
                8 => 0,
                9 => 0,
                10 => 0
            );

            $score_num = 1;
            foreach ($score_distribution as $score_value) {
                $score_distribution_percent[$score_num] = round($score_value * 100 / $score_distribution_sum, 1);
                $score_num++;
            }

            return $score_distribution_percent;
        } else {
            return false;
        }


    }

    public function renderBarChart($post_ID)
    {
        $score_value = get_post_meta($post_ID, 'score_value', true);
        $score_distribution_percent = $this->convertScoreDistribution($post_ID);
        $score_floor = floor($score_value);
        $score_ceil = ceil($score_value);

        if ($score_value > 0 && !empty($score_distribution_percent)) {


            $score_num = 1;
            $markup = '<p>';
            $markup .= '<span class="label">' . __('score distribution', 'marctv-shortscore') . ': <small>(<a href="' . $this->shortscore_explained_url . '">' . __('what is this?', 'marctv-shortscore') . '</a>)</small></span>';

            //$markup .= '<ol class="score-distribution-chart labels top">';
            //$markup .= '<li class="legend">100%</li>';

            foreach ($score_distribution_percent as $score_percent) {

                if ($score_num == $score_floor || $score_num == $score_ceil) {
                    //$markup .= '<li>&nbsp;</li>';
                } else {
                    //$markup .= '<li>&nbsp;</li>';
                }

                $score_num++;
            }

            //$markup .= '</ol>';

            $score_num = 1;
            $markup .= '<ol class="score-distribution-chart bars">';
            $markup .= '<li class="keep-height"></li>';
            foreach ($score_distribution_percent as $score_percent) {
                $markup .= '<li style="height:' . $score_percent . 'px"></li><!-- -->';
                $score_num++;
            }

            $markup .= '</ol>';
            $markup .= '<ol class="score-distribution-chart labels bottom">';
            //$markup .= '<li class="legend">0%</li>';
            $score_num = 1;
            foreach ($score_distribution_percent as $score_percent) {

                if ($score_num == $score_floor || $score_num == $score_ceil) {
                    $markup .= '<li class="selected"><strong>' . $score_num . '</strong></li>';
                } else {
                    $markup .= '<li>' . $score_num . '</li>';
                }

                $score_num++;
            }

            $markup .= '</ol>';
            $markup .= '</p>';
            return $markup;
        }

        return false;
    }

    public function saveCommentMetadata($comment_id)
    {

        $comment = get_comment($comment_id);


        if (get_post_type($comment->comment_post_ID) == 'game') {
            add_comment_meta($comment_id, 'score', $_POST['score'], true);
            $this->saveRatingsToPost($comment->comment_post_ID);
        }
    }

    public function saveRatingsToPost($post_ID)
    {
        $score_sum = 0;
        $score_count = 0;
        $score_arr = array();
        $score_distribution = array(
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0
        );
        $args = array(
            'status' => 'approve',
            'post_id' => $post_ID,
        );

        $comments = get_comments($args);

        foreach ($comments as $comment) :
            $comment->comment_ID;
            $meta_score = get_comment_meta($comment->comment_ID, 'score', true);

            $score_distribution[round($meta_score)]++;

            if ($meta_score) {
                $score_count++;
                $score_sum = $score_sum + $meta_score;
                $score_arr[] = $meta_score;
            }
        endforeach;

        $this->savePostMeta($post_ID, 'score_distribution', $score_distribution);

        if ($score_sum > 0 && $score_count > 0) {
            /* use median instead of average calculation */

            $score_value = round($this->calculateMedian($score_arr), 1);

            $this->savePostMeta($post_ID, 'score_value', $score_value);
            $this->savePostMeta($post_ID, 'score_sum', $score_sum);
            $this->savePostMeta($post_ID, 'score_count', $score_count);

        } else {
            $this->savePostMeta($post_ID, 'score_value', 0);
            $this->savePostMeta($post_ID, 'score_sum', 0);
            $this->savePostMeta($post_ID, 'score_count', 0);
        }
    }


    public function savePostMeta($post_ID, $meta_name, $meta_value)
    {
        add_post_meta($post_ID, $meta_name, $meta_value, true) || update_post_meta($post_ID, $meta_name, $meta_value);
    }

    public function verify_comment_meta_data($commentdata)
    {
        global $post;

        if (get_post_type($post->ID) == 'game') {
            $score = $_POST['score'];

            if (empty($score)) {
                wp_die(__('Error: please fill the required field (score).', 'marctv-shortscore'));
            }

            if ($score < 0 || $score > 10) {
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
                wp_die(__('Sorry, this account has already submitted a SHORTSCORE for this game.', 'marctv-shortscore'));
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
        $comment_ID = get_comment_ID();

        $comment = get_comment($comment_ID);

        if ($comment_ID) {
            $cid = $comment->user_id;
        }

        if (is_author($cid)) {
            $comment_text = '<h4><a href="' . get_comment_link($comment_ID) . '">' . get_the_title($comment->comment_post_ID) . '</a></h4>';
        }

        $score = get_comment_meta(get_comment_ID(), 'score', true);

        if (!empty($score)) {

            $score_int = floor($score);

            return $comment_text . '<div class="rating shortscore shortscore-' . $score_int . '">' . $score . '</div>';
        }

        return $comment_text;
    }

    public function my_modify_main_query($query)
    {
        if ($query->is_home() && $query->is_main_query()) { // Run only on the homepage
            $query->set('post_type', array('game', 'post'));
        }

        if (!is_admin()) {
            if (($query->is_search() || $query->is_archive()) && $query->is_main_query()) {
                $query->set('post_type', array('game', 'post'));

                $query->set('meta_key', 'score_count');
                $query->set('orderby', 'meta_value_num date');
                $query->set('meta_key', 'score_value');
                $query->set('orderby', 'meta_value_num date');

                $query->set('order', 'DESC');

            }
        }
    }

    public function comment_author_profile_link($authorName)
    {

        $user = get_user_by( 'login', $authorName );

        $return = '<a href="' . get_author_posts_url($user->ID) . '">' . $authorName . '</a>';
        
        return $return;
    }
}


/**
 * Initialize plugin.
 */
new MarcTVShortScore();
