<?php

class training_log_widget extends WP_Widget {
	private $plugin;
	private $daymap = array(
		'Mon' => 'M',
		'Tue' => 'T',
		'Wed' => 'O',
		'Thu' => 'T',
		'Fri' => 'F',
		'Sat' => 'L',
		'Sun' => 'S'
	);

	function training_log_widget() {
	    /* Widget settings. */
	    $widget_ops = array('classname' => 'training_log_widget', 'description' => 'Viser siste ukes trening');

	    /* Widget control settings. */
	    $control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'training_log_widget');

	    /* Create the widget. */
	    $this->WP_Widget('training_log_widget', 'Viser siste ukes trening', $widget_ops, $control_ops);

	    include_once dirname(__FILE__)."/training-log.php";
		$this->plugin = new TrainingLog(false);
	}

	function widget($args, $instance) {
		extract( $args );

		echo $before_widget;

		$title = apply_filters( 'widget_title', isset( $instance['title'] ) ? $instance['title'] : '', $instance, $this->id_base );
		$link = isset( $instance['link'] ) ? $instance['link'] : false;

		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;

		
		
		$year = date('Y');
		$month = date('m');
		//, new DateTimeZone('America/New_York')
		$count = 6;
		$now = new DateTime( $count . " days ago");
		$interval = new DateInterval( 'P1D');
		$period = new DatePeriod( $now, $interval, $count); 
		$date_range = $dates = array();
		foreach( $period as $day) {
		    $date_range[] = array(
		    	'date' => $day->format($this->plugin->date_format),
		    	'day' => $day->format('d'),
		    	'format_day' => $this->daymap[$day->format('D')]
		    );
		}
		$sqlSelect = "SELECT * FROM  " . $this->plugin->db_table_name . "  WHERE user_id =  " . $this->plugin->_currentUserId() . " AND date >= '" . $date_range[0]['date'] . "' AND date <= '" . $date_range[$count]['date'] . "' ORDER BY id DESC";
		global $wpdb;
		$rows = $wpdb->get_results( $sqlSelect );
		$max = 0;
		$total = array(
			'seconds'=>0,
			'kcal' =>0
		);
		for($i = 0; $i < count($date_range); $i++ ) {
			$start = mktime(0, 0, 0, $month, $date_range[$i]['day'], $year);
			$end = mktime(23, 59, 59, $month, $date_range[$i]['day'], $year); 
			$seconds = 0;
			$kcal = 0;
			$workouts = 0;
			$display = "";
			$time = 0;
			foreach($rows as $row) {
				$ts = strtotime($row->date);
				if($ts >= $start && $ts <= $end) {
					$seconds += $row->seconds;
					$kcal += $row->kcal;
					$workouts++;
				}
			}
			if($workouts > 0) {
				if($seconds > $max) {
					$max = $seconds;
				}
				switch ($workouts) {
				    case 1:
				        $display = "1 økt, ";
				        break;
				    default:
				        $display = $workouts . " økter, ";
				        break;
				}
				$raw = $this->plugin->sec_to_number($seconds);
				if($raw[0] > 0) {
					$display .= $raw[0] . "t og " . $raw[1] . "min trening";
				} else {
					$display .= $raw[1] . "min trening";
				}
				$display .= " (" . $kcal . "kcal)";
				$time = $raw[2];
				$total['seconds'] += $seconds;
				$total['kcal'] += $kcal;
			} else {
				$display = "Ingen trening denne dagen";
			}

			$dates[$i] = array(
				'title' => $date_range[$i]['format_day'],
				'time' => $time,
				'display' => $display,
				'kcal' => $kcal,
				'seconds' => $seconds,
				'prosent' => 0
			);
		}
		
		$raw = $this->plugin->sec_to_number($total['seconds']);
		$total['hour'] = $raw[0];
		$total['min'] = $raw[1];
		
		for($i = 0; $i < count($date_range); $i++ ) {
			if($dates[$i]['seconds'] > 0) {
				$dates[$i]['prosent'] = round(($dates[$i]['seconds'] / $max) * 100);
			} 
			
		}
		echo "<div><div class='training-log-sidebar'>";
		foreach($dates as $date) {
			echo "<div class='training-log-column'>";
			echo "<div><span style='height:".$date['prosent']."px' alt='".$date['display']."' title='".$date['display']."'></span></div> " . $date['title'];
			echo "</div>";
		}
		if($total['seconds']>0) {
		?>
			<div class="tl-box">
				<span class="tl-time"><span><?php echo $total['hour'] ?></span>timer <span><?php echo $total['min'] ?></span>min</span>
				<span class="tl-kcal"><span><?php echo $total['kcal'] ?></span>kcal</span>
			</div>
		<?php 
		}
		if($link) {
			echo '</div><p><a href="' . $link . '">Gå til treningsdagbok</a></p>';
		}
		echo "</div>";

		

		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
	    return $new_instance;
	}

	function form($instance) {

	    $defaults = array(
	        'title' => 'Title',
	        'text' => ''
	    );
	    $instance = wp_parse_args((array) $instance, $defaults);
	    ?>
	    <p>
	        <label for="<?php echo $this->get_field_id('title'); ?>">Tittel</label>
	        <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" style="width:90%;" />
	    </p>
	    <p>
	        <label for="<?php echo $this->get_field_id('link'); ?>">Lenke til side med treningsdagbok</label>
	        <input class="widefat" id="<?php echo $this->get_field_id('link'); ?>" name="<?php echo $this->get_field_name('link'); ?>" value="<?php echo $instance['link']; ?>" style="width:90%;" />
	    </p>

	    <?php
	}

}
