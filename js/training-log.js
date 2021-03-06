(function( TrainingLog, $, undefined ) {

	TrainingLog.addSession = function(obj, callback, error_callback) {
		$.post(TrainingLog.ajaxurl, {
			action : 'addSession',
			data : obj,
			nonce : TrainingLog.nonce
		} ,function(data){
			if(data.error && error_callback) {
				error_callback(data.error);
				return;
			}
			if(callback) {
				callback(data);
			}
		});
	},
	TrainingLog.editSession = function(obj_id, obj, callback, error_callback) {
		$.post(TrainingLog.ajaxurl, {
			action : 'editSession',
			id: obj_id,
			data : obj,
			nonce : TrainingLog.nonce
		} ,function(data){
			if(data.error && error_callback) {
				error_callback(data.error);
				return;
			}
			if(callback) {
				callback(data);
			}
		});
	},
	TrainingLog.deleteSession = function(id, callback, error_callback) {
		$.post(TrainingLog.ajaxurl, {
			action : 'deleteSession',
			id : id,
			nonce : TrainingLog.nonce
		} ,function(data){
			if(data.error && error_callback) {
				error_callback(data.error);
				return;
			}
			if(callback) {
				callback(data);
			}
		});
	},
	TrainingLog.listSessions = function(page, limit, callback, error_callback) {
		$.post(TrainingLog.ajaxurl, {
			action : 'listSessions',
			page : page,
			limit: limit,
			nonce : TrainingLog.nonce
		} ,function(data){
			if(data.error && error_callback) {
				error_callback(data.error);
				return;
			}
			if(callback) {
				callback(data);
			}
		});
	}
}( window.TrainingLog = window.TrainingLog || {}, jQuery ));