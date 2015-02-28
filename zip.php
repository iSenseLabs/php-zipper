<?php
$startTime = microtime(true);
header('Content-Type: application/json');

function flush_zip() {
	global $zip, $oFile;
	$zip->close();
	$zip->open($oFile);
}

function zip_dir($path, $base = '') {
	global $progress, $zip, $total_targets, $startTime, $max_execution_time, $is_initial_run, $iteration_number;
	
	$entries = scandir($path);
	
	foreach ($entries as $entry) {
		abort_if_requested();
		
		$execution_time = microtime(true)-$startTime;
		if ($execution_time >= $max_execution_time) stop_iteration();
		
		if (in_array($entry, array('.', '..'))) continue;
		set_time_limit(60);
		
		$full_path = rtrim($path) .'/'. $entry;
		if (is_excluded($full_path)) continue;
		
		if (is_dir($full_path)) {
			if ($iteration_number > $progress->getProgress(false)) {
				$progress->addMsg('Adding directory "' . $full_path . '"');
				zip_dir($full_path, $base.'/'.$entry);
			} else {
				zip_dir($full_path, $base.'/'.$entry);
			}
		} else {
			$iteration_number++;
			if ($iteration_number > $progress->getProgress(false)) {
				$progress->addMsg('Adding file "' . $full_path . '"');
				$zip->addFile($full_path, $base.'/'.$entry);
				$progress->iterateWith(1);
				
				if ($zip->numFiles % 50 == 0) flush_zip();//Write to disk every 50 files. This should free the memory taken up to this point
			}
		}
	}
}

function stop_iteration() {
	global $zip;
	$zip->close();
	
	$json = array(
		'error' => false,
		'continue' => true
	);
	echo json_encode($json);exit;
}

function is_excluded($path) {
	global $excludes;
	foreach ($excludes as $e) {
		if (strpos($path, $e) !== false) return true;
	}
	
	return false;
}

function build_exclude_find_params() {
	global $excludes;
	$params = '';
	foreach ($excludes as $e) {
		$params .= ' -not -path "*'.$e.'*"';
	}
	return $params;
}

function count_dir_files($path) {
	global $use_system_calls;
	
	$path = rtrim($path, '/');
	if ($use_system_calls) {
		exec('find '.$path.' -follow -type f'.build_exclude_find_params().' | wc -l', $output);
		if (!empty($output[0])) {
			return (int)trim($output[0]);
		}
		return 0;
	} else {
		$total = 0;
		if (!is_excluded($path)) {
			if (is_dir($path)) {
				$dh = opendir($path);
				while(false !== ($entry = readdir($dh))) {
					if (!in_array($entry, array('.','..')) && !is_excluded($entry)) {
						$full_path = $path . '/' . $entry;
						if (is_dir($full_path)) {
							$total += count_dir_files($full_path);
						} else {
							$total++;
						}
					}
				}
			} else {
				$total++;
			}
		}
		return $total;
	}
}

function abort_if_requested() {
	global $last_abort_check, $progress;
	if ((microtime(true) - $last_abort_check) > 0.5) {
		if ($progress->abortCalled()) {
			stop_iteration();
		}
		$last_abort_check = microtime(true);
	}
}

//Begin init process
require_once 'iprogress.php';
$progress = new iProgress('zip', 200);

$json = array();

$is_initial_run = !empty($_POST['is_initial_run']);
$flush_to_disk = !empty($_POST['flush_to_disk']) ? (int)$_POST['flush_to_disk'] : 50;
$max_execution_time = !empty($_POST['max_execution_time']) ? (int)$_POST['max_execution_time'] : 20;
$exclude_string = !empty($_POST['excludes']) ? $_POST['excludes'] : '';
$excludes = array_filter(array_map('trim', explode(',', $exclude_string)));
$use_system_calls = (!empty($_POST['use_system_calls']) && $_POST['use_system_calls'] == 'true') ? true : false;
$last_abort_check = microtime(true);

$targets = ($is_initial_run && !empty($_POST['targets'])) ? $_POST['targets'] : $progress->getData('targets');
if (!$targets) {
	$json['error'] = true;
	$json['msg'] = 'Bad targets';
	echo json_encode($json);exit;
}

if ($is_initial_run) {
	$progress->clear();
	$progress->addMsg('Scanning files to be compressed...');
	$progress->setData('targets', $targets);
}

$total_targets = $is_initial_run ? 0 : $progress->getMax();
$true_targets = array();

clearstatcache(true);
foreach ($targets as $target) {
	$path = realpath($target);
	
	if (file_exists($path)) {
		if ($is_initial_run) {
			if (is_dir($path)) {
				$total_targets += count_dir_files($path);
			} else {
				$total_targets++;
			}
		}
		
		$true_targets[] = $path;
	}
}

if ($is_initial_run) {
	$progress->addMsg('Found ' . $total_targets . ' items for zipping');
	$progress->setMax($total_targets);
}

$oFile = ($is_initial_run || !$progress->getData('oFile')) ? dirname(__FILE__).'/archive_'.time().'.zip' : $progress->getData('oFile');
$progress->setData('oFile', $oFile);

$zip = new ZipArchive();
$zip->open($oFile, ZipArchive::CREATE);
$iteration_number = 0;

if ($total_targets && $true_targets) {
	foreach ($true_targets as $target) {
		abort_if_requested();
		if (is_excluded($target)) continue;
		
		$execution_time = microtime(true)-$startTime;
		if ($execution_time >= $max_execution_time) stop_iteration();
		
		set_time_limit(60);
		if (is_dir($target)) {
			if ($iteration_number > $progress->getProgress(false)) {
				$progress->addMsg('Adding directory "' . $target . '"');
				zip_dir($target, basename($target));
			} else {
				zip_dir($target, basename($target));
			}
		} else {
			$iteration_number++;
			if ($iteration_number > $progress->getProgress(false)) {
				$progress->addMsg('Adding file "' . $target . '"');
				$zip->addFile($target, basename($target));
				$progress->iterateWith(1);
				
				if ($zip->numFiles % 50 == 0) flush_zip();//Write to disk every 50 files. This should free the memory taken up to this point
			}
		}
	}
	$progress->addMsg('--- The output file is: '.$oFile.' ---');
	$progress->addMsg('--- Finished! ---');
}

$zip->close();

$file_url = '//'.$_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . basename($oFile);
$json = array(
	'error' => false,
	'continue' => false,
	'fileURL' => $file_url
);
echo json_encode($json);exit;
