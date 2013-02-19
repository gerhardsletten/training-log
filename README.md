Training log for Wordpress
==========================

Training log is a plugin for Wordpress that gives logged in users a personal training-log. 

## The training log recorder:

With the shortcode-tags you can display a logger:

	[training_log_add]

Or by adding this php to your single.php in your theme:

	<?php 
	if( class_exists("TrainingLog") ) {
		$training_log = new TrainingLog();
		$training_log->training_log_add_direct();
	}
	?>

## Training logs

Add this shorttag to the page where you want to display the users personal training log:

	[training_log_table]

## Admin stuff

A page with all training logs are available in Wordpress admin and a setting for calories per seconds.

[Wordpress plugin by Metabits.no](http://www.metabits.no)
