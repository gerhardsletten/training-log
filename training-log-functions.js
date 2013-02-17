(function($) {
	$(document).ready( function() {
		$('#training-log-add').submit(function(e){
			e.preventDefault();
			TrainingLog.addSession($(this).serialize(), function(data) {
				console.log(data);
			}, function(data){
				console.log("Error:");
				console.log(data);
			});
		});
		
	});
})(jQuery);