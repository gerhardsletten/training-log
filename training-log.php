<?php
/*
Plugin Name: Training log
Plugin URI: http://www.metabits.no
Description: Plugin to store users personal training sessions
Author: Gerhard Sletten
Version: 1.0
Author URI: http://www.metabits.no
*/

if (!class_exists("TrainingLog")) {
	class TrainingLog {
		var $_wpdb;
		var $db_version = "1.3";
		var $db_version_key = "training_log_db_version";
		var $db_table_name = "training_log";
		var $date_format = 'Y-m-d H:i:s';
		var $date_format_js = 'c';
		var $name;

		function __construct($full = true) {
			global $wpdb;
			$this->_wpdb = $wpdb;
			$this->db_table_name = $this->_wpdb->prefix . $this->db_table_name;
			$this->name = strtolower(get_class());
			if($full) {
				load_plugin_textdomain($this->name, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');

				//register_activation_hook(__FILE__, 'webtrening_create_table' );
				
				add_action('plugins_loaded', array( &$this, "update_table_check" ));

				add_action( "admin_menu", array( &$this, "create_admin_menu" ) );
				add_shortcode( 'training_log_table', array( &$this, "training_log_table" ) );
				add_shortcode( 'training_log_add', array( &$this, "training_log_add" ) );
							
				// Add ajax functions
				add_action( 'wp_ajax_addSession', array( &$this, "addSession" ) );
				add_action( 'wp_ajax_nopriv_addSession', array( &$this, "addSession" ) );
				add_action( 'wp_ajax_editSession', array( &$this, "editSession" ) );
				add_action( 'wp_ajax_nopriv_editSession', array( &$this, "editSession" ) );
				add_action( 'wp_ajax_deleteSession', array( &$this, "deleteSession" ) );
				add_action( 'wp_ajax_nopriv_deleteSession', array( &$this, "deleteSession" ) );

				if ( !get_option('calories_per_second') ) {
				    update_option( 'calories_per_second', "0.5" );
				}
			
			
				include_once dirname(__FILE__)."/training-log-widget.php";
				add_action( 'widgets_init', array( &$this, "myplugin_register_widgets" ) );

				add_action( 'add_meta_boxes', array( &$this, "add_custom_box" ));
				add_action( 'save_post', array( &$this, "save_postdata" ));

				
			}
			
		}

		function add_custom_box() {
			add_meta_box( 
				'myplugin_sectionid',
				'Treningsdagbok',
				array( &$this, "custom_box" ),
				'post' 
			);
		}

		function custom_box() {
			global $meta_box_video, $post;
			// Use nonce for verification
			wp_nonce_field( plugin_basename( __FILE__ ), 'training-log-save' );
			$value = get_post_meta( $post->ID, 'kcal_post', false );
			$hide = get_post_meta( $post->ID, 'hide_traininglog', false ); ?>
			<p>
			  	<label for="kcal_post">KCAL for økt</label>
			  	<input type="text" value="<?php if($value[0] > 0) echo $value[0] ?>" placeholder="Skriv et nummer" id="kcal_post" name="kcal_post" />
			</p>
			<p>
			  	<label for="hide_traininglog">Skjul treningslog</label>
			  	<input type="hidden" value="0" name="hide_traininglog" />
			  	<input type="checkbox" value="1" <?php if($hide[0] == 1) echo "checked" ?> id="hide_traininglog" name="hide_traininglog" />
			</p>
		  <?php
		}

		function save_postdata( $post_id ) {
		  if (!$_POST || !isset($_POST['training-log-save']) ) 
		      return;

		  if (isset($_POST['training-log-save']) && !wp_verify_nonce( $_POST['training-log-save'], plugin_basename( __FILE__ ) ) )
		      return;

		  // Check permissions
		  if ( 'page' == $_POST['post_type'] ) {
		    if ( !current_user_can( 'edit_page', $post_id ) )
		        return;
		  } else {
		    	if ( !current_user_can( 'edit_post', $post_id ) )
		        	return;
		  }

		  	$old = get_post_meta($post_id, 'kcal_post', true);
			$new = intval($_POST['kcal_post']);

			if ($new > 0 && $new != $old) {
				update_post_meta($post_id, 'kcal_post', $new);
			} elseif (0 == $new) {
				delete_post_meta($post_id, 'kcal_post', $old);
			}

			$old = get_post_meta($post_id, 'hide_traininglog', true);
			$new = intval($_POST['hide_traininglog']);

			if ($new > 0 && $new != $old) {
				update_post_meta($post_id, 'hide_traininglog', $new);
			} elseif (0 == $new) {
				delete_post_meta($post_id, 'hide_traininglog', $old);
			}
		}

		function myplugin_register_widgets() {
			register_widget( 'training_log_widget' );
		}

		/* Creating table */

		function update_table_check() {
			$installed_ver = get_option( $this->db_version_key );
			if( $installed_ver != $this->db_version ) {
				$this->create_table();
			}
		}

		function create_table() {
			$sql = "CREATE TABLE `".$this->db_table_name."` (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(150) DEFAULT '',
				post_id bigint(20) unsigned NOT NULL DEFAULT '0',
				user_id bigint(20) unsigned NOT NULL DEFAULT '0',
				date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				kcal int(11) NOT NULL DEFAULT '0',
				seconds bigint(20) NOT NULL DEFAULT '0',
				PRIMARY KEY  (id))";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			if(dbDelta($sql)) {
				add_option($this->db_version_key, $this->db_version );
			}
		}
		
		function enqueue_ressources($full = false) {
			wp_enqueue_script( 'training-log-request', plugin_dir_url( __FILE__ ) . 'js/training-log.js', array( 'jquery' ) );
			wp_enqueue_script( 'training-log-functions', plugin_dir_url( __FILE__ ) . 'js/training-log-functions.js', array( 'jquery' ) );
			if($full) {
				wp_enqueue_script( 'raphael', plugin_dir_url( __FILE__ ) . 'js/vendors/raphael-min.js', array( 'jquery' ) );
				wp_enqueue_script( 'charts', plugin_dir_url( __FILE__ ) . 'js/vendors/charts.min.js', array( 'raphael' ) );
				wp_enqueue_script( 'magnific-popup', plugin_dir_url( __FILE__ ) . 'js/vendors/magnific-popup/jquery.magnific-popup.min.js', array( 'jquery' ) );
				wp_enqueue_script( 'charts-setup', plugin_dir_url( __FILE__ ) . 'js/charts.js', array( 'raphael','charts', 'jquery','magnific-popup','jquery-ui-datepicker' ) );

				wp_enqueue_style( 'magnific-popup', plugin_dir_url( __FILE__ ) . 'js/vendors/magnific-popup/magnific-popup.css' );
			}
			wp_localize_script( 'training-log-request', 'TrainingLog', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'training-log-nonce' ),
				)
			);
		}

		// Helper functions

		function _dateRangeForMonth($year, $month) {
			return array(
				date($this->date_format, mktime(0, 0, 0, $month, 1, $year)),
				date($this->date_format, mktime(23, 59, 59, $month+1, 0, $year))
			);
		}

		function _formatDate($datestring) {
			return date_i18n(get_option('date_format') ,strtotime($datestring));
		}

		function _formatTime($seconds) {
			$m = floor($seconds / 60);
			$s = $seconds - ($m*60);
			if($m<10)
				$m = "0" . $m;
			if($s<10)
				$s = "0" . $s;
			return $m. ":" . $s;
		}

		function _formatPost($post_id) {
			$post_title = get_the_title($post_id);
			return "<a href='". get_permalink($post_id) . "'>$post_title</a>";
		}

		function _formatKcal($kcal) {
			return $kcal . " " . __("KCAL", $this->name);
		}

		function _formatUser($user_id) {
			$user = get_userdata($user_id);
			return "<a href='".get_edit_user_link($user_id)."'>$user->user_firstname $user->user_lastname</a>";
				
		}

		// Shortcode to display table of users sessions
		
		function training_log_table($atts, $content = null ){
			$this->enqueue_ressources(true);
			$year = date('Y');
			$message = false;
			$out = "";
			$user_id = get_current_user_id();

			if(current_user_can( 'manage_options' )) {
				
				if( isset( $_GET['tl_user'] ) ) {
					
					$user_id = intval($_GET['tl_user']);
					$user_data = get_userdata( $user_id );; 
					$out .= "<p>Treningsdata for " . $user_data->first_name . " " . $user_data->last_name . " (" . $user_data->user_login . ")</p>";
				}
			}

			if( isset( $_GET['ty'] ) ) {
				$year = intval($_GET['ty']);
			}
			$month = date('m');
			if( isset( $_GET['tm'] ) ) {
				$month = intval($_GET['tm']);
			}
			if($month < 1) {
				$year--;
				$month = 12;
			};
			if($month > 12) {
				$year++;
				$month = 1;
			}

			$tl_own_title = "";
			$tl_own_seconds = "";
			$tl_own_kcal = "";
			$tl_own_date = "";

			if( isset( $_POST['tl_own_seconds'] ) ) {
				$tl_own_seconds = intval($_POST['tl_own_seconds']);
			}

			if( isset( $_POST['tl_own_kcal'] ) ) {
				$tl_own_kcal = intval($_POST['tl_own_kcal']);
			}

			if( isset( $_POST['tl_own_date'] ) ) {
				$tl_own_date = sanitize_text_field($_POST['tl_own_date']);
			}
			
			if( isset( $_POST['tl_own_title'] )  ) {
				$tl_own_title = sanitize_text_field($_POST['tl_own_title']);
			}

			if( isset( $_POST['tl_own_title'] ) || isset( $_POST['tl_own_seconds'] ) || isset( $_POST['tl_own_date'] ) ) {
				// User try to save a session
				$date = split('-', $tl_own_date);
				if( $tl_own_seconds > 0 && strlen($_POST['tl_own_title'])>1 && count($date) == 3) {
					$params = array(
						//Y-m-d
						'date' => date($this->date_format, mktime(12,0,0,$date[1],$date[2],$date[0])),
						'title' => $tl_own_title,
						'post_id' => 0,
						'user_id' => $this->_currentUserId(),
						'seconds' => $tl_own_seconds,
						'kcal' => $tl_own_kcal
					);
					if( $this->_wpdb->insert( $this->db_table_name , $params ) ) {
						$message = "Treningsøkten ble lagret!";
						$tl_own_title = "";
						$tl_own_seconds = "";
						$tl_own_kcal = "";
						$tl_own_date = "";
					} else {
						$message = "Feil: Treningsøkten ble ikke lagret!";
					}
				} else {
					$message = "Feil: Du må både fylle ut tittel, dato og antall minutter";
				}

			}

			
			if( isset( $_POST['tl_weight'] ) ) {
				update_usermeta( $user_id, 'tl_weight', intval($_POST['tl_weight']) );
			}
			if( isset( $_POST['tl_height'] ) ) {
				update_usermeta( $user_id, 'tl_height', intval($_POST['tl_height']) );
			}
			if( isset( $_POST['tl_sex'] ) ) {
				update_usermeta( $user_id, 'tl_sex', sanitize_text_field($_POST['tl_sex']) );
			}

			if( isset( $_POST['delete'] ) ) {
				$delete_id = intval($_POST['delete']);
				if( $this->_hasAccess($delete_id) ) {
					$sqlDelete = "DELETE FROM " . $this->db_table_name . " WHERE id = $delete_id";
					if ( $this->_wpdb->query($sqlDelete) ) {
						$message =  __("The traning log was removed.", $this->name);
					} else {
						$message =  __("Error: Could not removed the traning log.", $this->name);
					}
				}
			}

			$date_range = $this->_dateRangeForMonth($year, $month);
			$month_length = date("d", mktime(0, 0, 0, $month+1, 0, $year));
			$dates = array();

			$sqlSelect = "SELECT * FROM  $this->db_table_name  WHERE user_id =  " . $user_id . " AND date >= '$date_range[0]' AND date <= '$date_range[1]' ORDER BY id DESC";
			
			$rows = $default = $this->_wpdb->get_results( $sqlSelect );

			for($i = 1; $i <= $month_length; $i++) {
				$start = mktime(0, 0, 0, $month, $i, $year);
				$end = mktime(23, 59, 59, $month, $i, $year); 
				$seconds = 0;
				$kcal = 0;
				$workouts = 0;
				$display = "";
				$time = 0;
				foreach($default as $row) {
					$ts = strtotime($row->date);
					if($ts >= $start && $ts <= $end) {
						$seconds += $row->seconds;
						$kcal += $row->kcal;
						$workouts++;
					}
				}
				
				
				if($workouts > 0) {
					switch ($workouts) {
					    case 1:
					        $display = "1 økt, ";
					        break;
					    default:
					        $display = $workouts . " økter, ";
					        break;
					}
					$raw = $this->sec_to_number($seconds);
					if($raw[0] > 0) {
						$display .= $raw[0] . "t og " . $raw[1] . "min trening";
					} else {
						$display .= $raw[1] . "min trening";
					}
					$display .= " (" . $kcal . "kcal)";
					$time = $raw[2];
				}

				$dates[$i] = array(
					'title' => date($this->date_format_js,mktime(0, 0, 0, $month, $i, $year)),
					'time' => $time,
					'display' => $display,
					'kcal' => $kcal,
					'seconds' => $seconds
				);
			}
			$total = $day = array(
				'seconds' => 0,
				'kcal' => 0,
				'hour' => 0,
				'min' => 0
			);
			foreach($default as $row) {
				$total['seconds'] += $row->seconds;
				$total['kcal'] += $row->kcal;
			}
			$raw = $this->sec_to_number($total['seconds']);
			$total['hour'] = $raw[0];
			$total['min'] = $raw[1];

			foreach($dates as $row) {
				if($row['seconds'] > $day['seconds']) {
					$day['seconds'] = $row['seconds'];
					$day['kcal'] = $row['kcal'];
				}
				
			}

			$raw = $this->sec_to_number($day['seconds']);
			$day['hour'] = $raw[0];
			$day['min'] = $raw[1];

			$buttons = '<a href="?ty=' . $year . '&tm=' . ($month - 1) . '" class="prev-button">' . __("Previous month", $this->name) . '</a>';
			$buttons .= '<a href="?ty=' . $year . '&tm=' . ($month + 1) . '" class="next-button">' . __("Next month", $this->name) . '</a>';

			$out .= '<form method="post" id="traning-log-table">';
			if($message) {
				$out .= '<p class="message">' . $message . '</p>';
			}
			$out .= $buttons;
			$out .= "<div class='training-log-container'>";
			$out .= '<div class="tl-holder">
						<div class="tl-box">
							<strong>Beste dag</strong>
							<span class="tl-time"><span>'.$day['hour'].'</span>timer <span>'.$day['min'].'</span>min</span>
							<span class="tl-kcal"><span>'.$day['kcal'].'</span>kcal</span>
						</div>
						<div class="tl-box">
							<strong>Denne måneden</strong>
							<span class="tl-time"><span>'.$total['hour'].'</span>timer <span>'.$total['min'].'</span>min</span>
							<span class="tl-kcal"><span>'.$total['kcal'].'</span>kcal</span>
						</div>
					</div>';
			$out .= '<script> var training = ' . json_encode($dates) . ';</script>';
			$out .= '<div id="training-chart" class="training-chart" style="width: 718px; height: 180px;"></div>';

			$out .= '<table>
					<tr>
						<th class="col-date">'. __("Date", $this->name) .'</th>
						<th class="col-post">'. __("Post", $this->name) .'</th>
						<th class="col-time">'. __("Time", $this->name) .'</th>
						<th class="col-kcal">'. __("Calories", $this->name) .'</th>
						<th class="col-actions">'. __("Actions", $this->name) .'</th>
					</tr>';
			foreach ($rows as $row) {
				
				$out .= "
					<tr>
						<td>". $this->_formatDate($row->date) . "</td>";
				if($row->post_id > 0) {
					$out .= "<td>". $this->_formatPost($row->post_id) . "</td>";
				} else {
					$out .= "<td>". $row->title . "</td>";
				}
						
				$out .= "<td>". $this->_formatTime($row->seconds) . "</td>
						<td>". $this->_formatKcal($row->kcal) . "</td>
						<td><button type='submit' name='delete' value='$row->id' class='button-delete'>".__("Delete", $this->name)."</button></td>
					</tr>";
			}

			$out .= '</table></div>';
			$out .= $buttons;
			$out .= "</form>";
			$weight = get_user_meta($user_id, 'tl_weight', true);
			$height = get_user_meta($user_id, 'tl_height', true);
			$sex = get_user_meta($user_id, 'tl_sex', true);
			
			$out .= '<div class="tl_holder"><div class="tl_column"><div class="widget widget-traninglog"><h3 class="widget-title">BMI Måler</h3><form method="post" id="traning-log-table-settings">';
			if($weight && $height) {
				$bmi = round($weight / (($height/100)^2),2);
				$out .= "<p><label>BMI:</label><span>Din BMI er: " . $bmi . "</span></p>";
			}
			$out .= '<p><label>Vekt:</label> <input type="text" name="tl_weight" value="'.$weight.'" id="tl_weight" class="short" /></p>
						<p><label>Høyde:</label> <input type="text" name="tl_height" value="'.$height.'" id="tl_height" class="short" /></p>
						<p><label>Kjønn:</label> 
						<select name="tl_sex" id="tl_sex">
							<option>Ikke satt</option>
							<option value="female"';
			if($sex == 'female') {
				$out .= " selected ";
			}
			$out .= '>Kvinne</option>
							<option value="male"';
			if($sex == 'male') {
				$out .= " selected ";
			}
			$out .= '>Mann</option>
						</select></p>
						<input type="submit" value="Lagre" />
						</form></div></div>';
			$out .= '<div class="tl_column">
				<div class="widget widget-traninglog">
					<h3 class="widget-title">Nyhet: Legg inn annen økt</h3>
					<form method="post">
						<p>
							<label>Navn på økt</label>
							<input type="text" name="tl_own_title" placeholder="Løping" value="'.$tl_own_title.'" />
						</p>
						<p>
							<label>Dato</label>
							<input type="text" name="tl_own_date" placeholder="" value="'.$tl_own_date.'" id="tl_own_date" />
						</p>
						<p>
							<label>Antall minutter</label>
							<select name="tl_own_seconds" class="short">';
			$steps = (60*60) / 12;
			$max = 60*60*3; // 3 hours
			for($j = $steps; $j <= $max; $j += $steps) {
				$label = $this->_formatTime($j);
				if($j == $tl_own_seconds) {
					$out .= '<option value="'.$j.'" selected>'.$label.'</option>';
				} else {
					$out .= '<option value="'.$j.'">'.$label.'</option>';
				}
				
			}
								
			$out .= '</select></p>
					<p>
						<label>Antall kcal</label>
						<input type="text" name="tl_own_kcal" placeholder="355" class="short" value="'.$tl_own_kcal.'" />
					</p>
						<input type="submit" value="Lagre" />
					</form>
				</div>
			</div>';
			return $out;
		}

		function sec_to_number($sec) {
			$h = $m = 0;
			$min = $sec / 60;
			if($min >= 60) {
				$h = floor($min/60);
			} else {
				$h = 0;
			}
			$m = $min%60;
			$m2 = $m/60;
			return array($h,$m, $h+$m2);
		}

		function training_log_add($atts, $content = null ){
			$this->enqueue_ressources(false);
			$post_id = get_the_ID();
			$out = '<form class="training-log-add">
						<input type="hidden" name="post_id" value="'.$post_id.'" />
						<input type="hidden" name="id" value="" />
						<input type="hidden" name="cal_per_seconds" value="'.get_option('calories_per_second') .'" />';
			$kcal = get_post_meta($post_id, 'kcal_post', false);
			if($kcal && $kcal[0] > 0) {
				$kcal_tmp = intval($kcal[0]);
				$adjust = 1;
				$user_id = get_current_user_id();
				$weight = get_user_meta($user_id, 'tl_weight', true);
				$height = get_user_meta($user_id, 'tl_height', true);
				$sex = get_user_meta($user_id, 'tl_sex', true);
				if($weight) {
					// Adjust +/- 8% for hver 10 kg differanse på 80kg
					$adjust += ($weight - 80) * 0.008;
				}
				if($height) {
					// Adjust +/- 3% for hver 10cm differasne på 180
					$adjust += (($height - 180)/10) * 0.003;
				}
				if($sex && $sex == 'female') {
					// Adjust - 10% hvis kvinne
					$adjust += -0.01;
				}
				$kcal_tmp = $kcal_tmp * $adjust;
				$out .= '<input type="hidden" name="cal_total" value="'. $kcal_tmp .'" />';
			}
			$out .= '<input type="hidden" name="seconds" value="" />
						<input type="hidden" name="kcal" value="" />
						<dl class="training-log-seconds">
							<dt>' . __("Time", $this->name) . '</dt>
							<dd>0</dd>
						</dl>
						<dl class="training-log-kcal">
							<dt>' . __("KCAL", $this->name) . '</dt>
							<dd>0</dd>
						</dl>

						<button class="training-log-start">' . __("Start", $this->name) . '</button>
						<button class="training-log-stop">' . __("Stop", $this->name) . '</button>
						<div class="training-log-status">' . __("Recording", $this->name) . '</div>
					</form>';
			return $out;
		}
		function training_log_add_direct() {
			echo $this->training_log_add(array());
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

		function _hasAccess($id) {
			$current = $this->_wpdb->get_row("SELECT * FROM $this->db_table_name WHERE id = $id");
			$current_user_id = $this->_currentUserId();
			if ( $current_user_id > 0 && $current_user_id == $current->user_id ) {
				return true;
			} 
			return false;
		}

		function _cleanParams($params) {
			$return = array();
			$return['user_id'] = $this->_currentUserId();
			$return['post_id'] = intval($params['post_id']);
			$return['seconds'] = intval($params['seconds']);
			$return['kcal'] = round($return['seconds'] * get_option('calories_per_second'));
			if(isset($params['seconds_total']) && intval($params['seconds_total']) > 1) {
				$kcal = get_post_meta($return['post_id'], 'kcal_post', true);
				if( $kcal && $kcal > 0 ) {
					$return['kcal'] = round(($kcal / intval($params['seconds_total'])) * $return['seconds']);
				}
			}
			return $return;
		}

		// CRUD functions

		function addSession() {
			$this->_checkNonse();
			$params = $safeparams = $return =  array();
			$now = time();
			parse_str($_POST['data'], $params);

			$safeparams = $this->_cleanParams($params);
			$safeparams['date'] = date($this->date_format, $now - $safeparams['seconds']);
			
			if($safeparams['post_id'] > 0 && $safeparams['user_id'] > 0 && $safeparams['seconds'] >= 0) {
				if( $this->_wpdb->insert( $this->db_table_name , $safeparams ) ) {
					$safeparams['id']  = $this->_wpdb->insert_id;
					$return['message'] = __("Your session has been saved.", $this->name);
					$return['data'] = $safeparams;
				} else {
					$return['error'] = __("Unable to save the session", $this->name);
				}
				
			} else {
				$return['error'] = __("Some fields are missing", $this->name);
			}
			header('Content-type: application/json');
			echo json_encode($return);
			exit();
		}

		
		function editSession() {

			$this->_checkNonse();
			$params = $safeparams = $return =  array();
			parse_str($_POST['data'], $params);
			$id = intval($_POST['id']);
			if( $id && $id > 0 ) {
				if( $this->_hasAccess($id) ) {
					$safeparams = $this->_cleanParams($params);
					if($safeparams['post_id'] > 0 && $safeparams['user_id'] > 0 && $safeparams['seconds'] >= 0) {
						if( $this->_wpdb->update( $this->db_table_name , $safeparams, array('id'=>$id) ) ) {
							$safeparams['id']  = $id;
							$return['message'] = __("Your session has been saved.", $this->name);
							$return['data'] = $safeparams;
						} else {
							$return['error'] = __("Unable to save the session", $this->name);
						}
						
					} else {
						$return['error'] = __("Some fields are missing", $this->name);
					}
				} else {
					$return['error'] = __("This session do not belong to you.", $this->name);
				}
			} else {
				$return['error'] = __("The id of session is missing.", $this->name);
			}
			
			
			header('Content-type: application/json');
			echo json_encode($return);
			exit();
		}
		
		function create_admin_menu() {
			add_menu_page( __("Training log", $this->name), __("Training log", $this->name), "level_10", "training_log", array( &$this, "welcome" ) );
			add_submenu_page( "training_log", __("Integration info", $this->name), __("Integration info", $this->name), "level_10", "api", array( &$this, "integration_info" ) );
		}
		
		function welcome() {
			$page = 0;
			if( isset( $_POST['nextpage'] ) ) {
				$page = $_POST['page'] + 1;
			}
			if( isset( $_POST['prevpage'] ) ) {
				$page = $_POST['page'] - 1;
			}
			$filter = false;
			if( isset( $_POST['filter'] ) ) {
				$filter = $_POST['filter'];
			}

			$limit = 50;
			$show_next = false;
			if (isset($_POST['row']) && !empty($_POST['row'])) {
				$str = implode(',', $_POST['row']);
				$sqlDelete = "DELETE FROM " . $this->db_table_name . " WHERE id in($str)";
				$del = $this->_wpdb->query($sqlDelete);
			}
			if($filter) {
				$sqlSelect = "SELECT * FROM " . $this->db_table_name . " WHERE user_id = ".$filter." ORDER BY id DESC LIMIT " . $page * $limit . "," . (1+$limit);
			} else {
				$sqlSelect = "SELECT * FROM " . $this->db_table_name . " ORDER BY id DESC LIMIT " . $page * $limit . "," . (1+$limit);
				$filter = "";
			}
			
			$rows =  $this->_wpdb->get_results( $sqlSelect );
			
			$out .= "<div class='wrap'><form method=\"post\">";
			$out .= '<input type="hidden" name="page" value="'.$page.'" />';
			$out .= '<div style="float:right"><input type="text" placeholder="Filtrer på bruker id" name="filter" value="' . $filter . '"/><input type="submit" class="button-secondary action" value="Filtrer" /></div>';
			$out .= "<table class='widefat'><thead><tr>
				<th style='width:25px;'></th>
				<th style='width:25px;'>ID</th>
				<th>" . __("User", $this->name) . "</th>
				<th>" . __("Post", $this->name) . "</th>
				<th>" . __("Date", $this->name) . "</th>
				<th>" . __("Time", $this->name) . "</th>
				<th>" . __("Calories", $this->name) . "</th>
				</tr>
				</thead><tbody>
				";

			foreach ($rows as $key => $row) {
				if($key+1 > $limit) {
					$show_next = true;
					break;
				}

				$out .= "
					<tr>
						<td><input type='checkbox' name='row[]' value=" .$row->id ." /></td>
						<td>$row->id</td>
						<td>". $this->_formatUser($row->user_id) . " (".$row->user_id.")</td>
						<td>". $this->_formatPost($row->post_id) . "</td>
						<td>". $this->_formatDate($row->date) . "</td>
						<td>". $this->_formatTime($row->seconds) . "</td>
						<td>" . $this->_formatKcal($row->kcal) . "</td>
					</tr>";
			}

			$out .= '</tbody></table>';
			$out .= '<div class="alignleft actions"  style="margin: 10px 0 10px">';
			if($page > 0) {
				$out .= '<input type="submit" class="button-secondary action" name="prevpage" value="' . __("Previous page", $this->name) . '" />
				';
			}
			if($show_next) {
				$out .= '<input type="submit" class="button-secondary action" name="nextpage" value="' . __("Next page", $this->name) . '" />';
			}
			$out .= '</div><div class="alignright actions"  style="margin: 10px 0 10px">
				<input type="submit" class="button-secondary action" value="' . __("Remove selected", $this->name) . '" />
				</div></form>';
			$out .= "</div>";
			echo "<h2>". __("All training logs", $this->name) ."</h2>";
			echo $out;
		}

		function integration_info() {
			if( isset( $_POST['calories_per_second'] ) ) {
				$value = floatval($_POST['calories_per_second']);
				update_option( 'calories_per_second', $value );
			}

			$out = "<h2>". __("Training log shortcodes", $this->name) ."</h2>";
			$out .= "<div class='wrap'>
				<p><strong>[training_log_table]</strong>: ". __("Displays a table with the users training logs.", $this->name) ."</p>
				<p><strong>[training_log_add]</strong>: ". __("Displays a form for users to add a new training log.", $this->name) ."</p>";
			$out .= "<form method='post'><h3><label for='calories_per_second'>" . __("Calories per seconds", $this->name) ."</label></h3>"; 
			$out .= '<p><input id="calories_per_second" name="calories_per_second" type="text" size="15" maxlength="12" value="' . get_option('calories_per_second') . '"  /></p>
			<input type="submit" name="Update" value="' . __("Save", $this->name) . '" /></form>';
			echo $out;
		}
		
	}
}
if (class_exists("TrainingLog")) {
	$training_log = new TrainingLog();
}



?>