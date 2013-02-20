(function($) {
	$(document).ready( function() {
		$('.training-log-add').each(function(){
			var holder = $(this),
				id_field = holder.find('[name="id"]'),
				post_id_field = holder.find('[name="post_id"]'),
				seconds_field = holder.find('[name="seconds"]'),
				kcal_field = holder.find('[name="kcal"]'),
				startButton = holder.find('.training-log-start'),
				stopButton = holder.find('.training-log-stop'),
				seconds_display = holder.find('.training-log-seconds dd'),
				kcal_display = holder.find('.training-log-kcal dd'),
				cal_per_seconds = parseFloat( holder.find('[name="cal_per_seconds"]').val() ),
				seconds = 0,
				kcal = 0,
				current_id = false,
				is_saving = false,
				counter = null,
				status_field = holder.find('.training-log-status'),
				playing_message = status_field.text();
			
			stopButton.attr('disabled', 'disabled');

			function format_seconds(seconds) {
				minutes = Math.floor(seconds / 60);
				seconds = seconds - (minutes*60);
				if(minutes<10)
					minutes = "0" + minutes;
				if(seconds<10)
					seconds = "0" + seconds;
				return minutes + ":" + seconds;
			}

			function displayMessage(text, error) {
				if(text) {
					status_field.fadeIn().text(text);
					if(error) {
						status_field.addClass('training-log-error');
					} else {
						status_field.removeClass('training-log-error');
					}
				} else {
					status_field.text("").hide();
				}
				
			}

			holder.bind('TraningLog.Start', function(e){
				startButton.attr('disabled', 'disabled');
				stopButton.removeAttr("disabled");
				displayMessage(playing_message);
			});

			holder.bind('TraningLog.Stop', function(e, sec){
				kcal = parseInt(sec * cal_per_seconds);
				seconds_field.val(sec);
				kcal_field.val(kcal);
				if(!is_saving) {
					is_saving = true;
					if(!current_id) {
						TrainingLog.addSession(holder.serialize(), function(data) {
							displayMessage(data.message);
							current_id = data.data.id;
							is_saving = false;
						}, function(data){
							displayMessage(data.error, true);
						});
					} else {
						TrainingLog.editSession(holder.serialize(), function(data) {
							displayMessage(data.message);
							is_saving = false;
						}, function(data){
							displayMessage(data.error, true);
						});
					}
				}
				
				startButton.removeAttr("disabled");
				stopButton.attr('disabled', 'disabled');
			});

			holder.bind('TraningLog.Update', function(e, sec){
				kcal = parseInt(sec * cal_per_seconds);
				seconds_field.val(sec);
				kcal_field.val(kcal);
				seconds_display.text(format_seconds(sec));
				kcal_display.text(kcal);
			});

			displayMessage();

			seconds_display.text(format_seconds(seconds));

			startButton.click(function(e){
				e.preventDefault();
				holder.trigger('TraningLog.Start');
				counter = setInterval(function(){
					seconds++;
					holder.trigger('TraningLog.Update', seconds);
				},1000);
			});

			stopButton.click(function(e){
				e.preventDefault();
				holder.trigger('TraningLog.Stop', seconds);
				clearTimeout(counter);
			});
		});
	});
})(jQuery);