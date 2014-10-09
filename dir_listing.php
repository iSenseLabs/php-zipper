<?php
header('Content-Type: application/json');

$requested_dir = !empty($_GET['dir']) ? rtrim($_GET['dir'], '/') : dirname(__FILE__);
$requested_dir = realpath($requested_dir);
$json = array();

if (!is_dir($requested_dir)) {
	$json['error'] = true;
	$json['msg'] = 'Bad directory';
	echo json_encode($json);exit;
}

$entries = scandir($requested_dir);
$json['entries'] = array(
	'dirs' => array(),
	'files' => array()
);

foreach ($entries as $entry) {
	if ($entry == '.') continue;
	
	$full_path = $requested_dir.'/'.$entry;
	
	if (is_dir($full_path)) {
		$json['entries']['dirs'][md5($full_path)] = array(
			'absolute_path' => $full_path,
			'name' => $entry
		);
	} else {
		$json['entries']['files'][md5($full_path)] = array(
			'absolute_path' => $full_path,
			'name' => $entry
		);
	}
}

$json['error'] = false;
$json['cwd'] = $requested_dir;
echo json_encode($json);exit;