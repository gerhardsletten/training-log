<?php
/*
Plugin Name: Training log
Plugin URI: http://www.metabits.no
Description: Plugin to store users personal training sessions
Author: Gerhard Sletten
Version: 1.0
Author URI: http://www.metabits.no
*/
//require_once( dirname(__FILE__) . '/lib/imagemail.php' );
if (!class_exists("TrainingLog")) {
	class TrainingLog {
		var $_wpdb;
		var $db_version = "1.1";
		var $db_version_key = "training_log_db_version";
		var $db_table_name = "training_log";

		function __construct() {
			global $wpdb;
			$this->_wpdb = $wpdb;
			$this->db_table_name = $this->_wpdb->prefix . $this->db_table_name;

			//register_activation_hook(__FILE__, 'webtrening_create_table' );

			add_action('plugins_loaded', array( &$this, "update_table_check" ));
			
			add_action( "admin_menu", array( &$this, "create_admin_menu" ) );

			add_shortcode( 'training_log_table', array( &$this, "training_log_table" ) );

			add_shortcode( 'training_log_add', array( &$this, "training_log_add" ) );
			
			//add_action('wp_enqueue_scripts', array( &$this, "enqueue_ressources" ));
			
			// Add ajax functions
			add_action( 'wp_ajax_addSession', array( &$this, "addSession" ) );
			add_action( 'wp_ajax_nopriv_addSession', array( &$this, "addSession" ) );
			add_action( 'wp_ajax_editSession', array( &$this, "editSession" ) );
			add_action( 'wp_ajax_nopriv_editSession', array( &$this, "editSession" ) );
			add_action( 'wp_ajax_deleteSession', array( &$this, "deleteSession" ) );
			add_action( 'wp_ajax_nopriv_deleteSession', array( &$this, "deleteSession" ) );
			add_action( 'wp_ajax_listSessions', array( &$this, "listSessions" ) );
			add_action( 'wp_ajax_nopriv_listSessions', array( &$this, "listSessions" ) );
		}

		/* Creating table */

		function update_table_check() {
			$installed_ver = get_option( $this->db_version_key );
			if( $installed_ver != $this->db_version ) {
				$this->create_table();
			}
		}

		function create_table() {
			$sql = "CREATE TABLE IF NOT EXISTS `".$this->db_table_name."` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`post_id` bigint(20) unsigned NOT NULL DEFAULT '0',
				`user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
				`date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				`kcal` int(11) NOT NULL DEFAULT '0',
				`seconds` bigint(20) NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`))";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			if(dbDelta($sql)) {
				add_option($this->db_version_key, $this->db_version );
			}
		}
		
		function enqueue_ressources($full = false) {
			wp_enqueue_script( 'training-log-request', plugin_dir_url( __FILE__ ) . 'training-log.js', array( 'jquery' ) );
			if($full) {
				wp_enqueue_script( 'training-log-functions', plugin_dir_url( __FILE__ ) . 'training-log-functions.js', array( 'jquery' ) );
			}
			wp_localize_script( 'training-log-request', 'TrainingLog', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'training-log-nonce' ),
				)
			);
		}

		// Shortcode to display table of users sessions
		
		function training_log_table($atts, $content = null ){
			$this->enqueue_ressources(true);
			$page = 0;
			if( isset( $_POST['nextpage'] ) ) {
				$page = $_POST['page'] + 1;
			}
			if( isset( $_POST['prevpage'] ) ) {
				$page = $_POST['page'] - 1;
			}

			
			$limit = 2;
			$show_next = false;

			$sqlSelect = "SELECT * FROM " . $this->db_table_name . " ORDER BY id DESC LIMIT " . $page * $limit . "," . (1+$limit);
			$rows =  $this->_wpdb->get_results( $sqlSelect );
			$out = '<form method="post" id="traning-log-table">';
			$out .= '<input type="hidden" name="page" value="'.$page.'" />
				<table>
					<tr>
						<th class="col-date">Date</th>
						<th class="col-post">Post</th>
						<th class="col-time">Time</th>
						<th class="col-kcal">Calories</th>
						<th class="col-actions">Actions</th>
					</tr>';
			foreach ($rows as $key => $row) {
				if($key+1 > $limit) {
					$show_next = true;
					break;
				}
				$post_title = get_the_title($row->post_id);
				$out .= "
					<tr>
						<td>$row->date</td>
						<td><a href='". get_permalink($row->post_id) . "'>$post_title</a></td>
						<td>$row->seconds</td>
						<td>$row->kcal</td>
						<td><button type='submit' name='delete' value='$row->id'>Delete</button></td>
					</tr>";
			}

			$out .= '</table>';
			if($page > 0) {
				$out .= '<button type="submit" name="prevpage" class="prev-button">Previous page</button>';
			}
			if($show_next) {
				$out .= '<button type="submit" class="next-button" name="nextpage">Next page</button>';
			}
			$out .= "</form>";
			if( isset( $_POST['delete'] ) ) {
				$out .= $_POST['delete'];
			}
			return $out;
		}

		function training_log_add($atts, $content = null ){
			$this->enqueue_ressources(true);
			$post_id = get_the_ID();
			$out = '<form id="training-log-add">
						<input type="text" name="post_id" value="'.$post_id.'" placeholder="Post ID" />
						<input type="text" name="kcal" value="" placeholder="Calories" />
						<input type="text" name="seconds" value="" placeholder="Seconds" />
						<input type="submit" name="sumbmit" value="Add" />
					</form>';
			return $out;
		}

		// Helper CRUD functions

		function _checkNonse() {
			$nonce = $_POST['nonce'];
			if ( ! wp_verify_nonce( $nonce, 'training-log-nonce' ) )
				die ( 'Busted!');
			if($this->_currentUserId() < 1 ) {
				die ( 'You are not logged in' );
			}
		}

		function _currentUserId() {
			$current_user = wp_get_current_user();
			return $current_user->ID;
		}

		function _hasAccess($user_id) {
			$current_user_id = $this->_currentUserId();
			if ( $current_user_id > 0 && $current_user_id == $user_id ) {
				return true;
			} 
			return false;
		}

		// CRUD functions

		function addSession() {
			$this->_checkNonse();
			$params = $safeparams = $return =  array();
			$now = mktime();
			parse_str($_POST['data'], $params);

			$safeparams['user_id'] = $this->_currentUserId();
			$safeparams['post_id'] = intval($params['post_id']);
			$safeparams['seconds'] = intval($params['seconds']);
			$safeparams['kcal'] = intval($params['kcal']);
			$safeparams['date'] = date('Y-m-d H:i:s', $now - $safeparams['seconds']);
			
			if($safeparams['post_id'] > 0 && $safeparams['user_id'] > 0 && $safeparams['seconds'] > 0 && $safeparams['kcal'] > 0 && $safeparams['kcal'] > 0) {
				if( $this->_wpdb->insert( $this->db_table_name , $safeparams ) ) {
					$safeparams['id']  = $this->_wpdb->insert_id;
					$return['message'] = "Your session has been saved.";
					$return['data'] = $safeparams;
				} else {
					$return['error'] = "Unable to save the session";
				}
				
			} else {
				$return['error'] = "Some fields are missing";
			}
			header('Content-type: application/json');
			echo json_encode($return);
			exit();
		}
		
		function create_admin_menu() {
			add_menu_page( "Training log", "Training log", "level_10", "training_log", array( &$this, "welcome" ) );
			add_submenu_page( "training_log", "Integration info", 'Integration info', "level_10", "api", array( &$this, "integration_info" ) );
		}
		
		function welcome() {
			$page = 0;
			if( isset( $_POST['nextpage'] ) ) {
				$page = $_POST['page'] + 1;
			}
			if( isset( $_POST['prevpage'] ) ) {
				$page = $_POST['page'] - 1;
			}
			
			$limit = 50;
			$show_next = false;
			if (isset($_POST['row']) && !empty($_POST['row'])) {
				$str = implode(',', $_POST['row']);
				$sqlDelete = "DELETE FROM " . $this->db_table_name . " WHERE id in($str)";
				$del = $this->_wpdb->query($sqlDelete);
			}

			$sqlSelect = "SELECT * FROM " . $this->db_table_name . " ORDER BY id DESC LIMIT " . $page * $limit . "," . (1+$limit);
			$rows =  $this->_wpdb->get_results( $sqlSelect );
			
			$out .= "<div class='wrap'><form method=\"post\">";
			$out .= '<input type="hidden" name="page" value="'.$page.'" />';
			$out .= "<table class='widefat'><thead><tr>
				<th style='width:25px;'></th>
				<th style='width:25px;'>ID</th>
				<th>Bruker</th>
				<th>Post</th>
				<th>Date</th>
				<th>Time</th>
				<th>KCAL</th>
				</tr>
				</thead><tbody>
				";
			foreach ($rows as $key => $row) {
				echo $key;
				if($key+1 > $limit) {
					$show_next = true;
					break;
				}
				$user = get_userdata($row->user_id);
				$post_title = get_the_title($row->post_id);
				$out .= "
					<tr>
						<td><input type='checkbox' name='row[]' value=" .$row->id ." /></td>
						<td>$row->id</td>
						<td><a href='".get_edit_user_link($row->user_id)."'>$user->user_firstname $user->user_lastname</td>
						<td><a href='". get_permalink($row->post_id) . "'>$post_title</a></td>
						<td>$row->date</td>
						<td>$row->seconds</td>
						<td>$row->kcal</td>
					</tr>";
			}

			$out .= '</tbody></table>';
			$out .= '<div class="alignleft actions"  style="margin: 10px 0 10px">';
			if($page > 0) {
				$out .= '<input type="submit" class="button-secondary action" name="prevpage" value="Previous page" />
				';
			}
			if($show_next) {
				$out .= '<input type="submit" class="button-secondary action" name="nextpage" value="Next page" />';
			}
			$out .= '</div><div class="alignright actions"  style="margin: 10px 0 10px">
				<input type="submit" class="button-secondary action" value="Remove selected" />
				</div></form>';
			$out .= "</div>";
			echo "<h2>All training logs</h2>";
			echo $out;
		}

		function integration_info() {
			$out = "<h2>Training log shortcodes</h2>";
			$out .= "<div class='wrap'>
				<p><strong>[training_log_table]</strong>: Displays a table with the users training logs.</p>
				<p><strong>[training_log_add]</strong>: Displays a form for users to add a new training log.</p>";
			$out .= "<h3>Javascript integration</h3>
				<p>More to come..</p>";
			echo $out;
		}
		
	}
}
if (class_exists("TrainingLog")) {
	$training_log = new TrainingLog();
}



?>