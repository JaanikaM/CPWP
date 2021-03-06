<?php
/**
Plugin Name: WP Github Commits
Plugin URI: http://sudarmuthu.com/wordpress/wp-github-commits
Description: Displays the latest commits of a github repo in the sidebar
Author: Sudar
Version: 0.6
Author URI: http://sudarmuthu.com/
Donate Link: http://sudarmuthu.com/if-you-wanna-thank-me
Text Domain: wp-github-commits
Domain Path: languages/

=== RELEASE NOTES ===
Check readme file for full release notes
*/

/*  Copyright 2013  Sudar Muthu  (email : sudar@sudarmuthu.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * The main Plugin class
 *
 * @package WP_Github_Commits
 * @subpackage default
 * @author Sudar
 */
class WP_Github_Commits {

    const CUSTOM_FIELD = 'wp_github_commits_page_fields';
    const CUSTOM_FIELD_TITLE = 'gc_widget_title';
    const CUSTOM_FIELD_USER = 'github_user';
    const CUSTOM_FIELD_REPO = 'github_repo';

    const TITLE_FILTER = 'github-commits-title';
    const CACHE_KEY_SLUG = 'github-commits-';

    /**
     * Initalize the plugin by registering the hooks
     */
    function __construct() {

        // Load localization domain
        load_plugin_textdomain( 'wp-github-commits', false, dirname(plugin_basename(__FILE__)) .  '/languages' );

        // Register hooks and filters
        add_filter(self::TITLE_FILTER, array(&$this, 'filter_title'), 10, 3);

        add_action('admin_menu', array(&$this, 'add_custom_box'));
        add_action('save_post', array(&$this, 'save_postdata'));
    }

    /**
     * filter widget title
     */
    function filter_title($title, $user, $repo) {
        global $post;
        $post_id = $post->ID;

        if ($post_id > 0) {
            $wp_github_commits_page_fields = get_post_meta($post_id, self::CUSTOM_FIELD, TRUE);
            if (isset($wp_github_commits_page_fields) && is_array($wp_github_commits_page_fields)) {
                if ($wp_github_commits_page_fields[self::CUSTOM_FIELD_TITLE] != '') {
                    $title = $wp_github_commits_page_fields[self::CUSTOM_FIELD_TITLE];
                }
            }
        }

        $title = str_replace("[user]", $user, $title);
        $title = str_replace("[repo]", $repo, $title);

        return $title;
    }

    /**
     * Adds the custom section in the edit screens for all post types
     */
    function add_custom_box() {
		$post_types = get_post_types( array(), 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( $post_type->show_ui ) {
                add_meta_box( 'wp_github_commits_page_box', __( 'WP Github Commits Page Fields', 'wp-github-commits' ),
                    array(&$this, 'inner_custom_box'), $post_type->name, 'side' );
			}
        }
    }

    /**
     * Prints the inner fields for the custom post/page section
     */
    function inner_custom_box() {
        global $post;
        $post_id = $post->ID;

        $widget_title = '';
        $github_user = '';
        $github_repo = '';

        if ($post_id > 0) {
            $wp_github_commits_page_fields = get_post_meta($post_id, self::CUSTOM_FIELD, TRUE);

            if (isset($wp_github_commits_page_fields) && is_array($wp_github_commits_page_fields)) {
                $widget_title = $wp_github_commits_page_fields[self::CUSTOM_FIELD_TITLE];
                $github_user = $wp_github_commits_page_fields[self::CUSTOM_FIELD_USER];
                $github_repo = $wp_github_commits_page_fields[self::CUSTOM_FIELD_REPO];
            }
        }
        // Use nonce for verification
?>
        <input type="hidden" name="wp_github_commits_noncename" id="wp_github_commits_noncename" value="<?php echo wp_create_nonce( plugin_basename(__FILE__) );?>" />
        <p>
            <label> <?php _e('Widget Title', 'wp-github-commits'); ?> <input type="text" name="<?php echo self::CUSTOM_FIELD_TITLE; ?>" value ="<?php echo $widget_title; ?>"></label><br>
            <label> <?php _e('Github User', 'wp-github-commits'); ?> <input type="text" name="<?php echo self::CUSTOM_FIELD_USER; ?>" id = "<?php echo self::CUSTOM_FIELD_USER; ?>" value ="<?php echo $github_user; ?>"></label>
            <label> <?php _e('Github Repo', 'wp-github-commits'); ?> <input type="text" name="<?php echo self::CUSTOM_FIELD_REPO; ?>" id = "<?php echo self::CUSTOM_FIELD_REPO; ?>" value ="<?php echo $github_repo; ?>"></label>
        </p>
<?php
    }

    /**
     * When the post is saved, saves our custom data
     * @param string $post_id
     * @return string return post id if nothing is saved
     */
    function save_postdata( $post_id ) {

        // Don't do anything during Autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times

		if ( !array_key_exists('wp_github_commits_noncename', $_POST)) {
			return $post_id;
		}

        if ( !wp_verify_nonce( $_POST['wp_github_commits_noncename'], plugin_basename(__FILE__) )) {
            return $post_id;
        }

        if ( 'page' == $_POST['post_type'] ) {
            if ( !current_user_can( 'edit_page', $post_id )) {
                return $post_id;
            }
        } elseif (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        // OK, we're authenticated: we need to find and save the data

        $fields = array();

        if (isset($_POST[self::CUSTOM_FIELD_TITLE])) {
            $fields[self::CUSTOM_FIELD_TITLE] = $_POST[self::CUSTOM_FIELD_TITLE];
        } else {
            $fields[self::CUSTOM_FIELD_TITLE] = '';
        }

        if (isset($_POST[self::CUSTOM_FIELD_USER])) {
            $fields[self::CUSTOM_FIELD_USER] = $_POST[self::CUSTOM_FIELD_USER];
        } else {
            $fields[self::CUSTOM_FIELD_USER] = '';
        }

        if (isset($_POST[self::CUSTOM_FIELD_REPO])) {
            $fields[self::CUSTOM_FIELD_REPO] = $_POST[self::CUSTOM_FIELD_REPO];
        } else {
            $fields[self::CUSTOM_FIELD_REPO] = '';
        }

        update_post_meta($post_id, self::CUSTOM_FIELD, $fields);
    }

    /**
     * Get the github commits of a repo
     *
     */
    public function get_github_commits($user, $repo, $count = 5) {

        global $post;
        $post_id = $post->ID;

        $output = '';
        $counter = 0;

        if ($user == '' && $repo == '') {
            // Try to get it from custom field
            if ($post_id > 0) {

                $wp_github_commits_page_fields = get_post_meta($post_id, self::CUSTOM_FIELD, TRUE);

                if (isset($wp_github_commits_page_fields) && is_array($wp_github_commits_page_fields)) {
                    $widget_title = $wp_github_commits_page_fields[self::CUSTOM_FIELD_TITLE];
                    $github_user = $wp_github_commits_page_fields[self::CUSTOM_FIELD_USER];
                    $github_repo = $wp_github_commits_page_fields[self::CUSTOM_FIELD_REPO];
                }
            }

            if ($github_user == '' && $github_repo == '') {
                return $output;
            } else {
                $user = $github_user;
                $repo = $github_repo;
            }
        }

        $key = self::CACHE_KEY_SLUG . "$user-$repo";

        if (false === ( $commits = get_transient( $key ) ) ) {
            if(!class_exists('Github_API')){
                require_once dirname(__FILE__) . '/include/class-github-api.php';
            }

            $github_api = new Github_API();
            $commits = $github_api->get_repo_commits($user, $repo);

            set_transient($key, $commits, 5 * HOUR_IN_SECONDS); // 5 hours TODO: Make it configurable
        }

        // TODO: Make it plugable
        $output = '<ul class = "github-commits">';
        foreach($commits as $commit) {
            $counter ++;
            $commit_message = wp_trim_words( $commit->commit->message, 30, '...' );
            $output .= '<li class = "github-commit">';
            $output .= "<a href = 'https://github.com/$user/$repo/commit/{$commit->sha}'>" . $commit_message . '</a> ';
            $output .= __('by', 'wp-github-commits') . " <a href = 'https://github.com/{$commit->commit->author->name}'>" . $commit->commit->author->name . '</a> ';
            $output .= __('on', 'wp-github-commits') . ' ' . date("M d, Y @ H:i", strtotime($commit->commit->author->date));
            $output .= '</li>';

            if ($count == $counter) {
                break;
            }
        }

        $output .= '</ul>';
        return $output;
    }
}

// Start this plugin once all other plugins are fully loaded
add_action( 'init', 'WP_Github_Commits' ); function WP_Github_Commits() { global $wp_github_commits; $wp_github_commits = new WP_Github_Commits(); }

// register WP_Github_Commits_Widget widget
add_action('widgets_init', create_function('', 'return register_widget("WP_Github_Commits_Widget");'));

/**
 * WP_Github_Commits_Widget Class
 *
 * @package WP Github Commits
 * @subpackage widget
 * @author Sudar
 */
class WP_Github_Commits_Widget extends WP_Widget {
    /** constructor */
    function __construct() {
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'WP_Github_Commits_Widget', 'description' => __('Github commits for a user or repo', 'wp-github-commits'));

		/* Widget control settings. */
		$control_ops = array('id_base' => 'wp-github-commits' );

		/* Create the widget. */
		parent::__construct( 'wp-github-commits', __('WP Github Commits', 'wp-github-commits'), $widget_ops, $control_ops );
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {
        extract( $args );

		/* Set up some default widget settings. */
		$defaults = array( 'title' => __('Recent Commits', 'wp-github-commits'), 'user' => '', 'repo' => '');
		$instance = wp_parse_args( (array) $instance, $defaults );

        $title = $instance['title'];
        $user = $instance['user'];
        $repo = $instance['repo'];
        $count = absint($instance['count']);

        $title = apply_filters(WP_Github_Commits::TITLE_FILTER, $title, $user, $repo);
        $widget_content = get_github_commits($user, $repo, $count);

        if ($widget_content != '') {
            echo $before_widget;
            echo $before_title;
            echo $title;
            echo $after_title;
            echo $widget_content;
            echo $after_widget;
        }
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {
		$instance = $old_instance;

        // validate data
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['user'] = strip_tags($new_instance['user']);
        $instance['repo'] = strip_tags($new_instance['repo']);
        $instance['count'] = absint($new_instance['count']);

        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {

		/* Set up some default widget settings. */
		$defaults = array( 'title' => __('Recent Commits', 'wp-github-commits'), 'user' => '', 'repo' => '', 'count' => 5);
		$instance = wp_parse_args( (array) $instance, $defaults );

        $title = esc_attr($instance['title']);
		$user = $instance['user'];
		$repo = $instance['repo'];
        $count = absint($instance['count']);
?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp-github-commits'); ?>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('user'); ?>"><?php _e('User:', 'wp-github-commits'); ?>
            <input class="widefat" id="<?php echo $this->get_field_id('user'); ?>" name="<?php echo $this->get_field_name('user'); ?>" type="text" value="<?php echo $user; ?>" /></label>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('repo'); ?>"><?php _e('Repo:', 'wp-github-commits'); ?>
            <input class="widefat" id="<?php echo $this->get_field_id('repo'); ?>" name="<?php echo $this->get_field_name('repo'); ?>" type="text" value="<?php echo $repo; ?>" /></label>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('No of commits to show:', 'wp-github-commits'); ?>
            <input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo $count; ?>" /></label>
        </p>

<?php
    }
} // class WP_Github_Commits_Widget

/**
 * Template function to display the badge
 *
 * @param string $user
 * @param string $repo
 */
function get_github_commits($user, $repo, $count = 5) {
    global $wp_github_commits;
    return $wp_github_commits->get_github_commits($user, $repo, $count);
}
?>
