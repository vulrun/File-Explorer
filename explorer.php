<?php
/**
 ***********************************************
 * File Explorer
 * Copyright:  WebCDN (webcdn)
 * 
 * https://github.com/webcdn/File-Explorer
 * License: GNU
 ***********************************************
 */

session_start();
setlocale(LC_ALL, 'en_US.UTF-8');
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', true);
error_reporting(0);

define('VERSION', '1.2');
define('_CONFIG', __DIR__.'/.config');
define('_URL', $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST']);
$errors = error_get_last();


if( file_exists(_CONFIG) ){
	$config = json_decode( file_get_contents(_CONFIG) );
	$assets = trim(str_replace(_URL, '', $config->assets), '/');
	$MAX_UPLOAD_SIZE = min( inBytes( ini_get('post_max_size') ), inBytes( ini_get('upload_max_filesize') ) );
}
else {
	file_put_contents(_CONFIG, json_encode( array('password' => '0c7540eb7e65b553ec1ba6b20de79608', 'list_view' => false, 'assets' => 'https://cdn.rawgit.com/webcdn/File-Explorer/mdui/assets') ) );
	header('Refresh: 0');
	exit;
}

if( strlen($config->password) ){
	if( strlen($config->password) != 32 && !ctype_xdigit($config->password) ){
		file_put_contents(_CONFIG, json_encode( array('password' => md5( sha1($config->password) )) ) );
		header('Refresh: 0');
		exit;
	}
}

if( !$_SESSION['__allowed'] && strlen($config->password) ) {
	if( !empty($_POST['auth']) && md5(sha1($_POST['auth'])) === $config->password ) {
		$_SESSION['__allowed'] = true;
		$_SESSION['ok_pass'] = $_POST['auth'];
		header('Refresh: 0');
		exit;
	}
	else {
		echo "<!DOCTYPE html>\n";
		echo "<html lang='en'>\n";
		echo "\t<head>\n";
		echo "\t\t<meta charset='utf-8'>\n";
		echo "\t\t<link rel='canonical' href='"._URL.$_SERVER['PHP_SELF']."'>\n";
		echo "\t\t<title>File Explorer v".VERSION."</title>\n";
		echo "\t\t<meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0'>\n";
		echo "\t\t<link rel='icon' type='image/png' href='".$config->assets."/1f4c2.png'>\n";
		echo "\t\t<link rel='stylesheet' href='".$config->assets."/materialize.min.css'>\n";
		echo "\t\t<style>*{outline: none !important; user-select: none; font-family: Roboto, sans-serif;}.middle {width: 90%; min-width: 260px; max-width: 360px; position: absolute; left: 50% !important; top: 50%; transform: translate(-50%, -50%);}</style>\n";
		echo "\t</head>\n";
		echo "\t<body class='basecolor darken-1' onload='auth_login.auth.focus();'>\n";
		echo "\t\t<div class='middle'>\n";
		echo "\t\t\t<div class='card white z-depth-4'>\n";
		echo "\t\t\t\t<div class='card-content'>\n";
		echo "\t\t\t\t\t<div class='grey-text center card-title'><i class='material-icons medium'>lock</i></div>\n";
		echo "\t\t\t\t\t<div class='grey-text center card-title'>Protected</div>\n";
		echo "\t\t\t\t\t<form method='POST' id='auth_login'>\n";
		echo "\t\t\t\t\t\t<div class='input-field'>\n";
		echo "\t\t\t\t\t\t\t<i class='material-icons prefix'>lock</i>";
		echo "\t\t\t\t\t\t\t<input type='password' name='auth' placeholder='Enter your Password' autocomplete='off' spellcheck='false'/>\n";
		echo "\t\t\t\t\t\t</div>\n";
		echo "\t\t\t\t\t</form>\n";
		echo "\t\t\t\t</div>\n";
		echo "\t\t\t\t<div class='card-action center'>\n";
		echo "\t\t\t\t\t<button class='waves-effect waves-light btn width-block' type='submit' form='auth_login'>LOGIN</button>\n";
		echo "\t\t\t\t</div>\n";
		echo "\t\t\t</div>\n";
		echo "\t\t</div>\n";
		echo "\t</body>\n";
		echo "</html>\n";
		exit;
	}
}


if( empty($_COOKIE['__xsrf']) ){
	setcookie('__xsrf', sha1( uniqid() ) );
}

$path = !empty($_REQUEST['path']) ? $_REQUEST['path'] : '.';
$real = @realpath($path);
$deny_paths = array_unique( array_merge( lister(__FILE__), lister($assets) ) );

if( empty($_SESSION['gitJSON']) ){
	$_SESSION['gitJSON'] = @json_decode(file_get_contents('https://raw.githubusercontent.com/webcdn/File-Explorer/mdui/repo.json'),1);
}

if($real === false) {
	echo output(false, 'File or Directory Not Found');
	exit;
}
if( substr($real, 0, strlen(__DIR__)) !== __DIR__ || ( isset($_REQUEST['do']) && in_array($real, $deny_paths) && in_array($_REQUEST['do'], array('delete','rename','edit'))) ){
	echo output(false, 'Forbidden');
	exit;
}
if($_POST) {
	if( empty($_POST['xsrf']) && $_COOKIE['__xsrf'] != $_POST['xsrf'] ){
		echo output(false, 'XSRF Failure');
		exit;
	}
}

if( !empty($_REQUEST['do']) ){
	if($_GET['do'] == 'list') {
		clearstatcache();
		if ( is_dir($path) ) {
			$directory = $path;
			$files = array_diff( scan_dir($directory), array('.', '..') );
			// print_r($files);
			foreach($files as $e => $entry) if( substr($entry, 0, 1) !== '.' ) {
				$i = "$directory/$entry";
				$ext = strtolower(pathinfo($i, PATHINFO_EXTENSION));
				$stat = stat($i);
				$danger = in_array(realpath($i), $deny_paths);
				if( is_readable($i) ) $perms[] = 'Read';
				if( is_writable($i) ) $perms[] = 'Write';
				if( is_executable($i) ) $perms[] = 'Execute';
				$result[] = array(
					'name' => basename($i),
					'sort' => $e,
					'path' => preg_replace('@^\./@', '', $i),
					'real_path' => realpath($i),
					'type' => is_dir($i) ? 'Directory' : (function_exists('mime_content_type') ? mime_content_type($i) : $ext),
					'ext' => is_dir($i) ? '---' : $ext,
					'size' => is_dir($i) ? 0 : $stat['size'],
					'size_ok' => is_dir($i) ? '---' : formatFileSize($stat['size']),
					'perms' => (int) decoct( fileperms($i) & 0777 ),
					'perms_ok' => implode(' + ', $perms),
					'atime' => $stat['atime'],
					'atime_ok' => date('M d, Y - h:i A', $stat['atime']),
					'ctime' => $stat['ctime'],
					'ctime_ok' => date('M d, Y - h:i A', $stat['ctime']),
					'mtime' => $stat['mtime'],
					'mtime_ok' => date('M d, Y - h:i A', $stat['mtime']),
					'mtime_easy' => easy_time( $stat['mtime'] ),
					'is_dir' => is_dir($i),
					'is_deleteable' => is_writable($directory) && !$danger && is_recursively_deleteable($i),
					'is_editable' => !is_dir($i) && is_writable($i) && !$danger && in_array($ext, array('asp','aspx','c','cer','cfm','class','cpp','cs','csr','css','csv','dtd','fla','h','htm','html','java','js','jsp','json','log','lua','m','md','mht','pl','php','phps','phpx','py','sh','sln','sql','svg','swift','txt','vb','vcxproj','whtml','xcodeproj','xhtml','xml')),
					'is_executable' => is_executable($i),
					'is_readable' => is_readable($i),
					'is_writable' => is_writable($i) && !$danger,
					'is_zipable' => is_dir($i) && class_exists('ZipArchive'),
					'is_zip' => ($ext == 'zip') && class_exists('ZipArchive') ? true : false,
				);
				unset($perms);
			}
			header('Content-Type: application/json');
			echo json_encode(array('flag' => true, 'is_writable' => is_writable($path), 'response' => $result), JSON_PRETTY_PRINT);
			exit;
		}
		else {
			echo output(false, 'Not a Directory');
			exit;
		}
	}
	elseif ($_GET['do'] == 'download' && !is_dir($real)) {
		$filename = basename($path);
		header('Content-Type: ' . mime_content_type($path));
		header('Content-Length: '. filesize($path));
		header(sprintf('Content-Disposition: attachment; filename=%s', strpos('MSIE', $_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : $filename ));
		ob_flush();
		readfile($path);
		exit;
	}
	elseif ($_POST['do'] == 'raw' && !is_dir($real)) {
		header('Content-Type: text/plain');
		echo file_get_contents($real);
		exit;
	}
	elseif ($_POST['do'] == 'edit' && !is_dir($real)) {
		$content = $_POST['content'];
		$editStatus = (bool) file_put_contents($path, $content);
		echo ($editStatus) ? output(true, 'File Saved Successfully') : output(false, 'Unable to edit file');
		exit;
	}
	elseif ($_POST['do'] == 'mkdir') {
		chdir($path);
		$dir = str_replace('/', '', $_POST['dirname']);

		if(substr($dir, 0, 2) === '..') {
			echo output(false, 'Invalid Attempt');
		}
		else if (is_dir($dir)) {
			echo output(true, 'Directory Already Exist');
		}
		else {
			echo mkdir($dir, 0755) ? output(true, 'Directory Created') : output(false, 'Unable to create Directory');
		}
		exit;
	}
	elseif ($_POST['do'] == 'nwfile') {
		chdir($path);
		$fl = str_replace('/', '', $_POST['filename']);

		if(substr($fl, 0, 2) === '..') {
			echo output(false, 'Invalid Attempt');
		}
		else if (file_exists($fl)) {
			echo output(true, 'File Already Exist');
		}
		else {
			echo touch($fl) ? output(true, 'File Created') : output(false, 'Unable to create file');
		}
		exit;
	}
	elseif ($_POST['do'] == 'rename') {
		$new = str_replace('/', '', $_POST['newname']);

		if(substr($new, 0, 2) == '..') {
			echo output(false, 'Invalid Attempt');
		}
		else {
			echo rename($real, dirname($real).'/'.$new) ? output(true, 'Renamed Successfully') : output(false, 'Wrong Params');
		}
		exit;
	}
	elseif ($_POST['do'] == 'delete') {
		$tmp = is_dir($real) ? 'Directory' : 'File';
		echo rm_rf($real) ? output(true, $tmp . ' `' . basename($path) . '` Deleted Successfully') : output(false, 'Unable to delete');
		exit;
	}
	elseif ($_POST['do'] == 'permit') {
		$tmp = is_dir($real) ? 'Directory' : 'File';
		echo chmod_rf($real) ? output(true, $tmp . ' `' . basename($path) . '` Permission Reset') : output(false, 'Error to permit');
		exit;
	}
	elseif ($_POST['do'] == 'compress') {
		if (is_dir($real)){
			$zip = new ZipArchive();
			if ($zip->open($path.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE){
				$RDI = new RecursiveDirectoryIterator(pathinfo($path)['filename'], RecursiveDirectoryIterator::SKIP_DOTS);
				$RII = new RecursiveIteratorIterator($RDI, RecursiveIteratorIterator::LEAVES_ONLY);
				foreach ($RII as $loc) if (!$loc->isDir()) {
					$filePath = $loc->getRealPath();
					$zip->addFile($filePath, $loc);
				}
				$zip->close();
				echo output(true, '`'.basename($path).'.zip` created successfully');
			}
			else {
				echo output(false, 'Oops! Unable to compress');
			}
		}
		else {
			echo output(false, 'Oops! Directory is corrupted');
		}
		exit;
	}
	elseif ($_POST['do'] == 'extract') {
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		$pathTo = pathinfo($real, PATHINFO_DIRNAME);
		if (strtolower($ext) == 'zip'){
			$zip = new ZipArchive;
			if ($zip->open($path) === TRUE) {
				$zip->extractTo($pathTo);
				$zip->close();
				echo output(true, 'Archive Extracted Successfully');
			}
			else {
				echo output(false, 'Oops! Archive is corrupted');
			}
		}
		else {
			echo output(false, 'Oops!, Error while extracting `.'.$ext.'` file');
		}
		exit;
	}
	elseif ($_POST['do'] == 'upload') {
		chdir($path);
		move_uploaded_file($_FILES['file_data']['tmp_name'], $_FILES['file_data']['name']);
		exit;
	}
	elseif ($_POST['do'] == 'config') {
		logout();
		$config->list_view = !empty( $_POST['list_view'] ) ? true : false;
		$config->password = !empty( $_POST['pass'] ) ? md5(sha1($_POST['pass'])) : '';
		$config->assets = rtrim($_POST['assets'], '/');
		file_put_contents(_CONFIG, json_encode($config, JSON_PRETTY_PRINT));
		echo output(true, 'Settings Updated Successfully');
		exit;
	}
	elseif ($_REQUEST['do'] == 'logout') {
		echo logout() ? output(true, 'Logged Out Successfully') : output(false, 'Refreshing...');
		exit;
	}
	elseif ($_POST['do'] == 'upgrade') {
		if( $_SESSION['gitJSON']['version'] != VERSION) {
			$updateStatus = (bool) file_put_contents(basename(__FILE__), file_get_contents('https://raw.githubusercontent.com/webcdn/File-Explorer/mdui/explorer.php') );
			logout();
			echo ($updateStatus) ? output(true, 'Updated to Newer Version') : output(false, 'Failed to Update');
		}
		else {
			echo output(false, 'No Updates Available');
		}
		exit;
	}
}





function logout(){
	unset($_SESSION['gitVer']);
	unset($_SESSION['gitJSON']);
	unset($_SESSION['__allowed']);
	setcookie('__xsrf', '', time() - 3600);
	return true;
}


function easy_time($time) {
	$today = date('M d, Y');
	$date = date('M d, Y', $time);
	$time = date('h:i A', $time);
	return ($today == $date) ? $time : $date;
}

function scan_dir($path, $sort = 0) {
	$dir_list = $file_list  = array();
	$files = scandir($path, $sort);
	foreach($files as $file){
		if( is_dir("$path/$file") )
			$dir_list[]  = $file;
		else
			$file_list[] = $file;
	}
	return array_merge($dir_list, $file_list);
}

function rm_rf($loc, &$output = true) {
	if( is_dir($loc) ) {
		$files = array_diff( scandir($loc), array('.', '..') );
		foreach ($files as $file)
			rm_rf("$loc/$file", $output);
		$output &= rmdir($loc);
	}
	else {
		$output &= unlink($loc);
	}
	return $output;
}

function chmod_rf($loc, &$output = true) {
	if( is_dir($loc) ) {
		$output &= chmod($loc, 0755);
		$files = array_diff( scandir($loc), array('.', '..') );
		foreach ($files as $file)
			chmod_rf("$loc/$file", $output);
	}
	else {
		$output &= chmod($loc, 0644);
	}
	return $output;
}

function is_recursively_deleteable($d) {
	$stack = array($d);
	while( $loc = array_pop($stack) ) {
		if(!is_readable($loc) || !is_writable($loc))
			return false;
		if( is_dir($loc) ) {
			$files = array_diff( scandir($loc), array('.', '..') );
			foreach($files as $file)
				$stack[] = "$loc/$file";
		}
	}
	return true;
}

function lister($loc, &$output = array()) {
	$dir = realpath($loc);

	foreach(explode('/', $dir) as $i) { 
		if( !in_array($dir, $output) )
			$output[] = realpath($dir);
		$dir = dirname($dir);
	}
	if( is_dir($loc) ) {
		$files = array_diff( scandir($loc), array('.', '..') );
		foreach ($files as $file)
			lister("$loc/$file", $output);
	}
	return array_filter($output);
}

function output($flag, $response) {
	header('Content-Type: application/json');
	return json_encode( array('flag' => (bool) $flag, 'response' => $response), JSON_PRETTY_PRINT);
}

function inBytes($ini_v) {
	$ini_v = trim($ini_v);
	$units = array('K' => 1<<10, 'M' => 1<<20, 'G' => 1<<30);
	return intval($ini_v) * ($units[strtoupper( substr($ini_v, -1) )] ? : 1);
}

function formatFileSize($bytes, $round = 2) {
	if($bytes >= 0) {
		$units = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
		$pow = floor( max(0, log($bytes) ) / log(1024) );
		$pow = min( $pow, count($units) - 1 );
		$size = $bytes / pow(1024, $pow);
		return round( $size, $round ).' '.$units[$pow];
	}
	else {
		return 'Too Large';
	}
}

if( is_array($errors) ){
	error_log(json_encode(error_get_last(), JSON_PRETTY_PRINT), 1, 'info@grab.gq', 'From: file-explorer_v'.VERSION.'@webcdn.github.io');
}
?>
<!--
===============================================
! File Explorer
! Version: <?= VERSION; ?> 
! 
! https://github.com/webcdn/File-Explorer
! License: GNU
===============================================
-->
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<link rel="canonical" href="<?= _URL.$_SERVER['PHP_SELF']; ?>">
	<title>File Explorer v<?php echo VERSION; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
	<link rel="icon" type="image/png" href="<?php echo $config->assets; ?>/1f4c2.png">
	<link rel="stylesheet" href="<?php echo $config->assets; ?>/materialize.min.css">
	<style>
		* {outline: none !important; font-family: 'Roboto', sans-serif;}
		html {min-width: 280px;}
		body {overflow-y: scroll !important;}

		.modal.modal-footer .modal-content {padding-bottom: 12px;}

		header .breadcrumb.truncate {display: inline-block; max-width: 8rem;}
		header nav ul {background: inherit;}

		main > div {position: relative; margin: 0rem auto; min-height: 70vh;}
		main > div:before {transition: 0.4s; content: ''; position: absolute; top: 0; left: 0; z-index: 2; width: 100%; height: 100%;  background-image: url(<?php echo $config->assets; ?>/loader.svg); background-color: #EEE; background-position: center 5rem; background-size: 10rem; background-repeat: no-repeat; opacity: 0; transform: scale(0);}
		main > div.loading:before {opacity: 1; transform: scale(1);}

		main {margin-bottom: 2rem;}
		main .card {background: #FFF; box-shadow: none; margin: 0.5rem 0;}
		main .card:hover {box-shadow: inset 0 4em 2px rgba(50, 150, 250, 0.3);}
		main .card .card-content {padding: 1rem 4rem 1rem 1rem !important; color: #444;}
		main .card.is_file .card-content {line-height: 1rem;}
		main .card.is_file .card-content:before {content: attr(data-size); position: absolute; left: 3.4rem; bottom: 0.35rem; color: #BBB; font-size: 80%;}
		main .card .card-content i.left {margin-right: 10px;}
		main .card .more {cursor: pointer; position: absolute; right: 0; top: 0; width: 3.5rem; line-height: 3.5rem; border-radius: 2px; text-align: center; border-left: 1px solid #EEE;}
		main .card .more i.material-icons {line-height: inherit;}
		main .collection {position: absolute; min-width: 150px; z-index: 999; top: 3rem; right: 0; transition: scale(1);}
		main .collection .collection-item {padding: 10px; cursor: pointer;}


		main.list .tHead .tH {color: #777; font-size: 90%; cursor: pointer; position: relative;}
		main.list .tHead .tH.sort_asc:after {content: "\e316"; font-family: 'Material Icons'; color: #AAA; font-size: 1.75rem; line-height: 1; position: absolute;}
		main.list .tHead .tH.sort_desc:after {content: "\e313"; font-family: 'Material Icons'; color: #AAA; font-size: 1.75rem; line-height: 1; position: absolute;}
		main.list .tBody .tD:not(.s8) {color: #777; font-size: 90%;}

		main.list .collection {top: 2.25rem;}
		main.list .card {margin: 0 !important; margin-bottom: 0.5rem !important;}
		main.list .tHead,
		main.list .card .card-content {padding: 0.5rem 3rem 0.5rem 0.25rem !important; line-height: 1.75rem;}
		main.list .card .card-content:before {display: none;}
		main.list .card .more {width: 2.7rem; line-height: 2.7rem;}


		footer {position: fixed; bottom: 0; z-index: 1; width: 100%; height: 1.75rem; line-height: 1.85rem; padding: 0 1rem; color: #EEE; background: #245; box-shadow: inset 0 2px 1rem rgba(0,0,0,0.5); font-size: 0.7rem;}
		footer .upgrade {padding: 0.1rem 0.3rem; border-radius: 2px; cursor: pointer;}


		.modal.bottom-sheet.full {max-height: 100%;}
		.modal.bottom-sheet .modal-content {padding: 1rem !important;}
		.modal.bottom-sheet .modal-footer {padding: 1rem !important; height: 68px;}
		.modal textarea {height: 78vh !important; resize: none; padding: 0.5rem;}

		#uploadModal {box-shadow: inset 0 0 5rem rgba(0, 0, 0, 0.2);}
		#uploadModal .modal-content {color: #333; -webkit-transition: background 0.2s; transition: background 0.2s;}
		#uploadModal .modal-content.hover {background: #CC9;}
		#uploadModal .modal-content #drop_area {padding: 20vh 0; border: 5px dashed rgba(0,0,0,0.3) !important;}

		#progressModal .progress {height: 0.5rem;}
		#progressModal .pcent {height: 1rem; line-height: 1rem; font-size: 1rem;}
		#progressModal .pcent .material-icons {height: inherit; line-height: inherit; font-size: inherit;}

		#configModal .pwdeye {position: absolute; right: 0.2rem; bottom: 0.6rem;}
		#configModal .collapse_btn {display: block; cursor: pointer; padding: 2px 0; margin-top: 0.5rem;}
		#configModal .collapse_btn.active {box-shadow: 0 -5px 5px -5px #AAA;}

		.toast.wait {cursor: wait; padding-left: 4rem;}
		.toast.wait:before {
			content: '';
			position: absolute;
			left: 1rem;
			width: 2rem;
			height: 2rem;
			border: 2px solid;
			border-radius: 50%;
			-webkit-animation: pulsate 1s linear infinite;
			animation: pulsate 1s linear infinite;
		}
		@keyframes pulsate {
			0%		{ opacity: 0; transform: scale(0.1); -webkit-transform: scale(0.1);}
			50%		{ opacity: 1; }
			100%	{ opacity: 0; transform: scale(1.2); -webkit-transform: scale(1.2);}
		}

		@media (max-width : 600px) {
			header .breadcrumb.truncate {max-width: 5rem;}
			header .breadcrumb:before {margin: 0;}

			.toast.wait:before {top: 25%;}
			main {margin-bottom: 3.25rem;}

			footer {height: 2.75rem; line-height: 1.3rem; padding: 0.2rem;}
			footer .left-align, footer .right-align {float: none !important; text-align: center !important;}
		}
	</style>
</head>
<body class="grey lighten-3">
	<header>
		<nav class="row">
			<div class="col l8 m12 s12 no-padding">
				<div class="nav-wrapper" id="breadcrumb"></div>
			</div>
			<ul class="col l4 m12 s12 right right-align">
				<a class="tooltipped waves-effect waves-light modal-trigger" data-position="bottom" data-tooltip="Upload" data-target="uploadModal"><i class="material-icons">file_upload</i></a>
				<a class="tooltipped waves-effect waves-light modal-trigger" data-position="bottom" data-tooltip="New Folder" data-target="newDirModal"><i class="material-icons">create_new_folder</i></a>
				<a class="tooltipped waves-effect waves-light modal-trigger" data-position="bottom" data-tooltip="New File" data-target="newFileModal"><i class="material-icons">note_add</i></a>
				<a class="tooltipped waves-effect waves-light modal-trigger" data-position="bottom" data-tooltip="Settings" data-target="configModal"><i class="material-icons">settings</i></a>
			</ul>
		</nav>
		<div class="clearfix"></div>
	</header>
	<main class="<?php echo !empty($config->list_view) ? 'list' : 'tile'; ?>">
		<div class="row vmargin-1 loading" id="list"></div>
	</main>
	<footer class="row no-vmargin">
		<div class="col m6 s12 left-align">
			<span>Made with &nbsp;<i class="material-icons red-text tiny valign-middle">favorite</i>&nbsp; By &nbsp;<a target="_blank" href="https://github.com/webcdn" class="blue-grey-text text-lighten-4">WebCDN</a></span>
			<span> &nbsp; &bull; &nbsp;</span>
			<?php if( $_SESSION['gitJSON']['version'] != VERSION ) : ?>
				<a class="white blue-grey-text text-darken-2 upgrade">Upgrade<span class="hide-on-small-only"> to Version <?= $_SESSION['gitJSON']['version']; ?></span></a>
				<?php else : ?>
					<a target="_blank" href="https://github.com/webcdn/File-Explorer" class="blue-grey-text text-lighten-4">Version <?= VERSION; ?></a>
				<?php endif; ?>
			</div>
			<div class="col m6 s12 right-align">
				<a target="_blank" href="https://github.com/webcdn/File-Explorer/issues" class="blue-grey-text text-lighten-4">Report Bugs</a>
				<span> &nbsp; &bull; &nbsp;</span>
				<a target="_blank" href="https://github.com/webcdn/File-Explorer/issues/1" class="blue-grey-text text-lighten-4">Suggestions / Feedback</a>
				<span> &nbsp; &bull; &nbsp;</span>
				<a target="_blank" href="https://gg.gg/contribute" class="blue-grey-text text-lighten-4">Donate</a>
			</div>
		</footer>







		<!-- MODALs -->
		<div id="uploadModal" class="modal bottom-sheet full">
			<div class="modal-content">
				<h5 class="left">Upload Files</h5>
				<a class="right waves-effect waves-light btn-flat modal-action modal-close hoverable"><i class="material-icons">close</i></a>
				<div class="clearfix"></div>
				<div class="center vmargin-1" id="drop_area">
					<div class="drop_body">
						<h4>Drop Files Here</h4>
						<h5>or</h5>
						<label class="waves-effect waves-light btn vmargin-1">Choose Files<input class="hide" type="file" multiple></label>
						<h6>Maximum Upload File Size: &nbsp; <?php echo formatFileSize($MAX_UPLOAD_SIZE); ?>.</h6>
					</div>
				</div>
			</div>
		</div>

		<div id="progressModal" class="modal bottom-sheet full">
			<div class="modal-content">
				<h4 class="title">Uploading</h4>
				<div class="body"></div>
			</div>
			<div class="modal-footer">
				<a class="waves-effect waves-dark btn red abort">Abort</a>
			</div>
		</div>

		<div id="detailModal" class="modal bottom-sheet full">
			<div class="modal-content">
				<h5 class="left">Details and Info</h5>
				<a class="right waves-effect waves-light btn-flat modal-action modal-close hoverable"><i class="material-icons">close</i></a>
				<div class="clearfix"></div>
				<table>
					<col width="30%" />
					<col width="70%" />
					<tbody>
						<tr><th>Name</th>			<td class="name"></td></tr>
						<tr><th>Path</th>			<td><code class="path"></code></td></tr>
						<tr><th>Size</th>			<td class="size"></td></tr>
						<tr><th>Type</th>			<td class="type"></td></tr>
						<tr><th>Premission</th>		<td class="perm"></td></tr>
						<tr><th>Created Time</th>	<td class="ctime"></td></tr>
						<tr><th>Accessed Time</th>	<td class="atime"></td></tr>
						<tr><th>Modified Time</th>	<td class="mtime"></td></tr>
					</tbody>
				</table>
			</div>
			<div class="modal-footer">
				<button type="button" class="waves-effect waves-dark btn right black perm_it">Reset Premissions</button>
				<button type="reset" class="waves-effect waves-light btn-flat right modal-action modal-close">Close</button>
			</div>
		</div>

		<div id="newDirModal" class="modal bottomsheet">
			<div class="modal-content">
				<h5>Create New Folder</h5>
				<form id="new_dir">
					<div class="input-field">
						<i class="material-icons prefix">create_new_folder</i>
						<input id="dirname" class="dirname" type="text" placeholder="Enter Directory Name" />
						<label for="dirname">Enter Folder Name</label>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="submit" class="waves-effect waves-light btn right modal-action" form="new_dir">Create</button>
				<button type="reset" class="waves-effect waves-light btn-flat right modal-action modal-close">Close</button>
			</div>
		</div>

		<div id="newFileModal" class="modal bottomsheet">
			<div class="modal-content">
				<h5>Create New File</h5>
				<form id="new_file">
					<div class="input-field">
						<i class="material-icons prefix">note_add</i>
						<input class="filename" type="text" placeholder="Enter File Name" />
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="submit" class="waves-effect waves-light btn right modal-action" form="new_file">Create</button>
				<button type="reset" class="waves-effect waves-light btn-flat right modal-action modal-close">Close</button>
			</div>
		</div>

		<div id="renameModal" class="modal bottomsheet">
			<div class="modal-content">
				<h5>Rename</h5>
				<form id="rename_it">
					<div class="input-field">
						<i class="material-icons prefix">text_format</i>
						<input type="hidden" class="path" />
						<input type="text" class="newname" placeholder="Enter New Name" />
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="submit" class="waves-effect waves-light btn right modal-action" form="rename_it">Rename</button>
				<button type="reset" class="waves-effect waves-light btn-flat right modal-action modal-close">Close</button>
			</div>
		</div>

		<div id="editModal" class="modal bottom-sheet full">
			<div class="modal-content">
				<h6>File Name</h6>
				<form id="save_it">
					<input type="hidden" class="path" />
					<textarea class="form-control file_content" rows="30"></textarea>
				</form>
			</div>
			<div class="modal-footer">
				<button type="submit" class="waves-effect waves-light btn right modal-action" form="save_it">Save</button>
				<button type="reset" class="waves-effect waves-light btn-flat right modal-action modal-close">Close</button>
			</div>
		</div>

		<div id="configModal" class="modal modal-footer">
			<div class="modal-content">
				<h4 class="left">Settings</h4>
				<a class="right waves-effect waves-light btn-flat modal-action modal-close hoverable"><i class="material-icons">close</i></a>
				<div class="clearfix"></div>
				<form class="row no-vmargin" id="config_it">
					<input type="hidden" name="do" value="config">
					<input type="hidden" name="xsrf" value="<?= $_COOKIE['__xsrf']; ?>">
					<div class="input-field">
						<h6 class="left">View</h6>
						<div class="switch right">
							<label class="valign-wrapper">
								<span class="valign-wrapper"><i class="material-icons">view_module</i> Grid</span>
								<input type="checkbox" name="list_view" class="list_view" value="<?= !empty($config->list_view) ? '1' : '0'; ?>" <?= !empty($config->list_view) ? 'checked' : ''; ?>><span class="lever"></span>
								<span class="valign-wrapper"><i class="material-icons">view_list</i> List</span>
							</label>
						</div>
					</div>
					<div class="collapse_body" style="display: none;">
						<div class="input-field">
							<h6>Password</h6>
							<i class="material-icons prefix">lock</i>
							<input type="password" name="pass" class="pwd" placeholder="Enter Password" value="<?= $_SESSION['ok_pass']; ?>"/>
							<a class="waves-effect waves-dark btn-flat pwdeye" title="Show"><i class="material-icons">visibility</i></a>
						</div>
						<div class="input-field">
							<h6>ASSETS DIR</h6>
							<i class="material-icons prefix">link</i>
							<input type="text" name="assets" value="<?= $config->assets; ?>">
						</div>
					</div>
					<a class="collapse_btn blue-text text-darken-2">Show Advanced Settings</a>
				</form>
			</div>
			<div class="modal-footer">
				<button type="submit" class="waves-effect waves-light btn right modal-action" form="config_it">
					<i class="material-icons hide-on-med-and-up">save</i>
					<span class="hide-on-small-only">Save</span>
				</button>
				<button type="logout" class="waves-effect waves-dark btn right modal-action red logout">
					<i class="material-icons hide-on-med-and-up">power_settings_new</i>
					<span class="hide-on-small-only">Logout</span>
				</button>
			</div>
		</div>
		<!-- MODALs END -->
	<!-- 
		https://twemoji.maxcdn.com/svg/1f4c2.svg
		 DARK - #059 / #035 / #245
		COLOR - #269
		Light - #5AE
	-->
	<script src="<?php echo $config->assets; ?>/jquery.min.js"></script>
	<script src="<?php echo $config->assets; ?>/materialize.min.js"></script>
	<script>
		(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
		ga('create', 'UA-58646619-1', 'auto');
		ga('send', 'pageview');
	</script>
	<script>
		(function($){
			$.fn.clickSort = function() {
				var $table = this;
				this.find('.tH').click(function() {
					$table.sortBy( $(this).index(), $(this).hasClass('sort_asc') );
				});
				return this;
			};
			$.fn.autoSort = function() {
				var $e = this.find('.tHead .tH.sort_asc, .tHead .tH.sort_desc');
				if($e.length)
					this.sortBy( $e.index(), $e.hasClass('sort_desc') );
				return this;
			}
			$.fn.sortBy = function(idx, direction) {
				var $rows = this.find('.tBody');
				function data_sort(a) {
					var a_val = $(a).find('.tD:nth-child('+(idx+1)+')').attr('data-sort');
					return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
				}
				$rows.sort(function(a, b){
					var a_val = data_sort(a), b_val = data_sort(b);
					return (a_val < b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
				})
				this.find('.tH').removeClass('sort_asc sort_desc');
				$(this).find('.tHead .tH:nth-child('+(idx+1)+')').addClass(direction ? 'sort_desc' : 'sort_asc');
				for(var i = 0; i<$rows.length; i++)
					this.append($rows[i]);
				return this;
			}
		})(jQuery);

		$(function(){
			var XSRF = (document.cookie.match('(^|; )__xsrf=([^;]*)')||0)[2];
			var VERSION = '<?php echo VERSION; ?>';
			var MAX_UPLOAD_SIZE = <?php echo $MAX_UPLOAD_SIZE; ?>;
			var $list = $('#list');

			$(document).on('contextmenu', function(e) {
				e.preventDefault();
			});


			$('.modal').modal({
				dismissible: false,
				ready: function(modal, trigger) {
					modal.find('input[type="text"]').first().focus();
					$('body').addClass('no_scroll');
				},
				complete: function() {
					$('body').removeClass('no_scroll');
				}
			});

			$('.collapse_btn').click(function(){
				$(this).toggleClass('active');
				if( $(this).hasClass('active') )
					$(this).text('Hide Advanced Settings');
				else
					$(this).text('Show Advanced Settings');

				$('.collapse_body').slideToggle();
			});

			$('input').attr('autocomplete', 'off').attr('spellcheck', false);
			$(window).on('hashchange', list).trigger('hashchange');

			
			/* DRAG DROP and CHOOSE FILES
			*******************************************/
			$(window).on('dragover', function(e){
				e.preventDefault();
				e.stopPropagation();
				$('#uploadModal').modal('open');
			});

			$(window).on('drop', function(e){
				e.preventDefault();
				e.stopPropagation();
				$('#uploadModal').modal('close');
			});

			$('#drop_area').on('dragover', function(e){
				e.preventDefault();
				e.stopPropagation();
				$(this).parent().addClass('hover');
			});

			$('#drop_area').on('dragleave', function(e){
				e.preventDefault();
				e.stopPropagation();
				$(this).parent().removeClass('hover');
			});

			$('#drop_area').on('drop', function(e){
				e.preventDefault();
				e.stopPropagation();
				$(this).parent().removeClass('hover');
				var files = e.originalEvent.dataTransfer.files;

				if (files.length) {
					$('#uploadModal').modal('close');
					$('#progressModal').modal('open');
				}
				$.each(files, function(index, file) {
					uploadFile(file, index);
				});
			});

			$('input[type=file]').change(function(e) {
				e.preventDefault();
				if (this.files.length) {
					$('#uploadModal').modal('close');
					$('#progressModal').modal('open');
				}
				$.each(this.files, function(index, file) {
					uploadFile(file, index);
				});
			});

			/* UPLOADING FILES
			*******************************************/
			function uploadFile(file, index) {
				$modal = $('#progressModal');
				var folder = window.location.hash.substr(1);
				if(file.size > MAX_UPLOAD_SIZE) {
					$modal.find('.body').append( renderFileError(file, index) );
					return false;
				}

				$modal.find('.body').append( renderFileUpload(file, index) );
				var len = $modal.find('.uploading').length;
				document.title = 'Uploading ' + len + ' File(s)';
				$modal.find('.title').text('Uploading' + pad(len));

				var fd = new FormData();
				fd.append('do', 'upload');
				fd.append('file_data', file);
				fd.append('path', folder);
				fd.append('xsrf', XSRF);
				var XHR = new XMLHttpRequest();
				XHR.open('POST', '');

				XHR.upload.onprogress = function(e){
					if(e.lengthComputable) {
						var progress = e.loaded / e.total * 100 | 0;
						$modal.find('div.upload_'+index).find('.pcent').text( progress + '%');
						$modal.find('div.upload_'+index).find('.bar').css('width', progress + '%');
						if( progress == 100 ){
							$modal.find('div.upload_'+index).find('.bar').removeClass('determinate').addClass('indeterminate');
						}
					}
				};

				XHR.onload = function() {
					$modal.find('div.upload_'+index).removeClass('uploading').find('.pcent').html('<i class="material-icons tiny">check</i>');
					$modal.find('div.upload_'+index).find('.bar').removeClass('indeterminate').addClass('determinate green');

					var len = $modal.find('.uploading').length;
					document.title = 'Uploading ' + len + ' File(s)';
					$modal.find('.title').text('Uploading' + pad(len));
					if ( len < 1 ) {
						window.setTimeout(function(){
							list();
							document.title = 'File Explorer v' + VERSION;
							toastDestroy();
							Materialize.toast('Files Uploaded Successfully', 5000, 'green darken-3');
							$modal.modal('close');
							$modal.find('.body').empty();
						}, 1000);
					}
				};
				
				XHR.send(fd);

				$(document).on('click', '.abort', function(e){     
					XHR.abort();
					$modal.find('div.upload_'+index).removeClass('uploading').find('.pcent').addClass('red-text').text('Aborted');
					$modal.find('div.upload_'+index).find('.bar').removeClass('indeterminate').addClass('determinate red');

					window.setTimeout(function(){
						list();
						document.title = 'File Explorer v' + VERSION;
						toastDestroy();
						Materialize.toast('Files Aborted', 5000, 'red darken-3');
						$modal.modal('close');
						$modal.find('.body').empty();
					}, 2000);
				});
			}
			function pad(l){
				var s = [];
				for(var i=0; i<l; i++)
					s.push('.');
				return s.join('');
			}
			function renderFileUpload(file, index) {
				var filename = file.name + ' (' + formatFileSize(file.size) + ')';
				return $row = $('<div class="upload_'+ index +' uploading" />')
				.append( $('<h6/>').addClass('col s10 left truncate').text(filename) )
				.append( $('<h6/>').addClass('col s2 right pcent').text('0%') )
				.append( $('<div class="progress grey lighten-2"><div class="bar determinate"></div></div>') )
			}
			function renderFileError(file, index) {
				var filename = file.name + ' (' + formatFileSize(file.size) + ')';
				return $row = $('<div class="error" />')
				.append( $('<h6/>').addClass('left').text(filename) )
				.append( $('<h6/>').addClass('right red-text').text('Exceeds max upload size of ' + formatFileSize(MAX_UPLOAD_SIZE)) )
				.append( $('<div class="progress grey lighten-2"><div class="bar determinate red" style="width:100%;"></div></div>') )
			}


			/* CREATE NEW DIRECTORY
			*******************************************/
			$('#new_dir').submit(function(e) {
				$form = $(this);
				e.preventDefault();
				var hashval = window.location.hash.substr(1);
				var dirname = $form.find('.dirname').val();
				Materialize.toast('Creating...', 'stay', 'wait');

				dirname.length && $.post('', {do: 'mkdir', dirname: dirname, path: hashval, xsrf: XSRF}, function(data){
					list();
					$form.closest('.modal').modal('close');
					toastDestroy();
					Materialize.toast(data.response, 5000, data.flag == true ? 'green darken-3' : 'red darken-2');
					$form.find('.dirname').val('');
				}, 'json');
				return false;
			});


			/* CREATE NEW FILE
			*******************************************/
			$('#new_file').submit(function(e) {
				$form = $(this);
				e.preventDefault();
				var hashval = window.location.hash.substr(1);
				var filename = $form.find('.filename').val();
				Materialize.toast('Creating...', 'stay', 'wait');

				filename.length && $.post('', {do: 'nwfile', filename: filename, path: hashval, xsrf: XSRF}, function(data){
					list();
					$form.closest('.modal').modal('close');
					toastDestroy();
					Materialize.toast(data.response, 5000, data.flag == true ? 'green darken-3' : 'red darken-2');
					$form.find('.filename').val('');
				}, 'json');
				return false;
			});


			/* RENAME FILE and FOLDER
			*******************************************/ 
			$(document).on('click', '.rename', function(e) {
				$modal = $('#renameModal');
				var path = $(this).attr('data-path');
				var name = $(this).attr('data-name');

				$modal.modal('open');
				$modal.find('.path').val(path);
				$modal.find('.newname').val(name).attr('placeholder', name);
			});

			$('#rename_it').submit(function(e) {
				$form = $(this);
				e.preventDefault();
				var path = $form.find('.path').val();
				var newname = $form.find('.newname').val();
				Materialize.toast('Renaming...', 'stay', 'wait');

				path.length && newname.length && $.post('', {do: 'rename', newname: newname, path: path, xsrf: XSRF}, function(data){
					list();
					$form.closest('.modal').modal('close');
					toastDestroy();
					Materialize.toast(data.response, 5000, data.flag == true ? 'green darken-3' : 'red darken-2');
				}, 'json');
				return false;
			});


			/* OPEN EDITOR and SAVE FILE
			*******************************************/
			$(document).on('click', '.edit', function(event) {
				$editModal = $('#editModal');
				var name = $(this).attr('data-name');
				var path = $(this).attr('data-path');
				Materialize.toast('Opening...', 'stay', 'wait');

				$editModal.modal('open');
				$editModal.find('h6').text(name);
				$editModal.find('.path').val(path);
				$editModal.find('.file_content').empty();

				$.post('', {do: 'raw', path: path, xsrf: XSRF}, function(data){
					$editModal.find('.file_content').text(data);
					toastDestroy();
				});
			});

			$('#save_it').submit(function() {
				$editModal = $('#editModal');
				var path = $editModal.find('.path').val();
				var content = $editModal.find('.file_content').val();
				Materialize.toast('Saving...', 'stay', 'wait');

				$.post('', {do: 'edit', content: content, path: path, xsrf: XSRF}, function(data){
					list();
					$editModal.modal('close');
					toastDestroy();
					Materialize.toast(data.response, 5000, data.flag == true ? 'green darken-3' : 'red darken-2');
				}, 'json');
				return false;
			});


			/* DELETE FILE
			*******************************************/
			$(document).on('click', '.delete', function() {
				var path = $(this).attr('data-path');
				if (confirm('Do you want to Delete it ?') == true) {
					Materialize.toast('Deleting...', null, 'wait');

					$.post('', {do: 'delete', path: path, xsrf: XSRF}, function(data){
						list();
						toastDestroy();
						Materialize.toast(data.response, 5000, data.flag == true ? 'green darken-3' : 'red darken-2');
					}, 'json');
				}
				else {
					toastDestroy();
					Materialize.toast('Oh! Thanks God all is safe.', 5000, 'grey darken-1');
				}
			});


			/* COMPRESS DIRECTORY
			*******************************************/
			$(document).on('click', '.compress', function() {
				var path = $(this).attr('data-path');
				Materialize.toast('Compressing...', 'stay', 'wait');

				$.post('', {do: 'compress', path: path, xsrf: XSRF}, function(data){
					toastDestroy();
					if (data.flag == true) {
						list();
						Materialize.toast(data.response, 5000, 'green darken-3');
					}
					else {
						Materialize.toast(data.response, 5000, 'red darken-2');
					}
				}, 'json');
			});


			/* EXTRACT ZIP FILE
			*******************************************/
			$(document).on('click', '.extract', function() {
				var path = $(this).attr('data-path');
				Materialize.toast('Extracting...', 'stay', 'wait');

				$.post('', {do: 'extract', path: path, xsrf: XSRF}, function(data){
					toastDestroy();
					if (data.flag == true) {
						list();
						Materialize.toast(data.response, 5000, 'green darken-3');
					}
					else {
						Materialize.toast(data.response, 5000, 'red darken-2');
					}
				}, 'json');
			});


			/* VIEW DETAILS
			*******************************************/
			$(document).on('click', '.info', function() {
				$elem = $(this);
				$modal = $('#detailModal');
				$modal.modal('open');
				$modal.find('.name').text( $elem.attr('data-name') );
				$modal.find('.path').text( $elem.closest('.card').find('a').prop('href') );
				$modal.find('.type').text( $elem.attr('data-type') );
				$modal.find('.size').text( $elem.attr('data-size') );
				$modal.find('.perm').html( $elem.attr('data-perms') );
				$modal.find('.atime').text( $elem.attr('data-atime') );
				$modal.find('.ctime').text( $elem.attr('data-ctime') );
				$modal.find('.mtime').text( $elem.attr('data-mtime') );
				$modal.find('.perm_it').attr('data-path', $elem.attr('data-path') );
			});


			/* RESET PERMISSION
			*******************************************/ 
			$(document).on('click', '.perm_it', function(e) {
				var path = $(this).attr('data-path');
				Materialize.toast('Changing...', 'stay', 'wait');

				path.length && $.post('', {do: 'permit', path: path, xsrf: XSRF}, function(data){
					list();
					toastDestroy();
					Materialize.toast(data.response, 5000, data.flag == true ? 'green darken-3' : 'red darken-2');
					$('#detailModal').modal('close');
				}, 'json');
				return false;
			});


			/* LIST VIEW, PASSWORD and SETTINGS PANEL
			*******************************************/
			$('.pwdeye').click( function() {
				$pwd = $(this).siblings('.pwd');
				if($pwd.attr('type') == 'password') {
					$pwd.attr('type', 'text');
					$(this).attr('title', 'Hide').find('i').text('visibility_off');
				}
				else {
					$pwd.attr('type', 'password');
					$(this).attr('title', 'Show').find('i').text('visibility');
				}
			});

			$('.list_view').on('change', function(){
				$list.addClass('loading');

				if( $(this).is(':checked') ){
					$(this).val(1);
					setTimeout(function(){
						$('main').removeClass('tile').addClass('list');
						list();
					}, 1000);
				}
				else {
					$(this).val(0);
					setTimeout(function(){
						$('main').removeClass('list').addClass('tile');
						list();
					}, 1000);
				}
			});

			$('#config_it').submit(function(e) {
				$form = $(this);
				e.preventDefault();
				Materialize.toast('Updating Settings...', 'stay', 'wait');

				$.post('', $form.serialize(), function(data){
					$form.closest('.modal').modal('close');
					toastDestroy();
					Materialize.toast(data.response, 'stay', data.flag == true ? 'green darken-3' : 'red darken-2');

					window.setTimeout(function(){
						window.location.reload();
					}, 1000);
				}, 'json');
				return false;
			});


			/* LOGOUT SESSION AND COOKIE
			*******************************************/
			$(document).on('click', '.logout', function() {
				Materialize.toast('Please Wait...', 'stay', 'wait');

				$.post('', {do: 'logout', xsrf: XSRF}, function(data){
					$('#configModal').modal('close');
					toastDestroy();
					Materialize.toast(data.response, 'stay', data.flag == true ? 'green darken-3' : 'red darken-2');

					window.setTimeout(function(){
						window.location.reload();
					}, 1000);
				}, 'json');
			});



			/* UPDATE CODE VERSION
			*******************************************/
			$(document).on('click', '.upgrade', function() {
				$('#configModal').modal('close');
				Materialize.toast('Upgrading...', 'stay', 'wait');

				$.post('', {do: 'upgrade', xsrf: XSRF}, function(data){
					toastDestroy();
					if (data.flag == true) {
						Materialize.toast(data.response, 'stay', 'green darken-3');

						window.setTimeout(function(){
							window.location.reload();
						}, 1000);
					}
					else {
						Materialize.toast(data.response, 5000);
					}
				}, 'json');
			});



			/* LISTING and MENUS
			*******************************************/
			$(document).on('contextmenu', '#list .card', function(e) {
				e.preventDefault();
				$(this).find('.more').trigger('click');
			});

			$(document).on('click', '#list .more', function(e) {
				e.preventDefault();
				$('#list').find('.collection').fadeOut(100);
				$content_menu = $(this).closest('.card').find('.collection');
				if( $content_menu.css('display') == 'none' ) {
					$content_menu.slideDown({ duration: 300, easing: 'easeOutSine' });
				}
			});

			$(document).on('click', function(e) {
				var container = $('#list').find('.card');
				if ( !container.is(e.target) && container.has(e.target).length === 0 ){
					container.find('.collection').fadeOut(100);
				}
			});


			function list() {
				var hashval = window.location.hash.substr(1);
				$list.addClass('loading');
				$.get('', {do: 'list', path: hashval}, function(data) {
					$list.empty();
					$('#breadcrumb').empty().html( renderBreadcrumbs(hashval) );
					if(data.flag == true) {
						if( Array.isArray(data.response) ) {
							if( $('main').hasClass('list') ){
								$list.html('<div class="col s12 no-select tHead"><div class="tH col l6 m5 s8 sort_asc"> &nbsp; &nbsp; <b>Name</b></div><div class="tH col l1 m2 s4">Size</div><div class="tH col l2 m2 hide-on-small-only truncate">Modified</div><div class="tH col l3 m3 hide-on-small-only">Permission</div></div>');
							}
							$.each(data.response, function(index, value){
								$list.append(renderList(value));
							});
						}
						else {
							$list.html('<h4 class="center grey-text vmargin-3">No Files</h5>');
						}
					}
					else {
						Materialize.toast(data.response, 'stay', 'red darken');
						console.warn(data.response);
					}

					$list.clickSort().autoSort().removeClass('loading');
				}, 'json');
			}

			function renderList(data) {
				if( data.ext == '---' ){
					var icon = '<i class="material-icons left amber-text">folder</i>';
				}
				else if( ['aac','mp3','m4a','wav','wma'].indexOf(data.ext) > -1 ){
					var icon = '<i class="material-icons left blue-text">audiotrack</i>';
				}
				else if( ['ai','gif','jpg','jpeg','png','psd','svg','tiff'].indexOf(data.ext) > -1 ){
					var icon = '<i class="material-icons left green-text">image</i>';
				}
				else if( ['3gp','avi','flv','m4u','mkv','mov','mp4','mpg','mpeg','vob','wmv'].indexOf(data.ext) > -1 ){
					var icon = '<i class="material-icons left red-text">videocam</i>';
				}
				else if( ['sh','c','cpp','class','java','jsp','asp','aspx','sql','php','phps','phpx','htm','html','whtml','xhtml','mht','js','json','css','xml'].indexOf(data.ext) > -1 ){
					var icon = '<i class="material-icons left pink-text">code</i>';
				}
				else if( ['7z','gz','zip'].indexOf(data.ext) > -1 ){
					var icon = '<i class="material-icons left brown-text">work</i>';
				}
				else if( ['csv','doc','docx','xls','xlsx','pdf','ppt','pptx','odt','txt','log'].indexOf(data.ext) > -1 ){
					var icon = '<i class="material-icons left blue-grey-text text-darken-2">description</i>';
				}
				else {
					var icon = '<i class="material-icons left grey-text">insert_drive_file</i>';
				}

				var info = 'data-atime="'+data.atime_ok+'" data-ctime="'+data.ctime_ok+'" data-mtime="'+data.mtime_ok+'" data-size="'+data.size_ok+'"data-perms="'+data.perms_ok+' ('+ data.perms +')" data-type="'+data.type+'" data-name="'+data.name+'" data-path="'+data.path+'"';
				var menu = {
					open	: '<a class="grey-text collection-item" href="#' + data.path + '" title="Open"><i class="material-icons left">folder_open</i> Open</a>',
					view	: '<a class="grey-text collection-item" href="' + data.path + '" target="_blank" title="View"><i class="material-icons left">search</i> View</a>',
					dwnld	: '<a class="grey-text collection-item" href="?do=download&path=' + encodeURIComponent(data.path) + '" title="Download"><i class="material-icons left">file_download</i> Download</a>',
					edit	: '<a class="grey-text collection-item edit" data-path="' + data.path + '" data-name="' + data.name + '" title="Edit"><i class="material-icons left">edit</i> Edit</a>',
					rename	: '<a class="grey-text collection-item rename" data-path="' + data.path + '" data-name="' + data.name + '" title="Rename"><i class="material-icons left">text_format</i> Rename</a>',
					delete	: '<a class="grey-text collection-item delete" data-path="' + data.path + '" data-name="' + data.name + '" title="Delete"><i class="material-icons left">delete</i> Delete</a>',
					compress: '<a class="grey-text collection-item compress" data-path="' + data.path + '" data-name="' + data.name + '" title="Compress"><i class="material-icons left">archive</i> Compress</a>',
					extract	: '<a class="grey-text collection-item extract" data-path="' + data.path + '" data-name="' + data.name + '" title="Extract"><i class="material-icons left">unarchive</i> Extract</a>',
					info	: '<a class="grey-text collection-item info" ' + info + ' title="Info"><i class="material-icons left">info</i> View Details</a>',
				}
				$menu = $('<div/>').addClass('collection z-depth-2').css('display', 'none').html( (data.is_dir ? menu.open : menu.view) + (data.is_dir ? '' : menu.dwnld) + (data.is_editable ? menu.edit : '') + (data.is_writable ? menu.rename : '') + (data.is_deleteable ? menu.delete : '') + (data.is_zipable ? menu.compress : '') + (data.is_zip ? menu.extract : '') + menu.info );


				if( $('main').hasClass('tile') ){
					$link = $('<a/>')
					.addClass('card-content truncate no-select')
					.attr('href', data.is_dir ? '#' + data.path : data.path)
					.attr('target', data.is_dir ? '_self' : '_blank' )
					.attr('title', data.name)
					.attr('data-size', data.size_ok)
					.append(icon + data.name);

					var item = $('<div />').addClass('card').addClass(data.is_dir ? 'is_dir' : 'is_file').append($link).append('<a class="waves-effect waves-dark more"><i class="material-icons grey-text text-darken-2">more_vert</i></a>').append($menu);
					return $('<div class="col l3 m6 s12" />').html( item );
				}
				else {
					$link = $('<a/>')
					.addClass('card-content row no-vmargin truncate no-select')
					.attr('href', data.is_dir ? '#' + data.path : data.path)
					.attr('target', data.is_dir ? '_self' : '_blank' )
					.attr('title', data.name)
					.append('<div class="tD col l6 m5 s8 truncate" data-sort="' + data.sort + '">' + icon + data.name + '</div>')
					.append('<div class="tD col l1 m2 s4" data-sort="' + data.size + '">' + data.size_ok + '</div>')
					.append('<div class="tD col l2 m2 hide-on-small-only truncate" title="'+ data.mtime_ok +'" data-sort="' + data.mtime + '">' + data.mtime_easy + '</div>')
					.append('<div class="tD col l3 m3 hide-on-small-only truncate" title="'+ data.perms +'" data-sort="' + data.perms + '">' + data.perms_ok + '</div>')

					var item = $('<div />').addClass('card').addClass(data.is_dir ? 'is_dir' : 'is_file').append($link).append('<a class="waves-effect waves-dark more"><i class="material-icons grey-text text-darken-2">more_vert</i></a>').append($menu);
					return $('<div class="col s12 tBody" />').html( item );
				}
			}

			function renderBreadcrumbs(path) {
				var base = '',
				$html = $('<div class="col s12" style="display: inline-flex;" />').append('<a href="#" class="breadcrumb no-select"><i class="material-icons">home</i></a>');
				$.each(path.split('/'), function(index, value){
					if(value) {
						$html.append('<a href="#' + base + value + '" class="breadcrumb no-select truncate">' + value + '</a>');
						base += value + '/';
					}
				});
				return $html;
			}

			function formatFileSize(bytes, round = 2) {
				var units, pow, size;
				if(bytes >= 0) {
					units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
					pow = Math.floor( Math.max(0, Math.log(bytes) ) / Math.log(1024) );
					pow = Math.min(pow, (units.length - 1));
					size = bytes / Math.pow(1024, pow);
					return Number(size.toFixed(round)) + ' ' + units[pow];
				}
				else {
					return 'Too Large';
				}
			}

			function toastDestroy() {
				$('.toast').each(function(){
					$(this).velocity(
						{ opacity: 0, marginTop: '-40px' },
						{ duration: 500, easing: 'easeOutExpo', complete: function(){ $(this).remove();	},
					});
				});
			}
		});
	</script>
</body>
</html>
