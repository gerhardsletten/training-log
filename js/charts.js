(function($) {
	$(document).ready( function() {

		// If you want to override default options 
		var chart = new Charts.LineChart('training-chart', {
			show_grid: false,
			label_max: false,
			label_min: false,
			x_label_size: 13,
			label_format: "%d.%m",
			show_y_labels: false
		});

		var data = [];
		$.each(training, function(key,value){
			data.push([new Date(value.title),value.time,{tooltip:value.display}]);
		});

		// Line charts also support custom tooltips, eg: 
		chart.add_line({ 
			data: data,
			options: {
			    line_color: "#666666",
			    dot_color: "#91C700",
			    area_color: "",
			    area_opacity: 0.2,
			    dot_size: 5,
			    line_width: 4 
			  }
		});

		chart.draw();

		$( "#tl_own_date" ).datepicker({
			dateFormat:"yy-mm-dd"
		});
	});
})(jQuery);