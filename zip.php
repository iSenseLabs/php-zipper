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
		$execution_time = microtime(true)-$startTime;
		if ($execution_time >= $max_execution_time || headers_length() > 10000) stop_iteration();
		
		if (in_array($entry, array('.', '..'))) continue;
		set_time_limit(60);
		
		$full_path = rtrim($path) .'/'. $entry;
		if (is_dir($full_path)) {
			if ($iteration_number > $progress->getProgress(false)) {
				$progress->addMsg('Adding directory "' . basename($full_path) . '"');
				zip_dir($full_path, $base.'/'.$entry);
				$progress->addMsg('Added the directory "' . basename($full_path) . '"');
			} else {
				zip_dir($full_path, $base.'/'.$entry);
			}
		} else {
			$iteration_number++;
			if ($iteration_number > $progress->getProgress(false)) {
				$progress->addMsg('Adding file "' . basename($full_path) . '"');
				$zip->addFile($full_path, $base.'/'.$entry);
				$progress->addMsg('Added the file "' . basename($full_path) . '"');
				$progress->iterateWith(1);
				
				if ($zip->numFiles % 50 == 0) flush_zip();//Write to disk every 50 files. This should free the memory taken up to this point
			}
		}
	}
}

function stop_iteration() {
	global $oFile, $zip;
	$zip->close();
	
	$json = array(
		'error' => false,
		'continue' => true,
		'oFile' => $oFile
	);
	echo json_encode($json);exit;
}

function headers_length() {
	$headers = headers_list();
	$length = 0;
	foreach ($headers as $header) {
		$length += strlen($header);
	}
	
	return $length;
}

require_once 'iprogress.php';
$progress = new iProgress('zip', true, 200);

$json = array();

$targets = !empty($_POST['targets']) ? $_POST['targets'] : array();
if (!$targets) {
	$json['error'] = true;
	$json['msg'] = 'Bad targets';
	echo json_encode($json);exit;
}

$is_initial_run = !empty($_POST['is_initial_run']);
$flush_to_disk = !empty($_POST['flush_to_disk']) ? (int)$_POST['flush_to_disk'] : 50;
$max_execution_time = !empty($_POST['max_execution_time']) ? (int)$_POST['max_execution_time'] : true;

if ($is_initial_run) {
	$progress->addMsg('Scanning files to be compressed...');
	$progress->clear();
}

$total_targets = $is_initial_run ? 0 : $progress->getMax();
$true_targets = array();

foreach ($targets as $target) {
	$path = realpath($target);
	
	if (file_exists($path)) {
		if ($is_initial_run) {
			if (is_dir($path)) {
				exec('find '.$path.' -follow -type f | wc -l', $output);
				if (!empty($output[0])) {
					$subtargets = (int)trim($output[0]);
					$total_targets += $subtargets;
				}
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

$oFile = ($is_initial_run || empty($_POST['oFile'])) ? dirname(__FILE__).'/archive_'.time().'.zip' : $_POST['oFile'];

$zip = new ZipArchive();
$zip->open($oFile, ZipArchive::CREATE);
$iteration_number = 0;

if ($total_targets && $true_targets) {
	foreach ($true_targets as $target) {
		$execution_time = microtime(true)-$startTime;
		if ($execution_time >= $max_execution_time || headers_length() > 10000) stop_iteration();
		
		set_time_limit(60);
		if (is_dir($target)) {
			if ($iteration_number > $progress->getProgress(false)) {
				$progress->addMsg('Adding directory "' . basename($target) . '"');
				zip_dir($target, basename($target));
				$progress->addMsg('Added the directory "' . basename($target) . '"');
			} else {
				zip_dir($target, basename($target));
			}
		} else {
			$iteration_number++;
			if ($iteration_number > $progress->getProgress(false)) {
				$progress->addMsg('Adding file "' . basename($target) . '"');
				$zip->addFile($target, basename($target));
				$progress->addMsg('Added the file "' . basename($target) . '"');
				$progress->iterateWith(1);
				
				if ($zip->numFiles % 50 == 0) flush_zip();//Write to disk every 50 files. This should free the memory taken up to this point
			}
		}
	}
	$progress->addMsg('--- The output file is: '.$oFile.' ---');
	$progress->addMsg('--- Finished! ---');
}

$zip->close();

$json = array(
	'error' => false,
	'continue' => false,
	'oFile' => ''
);
echo json_encode($json);exit;