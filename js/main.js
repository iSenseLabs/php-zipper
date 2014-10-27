var cwd = '';
var dir_listing = [];
var dirListingObj = $('#directoryListing');
var progressLogObj = $('#processLog');
var progressBarObj = $('#progressBar');
var progressBarMsgObj = $('#progressBarMsg');
var processFinished = true;
var progressLastRun = false;
var lastPrintedMessage = '';
var initialRun = 1;
var selected_paths = [];
var lastZipResponse = {};
var isPaused = false;

function populate_dir_listing(entries) {
	dirListingObj.empty();
	
	if (entries.dirs) {
		var dir_html = '';
		for (x in entries.dirs) {
			dir_html += '<div class="checkbox"><input type="checkbox" name="targetEntries" value="'+x+'" id="'+x+'" /><label for="'+x+'"><i class="glyphicon glyphicon-folder-close"></i>&nbsp;<a class="dir-listing-entry" data-key="'+x+'">'+entries.dirs[x].name+'</a></label></div>';
		}
		dirListingObj.append(dir_html);
	}
	
	if (entries.files) {
		var files_html = '';
		for (x in entries.files) {
			files_html += '<div class="checkbox"><input type="checkbox" name="targetEntries" value="'+x+'" id="'+x+'" /><label for="'+x+'"><i class="glyphicon glyphicon-file"></i>&nbsp;'+entries.files[x].name+'</label></div>';
		}
		dirListingObj.append(files_html);
	}
}

function get_dir_listing(target_dir) {
	target_dir = target_dir ? target_dir : '';
	
	$.ajax({
		url: 'dir_listing.php',
		type: 'GET',
		data: {
			dir: target_dir
		},
		dataType: 'json',
		success: function(resp) {
			if (!resp.error) {
				cwd = resp.cwd;
				dir_listing = resp.entries;
				populate_dir_listing(dir_listing);
			} else {
				swal({
				    title: 'Error:',
				    text: resp.msg,
				    type: 'error'
				});
			}
		}
	});
}

function watchProgress() {
	if (processFinished || isPaused) progressLastRun = true;
	else progressLastRun = false;
	setTimeout(function() {
		$.ajax({
			url: 'get_progress.php',
			type: 'GET',
			dataType: 'json',
			success: function(resp) {
				var start = resp.msgs.indexOf(lastPrintedMessage);
				
				var newMessages = '';
				var newMessagesCount = 0;
				for (x = start+1;x<resp.msgs.length;x++) {
					lastPrintedMessage = resp.msgs[x];
					newMessages += "\n" + resp.msgs[x];
					newMessagesCount++;
				}
				
				var logLength = progressLogObj.val().split("\n").length;
				var logMessages = progressLogObj.val();
				if (length >= 200) {
					var logHistory = progressLogObj.val().split("\n");
					logHistory.splice(0, logLength-(199+newMessagesCount)); //Keep the history with a maximum of 1000 lines
					logMessages = logHistory.join("\n");
				}
				progressLogObj.val(logMessages + newMessages);
				progressLogObj.scrollTop(progressLogObj[0].scrollHeight);
				
				progressBarObj.attr('aria-valuenow', resp.percent);
				progressBarObj.css('width', resp.percent+'%');
				progressBarMsgObj.text(resp.percent+'% completed');
				
				if (progressLastRun) {
					progressBarObj.removeClass('active');
					$('#btnZipAll, #btnZipSelected, #btnContinue').removeClass('disabled');
					$('#btnPause').addClass('disabled');
				}
			},
			complete: function() {
				if (!progressLastRun) {
					watchProgress();
				}
			}
		});
	}, 1000);
}

function zip(target_paths) {
    if (isPaused) return;
		
	if (initialRun) {
		progressLogObj.val("");
		lastPrintedMessage = '';
	}
	
	$('#btnZipAll, #btnZipSelected, #btnContinue').addClass('disabled');
	$('#btnPause').removeClass('disabled');
	progressBarObj.addClass('active');
	
	var flushToDisk = $('#flushToDisk').val() ? $('#flushToDisk').val() : 50;
	var maxExecutionTime = $('#maxExecutionTime').val() ? $('#maxExecutionTime').val() : 20;
	var exclude_strings = $('#excludes').val() ? $('#excludes').val() : '';
	var useSystemCalls = $('#useSystemCalls').is(':checked');
	var preloadFiles = $('#preloadFiles').is(':checked');
	var zip_url = preloadFiles ? 'zip-preload.php' : 'zip.php';
	
	$.ajax({
		url: zip_url,
		type: 'POST',
		data: {
			targets: target_paths,
			flush_to_disk: flushToDisk,
			max_execution_time: maxExecutionTime,
			excludes: exclude_strings,
			is_initial_run: initialRun,
			use_system_calls: useSystemCalls
		},
		dataType: 'json',
		beforeSend: function() {
			processFinished = false;
			if (initialRun) {
				watchProgress();
			}
			initialRun = 0;
		},
		success: function(resp) {
			lastZipResponse = resp;
			if (resp.error) {
				swal({
				    title: 'Error:',
				    text: resp.msg,
				    type: 'error'
				});
			} else {
				if (resp.fileURL) {
				    swal({
    				    title: 'Your ZIP is ready!',
    				    text: 'Would you like to download it?',
    				    type: 'success',
    				    showCancelButton: true,
    				    confirmButtonText: 'Download',
    				    confirmButtonColor: '#FF4A4A'
    				},
    				function(){
    				    window.location = resp.fileURL;
    				});
				}
			}
		},
		complete: function() {
			if (!lastZipResponse.error && lastZipResponse.continue) {
				zip(selected_paths);
			} else {
				processFinished = true;
			}
		}
	});
}

$(document).on('click', '.dir-listing-entry', function(e){
	e.preventDefault();
	var key = $(this).attr('data-key');
	if (dir_listing.dirs[key]) {
		get_dir_listing(dir_listing.dirs[key].absolute_path);
	}
});

$(document).on('click', '#btnZipSelected:not(.disabled)', function() {
	initialRun = 1;
	isPaused = false;
	selected_paths = [];
	$('input[name="targetEntries"]:checked').each(function(i, e){
		var key = e.value;
		if (dir_listing.dirs[key]) {
			selected_paths.push(dir_listing.dirs[key].absolute_path);
		} else if (dir_listing.files[key]) {
			selected_paths.push(dir_listing.files[key].absolute_path);
		}
	});
	
	if (selected_paths.length) {
		zip(selected_paths);
	}
});

$(document).on('click', '#btnZipAll:not(.disabled)', function() {
	initialRun = 1;
	isPaused = false;
	if (cwd) {
		selected_paths = [cwd];
		zip(selected_paths);
	}
});

$(document).on('click', '#btnPause:not(.disabled)', function() {
    $.ajax({url: 'abort.php'});
	isPaused = true;
	$(this).addClass('disabled');
});

$(document).on('click', '#btnContinue:not(.disabled)', function() {
    if (selected_paths) {
    	isPaused = false;
    	zip(selected_paths);
    	watchProgress();
    }
});

$(document).ready(function(){
	get_dir_listing(cwd);
});