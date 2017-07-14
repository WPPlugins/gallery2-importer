<?php
/*
Plugin Name: Gallery2 Importer
Description: Import albums, images, and descriptions from a Gallery2 installation.
Author: sillybean
Author URI: http://sillybean.net/
Version: 0.3.1
Stable tag: 0.3.1
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

// Load Registration API for new users
require_once(ABSPATH . WPINC . '/registration.php');

/**
 * Gallery2 Importer
 *
 * @package WordPress
 * @subpackage Importer
 */

if ( class_exists( 'WP_Importer' ) ) {
class Gallery2_Import extends WP_Importer {

	var $posts = array ();
	var $comments = array ();
	var $parentmap = array ();

	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>'.__('Import Gallery2', 'g2-importer').'</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {		
		?>
		<div class="narrow">
		<p><?php _e('Howdy! This plugin allows you to import albums, images, and descriptions from a Gallery2 installation into your WordPress site. 
					Albums will be saved as pages containing the [gallery] shortcode, and images will be saved as attachments.', 'g2-importer'); ?></p>
		
		<form name="form1" method="post" action="admin.php?import=gallery2&step=1">
		<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="database"><?php _e('Gallery database name', 'g2-importer') ?></label></th>
			<td><input type="text" name="database" id="database" value="gallery" /></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="username"><?php _e('Gallery database username', 'g2-importer') ?></label></th>
			<td><input type="text" name="username" id="username" value="gallery" /></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="password"><?php _e('Gallery database password', 'g2-importer') ?></label></th>
			<td><input type="text" name="password" id="password" value="gallery" /></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="host"><?php _e('Gallery database host', 'g2-importer') ?></label></th>
			<td><input type="text" name="host" id="host" value="localhost" /></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="tablePrefix"><?php _e('Gallery database table prefix', 'g2-importer') ?></label></th>
			<td><input type="text" name="tablePrefix" id="tablePrefix" value="g2_" /></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="columnPrefix"><?php _e('Gallery database column prefix', 'g2-importer') ?></label></th>
			<td><input type="text" name="columnPrefix" id="columnPrefix" value="g_" /></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="baseurl"><?php _e('Gallery home URL', 'g2-importer') ?></label></th>
			<td><input type="text" name="baseurl" id="baseurl" value="http://" />  <span class="description"><?php _e('e.g. http://example.com/gallery/', 'g2-importer') ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="status"><?php _e('Status for imported pages', 'g2-importer') ?></label></th>
			<td><select name="status" id="status">
					<option value="publish"><?php _e('Published', 'g2-importer') ?></option>
					<option value="draft"><?php _e('Draft', 'g2-importer') ?></option>
					<option value="private"><?php _e('Private', 'g2-importer') ?></option>
					<option value="pending"><?php _e('Pending Approval', 'g2-importer') ?></option>
				</select>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="author"><?php _e('Authors for imported pages', 'g2-importer') ?></label></th>
			<td><select name="author" id="author">
					<option value="create"><?php _e('Import Gallery users', 'g2-importer') ?></option>
					<option value="me"><?php _e('Assign all pages to me', 'g2-importer') ?></option>
				</select>
				<br />
				<span class="description"><?php _e('Gallery users will be matched to WordPress users by email address. Gallery users that do not exist in WordPress will be created with randomly generated passwords.', 'g2-importer') ?></span>
			</td>
		</tr>
		
		</table>
		
		<?php wp_nonce_field('import-g2','import-g2'); ?>
		
		<p class="submit">
			<input type="submit" name="Submit" class="button" value="<?php _e('Import') ?>" />
		</p>
		</form>
		</div>
		<?php
	}

	function _normalize_tag( $matches ) {
		return '<' . strtolower( $matches[1] );
	}

	function get_posts_and_comments() {
		$this->username = $_POST['username'];
		$this->password = $_POST['password'];
		$this->database = $_POST['database'];
		$this->host = $_POST['host'];
		$this->baseurl = $_POST['baseurl'];
		$this->status = $_POST['status'];
		$this->author = $_POST['author'];
		$tbl = $_POST['tablePrefix'];
		$col = $_POST['columnPrefix'];

		$connection = mysql_connect($this->host, $this->username, $this->password) or wp_die ("Couldn't create database connection.");
		$db = mysql_select_db($this->database, $connection) or wp_die ("Couldn't select the database.");

		// select gallery items
		$sql = 'SELECT '.$tbl.'Item.'.$col.'ID, '.$tbl.'Item.'.$col.'canContainChildren, '.$tbl.'Item.'.$col.'originationTimestamp, '.$tbl.'Item.'.$col.'title, '
			.$tbl.'Item.'.$col.'description, '.$tbl.'FileSystemEntity.'.$col.'pathComponent, '.$tbl.'ItemAttributesMap.'.$col.'parentSequence, '.$tbl.'User.'.$col.'username, '.$tbl.'User.'.$col.'email
			FROM '.$tbl.'Item, '.$tbl.'ItemAttributesMap, '.$tbl.'FileSystemEntity, '.$tbl.'User
			WHERE '.$tbl.'Item.'.$col.'ID = '.$tbl.'ItemAttributesMap.'.$col.'itemId
			AND '.$tbl.'Item.'.$col.'ID = '.$tbl.'FileSystemEntity.'.$col.'ID
			AND '.$tbl.'Item.'.$col.'ownerId = '.$tbl.'User.'.$col.'id
			ORDER BY '.$tbl.'Item.'.$col.'canContainChildren DESC , '.$tbl.'Item.'.$col.'ID ASC';
		$result = mysql_query($sql, $connection) or wp_die ("Couldn't select gallery albums from the database.");

		while ($entry = mysql_fetch_array($result))
			$this->posts[] = $entry;
			
		// select comments
		$sql2 = 'SELECT '.$tbl.'Comment.'.$col.'id, '.$tbl.'Comment.'.$col.'subject, '.$tbl.'Comment.'.$col.'comment, '.$tbl.'Comment.'.$col.'date, '.$tbl.'Comment.'.$col.'host, '
			.$tbl.'ChildEntity.'.$col.'parentId, '.$tbl.'User.'.$col.'fullName, '.$tbl.'User.'.$col.'userName, '.$tbl.'User.'.$col.'email
			FROM '.$tbl.'Comment, '.$tbl.'ChildEntity, '.$tbl.'User
			WHERE '.$tbl.'Comment.'.$col.'id = '.$tbl.'ChildEntity.'.$col.'id
			AND '.$tbl.'Comment.'.$col.'commenterID = '.$tbl.'User.'.$col.'id';
		$result2 = mysql_query($sql2, $connection) or wp_die ("Couldn't select gallery comments from the database.");

		while ($entry2 = mysql_fetch_array($result2))
			$this->comments[] = $entry2;
		
		mysql_free_result($result);
		mysql_free_result($result2);
		mysql_close($connection);	
		
		$index = 0;
		foreach ($this->posts as $post) {
			$post_title = $post[$col.'title'];
			
			$post_date_gmt = $post[$col.'originationTimestamp'];
			$post_date_gmt = gmdate('Y-m-d H:i:s', $post_date_gmt);
			$post_date = get_date_from_gmt( $post_date_gmt );

			$gID = $post[$col.'ID'];
			$path = $post[$col.'pathComponent'];
			$parents = $post[$col.'parentSequence'];

			$post_author = $this->get_author($post[$col.'username'], $post[$col.'email']);
			$post_status = $this->status;
			
			$post_content = $post[$col.'description'];
			// Clean up content
			$post_content = preg_replace_callback('|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $post_content);
			$post_content = str_replace('<br>', '<br />', $post_content);
			$post_content = str_replace('<hr>', '<hr />', $post_content);
						
			if ($post[$col.'canContainChildren']) {
				$post_type = 'page';
				$post_content .= "\n\n[gallery]";
			}
			else {
				$post_type = 'attachment';
			}
			
			$this->posts[$index] = compact('post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_status', 'post_type', 'gID', 'path', 'parents');
			$index++;
		}
		
		$index2 = 0;
		foreach ($this->comments as $comment) {
			$comment_date_gmt = $comment[$col.'date'];
			$comment_date_gmt = gmdate('Y-m-d H:i:s', $comment_date_gmt);
			$comment_date = get_date_from_gmt( $comment_date_gmt );
			
			$comment_author_email = $comment[$col.'email'];
			$user_id = $this->get_author($comment[$col.'userName'], $comment_author_email);
			if (!empty($user_id)) {
				$user = get_userdata($user_id);
				$comment_author = $user->display_name;
				if (!empty($user_url)) $comment_author_url = $user_url;
				else $comment_author_url = '';
			}
			else {
				$comment_author = $comment[$col.'fullName'];
			}
			$comment_author_IP = $comment[$col.'host'];
			
			$comment_subject = $comment[$col.'subject'];
			$comment_content = $comment[$col.'subject']."\n\n".$comment[$col.'comment'];
			// Clean up content
			$comment_content = preg_replace_callback('|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $comment_content);
			$comment_content = str_replace('<br>', '<br />', $comment_content);
			
			$parentgID = $comment[$col.'parentId'];
						
			$comment_approved = 1;
			
			$this->comments[$index2] = compact('comment_author', 'comment_author_email', 'comment_author_IP'. 'comment_date', 'comment_content', 'parentgID', 'comment_subject', 'user_id');
			$index2++;
		}
		
		echo '<h3>';
		printf(__('Importing %d total Gallery2 albums and images and %d comments...', 'g2-importer'), $index, $index2);
		echo '</h3>';
	}

	function import_posts() {	
		echo '<ol>';

		foreach ($this->posts as $post) {
			echo "<li>".__('Importing ', 'g2-importer');

			extract($post);
			
			echo '<em>'.$post_title.'</em>';
			_e('... ');
			
			if ($post_id = post_exists($post_title, $post_content, $post_date)) {
				_e('Post already imported', 'g2-importer');
				$this->parentmap[$gID] = array('postID' => $post_id, 'title' => $post_title, 'parentpath' => rtrim($parents, '/'));
			} else {
				
				// This is where we determine whether to import a post or an attachment.
				if ($post_type == 'page') {
					$post_id = wp_insert_post($post);
					$this->parentmap[$gID] = array('postID' => $post_id, 'title' => $post_title, 'parentpath' => rtrim($parents, '/'));
				}
				else {
					set_time_limit(560);  // something insanely high, to prevent hangups on large images
					$parentgID = rtrim($parents, '/');
					$parentgID = strrchr($parentgID, '/');
					$parentgID = ltrim($parentgID, '/');
					// we should already have all the parent pages, since we sorted the query on canContainChildren. There should be none we can't find in the array.
					$parent = $this->parentmap[$parentgID]['postID'];
					// Add file extension. Ignored by Gallery but makes the file pass WP's security check.
					$file = $this->baseurl.'/main.php?g2_view=core.DownloadItem&g2_itemId='.$gID.strrchr($path, '.'); 
					
					// the rest adapted from media_sideload_image()
					
					// Download file to temp location
					$tmp = download_url($file);
					 
					// Set variables for storage
					// fix file filename for query strings
					$gotfile = preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $file, $matches);
					if ($gotfile) 
						$file_array['name'] = basename($matches[0]);
					$file_array['tmp_name'] = $tmp;
				
					// If error storing temporarily, unlink
					if ( is_wp_error($tmp) ) {
					      @unlink($file_array['tmp_name']);
					      $file_array['tmp_name'] = '';
					}
				
					// do the validation and storage stuff
					$post_id = media_handle_sideload($file_array, $parent, '', $post);
					
					$this->parentmap[$gID] = array('postID' => $post_id, 'title' => $post_title, 'parentpath' => rtrim($parents, '/'));
					
					// clean up temp files
					@unlink($file_array['tmp_name']);
					
					usleep(500);
				}

				if (!$post_id) {
					_e('Couldn&#8217;t get post ID', 'g2-importer');
					return;
				}

				_e('Done!', 'g2-importer');
				flush();
			}
			echo '</li>';
		}

		echo '</ol>';
	}
	
	function fix_parents() {		
		echo '<p>';
		_e('Setting gallery page hierarchy...', 'g2-importer');
		foreach ($this->parentmap as $gID => $parentinfo) {
			$my_post = array();
			$my_post['ID'] = $parentinfo['postID'];
			$parents = explode('/', $parentinfo['parentpath']); // can't do the strrchr thing because there might be no slash
			$parentgID = end($parents);
			if (!empty($parentgID)) {  // if there is no parent or it's 0, it's the top-level gallery and we don't need to update
				$my_post['post_parent'] = $this->parentmap[$parentgID]['postID'];
				wp_update_post( $my_post );
			}
		}
		_e('Done!', 'g2-importer');
		echo '</p>';
	}
	
	function import_comments() {
		echo '<h3>'.__('Comments', 'g2-importer').'</h3><ol>';

		foreach ($this->comments as $comment) {
			echo "<li>".__('Importing ', 'g2-importer');

			echo '<em>'.$comment['comment_subject'].'</em>';
			_e('... ', 'g2-importer');
			unset($comment['comment_subject']);
			
			$parentgID = $comment['parentgID'];
			$postID = $this->parentmap[$parentgID]['postID'];
			// if it's an attachment, we want the parent post ID instead
			$thispost = get_post($postID);
			if ($thispost->post_type == 'attachment') {
				$postID = $thispost->post_parent;
			}
			$comment['comment_post_ID'] = $postID;
			
			$comment_id = wp_insert_comment($comment);
			if (!$comment_id) {
				_e('Couldn&#8217;t get comment ID', 'g2-importer');
				return;
			}

			_e('Done!', 'g2-importer');
			echo '</li>';
			flush();
		}

		echo '</ol>';
	}
	
	function get_author($new_author_name, $new_author_email) {
		global $current_user;
		
		if ($this->author == 'create') {
			$user_id = email_exists($new_author_email);
			if ( !$user_id ) {
				$user_id = wp_create_user($new_author_name, wp_generate_password(), $new_author_email);
			}

			if ( !is_wp_error( $user_id ) ) {
				return $user_id;
			}
		}
		// fallback: current user ID
		return intval($current_user->ID);
	}
		
	function import() {
		$this->get_posts_and_comments();
		$result = $this->import_posts();
		$this->fix_parents();
		if ( is_wp_error( $result ) )
			return $result;
			
		$result2 = $this->import_comments();
		if ( is_wp_error( $result2 ) )
			return $result2;
			
		do_action('import_done', 'g2-importer');

		echo '<h3>';
		printf(__('All done. <a href="%s">Have fun!</a>', 'g2-importer'), 'edit.php?post_type=page');
		echo '</h3>';
	}

	function dispatch() {
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];

		$this->header();

		switch ($step) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer('import-g2','import-g2');
				$result = $this->import();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
		}

		$this->footer();
	}

	function Gallery2_Import() {
		// Nothing.
	}
}

$gallery2_import = new Gallery2_Import();

register_importer('gallery2', __('Gallery2', 'g2-importer'), __('Import albums, images, and descriptions from a Gallery2 installation.', 'g2-importer'), array ($gallery2_import, 'dispatch'));

} // class_exists( 'WP_Importer' )

function gallery2_importer_init() {
    load_plugin_textdomain( 'g2-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'gallery2_importer_init' );

?>