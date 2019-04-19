<?php
/**
 ***********************************************
 * File Explorer
 * @author WebCDN (https://github.com/webcdn)
 * 
 * @link https://github.com/webcdn/File-Explorer
 * @license MIT
 ***********************************************
**/
session_start();
setlocale(LC_ALL, 'en_US.UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
define('VERSION', '2.0.3-beta');
define('_CONFIG', __DIR__.'/.htconfig');
$phpVer = phpversion();

if( file_exists(_CONFIG) ){
	@chmod(_CONFIG, 0644);
	$config = json_decode( getData(_CONFIG) );
	$max_upload_size = min( inBytes( ini_get('post_max_size') ), inBytes( ini_get('upload_max_filesize') ) );

	$config->go_up       = (bool) $config->go_up;
	$config->show_hidden = (bool) $config->show_hidden;
}
else if( is_writable(__DIR__) && version_compare($phpVer, '5.5.38') > -1 && getData(true) && @$_POST['do'] == 'install' ){
	sleep(1);
	$pass = md5( sha1($_POST['pwd']) );
	setData(_CONFIG, json_encode(array('go_up' => false, 'show_hidden' => false, 'password' => $pass), JSON_PRETTY_PRINT));
	header('Refresh: 0;');
	exit;
}
else {
	$phpInfo = array(
		'PHP Version' => version_compare($phpVer, '5.5.38') > -1 ? '<small>Superb!</small><b class="green">'.$phpVer.'</b>' : '<small>Upgrade to 5.5.38 or greater</small><b class="red">'.$phpVer.'</b>',
		'PHP cURL' => function_exists('curl_init') ? '<small>Awesome!</small><b class="green">'.curl_version()['version'].'</b>' : '<small>Recommended</small><svg width="26" height="26" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" class="red"/></svg>',
	);
	if( !function_exists('curl_init') ){
		$phpInfo['file_get_contents'] = function_exists('file_get_contents') && ini_get('allow_url_fopen') ? '<small>Glad to hear that!</small><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" class="green"/></svg>' : '<small>enable cURL or allow access to external URL(s)</small><svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" class="red"/></svg>';
	}
	$phpInfo['Write Permissions'] = is_writable(__DIR__) ? '<small>Sounds Great!</small><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" class="green"/></svg>' : '<small>set write permissions for current directory</small><svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" class="red"/></svg>';
	$phpInfo['Zip Archive'] = class_exists('ZipArchive') ? '<small>You&#39;re ready to rock!</small><br/><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" class="green"/></svg>' : '<small>compression & extraction will not work</small><br/><svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" class="red"/></svg>';
	html_setup($phpInfo);
	exit;
}

if( !isset($_SESSION['__allowed']) && strlen($config->password) ) {
	if( !empty($_POST['auth']) ){
		sleep(1);
		if( md5(sha1($_POST['auth'])) === $config->password ) {
			$label = '<label for="auth" style="color: #383;">Successfully Logged In</label>';
			$_SESSION['__allowed'] = $_POST['auth'];
			header('Refresh: 0;');
			exit;
		}
		$label = '<label for="auth" style="color: #D22;">Incorrect Password</label>';
	}
	else if( !empty($_REQUEST['do']) ){
		output(false, 'Session Destroyed, you need to Login Again!');
	}
	$label = isset($label) ? $label : '<label for="auth">File Explorer v'.VERSION.'</label>';
	html_login($label);
	exit;
}

$path = empty($_REQUEST['path']) ? '.' : $_REQUEST['path'];
$real = @realpath($path);
$is_up = strlen($real) < strlen(__DIR__);
$deny_paths = up_paths( array(__FILE__, _CONFIG) );
$reqs_paths = isset( $_REQUEST['ways'] ) ? array_map(function($p){return realpath($p);}, $_REQUEST['ways']) : array(realpath($path));
$editable_files = array('asp','aspx','c','cer','cfm','class','cpp','cs','csr','css','csv','dtd','fla','h','htm','html','java','js','jsp','json','log','lua','m','md','mht','pl','php','phps','phpx','py','sh','sln','sql','svg','swift','txt','vb','vcxproj','whtml','xcodeproj','xhtml','xml');


if( empty($_SESSION['gitJSON']) ){
	$_SESSION['gitJSON'] = @json_decode(getData('https://raw.githubusercontent.com/webcdn/File-Explorer/standalone/repo.json'), true);
}

if( empty($_COOKIE['__xsrf']) ){
	setcookie('__xsrf', sha1( uniqid() ) );
}
if($real === false) {
	output(false, 'File or Directory Not Found');
}
if($_POST && @$_COOKIE['__xsrf'] != @$_POST['xsrf'] ){
	output(false, 'XSRF Failure');
}
if( $is_up && !$config->go_up ){
	output(false, 'Forbidden Access');
}


$welcome = @$_SESSION['gitJSON']['latest_ver'] != VERSION ? 'New Update Available, Upgrade Now' : 'Logged In Successfully';

if( !empty($_REQUEST['do']) ){
	if( in_array($_REQUEST['do'], array('edit', 'rename', 'permit', 'trash')) && !empty(array_intersect($reqs_paths, $deny_paths)) ){
		output(false, 'Oops! You\'re trying to play with source files.');
	}

	else if( @$_POST['do'] == 'list' ){
		clearstatcache();
		if( !is_executable($path) ) {
			output(false, 'Not Enough Permissions');
		}
		else if( is_dir($path) ) {
			$result = array();
			$fps = scan_dir($path, 'dirFirst');

			empty($fps) && output(false, 'Directory Empty');

			foreach($fps as $i => $fp) {
				if( !$config->show_hidden && substr(basename($fp), 0, 1) == '.' ){
					continue;
				}
				$ext = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
				$stat = @stat($fp);
				$danger = in_array(realpath($fp), $deny_paths);
				$result[] = array(
					'sort' => $i,
					'name' => basename($fp),
					'path' => preg_replace('@^\./@', '', $fp),
					'real_path' => realpath($fp),
					'type' => function_exists('mime_content_type') ? mime_content_type($fp) : $ext,
					'ext' => is_dir($fp) ? '---' : $ext,
					'size' => is_dir($fp) ? 0 : $stat['size'],
					'perm' => '0' . decoct( @fileperms($fp) & 0777 ),
					'ownr' => $stat['uid'],
					'ownr_ok' => function_exists('posix_getpwuid') ? posix_getpwuid($stat['uid'])['name'] : $stat['uid'],
					'atime' => $stat['atime'],
					'ctime' => $stat['ctime'],
					'mtime' => $stat['mtime'],
					'is_dir'=> is_dir($fp),
					'is_deletable' => is_writable($path) && !$danger && is_recursively_rdwr($fp),
					'is_editable' => !is_dir($fp) && is_writable($fp) && !$danger && in_array($ext, $editable_files),
					'is_writable' => is_writable($fp) && !$danger,
					'is_readable' => is_readable($fp),
					'is_executable' => is_executable($fp),
					'is_recursable' => is_recursively_rdwr($fp) && !$danger,
					'is_zipable' => is_dir($fp) && class_exists('ZipArchive') && is_recursively_rdwr($fp),
					'is_zip' => $ext == 'zip' && class_exists('ZipArchive'),
				);
			}
			output(true, $result);
		}
		else {
			output(false, 'Not a Directory');
		}
	}

	elseif( @$_GET['do'] == 'download' && !is_dir($real) ){
		$filename = basename($path);
		header('Content-Type: ' . mime_content_type($path));
		header('Content-Length: '. filesize($path));
		header(sprintf('Content-Disposition: attachment; filename=%s', strpos('MSIE', $_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : $filename ));
		ob_flush();
		readfile($path);
		exit;
	}

	elseif( @$_GET['do'] == 'edit' && !is_dir($real) && file_exists($real) ){
		if( @$_POST['do'] == 'save' && isset($_POST['content']) ){
			setData($path, $_POST['content']) ? output(true, 'File Saved Successfully') : output(false, 'Damn! saving error');
		}
		html_editor($real);
		exit;
	}

	elseif( @$_POST['do'] == 'upload' ){
		chdir($path);
		move_uploaded_file($_FILES['file_data']['tmp_name'], $_FILES['file_data']['name']);
		exit;
	}

	elseif( @$_POST['do'] == 'mkdir' ){
		chdir($path);
		$dir = trim( preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', @$_POST['dirname']), ' .');

		if( in_array($dir, array('.', '..')) ) {
			output(false, 'Invalid Attempt');
		}
		else if( is_dir($dir) ){
			output(false, 'Directory Already Exist');
		}
		else {
			mkdir($dir, 0755) ? output(true, 'Directory Created') : output(false, 'Unable to create directory');
		}
	}

	elseif( @$_POST['do'] == 'nwfile' ){
		chdir($path);
		$fl = trim( preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', @$_POST['filename']), ' .');

		if( in_array($fl, array('.', '..')) ) {
			output(false, 'Invalid Attempt');
		}
		else if( file_exists($fl) ) {
			output(false, 'File Already Exist');
		}
		else {
			touch($fl) ? output(true, 'File Created') : output(false, 'Unable to create file');
		}
	}

	elseif( @$_POST['do'] == 'rename' ){
		$new = trim( preg_replace('/[\<\>\:\"\/\\\|\?\*]/', '', @$_POST['newname']), ' .');

		if( in_array($new, array('.', '..')) ) {
			output(false, 'Invalid Attempt');
		}
		else {
			rename($real, dirname($real).'/'.$new) ? output(true, 'Renamed Successfully') : output(false, 'Wrong Params');
		}
	}

	elseif( @$_POST['do'] == 'copy' ){
		$ways = array_diff($_POST['ways'], $deny_paths, array('.', '..'));

		if( is_array($ways) ){
			$ack = true;
			foreach ($ways as $way) {
				$ack &= cp_rf($way, $path, $way);
			}
			@$ack ? output(true, 'Copied Successfully') : output(false, 'Wrong Params');
		}
		else {
			output(false, 'Copying Failed');
		}
	}

	elseif( @$_POST['do'] == 'move' ){
		$ways = array_diff($_POST['ways'], $deny_paths, array('.', '..'));

		if( is_array($ways) ){
			$ack = true;
			foreach ($ways as $way) {
				$ack &= rename($way, $path . '/' . basename($way));
			}
			$ack ? output(true, 'Moved Successfully') : output(false, 'Wrong Params');
		}
		else {
			output(false, 'Moving Failed');
		}
	}

	elseif( @$_REQUEST['do'] == 'trash' ){
		$ways = array_diff($_POST['ways'], $deny_paths, array('.', '..'));

		if( is_array($ways) ){
			$ack = true;
			foreach ($ways as $way) {
				$ack &= rm_rf($way);
			}
			$ack ? output(true, 'Deleted Successfully') : output(false, 'Unable to delete files');
		}
		else {
			output(false, 'Deletion Failed');
		}
	}

	elseif( @$_POST['do'] == 'compress' ){
		if (is_dir($real)){
			$zip = new ZipArchive();
			if( $zip->open($path.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) === true ){
				foreach(scan_dir($path, 'recursive,skipDirs') as $loc ){
					$zip->addFile($loc, str_replace($path, '', $loc));
				}
				$zip->close();
				output(true, '`'.basename($path).'.zip` created successfully');
			}
			else {
				output(false, 'Oops! Unable to compress');
			}
		}
		else {
			output(false, 'Oops! Directory is corrupted');
		}
	}

	elseif( @$_POST['do'] == 'extract' ){
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		$pathTo = pathinfo($real, PATHINFO_DIRNAME);
		if( strtolower($ext) == 'zip' ){
			$zip = new ZipArchive;
			if( $zip->open($path) === true ){
				$zip->extractTo($pathTo);
				$zip->close();
				output(true, 'Archive Extracted Successfully');
			}
			else {
				output(false, 'Oops! Archive is corrupted');
			}
		}
		else {
			output(false, "Oops!, Error while extracting `.$ext` file");
		}
	}

	elseif( @$_POST['do'] == 'permit' ){
		$perm = octdec($_POST['perm']);
		$rcrs = @$_POST['recurse'];
		
		if( empty($rcrs) ){
			$ack = chmod($real, $perm);
		}
		else if( is_dir($real) ){
			$ack = true;
			foreach( scan_dir($real, 'recursive') as $list ){
				if( (is_dir($list) && stripos($rcrs, 'd') !== false) || (!is_dir($list) && stripos($rcrs, 'f') !== false) ){
					$ack &= chmod($list, $perm);
				}
			}
		}
		$ack ? output(true, 'Permission Modified') : output(false, 'Error to permit');
	}

	elseif( @$_POST['do'] == 'config' ){
		logout();
		$save = $config;
		$save->go_up = $config->go_up ;
		$save->show_hidden = @$_POST['hdfl'] == 'true';
		$save->password = @md5(sha1($_POST['pass']));
		setData(_CONFIG, json_encode($save, JSON_PRETTY_PRINT));
		output(true, 'Settings Updated Successfully');
	}

	elseif( @$_REQUEST['do'] == 'logout' ){
		logout() ? output(true, 'Logged Out Successfully') : output(false, 'Refreshing...');
	}

	elseif( @$_POST['do'] == 'upgrade' ){
		if( @$_SESSION['gitJSON']['latest_ver'] != VERSION) {
			$getUpdatedData = getData(@$_SESSION['gitJSON']['latest_url']);
			logout();
			setData(basename(__FILE__), $getUpdatedData) ? output(true, 'Updated to Newer Version') : output(false, 'Failed to Update');
		}
		else {
			output(false, 'No Updates Available');
		}
	}
}



function getData($uri){
	$is_URL = !!preg_match('/(https?:\/\/)/i', $uri);

	if( function_exists('curl_init') && ($is_URL || is_bool($uri)) ){
		if( is_bool($uri) ){
			return true;
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $uri);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	elseif( function_exists('file_get_contents') && (!$is_URL || ini_get('allow_url_fopen')) ){
		if( is_bool($uri) ){
			return true;
		}
		return file_get_contents($uri);
	}
	return false;
}

function setData($uri, $data){
	if( function_exists('file_put_contents') ){
		return file_put_contents($uri, $data) !== false;
	}
	elseif( $fh = fopen($uri, 'wb') ){
		fwrite($fh, $data);
		fclose($fh);
		return true;
	}
	return false;
}

function scan_dir($path, $opts = null, &$list = array() ){
	$files = scandir($path, stripos($opts, 'desc') !== false ? 1 : 0);
	$files = array_diff( $files, array('.', '..') );

	foreach($files as $file){
		$fullpath = "$path/$file";
		if( is_dir($fullpath) ){
			stripos($opts, 'skipDirs' ) === false && array_push($list, $fullpath);
			stripos($opts, 'recursive') !== false && scan_dir("$path/$file", $opts, $list);
		}
		else {
			stripos($opts, 'skipFiles') === false && array_push($list, $fullpath);
		}
	}

	if( stripos($opts, 'dirFirst') !== false ){
		$dirA = $filA = array();
		foreach ($list as $l) {
			is_dir(realpath($l)) ? array_push($dirA, $l) : array_push($filA, $l);
		}
		return array_merge($dirA, $filA);
	}
	return $list;
}

function cp_rf($src, $dst, $dir, &$output = true) {
	$base = basename($dir);
	$handle = str_replace($dir, "$dst/$base", $src);
	if( is_dir($src) ) {
		$output &= mkdir($handle);
		$files = array_diff( scandir($src), array('.', '..') );
		foreach ($files as $file)
			cp_rf("$src/$file", $dst, $dir, $output);
	}
	else {
		$output &= copy($src, $handle);
	}
	return $output;
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

function is_recursively_rdwr($d) {
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

function up_paths($loc) {
	$output = array();
	$paths = is_array($loc) ? $loc : array($loc);
	foreach ($paths as $path) {
		$path = realpath($path);
		foreach(explode('/', $path) as $i) { 
			if( !in_array($path, $output) ){
				$output[] = realpath($path);
			}
			$path = dirname($path);
		}
	}
	sort($output);
	return array_filter($output);
}

function logout(){
	return session_destroy() && setcookie('__xsrf', '', time() - 3600);
}

function output($flag, $response, $xtra = array()) {
	header('Content-Type: application/json');
	$xtra = is_array($xtra) ? $xtra : array($xtra);
	$data = array('flag' => (bool) $flag, 'response' => $response);
	exit(json_encode($data + $xtra, JSON_PRETTY_PRINT));
}

function inBytes($ini_v) {
	$ini_v = trim($ini_v);
	$units = array('K' => 1<<10, 'M' => 1<<20, 'G' => 1<<30);
	return intval($ini_v) * ($units[strtoupper( substr($ini_v, -1) )] ? : 1);
}
?>
<!--============================================
# File Explorer
# Version: <?= VERSION; ?> 
# 
# https://github.com/webcdn/File-Explorer
# License: GNU
=============================================-->
<!DOCTYPE html>
<html lang="en" version="<?= VERSION; ?>">
<head>
	<title>File Explorer v<?= VERSION; ?></title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
	<link rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEgAAABICAMAAABiM0N1AAAAwFBMVEVMaXEhZZlLoeZUrO0obqRUrO0hZZkob69UrO5UrO4iZpkhZZlTq+1Vf6wiZJohZphUrO5UrO4hZZkhZZlUrO4hZZkiZJpVrO0hZplFldNUq+48icRFltRUrO1UrO5Uq+0hZZlVq+4eZJohZphUrO5Mn94iZplVrO4hZZlJm9tXruwiZphDlNFWrO1UrO1Vq+1VrO5Uq+0veK8mbKFOouM2gbpRp+hTqus6h8IzfbZEldNAkc0jaJwsc6lHmtgiZpkio2ylAAAAMHRSTlMA/A039JHTB96uLZYdAyiRcviI29XoTc2+zepM0VaewKZfIZh89l/z8+wp9O9HgmUP4OQcAAABgElEQVR4Ae3UBXbdQBBE0RYzmNkOg7HF5tn/qsLtz6IKJ3cBb0ZSHdF/fyo/sc/UNG3Lfrkd0UBOoKllNrYHdnbVKsGgSwVqtWdD3o+mWqxTb4lq9Yb6slWrM596eqfabb2gflSXjRgNibN138FC7Xk78dGQ0AIHDYldBw2J5HuFNB8NieR7hezvFTobGSqq27LmGfsnqT40VNw1vIxrDAvlNa+wZgwJ3WS8kusQ2BHPe4fyhtt4fUMPNbfaJLAjXAK/l9jpESryWxb93tH9p9lmPI41FcpLHi+dhKqMAUc0tTeEIaGiZkgooWuGrEUSKhni0rfQPWNOJPTAmGMJ3TDmUkLXjLmS0B1jLiT0yJhTCZWM0SXUMGSfvoUKxuxIKGeMJ6GKMZaE0BmlEkJndCQhdEaGhNAZhd9C9xn8WyP6HjNy6RMT/GgyIzp/KKqGMa/ok0OGZSF9cpAx6pjou1zJ1ekL/T3YCekbfS8D3o+n08TB3uuGR3i7aRn0B/vvI/0jAVz6iypMAAAAAElFTkSuQmCC">
	<style>
	*,
	*::before,
	*::after {
		outline: none;
		margin: 0;
		padding: 0;
		border: 0;
		color: inherit;
		font: inherit;
		font-size: 100%;
		line-height: 1.5;
		vertical-align: baseline;
		-webkit-box-sizing: border-box;
		box-sizing: border-box;
		-webkit-user-select: none;
		-moz-user-select: none;
		 -ms-user-select: none;
		     user-select: none;
		-webkit-transition: 0.2s;
		-o-transition: 0.2s;
		transition: 0.2s;
	}
	html {
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', Helvetica, Arial, sans-serif;
		font-size: 14px;
		min-width: 280px;
	}
	body {
		position: relative;
		height: 90vh;
		overflow: hidden;
		overflow-y: scroll;
		background-color: whitesmoke;
		-webkit-transition: padding 0s;
		-o-transition: padding 0s;
		transition: padding 0s;
	}
	@media(max-width: 576px){
		body {background-color: #EEE};
	}
	body:before {
		content: '';
		position: absolute;
		z-index: 2;
		top: 0;
		left: 0;
		right: 0;
		height: inherit;
		background-size: 10rem;
		background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj4KCTxjaXJjbGUgY3g9IjUwIiBjeT0iNTAiIHI9IjQwIiBmaWxsPSJub25lIiBzdHJva2U9IiM1NTUiIHN0cm9rZS13aWR0aD0iOCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIj4KCQk8YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJzdHJva2UtZGFzaG9mZnNldCIgZHVyPSIycyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIGZyb209IjUwMiIgdG89IjAiIC8+CgkJPGFuaW1hdGUgYXR0cmlidXRlTmFtZT0ic3Ryb2tlLWRhc2hhcnJheSIgZHVyPSIycyIgcmVwZWF0Q291bnQ9ImluZGVmaW5pdGUiIHZhbHVlcz0iMTUwIDEwMDsgNSAyNTA7IDE1MCAxMDAiIC8+Cgk8L2NpcmNsZT4KPC9zdmc+);
		background-color: #EEE;
		background-repeat: no-repeat;
		background-position: center 5rem;
		opacity: 0;
		-webkit-transition: 0.4s;
		-o-transition: 0.4s;
		transition: 0.4s;
		-webkit-transform: scale(0);
		    -ms-transform: scale(0);
		        transform: scale(0);
		-webkit-transform-origin: center;
		    -ms-transform-origin: center;
		        transform-origin: center;
	}
	body.loading:before {
		opacity: 1;
		-webkit-transform: scale(1);
		    -ms-transform: scale(1);
		        transform: scale(1);
	}
	body.modal_on {
		overflow-y: hidden;
		padding-right: 16px;
	}
	a {
		color: #FFF;
		cursor: pointer;
		text-decoration: none;
	}

	input[type=text],
	input[type=email],
	input[type=password] {
		-webkit-box-flex: 1;
		    -ms-flex: 1;
		        flex: 1;
		display: inline-block;
		height: 2.5rem;
		line-height: 2.5rem;
		padding: 0 1rem;
		margin: 0.5rem 0;
		border: 1px solid #CCC;
		border-radius: 2px; 
		vertical-align: middle;
		letter-spacing: 0.25px;
		background-color: white;
		-webkit-tap-highlight-color: transparent;
	}
	input[type=text]:focus,
	input[type=email]:focus,
	input[type=password]:focus {
		border: 1px solid #369;
	}
	input.disabled,
	input[disabled] {
		opacity: 0.4;
		-webkit-filter: grayscale(1);
		filter: grayscale(1);
		-webkit-user-select: none;
		-moz-user-select: none;
		 -ms-user-select: none;
		     user-select: none;
		pointer-events: none;
	}
	input[type=radio],
	input[type=checkbox] {
		position: relative;
		max-width: 18px;
		width: 18px;
		height: 18px;
		padding: 0;
		outline: 0;
		border: 0;
		margin: 0;
		margin-right: 0.5rem;
		background: transparent;
		-webkit-box-sizing: border-box;
		        box-sizing: border-box;
		-webkit-box-shadow: none;
		        box-shadow: none;
		text-align: center;
		vertical-align: middle;
		-webkit-appearance: none;
		cursor: pointer;
		color: #888;
	}
	input[type=radio]:after,
	input[type=checkbox]:after {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		width: 18px;
		height: 18px;
		z-index: 0;
		border: 2px solid;
		-webkit-transition: 0.1s;
		-o-transition: 0.1s;
		transition: 0.1s;
	}
	input[type=radio]:after {
		border-radius: 100%;
	}
	input[type=checkbox]:checked:after {
		top: -4px;
		left: -4px;
		width: 10px;
		height: 22px;
		border-top-color: transparent;
		border-left-color: transparent;
		-webkit-transform: rotate(40deg);
		-ms-transform: rotate(40deg);
		    transform: rotate(40deg);
		-webkit-backface-visibility: hidden;
		backface-visibility: hidden;
		-webkit-transform-origin: 100% 100%;
		-ms-transform-origin: 100% 100%;
		    transform-origin: 100% 100%;
	}
	input[type=radio]:checked:after {
		background-color: #369;
		border-color: #369;
		-webkit-backface-visibility: hidden;
		backface-visibility: hidden;
	}
	.btn {
		display: inline-block;
		height: 2.25rem;
		line-height: 2.25rem;
		padding: 0 1rem;
		margin: 0.25rem;
		border-radius: 2px;
		border: 1px solid transparent;
		color: #fff;
		background-color: #369;
		text-align: center;
		text-decoration: none;
		text-transform: uppercase;
		vertical-align: middle;
		letter-spacing: 0.5px;
		-webkit-transition: background-color 0.2s ease-out;
		-o-transition: background-color 0.2s ease-out;
		transition: background-color 0.2s ease-out;
		-webkit-tap-highlight-color: transparent;
		cursor: pointer;
		-webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.2);
		        box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.2);
	}
	.btn svg {
		fill: #FFF;
		width: 100%;
		height: 100%;
	}
	.btn.flat {
		border: 1px solid #DDD;
		color: #555;
		background-color: rgba(0,0,0,0.05);
		-webkit-box-shadow: none;
		        box-shadow: none;
	}
	.btn.flat svg {
		fill: #666;
	}


	.options {
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-orient: vertical;
		-webkit-box-direction: normal;
		    -ms-flex-direction: column;
		        flex-direction: column;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
		-webkit-box-pack: justify;
		    -ms-flex-pack: justify;
		        justify-content: space-between;
		position: fixed;
		z-index: 103;
		left: -99999px;
		top: -99999px;
		margin: 0;
		padding: 0.5rem 0;
		background-color: #FFF;
		border-radius: 4px;
		-webkit-box-shadow: 0 8px 10px 1px rgba(0,0,0,0.14), 0 3px 14px 2px rgba(0,0,0,0.12), 0 5px 5px -3px rgba(0,0,0,0.2);
		        box-shadow: 0 8px 10px 1px rgba(0,0,0,0.14), 0 3px 14px 2px rgba(0,0,0,0.12), 0 5px 5px -3px rgba(0,0,0,0.2);
		overflow: hidden;
		opacity: 0;
		visibility: hidden;
		height: 0;
		-webkit-transition: opacity 0.2s ease, height 0.2s linear;
		-o-transition: opacity 0.2s ease, height 0.2s linear;
		transition: opacity 0.2s ease, height 0.2s linear;
	}
	.options:before {
		content: attr(alt);
		width: 100%;
		padding: 0 1.5rem;
		margin-bottom: 0.75rem;
		opacity: 0.3;
		font-weight: bold;
	}
	.options a {
		width: 100%;
		line-height: 1;
		padding: 0.5rem 1.5rem;
		color: #444;
		-webkit-user-select: none;
		   -moz-user-select: none;
		    -ms-user-select: none;
		        user-select: none;
		cursor: pointer;
	}
	.options a:hover {
		-webkit-box-shadow: inset 0 0 0 2rem rgba(0,0,0,0.1);
		        box-shadow: inset 0 0 0 2rem rgba(0,0,0,0.1);
	}

	.toast {
		position: fixed;
		left: 0;
		right: 0;
		bottom: -10rem;
		z-index: 10000;
		opacity: 0;
		width: auto;
		height: auto;
		min-height: 3rem;
		line-height: 1.5;
		margin: 1rem;
		padding: 0.5rem 1rem;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
		-webkit-box-pack: justify;
		    -ms-flex-pack: justify;
		        justify-content: space-between;
		color: #FFF;
		background-color: #222;
		border-radius: 2px;
		font-size: 0.9rem;
		font-weight: 400;
		letter-spacing: 0.2px;
		-webkit-box-shadow: 0 2px 2px 0 rgba(0, 0, 0, 0.14), 0 3px 1px -2px rgba(0, 0, 0, 0.12), 0 1px 5px 0 rgba(0, 0, 0, 0.2);
		box-shadow: 0 2px 2px 0 rgba(0, 0, 0, 0.14), 0 3px 1px -2px rgba(0, 0, 0, 0.12), 0 1px 5px 0 rgba(0, 0, 0, 0.2);
		-webkit-transition: 0.5s;
		-o-transition: 0.5s;
		transition: 0.5s;
	}
	.toast.wait {
		cursor: wait;
		padding-left: 4rem;
	}
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
	@-webkit-keyframes pulsate {
		0%		{ opacity: 0; transform: scale(0.1); -webkit-transform: scale(0.1);}
		50%		{ opacity: 1; }
		100%	{ opacity: 0; transform: scale(1.2); -webkit-transform: scale(1.2);}
	}
	@keyframes pulsate {
		0%		{ opacity: 0; transform: scale(0.1); -webkit-transform: scale(0.1);}
		50%		{ opacity: 1; }
		100%	{ opacity: 0; transform: scale(1.2); -webkit-transform: scale(1.2);}
	}
	@media (min-width: 576px) {
		.toast {
			min-width: 18rem;
			max-width: 30rem;
			right: auto;
		}
	}

	.overlay {
		overflow: hidden;
		position: fixed;
		z-index: 101;
		bottom: 0;
		right: 0;
		left: 0;
		top: 0;
		background-color: #000;
		visibility: hidden;
		opacity: 0;
	}
	body.toast_on .overlay,
	body.modal_on .overlay {
		opacity: 0.7;
		visibility: visible;
	}

	header {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 100;
		height: 4rem;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
		-webkit-box-pack: justify;
		    -ms-flex-pack: justify;
		        justify-content: space-between;
		color: #EEE;
		background-color: #369;
		-webkit-box-shadow: 0 4px 5px 0 rgba(0,0,0,0.14), 0 1px 10px 0 rgba(0,0,0,0.12), 0 2px 4px -1px rgba(0,0,0,0.2);
		        box-shadow: 0 4px 5px 0 rgba(0,0,0,0.14), 0 1px 10px 0 rgba(0,0,0,0.12), 0 2px 4px -1px rgba(0,0,0,0.2);
	}
	header * {
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		height: inherit;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
	}
	header svg {
		fill: #EEE;
		margin: auto;
		-webkit-box-flex: 1;
		    -ms-flex: 1 0 1.5rem;
		        flex: 1 0 1.5rem;
		max-width: 1.5rem;
		max-height: 1.5rem;
	}
	header #breadcrumb {
		-webkit-box-flex: 1;
		    -ms-flex: 1;
		        flex: 1;
		height: 4rem;
		line-height: 4rem;
		-webkit-box-align: initial;
		    -ms-flex-align: initial;
		        align-items: initial;
		overflow-x: hidden;
		overflow-y: hidden;
	}
	header #breadcrumb:hover {
		overflow-x: auto;
	}
	header #breadcrumb a:last-child {
		padding-right: 1rem;
	}
	header #breadcrumb a:first-child {
		-webkit-box-flex: 1;
		    -ms-flex: 1 0 3rem;
		        flex: 1 0 3rem;
		max-width: 3rem;
		margin-left: 0;
		margin-right: -0.5rem;
		padding-right: 0;
	}
	header #breadcrumb a {
		-webkit-box-flex: 0;
		    -ms-flex: 0 0 0%;
		        flex: 0 0 0%;
		max-width: initial;
		line-height: inherit;
		margin-left: 0.5rem;
		white-space: nowrap;
	}
	header #breadcrumb a + a:before {
		content: '/';
		opacity: 0.4;
		margin-right: 0.5rem;
		font-weight: bold;
	}
	header nav {
		height: 4rem;
		background-color: rgba(0,0,0,0.05);
	}
	header nav a {
		padding: 0.5rem 0.75rem;
		font-size: 0.8rem;
		-webkit-box-orient: vertical;
		-webkit-box-direction: normal;
		    -ms-flex-direction: column;
		        flex-direction: column;
	}
	header nav a:hover {
		background-color: rgba(0,0,0,0.2);
	}
	header nav a.toggle_view[title]:after {
		content: attr(title);
	}
	header nav a.toggle_view {
		width: 80px;
	}
	header nav a.toggle_view[title*=Grid] svg path {
		d: path("M3,3v8h8V3H3z M9,9H5V5h4V9z M3,13v8h8v-8H3z M9,19H5v-4h4V19z M13,3v8h8V3H13z M19,9h-4V5h4V9z M13,13v8h8v-8H13z M19,19h-4v-4h4V19z");
	}
	header nav a.toggle_view[title*=List] svg path {
		d: path("M4 14h4v-4H4v4zm0 5h4v-4H4v4zM4 9h4V5H4v4zm5 5h12v-4H9v4zm0 5h12v-4H9v4zM9 5v4h12V5H9z");
	}

	@media(max-width: 576px){
		header {
			-webkit-box-orient: vertical;
			-webkit-box-direction: normal;
			    -ms-flex-direction: column;
			        flex-direction: column;
			-webkit-box-align: start;
			    -ms-flex-align: start;
			        align-items: flex-start;
			height: 7rem;
		}
		header #breadcrumb {
			width: 100%;
			height: 3rem;
			line-height: 3rem;
		}
		header nav {
			width: 100%;
		}
	}


	main {
		width: 100%;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-ms-flex-wrap: wrap;
		    flex-wrap: wrap;
		padding: 0.5rem;
		margin: auto;
		margin-top: 4.5rem;
		margin-bottom: 2.5rem;
	}
	@media(max-width: 576px){
		main {
			margin-top: 7.5rem;
			margin-bottom: 4rem;
		}
	}
	main .item {
		-webkit-box-flex: 1;
		    -ms-flex: 1 0 100%;
		        flex: 1 0 100%;
		max-width: 100%;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
	}
	main .item + .item a {
		border-top: 1px solid #EEE;
	}
	main .item a {
		-webkit-box-flex: 1;
		    -ms-flex: 1;
		        flex: 1;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
		height: 3.5rem;
		line-height: 3.5rem;
		margin: 0;
		padding: 0.5rem;
		border-radius: 0;
		border: 1px solid transparent;
		color: #444;
		background-color: white;
		overflow: hidden;
		-webkit-transition: background-color 0.01s;
		-o-transition: background-color 0.01s;
		transition: background-color 0.01s;
		-webkit-box-shadow: 0 1px 2px rgba(0,0,0,0.05), 0 1px 1px rgba(0,0,0,0.1);
		        box-shadow: 0 1px 2px rgba(0,0,0,0.05), 0 1px 1px rgba(0,0,0,0.1);
	}
	main .item.hover a,
	main .item:hover a {
		background-color: #DEF;
	}
	main .item.selected a {
		border: 1px solid #BCD;
		background-color: #CDE;
		-webkit-box-shadow: 0 3px 2px 0 rgba(0,0,0,0.14), 0 2px 1px -2px rgba(0,0,0,0.12), 0 1px 6px 0 rgba(0,0,0,0.2);
		        box-shadow: 0 3px 2px 0 rgba(0,0,0,0.14), 0 2px 1px -2px rgba(0,0,0,0.12), 0 1px 6px 0 rgba(0,0,0,0.2);
	}
	main .item.selected:hover a {
		background-color: #BCD;
	}
	main .item span {
		-webkit-box-flex: 1;
		    -ms-flex: 1;
		        flex: 1;
		height: inherit;
		display: block;
		height: inherit;
		line-height: inherit;
		color: inherit;
		overflow: hidden;
		white-space: nowrap;
		-o-text-overflow: ellipsis;
		   text-overflow: ellipsis;
	}
	main .item .is_file span[rel] {
		position: relative;
		margin-top: -0.75rem;
	}
	main .item .is_file span[rel]:before {
		content: attr(rel);
		position: absolute;
		bottom: 0;
		font-size: 0.7rem;
		opacity: 0.5;
	}
	main .item a svg.icon {
		width: 2rem;
		height: 2rem;
		opacity: 0.8;
		margin: auto 0.5rem;
		vertical-align: middle;
	}
	main .item a svg.more {
		fill: #666;
		width: 2rem;
		height: 2rem;
		margin: auto;
		opacity: 0.5;
	}
	main .item.selected a svg.icon path {
		fill: #555;
		d: path("M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z");
	}
	@media(min-width: 576px){
		main.gridView .item {
			max-width: 50%;
		}
		main.gridView .item a {
			margin: 0.5rem;
			border-radius: 4px;
		}
		main.gridView .item + .item a {
			border-top: none;
		}
	}
	@media(min-width: 768px){
		main.gridView .item {
			max-width: 33.3333%;
		}
	}
	@media(min-width: 992px){
		main.gridView .item {
			max-width: 25%;
		}
	}
	@media(min-width: 1200px){
		main.gridView .item {
			max-width: 20%;
		}
	}

	main.listView .item span {
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
	}
	main.listView .item span:not(.name) {
		opacity: 0.7;
	}
	main.listView .item a,
	main.listView .item.tHead {
		line-height: 3rem;
		height: 3rem;
		margin: 0;
		padding: 0.25rem;
	}
	main.listView .item a svg.icon {
		width: 1.75rem;
		height: 1.75rem;
		margin-left: 0.5rem;
		margin-right: 1rem;
	}
	main.listView .item:not(.tHead) span.name {padding-right: 1rem; display: block;}
	main.listView .item span.size {max-width: 7rem;}
	main.listView .item span.time {max-width: 10rem; display: none;}
	main.listView .item span.perm {max-width: 8rem;  display: none;}
	main.listView .item span.ownr {max-width: 10rem; display: none;}
	main.listView .item  svg.more {
		fill: #666;
		height: 1.5rem;
		margin: auto;
		opacity: 0.5;
	}
	main.listView .item.tHead {
		padding-right: 2.5rem;
	}
	main.listView .item.tHead span {
		cursor: pointer;
		opacity: 0.7;
	}
	main.listView .item.tHead span:after {
		opacity: 0.6;
		width: 1.5rem;
		height: 1.5rem;
		margin: auto 0.5rem;
	}
	main.listView .item.tHead span.sort_asc:after {
		content: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTEyIDhsLTYgNiAxLjQxIDEuNDFMMTIgMTAuODNsNC41OSA0LjU4TDE4IDE0eiIvPjwvc3ZnPgo=');
	}
	main.listView .item.tHead span.sort_desc:after {
		content: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTE2LjU5IDguNTlMMTIgMTMuMTcgNy40MSA4LjU5IDYgMTBsNiA2IDYtNnoiLz48L3N2Zz4=');
	}
	@media(min-width: 576px){
		main.listView .item span.time {display: -webkit-box;display: -ms-flexbox;display: flex;}
	}
	@media(min-width: 768px){
		main.listView .item span.perm {display: -webkit-box;display: -ms-flexbox;display: flex;}
	}
	@media(min-width: 992px){
		main.listView .item span.ownr {display: -webkit-box;display: -ms-flexbox;display: flex;}
	}

	.modal {
		overflow: hidden;
		overflow-y: scroll;
		position: fixed;
		z-index: 102;
		top: 0;
		bottom: 0;
		right: -23rem;
		padding: 1rem;
		width: 100%;
		max-width: 22rem;
		background-color: whitesmoke;
		-webkit-box-shadow: -4px 0 5px 0 rgba(0,0,0,0.14), -1px 0 10px 0 rgba(0,0,0,0.12), -2px 0 4px -1px rgba(0,0,0,0.2);
		        box-shadow: -4px 0 5px 0 rgba(0,0,0,0.14), -1px 0 10px 0 rgba(0,0,0,0.12), -2px 0 4px -1px rgba(0,0,0,0.2);
	}
	.modal.on {right: 0;}
	.modal .title {
		font-size: 1.5rem;
		margin-top: 0.5rem;
		margin-bottom: 1.5rem;
	}
	.modal .inputs {
		position: relative;
		margin: 1.5rem 0;
	}
	.modal .inputs[title] {
		padding-top: 2rem;
	}
	.modal .inputs[title]:before {
		content: attr(title);
		position: absolute;
		top: 0;
		opacity: 0.4;
		font-weight: bold;
	}
	.modal .inputs.inline {
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
	}
	.modal label:not(.btn) {
		-webkit-box-flex: 1;
		    -ms-flex: 1;
		        flex: 1;
		color: #555;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-orient: vertical;
		-webkit-box-direction: normal;
		    -ms-flex-direction: column;
		        flex-direction: column;
	}
	.modal label.inline:not(.btn) {
		cursor: pointer;
		-webkit-box-orient: horizontal;
		-webkit-box-direction: normal;
		    -ms-flex-direction: row;
		        flex-direction: row;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
	}
	.modal .action {
		margin: 0 -0.5rem;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
		-webkit-box-pack: end;
		    -ms-flex-pack: end;
		        justify-content: flex-end;
	}



	#uploadModal #drop_area.hover {
		background-color: #eef;
	}
	#uploadModal #drop_area {
		position: relative;
		text-align: center;
		height: 0;
		padding-bottom: 100%;
		border: 5px dashed rgba(0,0,0,0.3);
		color: #333;
		background-color: #FFF;
		-webkit-transition: background 0.2s;
		-o-transition: background 0.2s;
		transition: background 0.2s;
		-webkit-box-shadow: inset 0 0 15rem 1rem rgba(0,0,0,0.1);
		        box-shadow: inset 0 0 15rem 1rem rgba(0,0,0,0.1);
	}
	#uploadModal .inputs {
		display: block;
		position: absolute;
		top: 50%;
		left: 0;
		right: 0;
		bottom: 0;
		margin: 0;
		-webkit-transform: translateY(-50%);
		    -ms-transform: translateY(-50%);
		        transform: translateY(-50%);
		color: #666;
	}
	#uploadModal input[type=file] {
		opacity: 0;
		visibility: hidden;
		position: absolute;
		left: -999999999px;
	}
	#uploadModal .small {
		color: #888;
		font-size: 0.8rem;
		margin-top: 0.5rem;
	}



	#progressModal ul.body li {
		position: relative;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-orient: vertical;
		-webkit-box-direction: normal;
		    -ms-flex-direction: column;
		        flex-direction: column;
		font-size: 0.9rem;
		margin: 1.5rem auto;
	}
	#progressModal ul.body li[title] {
		padding-bottom: 1.5rem;
	}
	#progressModal ul.body li[title]:after {
		content: attr(title);
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		opacity: 0.6;
		font-size: 0.8rem;
		font-weight: bold;
		text-align: right;
	}
	#progressModal ul.body li[data-size]:before {
		content: attr(data-size);
		position: absolute;
		top: 0;
		right: 0;
		margin: 0.5rem 0;
		opacity: 0.4;
		font-weight: bold;
	}
	#progressModal label {
		display: block;
		overflow: hidden;
		white-space: nowrap;
		-o-text-overflow: ellipsis;
		   text-overflow: ellipsis;
		opacity: 0.8;
	}
	#progressModal li[data-size] label {
		margin: 0.5rem 5rem 0.5rem 0;
	}
	#progressModal .progress {
		position: relative;
		overflow: hidden;
		width: 100%;
		height: 0.5rem;
		border-radius: 2px;
		background-color: #DDD;
	}
	#progressModal .progress > span {
		width: 0;
		height: inherit;
		position: absolute;
		z-index: 1;
		left: 0;
		background-color: #369;
	}
	#progressModal li:not(.uploading) .progress > span {
		background-color: forestgreen;
	}
	#progressModal li.error .progress > span {
		background-color: red;
	}

	#configModal .pwdeye {
		position: absolute;
		bottom: 0.5rem;
		right: 0;
		margin: 0;
		padding: 0.5rem;
		width: 2.5rem;
		height: 2.5rem;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		border-left: 1px solid #CCC;
	}
	#configModal .pwdeye.off svg path {
		d: path("M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z");
	}

	#detailModal .inputs {
		color: #777;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-orient: horizontal;
		-webkit-box-direction: normal;
		    -ms-flex-direction: row;
		        flex-direction: row;
		-webkit-box-pack: justify;
		    -ms-flex-pack: justify;
		        justify-content: space-between;
	}

	#detailModal .inputs b {
		line-height: 1.5rem;
		margin-left: 1rem;
		text-align: right;
		font-weight: bold;
		font-size: 0.8rem;
		font-family: monospace;
	}

	footer {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 99;
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-webkit-box-align: center;
		    -ms-flex-align: center;
		        align-items: center;
		-webkit-box-pack: justify;
		    -ms-flex-pack: justify;
		        justify-content: space-between;
		height: 2rem;
		line-height: 2rem;
		padding: 0 1rem;
		color: #EEE;
		background-color: #035;
		-webkit-box-shadow: inset 0 2px 3rem rgba(0,0,0,0.5);
		        box-shadow: inset 0 2px 3rem rgba(0,0,0,0.5);
		font-size: 0.75rem;
	}
	footer * {
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		height: inherit;
		line-height: inherit;
	}
	footer svg {
		max-width: 20px;
		max-height: 20px;
		margin: auto;
	}
	footer .btn {
		height: 1.3rem;
		line-height: 1.3rem;
		padding: 0 0.5rem;
		margin: 0.35rem;
		background-color: #EEE;
		color: #222;
		font-weight: 700;
		text-transform: initial;
	}
	@media (max-width: 576px) {
		footer {
			-webkit-box-orient: vertical;
			-webkit-box-direction: normal;
			    -ms-flex-direction: column;
			        flex-direction: column;
			padding: 0.5rem;
			height: 3.6rem;
			line-height: 1.5rem;
		}
		footer > * {
			height: 1.5rem;
		}
	}
</style>
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-113019966-1"></script>
<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	gtag('config', 'UA-113019966-1');
</script>
</head>
<body onload="toast('<?= @$welcome; ?>');">
	<header>
		<div id="breadcrumb"></div>
		<nav>
			<a class="toggle_view" title="<?= @$_COOKIE['fe_view'] == 'listView' ? 'Grid View' : 'List View'; ?>"><svg viewBox="0 0 24 24"><path /></svg></a>
			<a onclick="modal('on', '#uploadModal');" title="Upload"><svg viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>Upload</a>
			<a onclick="modal('on', '#newDirModal');" title="New Folder"><svg viewBox="0 0 24 24"><path d="M20 6h-8l-2-2H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-1 8h-3v3h-2v-3h-3v-2h3V9h2v3h3v2z"/></svg>New Folder</a>
			<a onclick="modal('on', '#newFileModal');" title="New File"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 14h-3v3h-2v-3H8v-2h3v-3h2v3h3v2zm-3-7V3.5L18.5 9H13z"/></svg>New File</a>
			<a onclick="modal('on', '#configModal');" title="Settings"><svg viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg>Settings</a>
			<a class="logout" title="Logout"><svg viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg>Logout</a>
		</nav>
	</header>
	<main class="<?= isset($_COOKIE['fe_view']) ? $_COOKIE['fe_view'] : 'gridView'; ?>"></main>
	<footer>
		<div>
			<?php if( @$_SESSION['gitJSON']['latest_ver'] == VERSION ) :?><span>File Explorer v<?= VERSION; ?></span><?php else : ?><a class="btn upgrade">New Update Available</a><?php endif; ?>
			<b> &nbsp; &bull; &nbsp;</b>
			<span>Made with &nbsp;<svg viewBox="0 0 24 24"><path fill="#D00" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>&nbsp; By &nbsp;<a target="_blank" href="https://github.com/webcdn">WebCDN</a></span>
		</div>
		<div>
			<a target="_blank" href="https://github.com/webcdn/File-Explorer/issues">Report Bugs</a>
			<b> &nbsp; &bull; &nbsp;</b>
			<a target="_blank" href="https://github.com/webcdn/File-Explorer/issues/1">Suggestions / Feedback</a>
			<b> &nbsp; &bull; &nbsp;</b>
			<a target="_blank" href="https://gg.gg/contribute">Donate</a>
		</div>
	</footer>

	<div class="overlay"></div>
	<div class="options" alt="Options"></div>

	<!-- MODALs -->
	<div id="uploadModal" class="modal">
		<button class="btn flat" style="float: right;" onclick="modal('off');">Close</button>
		<h5 class="title">Upload Files</h5>
		<div id="drop_area">
			<div class="inputs">
				<p>Drop Files Here</p>
				<p>or</p>
				<label for="uploadfile" class="btn">Choose Files</label>
				<input id="uploadfile" type="file" multiple>
				<p class="small">Maximum Upload File Size: &nbsp; <b class="maxSize"><?= $max_upload_size; ?></b></p>
			</div>
		</div>
	</div>

	<div id="progressModal" class="modal">
		<h4 class="title">Uploading</h4>
		<ul class="body"></ul>
		<div class="action">
			<button type="button" class="btn">Abort</button>
		</div>
	</div>

	<div id="newDirModal" class="modal">
		<h5 class="title">Create New Folder</h5>
		<form >
			<div class="inputs">
				<label>Enter Directory Name<input id="dirname" type="text"></label>
			</div>
			<div class="action">
				<button type="submit" class="btn">Create</button>
				<button type="button" class="btn flat" onclick="modal('off');">Close</button>
			</div>
		</form>
	</div>

	<div id="newFileModal" class="modal">
		<h5 class="title">Create New File</h5>
		<form>
			<div class="inputs">
				<label>Enter File Name<input id="filename" type="text"></label>
			</div>
			<div class="action">
				<button type="submit" class="btn">Create</button>
				<button type="button" class="btn flat" onclick="modal('off');">Close</button>
			</div>
		</form>
	</div>

	<div id="renameModal" class="modal">
		<h5 class="title">Rename</h5>
		<form>
			<input type="hidden" id="path" />
			<div class="inputs">
				<label>Enter New Name<input id="newname" type="text"></label>
			</div>
			<div class="action">
				<button type="submit" class="btn">Rename</button>
				<button type="button" class="btn flat" onclick="modal('off');">Close</button>
			</div>
		</form>
	</div>

	<div id="permitModal" class="modal">
		<h5 class="title">Set Permission</h5>
		<div class="inputs inline" title="Owner Permissions">
			<label class="inline"><input type="checkbox" id="ownRead">Read</label>
			<label class="inline"><input type="checkbox" id="ownWrit">Write</label>
			<label class="inline"><input type="checkbox" id="ownExec">Execute</label>
		</div>
		<div class="inputs inline" title="Group Permissions">
			<label class="inline"><input type="checkbox" id="grpRead">Read</label>
			<label class="inline"><input type="checkbox" id="grpWrit">Write</label>
			<label class="inline"><input type="checkbox" id="grpExec">Execute</label>
		</div>
		<div class="inputs inline" title="Public Permissions">
			<label class="inline"><input type="checkbox" id="pubRead">Read</label>
			<label class="inline"><input type="checkbox" id="pubWrit">Write</label>
			<label class="inline"><input type="checkbox" id="pubExec">Execute</label>
		</div>
		<form>
			<input type="hidden" id="perm_path" name="perm_path">
			<div class="inputs"><label>Permission<input id="perm_code" type="text" maxlength="4" pattern="^0[0-7][0-7][0-7]$"></label></div>
			<div class="inputs recurse"><label class="inline"><input type="checkbox" id="perm_recursive_chk">Recurse into All Sub-Directories</label></div>
			<div class="inputs recurse"><label class="inline"><input type="radio" name="recurse" value="df" disabled>All Files & Directories</label></div>
			<div class="inputs recurse"><label class="inline"><input type="radio" name="recurse" value="d"  disabled>Directories Only</label></div>
			<div class="inputs recurse"><label class="inline"><input type="radio" name="recurse" value="f"  disabled>Files Only</label></div>
			<div class="action">
				<button type="submit" class="btn">Modify</button>
				<button type="button" class="btn flat" onclick="modal('off');">Close</button>
			</div>
		</form>
	</div>

	<div id="detailModal" class="modal">
		<h5 class="title">Details and Info</h5>
		<div class="inputs"><span>Name</span><b class="name"></b></div>
		<div class="inputs"><span>Path</span><b class="path"></b></div>
		<div class="inputs"><span>Size</span><b class="size"></b></div>
		<div class="inputs"><span>Type</span><b class="type"></b></div>
		<div class="inputs"><span>Owner</span><b class="ownr"></b></div>
		<div class="inputs"><span>Permission</span><b class="perm"></b></div>
		<div class="inputs"><span>Created Time</span><b class="ctime"></b></div>
		<div class="inputs"><span>Accessed Time</span><b class="atime"></b></div>
		<div class="inputs"><span>Modified Time</span><b class="mtime"></b></div>
		<div class="action">
			<button type="button" class="btn" onclick="copy(document.querySelector('#detailModal b.path').innerHTML); toast('Copied to clipboard')">Copy Path</button>
			<button type="button" class="btn flat" onclick="modal('off');">Close</button>
		</div>
	</div>

	<div id="configModal" class="modal">
		<h5 class="title">Settings</h5>
		<form>
			<div class="inputs">
				<label>Enter Password<input id="pass" type="password" value="<?= @$_SESSION['__allowed']; ?>" required></label>
				<a class="btn flat pwdeye" title="Show"><svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg></a>
			</div>
			<div class="inputs">
				<label class="inline"><input type="checkbox" id="hdfl" <?= @$config->show_hidden == 'true' ? 'checked': ''; ?>>Show Hidden Files</label>
			</div>
			<div class="action">
				<button type="submit" class="btn">Save</button>
				<button type="button" class="btn flat" onclick="modal('off');">Close</button>
			</div>
			<div class="action" style="position: absolute; bottom: 1rem; left: 0; right: 0; text-align: center; justify-content: center;">
				<button type="button" class="btn upgrade" style="background-color: #070;">Upgrade</button>
				<button type="button" class="btn logout" style="background-color: #B00;">Log Out</button>
			</div>
		</form>
	</div>
	<!-- MODALs END -->

	<script type="text/javascript" src="data:text/javascript;base64,LyohIGpRdWVyeSB2My4zLjEgfCAoYykgSlMgRm91bmRhdGlvbiBhbmQgb3RoZXIgY29udHJpYnV0b3JzIHwganF1ZXJ5Lm9yZy9saWNlbnNlICovCiFmdW5jdGlvbihlLHQpeyJ1c2Ugc3RyaWN0Ijsib2JqZWN0Ij09dHlwZW9mIG1vZHVsZSYmIm9iamVjdCI9PXR5cGVvZiBtb2R1bGUuZXhwb3J0cz9tb2R1bGUuZXhwb3J0cz1lLmRvY3VtZW50P3QoZSwhMCk6ZnVuY3Rpb24oZSl7aWYoIWUuZG9jdW1lbnQpdGhyb3cgbmV3IEVycm9yKCJqUXVlcnkgcmVxdWlyZXMgYSB3aW5kb3cgd2l0aCBhIGRvY3VtZW50Iik7cmV0dXJuIHQoZSl9OnQoZSl9KCJ1bmRlZmluZWQiIT10eXBlb2Ygd2luZG93P3dpbmRvdzp0aGlzLGZ1bmN0aW9uKGUsdCl7InVzZSBzdHJpY3QiO3ZhciBuPVtdLHI9ZS5kb2N1bWVudCxpPU9iamVjdC5nZXRQcm90b3R5cGVPZixvPW4uc2xpY2UsYT1uLmNvbmNhdCxzPW4ucHVzaCx1PW4uaW5kZXhPZixsPXt9LGM9bC50b1N0cmluZyxmPWwuaGFzT3duUHJvcGVydHkscD1mLnRvU3RyaW5nLGQ9cC5jYWxsKE9iamVjdCksaD17fSxnPWZ1bmN0aW9uIGUodCl7cmV0dXJuImZ1bmN0aW9uIj09dHlwZW9mIHQmJiJudW1iZXIiIT10eXBlb2YgdC5ub2RlVHlwZX0seT1mdW5jdGlvbiBlKHQpe3JldHVybiBudWxsIT10JiZ0PT09dC53aW5kb3d9LHY9e3R5cGU6ITAsc3JjOiEwLG5vTW9kdWxlOiEwfTtmdW5jdGlvbiBtKGUsdCxuKXt2YXIgaSxvPSh0PXR8fHIpLmNyZWF0ZUVsZW1lbnQoInNjcmlwdCIpO2lmKG8udGV4dD1lLG4pZm9yKGkgaW4gdiluW2ldJiYob1tpXT1uW2ldKTt0LmhlYWQuYXBwZW5kQ2hpbGQobykucGFyZW50Tm9kZS5yZW1vdmVDaGlsZChvKX1mdW5jdGlvbiB4KGUpe3JldHVybiBudWxsPT1lP2UrIiI6Im9iamVjdCI9PXR5cGVvZiBlfHwiZnVuY3Rpb24iPT10eXBlb2YgZT9sW2MuY2FsbChlKV18fCJvYmplY3QiOnR5cGVvZiBlfXZhciBiPSIzLjMuMSIsdz1mdW5jdGlvbihlLHQpe3JldHVybiBuZXcgdy5mbi5pbml0KGUsdCl9LFQ9L15bXHNcdUZFRkZceEEwXSt8W1xzXHVGRUZGXHhBMF0rJC9nO3cuZm49dy5wcm90b3R5cGU9e2pxdWVyeToiMy4zLjEiLGNvbnN0cnVjdG9yOncsbGVuZ3RoOjAsdG9BcnJheTpmdW5jdGlvbigpe3JldHVybiBvLmNhbGwodGhpcyl9LGdldDpmdW5jdGlvbihlKXtyZXR1cm4gbnVsbD09ZT9vLmNhbGwodGhpcyk6ZTwwP3RoaXNbZSt0aGlzLmxlbmd0aF06dGhpc1tlXX0scHVzaFN0YWNrOmZ1bmN0aW9uKGUpe3ZhciB0PXcubWVyZ2UodGhpcy5jb25zdHJ1Y3RvcigpLGUpO3JldHVybiB0LnByZXZPYmplY3Q9dGhpcyx0fSxlYWNoOmZ1bmN0aW9uKGUpe3JldHVybiB3LmVhY2godGhpcyxlKX0sbWFwOmZ1bmN0aW9uKGUpe3JldHVybiB0aGlzLnB1c2hTdGFjayh3Lm1hcCh0aGlzLGZ1bmN0aW9uKHQsbil7cmV0dXJuIGUuY2FsbCh0LG4sdCl9KSl9LHNsaWNlOmZ1bmN0aW9uKCl7cmV0dXJuIHRoaXMucHVzaFN0YWNrKG8uYXBwbHkodGhpcyxhcmd1bWVudHMpKX0sZmlyc3Q6ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5lcSgwKX0sbGFzdDpmdW5jdGlvbigpe3JldHVybiB0aGlzLmVxKC0xKX0sZXE6ZnVuY3Rpb24oZSl7dmFyIHQ9dGhpcy5sZW5ndGgsbj0rZSsoZTwwP3Q6MCk7cmV0dXJuIHRoaXMucHVzaFN0YWNrKG4+PTAmJm48dD9bdGhpc1tuXV06W10pfSxlbmQ6ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5wcmV2T2JqZWN0fHx0aGlzLmNvbnN0cnVjdG9yKCl9LHB1c2g6cyxzb3J0Om4uc29ydCxzcGxpY2U6bi5zcGxpY2V9LHcuZXh0ZW5kPXcuZm4uZXh0ZW5kPWZ1bmN0aW9uKCl7dmFyIGUsdCxuLHIsaSxvLGE9YXJndW1lbnRzWzBdfHx7fSxzPTEsdT1hcmd1bWVudHMubGVuZ3RoLGw9ITE7Zm9yKCJib29sZWFuIj09dHlwZW9mIGEmJihsPWEsYT1hcmd1bWVudHNbc118fHt9LHMrKyksIm9iamVjdCI9PXR5cGVvZiBhfHxnKGEpfHwoYT17fSkscz09PXUmJihhPXRoaXMscy0tKTtzPHU7cysrKWlmKG51bGwhPShlPWFyZ3VtZW50c1tzXSkpZm9yKHQgaW4gZSluPWFbdF0sYSE9PShyPWVbdF0pJiYobCYmciYmKHcuaXNQbGFpbk9iamVjdChyKXx8KGk9QXJyYXkuaXNBcnJheShyKSkpPyhpPyhpPSExLG89biYmQXJyYXkuaXNBcnJheShuKT9uOltdKTpvPW4mJncuaXNQbGFpbk9iamVjdChuKT9uOnt9LGFbdF09dy5leHRlbmQobCxvLHIpKTp2b2lkIDAhPT1yJiYoYVt0XT1yKSk7cmV0dXJuIGF9LHcuZXh0ZW5kKHtleHBhbmRvOiJqUXVlcnkiKygiMy4zLjEiK01hdGgucmFuZG9tKCkpLnJlcGxhY2UoL1xEL2csIiIpLGlzUmVhZHk6ITAsZXJyb3I6ZnVuY3Rpb24oZSl7dGhyb3cgbmV3IEVycm9yKGUpfSxub29wOmZ1bmN0aW9uKCl7fSxpc1BsYWluT2JqZWN0OmZ1bmN0aW9uKGUpe3ZhciB0LG47cmV0dXJuISghZXx8IltvYmplY3QgT2JqZWN0XSIhPT1jLmNhbGwoZSkpJiYoISh0PWkoZSkpfHwiZnVuY3Rpb24iPT10eXBlb2Yobj1mLmNhbGwodCwiY29uc3RydWN0b3IiKSYmdC5jb25zdHJ1Y3RvcikmJnAuY2FsbChuKT09PWQpfSxpc0VtcHR5T2JqZWN0OmZ1bmN0aW9uKGUpe3ZhciB0O2Zvcih0IGluIGUpcmV0dXJuITE7cmV0dXJuITB9LGdsb2JhbEV2YWw6ZnVuY3Rpb24oZSl7bShlKX0sZWFjaDpmdW5jdGlvbihlLHQpe3ZhciBuLHI9MDtpZihDKGUpKXtmb3Iobj1lLmxlbmd0aDtyPG47cisrKWlmKCExPT09dC5jYWxsKGVbcl0scixlW3JdKSlicmVha31lbHNlIGZvcihyIGluIGUpaWYoITE9PT10LmNhbGwoZVtyXSxyLGVbcl0pKWJyZWFrO3JldHVybiBlfSx0cmltOmZ1bmN0aW9uKGUpe3JldHVybiBudWxsPT1lPyIiOihlKyIiKS5yZXBsYWNlKFQsIiIpfSxtYWtlQXJyYXk6ZnVuY3Rpb24oZSx0KXt2YXIgbj10fHxbXTtyZXR1cm4gbnVsbCE9ZSYmKEMoT2JqZWN0KGUpKT93Lm1lcmdlKG4sInN0cmluZyI9PXR5cGVvZiBlP1tlXTplKTpzLmNhbGwobixlKSksbn0saW5BcnJheTpmdW5jdGlvbihlLHQsbil7cmV0dXJuIG51bGw9PXQ/LTE6dS5jYWxsKHQsZSxuKX0sbWVyZ2U6ZnVuY3Rpb24oZSx0KXtmb3IodmFyIG49K3QubGVuZ3RoLHI9MCxpPWUubGVuZ3RoO3I8bjtyKyspZVtpKytdPXRbcl07cmV0dXJuIGUubGVuZ3RoPWksZX0sZ3JlcDpmdW5jdGlvbihlLHQsbil7Zm9yKHZhciByLGk9W10sbz0wLGE9ZS5sZW5ndGgscz0hbjtvPGE7bysrKShyPSF0KGVbb10sbykpIT09cyYmaS5wdXNoKGVbb10pO3JldHVybiBpfSxtYXA6ZnVuY3Rpb24oZSx0LG4pe3ZhciByLGksbz0wLHM9W107aWYoQyhlKSlmb3Iocj1lLmxlbmd0aDtvPHI7bysrKW51bGwhPShpPXQoZVtvXSxvLG4pKSYmcy5wdXNoKGkpO2Vsc2UgZm9yKG8gaW4gZSludWxsIT0oaT10KGVbb10sbyxuKSkmJnMucHVzaChpKTtyZXR1cm4gYS5hcHBseShbXSxzKX0sZ3VpZDoxLHN1cHBvcnQ6aH0pLCJmdW5jdGlvbiI9PXR5cGVvZiBTeW1ib2wmJih3LmZuW1N5bWJvbC5pdGVyYXRvcl09bltTeW1ib2wuaXRlcmF0b3JdKSx3LmVhY2goIkJvb2xlYW4gTnVtYmVyIFN0cmluZyBGdW5jdGlvbiBBcnJheSBEYXRlIFJlZ0V4cCBPYmplY3QgRXJyb3IgU3ltYm9sIi5zcGxpdCgiICIpLGZ1bmN0aW9uKGUsdCl7bFsiW29iamVjdCAiK3QrIl0iXT10LnRvTG93ZXJDYXNlKCl9KTtmdW5jdGlvbiBDKGUpe3ZhciB0PSEhZSYmImxlbmd0aCJpbiBlJiZlLmxlbmd0aCxuPXgoZSk7cmV0dXJuIWcoZSkmJiF5KGUpJiYoImFycmF5Ij09PW58fDA9PT10fHwibnVtYmVyIj09dHlwZW9mIHQmJnQ+MCYmdC0xIGluIGUpfXZhciBFPWZ1bmN0aW9uKGUpe3ZhciB0LG4scixpLG8sYSxzLHUsbCxjLGYscCxkLGgsZyx5LHYsbSx4LGI9InNpenpsZSIrMSpuZXcgRGF0ZSx3PWUuZG9jdW1lbnQsVD0wLEM9MCxFPWFlKCksaz1hZSgpLFM9YWUoKSxEPWZ1bmN0aW9uKGUsdCl7cmV0dXJuIGU9PT10JiYoZj0hMCksMH0sTj17fS5oYXNPd25Qcm9wZXJ0eSxBPVtdLGo9QS5wb3AscT1BLnB1c2gsTD1BLnB1c2gsSD1BLnNsaWNlLE89ZnVuY3Rpb24oZSx0KXtmb3IodmFyIG49MCxyPWUubGVuZ3RoO248cjtuKyspaWYoZVtuXT09PXQpcmV0dXJuIG47cmV0dXJuLTF9LFA9ImNoZWNrZWR8c2VsZWN0ZWR8YXN5bmN8YXV0b2ZvY3VzfGF1dG9wbGF5fGNvbnRyb2xzfGRlZmVyfGRpc2FibGVkfGhpZGRlbnxpc21hcHxsb29wfG11bHRpcGxlfG9wZW58cmVhZG9ubHl8cmVxdWlyZWR8c2NvcGVkIixNPSJbXFx4MjBcXHRcXHJcXG5cXGZdIixSPSIoPzpcXFxcLnxbXFx3LV18W15cMC1cXHhhMF0pKyIsST0iXFxbIitNKyIqKCIrUisiKSg/OiIrTSsiKihbKl4kfCF+XT89KSIrTSsiKig/OicoKD86XFxcXC58W15cXFxcJ10pKiknfFwiKCg/OlxcXFwufFteXFxcXFwiXSkqKVwifCgiK1IrIikpfCkiK00rIipcXF0iLFc9IjooIitSKyIpKD86XFwoKCgnKCg/OlxcXFwufFteXFxcXCddKSopJ3xcIigoPzpcXFxcLnxbXlxcXFxcIl0pKilcIil8KCg/OlxcXFwufFteXFxcXCgpW1xcXV18IitJKyIpKil8LiopXFwpfCkiLCQ9bmV3IFJlZ0V4cChNKyIrIiwiZyIpLEI9bmV3IFJlZ0V4cCgiXiIrTSsiK3woKD86XnxbXlxcXFxdKSg/OlxcXFwuKSopIitNKyIrJCIsImciKSxGPW5ldyBSZWdFeHAoIl4iK00rIiosIitNKyIqIiksXz1uZXcgUmVnRXhwKCJeIitNKyIqKFs+K35dfCIrTSsiKSIrTSsiKiIpLHo9bmV3IFJlZ0V4cCgiPSIrTSsiKihbXlxcXSdcIl0qPykiK00rIipcXF0iLCJnIiksWD1uZXcgUmVnRXhwKFcpLFU9bmV3IFJlZ0V4cCgiXiIrUisiJCIpLFY9e0lEOm5ldyBSZWdFeHAoIl4jKCIrUisiKSIpLENMQVNTOm5ldyBSZWdFeHAoIl5cXC4oIitSKyIpIiksVEFHOm5ldyBSZWdFeHAoIl4oIitSKyJ8WypdKSIpLEFUVFI6bmV3IFJlZ0V4cCgiXiIrSSksUFNFVURPOm5ldyBSZWdFeHAoIl4iK1cpLENISUxEOm5ldyBSZWdFeHAoIl46KG9ubHl8Zmlyc3R8bGFzdHxudGh8bnRoLWxhc3QpLShjaGlsZHxvZi10eXBlKSg/OlxcKCIrTSsiKihldmVufG9kZHwoKFsrLV18KShcXGQqKW58KSIrTSsiKig/OihbKy1dfCkiK00rIiooXFxkKyl8KSkiK00rIipcXCl8KSIsImkiKSxib29sOm5ldyBSZWdFeHAoIl4oPzoiK1ArIikkIiwiaSIpLG5lZWRzQ29udGV4dDpuZXcgUmVnRXhwKCJeIitNKyIqWz4rfl18OihldmVufG9kZHxlcXxndHxsdHxudGh8Zmlyc3R8bGFzdCkoPzpcXCgiK00rIiooKD86LVxcZCk/XFxkKikiK00rIipcXCl8KSg/PVteLV18JCkiLCJpIil9LEc9L14oPzppbnB1dHxzZWxlY3R8dGV4dGFyZWF8YnV0dG9uKSQvaSxZPS9eaFxkJC9pLFE9L15bXntdK1x7XHMqXFtuYXRpdmUgXHcvLEo9L14oPzojKFtcdy1dKyl8KFx3Kyl8XC4oW1x3LV0rKSkkLyxLPS9bK35dLyxaPW5ldyBSZWdFeHAoIlxcXFwoW1xcZGEtZl17MSw2fSIrTSsiP3woIitNKyIpfC4pIiwiaWciKSxlZT1mdW5jdGlvbihlLHQsbil7dmFyIHI9IjB4Iit0LTY1NTM2O3JldHVybiByIT09cnx8bj90OnI8MD9TdHJpbmcuZnJvbUNoYXJDb2RlKHIrNjU1MzYpOlN0cmluZy5mcm9tQ2hhckNvZGUocj4+MTB8NTUyOTYsMTAyMyZyfDU2MzIwKX0sdGU9LyhbXDAtXHgxZlx4N2ZdfF4tP1xkKXxeLSR8W15cMC1ceDFmXHg3Zi1cdUZGRkZcdy1dL2csbmU9ZnVuY3Rpb24oZSx0KXtyZXR1cm4gdD8iXDAiPT09ZT8iXHVmZmZkIjplLnNsaWNlKDAsLTEpKyJcXCIrZS5jaGFyQ29kZUF0KGUubGVuZ3RoLTEpLnRvU3RyaW5nKDE2KSsiICI6IlxcIitlfSxyZT1mdW5jdGlvbigpe3AoKX0saWU9bWUoZnVuY3Rpb24oZSl7cmV0dXJuITA9PT1lLmRpc2FibGVkJiYoImZvcm0iaW4gZXx8ImxhYmVsImluIGUpfSx7ZGlyOiJwYXJlbnROb2RlIixuZXh0OiJsZWdlbmQifSk7dHJ5e0wuYXBwbHkoQT1ILmNhbGwody5jaGlsZE5vZGVzKSx3LmNoaWxkTm9kZXMpLEFbdy5jaGlsZE5vZGVzLmxlbmd0aF0ubm9kZVR5cGV9Y2F0Y2goZSl7TD17YXBwbHk6QS5sZW5ndGg/ZnVuY3Rpb24oZSx0KXtxLmFwcGx5KGUsSC5jYWxsKHQpKX06ZnVuY3Rpb24oZSx0KXt2YXIgbj1lLmxlbmd0aCxyPTA7d2hpbGUoZVtuKytdPXRbcisrXSk7ZS5sZW5ndGg9bi0xfX19ZnVuY3Rpb24gb2UoZSx0LHIsaSl7dmFyIG8scyxsLGMsZixoLHYsbT10JiZ0Lm93bmVyRG9jdW1lbnQsVD10P3Qubm9kZVR5cGU6OTtpZihyPXJ8fFtdLCJzdHJpbmciIT10eXBlb2YgZXx8IWV8fDEhPT1UJiY5IT09VCYmMTEhPT1UKXJldHVybiByO2lmKCFpJiYoKHQ/dC5vd25lckRvY3VtZW50fHx0OncpIT09ZCYmcCh0KSx0PXR8fGQsZykpe2lmKDExIT09VCYmKGY9Si5leGVjKGUpKSlpZihvPWZbMV0pe2lmKDk9PT1UKXtpZighKGw9dC5nZXRFbGVtZW50QnlJZChvKSkpcmV0dXJuIHI7aWYobC5pZD09PW8pcmV0dXJuIHIucHVzaChsKSxyfWVsc2UgaWYobSYmKGw9bS5nZXRFbGVtZW50QnlJZChvKSkmJngodCxsKSYmbC5pZD09PW8pcmV0dXJuIHIucHVzaChsKSxyfWVsc2V7aWYoZlsyXSlyZXR1cm4gTC5hcHBseShyLHQuZ2V0RWxlbWVudHNCeVRhZ05hbWUoZSkpLHI7aWYoKG89ZlszXSkmJm4uZ2V0RWxlbWVudHNCeUNsYXNzTmFtZSYmdC5nZXRFbGVtZW50c0J5Q2xhc3NOYW1lKXJldHVybiBMLmFwcGx5KHIsdC5nZXRFbGVtZW50c0J5Q2xhc3NOYW1lKG8pKSxyfWlmKG4ucXNhJiYhU1tlKyIgIl0mJigheXx8IXkudGVzdChlKSkpe2lmKDEhPT1UKW09dCx2PWU7ZWxzZSBpZigib2JqZWN0IiE9PXQubm9kZU5hbWUudG9Mb3dlckNhc2UoKSl7KGM9dC5nZXRBdHRyaWJ1dGUoImlkIikpP2M9Yy5yZXBsYWNlKHRlLG5lKTp0LnNldEF0dHJpYnV0ZSgiaWQiLGM9Yikscz0oaD1hKGUpKS5sZW5ndGg7d2hpbGUocy0tKWhbc109IiMiK2MrIiAiK3ZlKGhbc10pO3Y9aC5qb2luKCIsIiksbT1LLnRlc3QoZSkmJmdlKHQucGFyZW50Tm9kZSl8fHR9aWYodil0cnl7cmV0dXJuIEwuYXBwbHkocixtLnF1ZXJ5U2VsZWN0b3JBbGwodikpLHJ9Y2F0Y2goZSl7fWZpbmFsbHl7Yz09PWImJnQucmVtb3ZlQXR0cmlidXRlKCJpZCIpfX19cmV0dXJuIHUoZS5yZXBsYWNlKEIsIiQxIiksdCxyLGkpfWZ1bmN0aW9uIGFlKCl7dmFyIGU9W107ZnVuY3Rpb24gdChuLGkpe3JldHVybiBlLnB1c2gobisiICIpPnIuY2FjaGVMZW5ndGgmJmRlbGV0ZSB0W2Uuc2hpZnQoKV0sdFtuKyIgIl09aX1yZXR1cm4gdH1mdW5jdGlvbiBzZShlKXtyZXR1cm4gZVtiXT0hMCxlfWZ1bmN0aW9uIHVlKGUpe3ZhciB0PWQuY3JlYXRlRWxlbWVudCgiZmllbGRzZXQiKTt0cnl7cmV0dXJuISFlKHQpfWNhdGNoKGUpe3JldHVybiExfWZpbmFsbHl7dC5wYXJlbnROb2RlJiZ0LnBhcmVudE5vZGUucmVtb3ZlQ2hpbGQodCksdD1udWxsfX1mdW5jdGlvbiBsZShlLHQpe3ZhciBuPWUuc3BsaXQoInwiKSxpPW4ubGVuZ3RoO3doaWxlKGktLSlyLmF0dHJIYW5kbGVbbltpXV09dH1mdW5jdGlvbiBjZShlLHQpe3ZhciBuPXQmJmUscj1uJiYxPT09ZS5ub2RlVHlwZSYmMT09PXQubm9kZVR5cGUmJmUuc291cmNlSW5kZXgtdC5zb3VyY2VJbmRleDtpZihyKXJldHVybiByO2lmKG4pd2hpbGUobj1uLm5leHRTaWJsaW5nKWlmKG49PT10KXJldHVybi0xO3JldHVybiBlPzE6LTF9ZnVuY3Rpb24gZmUoZSl7cmV0dXJuIGZ1bmN0aW9uKHQpe3JldHVybiJpbnB1dCI9PT10Lm5vZGVOYW1lLnRvTG93ZXJDYXNlKCkmJnQudHlwZT09PWV9fWZ1bmN0aW9uIHBlKGUpe3JldHVybiBmdW5jdGlvbih0KXt2YXIgbj10Lm5vZGVOYW1lLnRvTG93ZXJDYXNlKCk7cmV0dXJuKCJpbnB1dCI9PT1ufHwiYnV0dG9uIj09PW4pJiZ0LnR5cGU9PT1lfX1mdW5jdGlvbiBkZShlKXtyZXR1cm4gZnVuY3Rpb24odCl7cmV0dXJuImZvcm0iaW4gdD90LnBhcmVudE5vZGUmJiExPT09dC5kaXNhYmxlZD8ibGFiZWwiaW4gdD8ibGFiZWwiaW4gdC5wYXJlbnROb2RlP3QucGFyZW50Tm9kZS5kaXNhYmxlZD09PWU6dC5kaXNhYmxlZD09PWU6dC5pc0Rpc2FibGVkPT09ZXx8dC5pc0Rpc2FibGVkIT09IWUmJmllKHQpPT09ZTp0LmRpc2FibGVkPT09ZToibGFiZWwiaW4gdCYmdC5kaXNhYmxlZD09PWV9fWZ1bmN0aW9uIGhlKGUpe3JldHVybiBzZShmdW5jdGlvbih0KXtyZXR1cm4gdD0rdCxzZShmdW5jdGlvbihuLHIpe3ZhciBpLG89ZShbXSxuLmxlbmd0aCx0KSxhPW8ubGVuZ3RoO3doaWxlKGEtLSluW2k9b1thXV0mJihuW2ldPSEocltpXT1uW2ldKSl9KX0pfWZ1bmN0aW9uIGdlKGUpe3JldHVybiBlJiYidW5kZWZpbmVkIiE9dHlwZW9mIGUuZ2V0RWxlbWVudHNCeVRhZ05hbWUmJmV9bj1vZS5zdXBwb3J0PXt9LG89b2UuaXNYTUw9ZnVuY3Rpb24oZSl7dmFyIHQ9ZSYmKGUub3duZXJEb2N1bWVudHx8ZSkuZG9jdW1lbnRFbGVtZW50O3JldHVybiEhdCYmIkhUTUwiIT09dC5ub2RlTmFtZX0scD1vZS5zZXREb2N1bWVudD1mdW5jdGlvbihlKXt2YXIgdCxpLGE9ZT9lLm93bmVyRG9jdW1lbnR8fGU6dztyZXR1cm4gYSE9PWQmJjk9PT1hLm5vZGVUeXBlJiZhLmRvY3VtZW50RWxlbWVudD8oZD1hLGg9ZC5kb2N1bWVudEVsZW1lbnQsZz0hbyhkKSx3IT09ZCYmKGk9ZC5kZWZhdWx0VmlldykmJmkudG9wIT09aSYmKGkuYWRkRXZlbnRMaXN0ZW5lcj9pLmFkZEV2ZW50TGlzdGVuZXIoInVubG9hZCIscmUsITEpOmkuYXR0YWNoRXZlbnQmJmkuYXR0YWNoRXZlbnQoIm9udW5sb2FkIixyZSkpLG4uYXR0cmlidXRlcz11ZShmdW5jdGlvbihlKXtyZXR1cm4gZS5jbGFzc05hbWU9ImkiLCFlLmdldEF0dHJpYnV0ZSgiY2xhc3NOYW1lIil9KSxuLmdldEVsZW1lbnRzQnlUYWdOYW1lPXVlKGZ1bmN0aW9uKGUpe3JldHVybiBlLmFwcGVuZENoaWxkKGQuY3JlYXRlQ29tbWVudCgiIikpLCFlLmdldEVsZW1lbnRzQnlUYWdOYW1lKCIqIikubGVuZ3RofSksbi5nZXRFbGVtZW50c0J5Q2xhc3NOYW1lPVEudGVzdChkLmdldEVsZW1lbnRzQnlDbGFzc05hbWUpLG4uZ2V0QnlJZD11ZShmdW5jdGlvbihlKXtyZXR1cm4gaC5hcHBlbmRDaGlsZChlKS5pZD1iLCFkLmdldEVsZW1lbnRzQnlOYW1lfHwhZC5nZXRFbGVtZW50c0J5TmFtZShiKS5sZW5ndGh9KSxuLmdldEJ5SWQ/KHIuZmlsdGVyLklEPWZ1bmN0aW9uKGUpe3ZhciB0PWUucmVwbGFjZShaLGVlKTtyZXR1cm4gZnVuY3Rpb24oZSl7cmV0dXJuIGUuZ2V0QXR0cmlidXRlKCJpZCIpPT09dH19LHIuZmluZC5JRD1mdW5jdGlvbihlLHQpe2lmKCJ1bmRlZmluZWQiIT10eXBlb2YgdC5nZXRFbGVtZW50QnlJZCYmZyl7dmFyIG49dC5nZXRFbGVtZW50QnlJZChlKTtyZXR1cm4gbj9bbl06W119fSk6KHIuZmlsdGVyLklEPWZ1bmN0aW9uKGUpe3ZhciB0PWUucmVwbGFjZShaLGVlKTtyZXR1cm4gZnVuY3Rpb24oZSl7dmFyIG49InVuZGVmaW5lZCIhPXR5cGVvZiBlLmdldEF0dHJpYnV0ZU5vZGUmJmUuZ2V0QXR0cmlidXRlTm9kZSgiaWQiKTtyZXR1cm4gbiYmbi52YWx1ZT09PXR9fSxyLmZpbmQuSUQ9ZnVuY3Rpb24oZSx0KXtpZigidW5kZWZpbmVkIiE9dHlwZW9mIHQuZ2V0RWxlbWVudEJ5SWQmJmcpe3ZhciBuLHIsaSxvPXQuZ2V0RWxlbWVudEJ5SWQoZSk7aWYobyl7aWYoKG49by5nZXRBdHRyaWJ1dGVOb2RlKCJpZCIpKSYmbi52YWx1ZT09PWUpcmV0dXJuW29dO2k9dC5nZXRFbGVtZW50c0J5TmFtZShlKSxyPTA7d2hpbGUobz1pW3IrK10paWYoKG49by5nZXRBdHRyaWJ1dGVOb2RlKCJpZCIpKSYmbi52YWx1ZT09PWUpcmV0dXJuW29dfXJldHVybltdfX0pLHIuZmluZC5UQUc9bi5nZXRFbGVtZW50c0J5VGFnTmFtZT9mdW5jdGlvbihlLHQpe3JldHVybiJ1bmRlZmluZWQiIT10eXBlb2YgdC5nZXRFbGVtZW50c0J5VGFnTmFtZT90LmdldEVsZW1lbnRzQnlUYWdOYW1lKGUpOm4ucXNhP3QucXVlcnlTZWxlY3RvckFsbChlKTp2b2lkIDB9OmZ1bmN0aW9uKGUsdCl7dmFyIG4scj1bXSxpPTAsbz10LmdldEVsZW1lbnRzQnlUYWdOYW1lKGUpO2lmKCIqIj09PWUpe3doaWxlKG49b1tpKytdKTE9PT1uLm5vZGVUeXBlJiZyLnB1c2gobik7cmV0dXJuIHJ9cmV0dXJuIG99LHIuZmluZC5DTEFTUz1uLmdldEVsZW1lbnRzQnlDbGFzc05hbWUmJmZ1bmN0aW9uKGUsdCl7aWYoInVuZGVmaW5lZCIhPXR5cGVvZiB0LmdldEVsZW1lbnRzQnlDbGFzc05hbWUmJmcpcmV0dXJuIHQuZ2V0RWxlbWVudHNCeUNsYXNzTmFtZShlKX0sdj1bXSx5PVtdLChuLnFzYT1RLnRlc3QoZC5xdWVyeVNlbGVjdG9yQWxsKSkmJih1ZShmdW5jdGlvbihlKXtoLmFwcGVuZENoaWxkKGUpLmlubmVySFRNTD0iPGEgaWQ9JyIrYisiJz48L2E+PHNlbGVjdCBpZD0nIitiKyItXHJcXCcgbXNhbGxvd2NhcHR1cmU9Jyc+PG9wdGlvbiBzZWxlY3RlZD0nJz48L29wdGlvbj48L3NlbGVjdD4iLGUucXVlcnlTZWxlY3RvckFsbCgiW21zYWxsb3djYXB0dXJlXj0nJ10iKS5sZW5ndGgmJnkucHVzaCgiWypeJF09IitNKyIqKD86Jyd8XCJcIikiKSxlLnF1ZXJ5U2VsZWN0b3JBbGwoIltzZWxlY3RlZF0iKS5sZW5ndGh8fHkucHVzaCgiXFxbIitNKyIqKD86dmFsdWV8IitQKyIpIiksZS5xdWVyeVNlbGVjdG9yQWxsKCJbaWR+PSIrYisiLV0iKS5sZW5ndGh8fHkucHVzaCgifj0iKSxlLnF1ZXJ5U2VsZWN0b3JBbGwoIjpjaGVja2VkIikubGVuZ3RofHx5LnB1c2goIjpjaGVja2VkIiksZS5xdWVyeVNlbGVjdG9yQWxsKCJhIyIrYisiKyoiKS5sZW5ndGh8fHkucHVzaCgiLiMuK1srfl0iKX0pLHVlKGZ1bmN0aW9uKGUpe2UuaW5uZXJIVE1MPSI8YSBocmVmPScnIGRpc2FibGVkPSdkaXNhYmxlZCc+PC9hPjxzZWxlY3QgZGlzYWJsZWQ9J2Rpc2FibGVkJz48b3B0aW9uLz48L3NlbGVjdD4iO3ZhciB0PWQuY3JlYXRlRWxlbWVudCgiaW5wdXQiKTt0LnNldEF0dHJpYnV0ZSgidHlwZSIsImhpZGRlbiIpLGUuYXBwZW5kQ2hpbGQodCkuc2V0QXR0cmlidXRlKCJuYW1lIiwiRCIpLGUucXVlcnlTZWxlY3RvckFsbCgiW25hbWU9ZF0iKS5sZW5ndGgmJnkucHVzaCgibmFtZSIrTSsiKlsqXiR8IX5dPz0iKSwyIT09ZS5xdWVyeVNlbGVjdG9yQWxsKCI6ZW5hYmxlZCIpLmxlbmd0aCYmeS5wdXNoKCI6ZW5hYmxlZCIsIjpkaXNhYmxlZCIpLGguYXBwZW5kQ2hpbGQoZSkuZGlzYWJsZWQ9ITAsMiE9PWUucXVlcnlTZWxlY3RvckFsbCgiOmRpc2FibGVkIikubGVuZ3RoJiZ5LnB1c2goIjplbmFibGVkIiwiOmRpc2FibGVkIiksZS5xdWVyeVNlbGVjdG9yQWxsKCIqLDp4IikseS5wdXNoKCIsLio6Iil9KSksKG4ubWF0Y2hlc1NlbGVjdG9yPVEudGVzdChtPWgubWF0Y2hlc3x8aC53ZWJraXRNYXRjaGVzU2VsZWN0b3J8fGgubW96TWF0Y2hlc1NlbGVjdG9yfHxoLm9NYXRjaGVzU2VsZWN0b3J8fGgubXNNYXRjaGVzU2VsZWN0b3IpKSYmdWUoZnVuY3Rpb24oZSl7bi5kaXNjb25uZWN0ZWRNYXRjaD1tLmNhbGwoZSwiKiIpLG0uY2FsbChlLCJbcyE9JyddOngiKSx2LnB1c2goIiE9IixXKX0pLHk9eS5sZW5ndGgmJm5ldyBSZWdFeHAoeS5qb2luKCJ8IikpLHY9di5sZW5ndGgmJm5ldyBSZWdFeHAodi5qb2luKCJ8IikpLHQ9US50ZXN0KGguY29tcGFyZURvY3VtZW50UG9zaXRpb24pLHg9dHx8US50ZXN0KGguY29udGFpbnMpP2Z1bmN0aW9uKGUsdCl7dmFyIG49OT09PWUubm9kZVR5cGU/ZS5kb2N1bWVudEVsZW1lbnQ6ZSxyPXQmJnQucGFyZW50Tm9kZTtyZXR1cm4gZT09PXJ8fCEoIXJ8fDEhPT1yLm5vZGVUeXBlfHwhKG4uY29udGFpbnM/bi5jb250YWlucyhyKTplLmNvbXBhcmVEb2N1bWVudFBvc2l0aW9uJiYxNiZlLmNvbXBhcmVEb2N1bWVudFBvc2l0aW9uKHIpKSl9OmZ1bmN0aW9uKGUsdCl7aWYodCl3aGlsZSh0PXQucGFyZW50Tm9kZSlpZih0PT09ZSlyZXR1cm4hMDtyZXR1cm4hMX0sRD10P2Z1bmN0aW9uKGUsdCl7aWYoZT09PXQpcmV0dXJuIGY9ITAsMDt2YXIgcj0hZS5jb21wYXJlRG9jdW1lbnRQb3NpdGlvbi0hdC5jb21wYXJlRG9jdW1lbnRQb3NpdGlvbjtyZXR1cm4gcnx8KDEmKHI9KGUub3duZXJEb2N1bWVudHx8ZSk9PT0odC5vd25lckRvY3VtZW50fHx0KT9lLmNvbXBhcmVEb2N1bWVudFBvc2l0aW9uKHQpOjEpfHwhbi5zb3J0RGV0YWNoZWQmJnQuY29tcGFyZURvY3VtZW50UG9zaXRpb24oZSk9PT1yP2U9PT1kfHxlLm93bmVyRG9jdW1lbnQ9PT13JiZ4KHcsZSk/LTE6dD09PWR8fHQub3duZXJEb2N1bWVudD09PXcmJngodyx0KT8xOmM/TyhjLGUpLU8oYyx0KTowOjQmcj8tMToxKX06ZnVuY3Rpb24oZSx0KXtpZihlPT09dClyZXR1cm4gZj0hMCwwO3ZhciBuLHI9MCxpPWUucGFyZW50Tm9kZSxvPXQucGFyZW50Tm9kZSxhPVtlXSxzPVt0XTtpZighaXx8IW8pcmV0dXJuIGU9PT1kPy0xOnQ9PT1kPzE6aT8tMTpvPzE6Yz9PKGMsZSktTyhjLHQpOjA7aWYoaT09PW8pcmV0dXJuIGNlKGUsdCk7bj1lO3doaWxlKG49bi5wYXJlbnROb2RlKWEudW5zaGlmdChuKTtuPXQ7d2hpbGUobj1uLnBhcmVudE5vZGUpcy51bnNoaWZ0KG4pO3doaWxlKGFbcl09PT1zW3JdKXIrKztyZXR1cm4gcj9jZShhW3JdLHNbcl0pOmFbcl09PT13Py0xOnNbcl09PT13PzE6MH0sZCk6ZH0sb2UubWF0Y2hlcz1mdW5jdGlvbihlLHQpe3JldHVybiBvZShlLG51bGwsbnVsbCx0KX0sb2UubWF0Y2hlc1NlbGVjdG9yPWZ1bmN0aW9uKGUsdCl7aWYoKGUub3duZXJEb2N1bWVudHx8ZSkhPT1kJiZwKGUpLHQ9dC5yZXBsYWNlKHosIj0nJDEnXSIpLG4ubWF0Y2hlc1NlbGVjdG9yJiZnJiYhU1t0KyIgIl0mJighdnx8IXYudGVzdCh0KSkmJigheXx8IXkudGVzdCh0KSkpdHJ5e3ZhciByPW0uY2FsbChlLHQpO2lmKHJ8fG4uZGlzY29ubmVjdGVkTWF0Y2h8fGUuZG9jdW1lbnQmJjExIT09ZS5kb2N1bWVudC5ub2RlVHlwZSlyZXR1cm4gcn1jYXRjaChlKXt9cmV0dXJuIG9lKHQsZCxudWxsLFtlXSkubGVuZ3RoPjB9LG9lLmNvbnRhaW5zPWZ1bmN0aW9uKGUsdCl7cmV0dXJuKGUub3duZXJEb2N1bWVudHx8ZSkhPT1kJiZwKGUpLHgoZSx0KX0sb2UuYXR0cj1mdW5jdGlvbihlLHQpeyhlLm93bmVyRG9jdW1lbnR8fGUpIT09ZCYmcChlKTt2YXIgaT1yLmF0dHJIYW5kbGVbdC50b0xvd2VyQ2FzZSgpXSxvPWkmJk4uY2FsbChyLmF0dHJIYW5kbGUsdC50b0xvd2VyQ2FzZSgpKT9pKGUsdCwhZyk6dm9pZCAwO3JldHVybiB2b2lkIDAhPT1vP286bi5hdHRyaWJ1dGVzfHwhZz9lLmdldEF0dHJpYnV0ZSh0KToobz1lLmdldEF0dHJpYnV0ZU5vZGUodCkpJiZvLnNwZWNpZmllZD9vLnZhbHVlOm51bGx9LG9lLmVzY2FwZT1mdW5jdGlvbihlKXtyZXR1cm4oZSsiIikucmVwbGFjZSh0ZSxuZSl9LG9lLmVycm9yPWZ1bmN0aW9uKGUpe3Rocm93IG5ldyBFcnJvcigiU3ludGF4IGVycm9yLCB1bnJlY29nbml6ZWQgZXhwcmVzc2lvbjogIitlKX0sb2UudW5pcXVlU29ydD1mdW5jdGlvbihlKXt2YXIgdCxyPVtdLGk9MCxvPTA7aWYoZj0hbi5kZXRlY3REdXBsaWNhdGVzLGM9IW4uc29ydFN0YWJsZSYmZS5zbGljZSgwKSxlLnNvcnQoRCksZil7d2hpbGUodD1lW28rK10pdD09PWVbb10mJihpPXIucHVzaChvKSk7d2hpbGUoaS0tKWUuc3BsaWNlKHJbaV0sMSl9cmV0dXJuIGM9bnVsbCxlfSxpPW9lLmdldFRleHQ9ZnVuY3Rpb24oZSl7dmFyIHQsbj0iIixyPTAsbz1lLm5vZGVUeXBlO2lmKG8pe2lmKDE9PT1vfHw5PT09b3x8MTE9PT1vKXtpZigic3RyaW5nIj09dHlwZW9mIGUudGV4dENvbnRlbnQpcmV0dXJuIGUudGV4dENvbnRlbnQ7Zm9yKGU9ZS5maXJzdENoaWxkO2U7ZT1lLm5leHRTaWJsaW5nKW4rPWkoZSl9ZWxzZSBpZigzPT09b3x8ND09PW8pcmV0dXJuIGUubm9kZVZhbHVlfWVsc2Ugd2hpbGUodD1lW3IrK10pbis9aSh0KTtyZXR1cm4gbn0sKHI9b2Uuc2VsZWN0b3JzPXtjYWNoZUxlbmd0aDo1MCxjcmVhdGVQc2V1ZG86c2UsbWF0Y2g6VixhdHRySGFuZGxlOnt9LGZpbmQ6e30scmVsYXRpdmU6eyI+Ijp7ZGlyOiJwYXJlbnROb2RlIixmaXJzdDohMH0sIiAiOntkaXI6InBhcmVudE5vZGUifSwiKyI6e2RpcjoicHJldmlvdXNTaWJsaW5nIixmaXJzdDohMH0sIn4iOntkaXI6InByZXZpb3VzU2libGluZyJ9fSxwcmVGaWx0ZXI6e0FUVFI6ZnVuY3Rpb24oZSl7cmV0dXJuIGVbMV09ZVsxXS5yZXBsYWNlKFosZWUpLGVbM109KGVbM118fGVbNF18fGVbNV18fCIiKS5yZXBsYWNlKFosZWUpLCJ+PSI9PT1lWzJdJiYoZVszXT0iICIrZVszXSsiICIpLGUuc2xpY2UoMCw0KX0sQ0hJTEQ6ZnVuY3Rpb24oZSl7cmV0dXJuIGVbMV09ZVsxXS50b0xvd2VyQ2FzZSgpLCJudGgiPT09ZVsxXS5zbGljZSgwLDMpPyhlWzNdfHxvZS5lcnJvcihlWzBdKSxlWzRdPSsoZVs0XT9lWzVdKyhlWzZdfHwxKToyKigiZXZlbiI9PT1lWzNdfHwib2RkIj09PWVbM10pKSxlWzVdPSsoZVs3XStlWzhdfHwib2RkIj09PWVbM10pKTplWzNdJiZvZS5lcnJvcihlWzBdKSxlfSxQU0VVRE86ZnVuY3Rpb24oZSl7dmFyIHQsbj0hZVs2XSYmZVsyXTtyZXR1cm4gVi5DSElMRC50ZXN0KGVbMF0pP251bGw6KGVbM10/ZVsyXT1lWzRdfHxlWzVdfHwiIjpuJiZYLnRlc3QobikmJih0PWEobiwhMCkpJiYodD1uLmluZGV4T2YoIikiLG4ubGVuZ3RoLXQpLW4ubGVuZ3RoKSYmKGVbMF09ZVswXS5zbGljZSgwLHQpLGVbMl09bi5zbGljZSgwLHQpKSxlLnNsaWNlKDAsMykpfX0sZmlsdGVyOntUQUc6ZnVuY3Rpb24oZSl7dmFyIHQ9ZS5yZXBsYWNlKFosZWUpLnRvTG93ZXJDYXNlKCk7cmV0dXJuIioiPT09ZT9mdW5jdGlvbigpe3JldHVybiEwfTpmdW5jdGlvbihlKXtyZXR1cm4gZS5ub2RlTmFtZSYmZS5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpPT09dH19LENMQVNTOmZ1bmN0aW9uKGUpe3ZhciB0PUVbZSsiICJdO3JldHVybiB0fHwodD1uZXcgUmVnRXhwKCIoXnwiK00rIikiK2UrIigiK00rInwkKSIpKSYmRShlLGZ1bmN0aW9uKGUpe3JldHVybiB0LnRlc3QoInN0cmluZyI9PXR5cGVvZiBlLmNsYXNzTmFtZSYmZS5jbGFzc05hbWV8fCJ1bmRlZmluZWQiIT10eXBlb2YgZS5nZXRBdHRyaWJ1dGUmJmUuZ2V0QXR0cmlidXRlKCJjbGFzcyIpfHwiIil9KX0sQVRUUjpmdW5jdGlvbihlLHQsbil7cmV0dXJuIGZ1bmN0aW9uKHIpe3ZhciBpPW9lLmF0dHIocixlKTtyZXR1cm4gbnVsbD09aT8iIT0iPT09dDohdHx8KGkrPSIiLCI9Ij09PXQ/aT09PW46IiE9Ij09PXQ/aSE9PW46Il49Ij09PXQ/biYmMD09PWkuaW5kZXhPZihuKToiKj0iPT09dD9uJiZpLmluZGV4T2Yobik+LTE6IiQ9Ij09PXQ/biYmaS5zbGljZSgtbi5sZW5ndGgpPT09bjoifj0iPT09dD8oIiAiK2kucmVwbGFjZSgkLCIgIikrIiAiKS5pbmRleE9mKG4pPi0xOiJ8PSI9PT10JiYoaT09PW58fGkuc2xpY2UoMCxuLmxlbmd0aCsxKT09PW4rIi0iKSl9fSxDSElMRDpmdW5jdGlvbihlLHQsbixyLGkpe3ZhciBvPSJudGgiIT09ZS5zbGljZSgwLDMpLGE9Imxhc3QiIT09ZS5zbGljZSgtNCkscz0ib2YtdHlwZSI9PT10O3JldHVybiAxPT09ciYmMD09PWk/ZnVuY3Rpb24oZSl7cmV0dXJuISFlLnBhcmVudE5vZGV9OmZ1bmN0aW9uKHQsbix1KXt2YXIgbCxjLGYscCxkLGgsZz1vIT09YT8ibmV4dFNpYmxpbmciOiJwcmV2aW91c1NpYmxpbmciLHk9dC5wYXJlbnROb2RlLHY9cyYmdC5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpLG09IXUmJiFzLHg9ITE7aWYoeSl7aWYobyl7d2hpbGUoZyl7cD10O3doaWxlKHA9cFtnXSlpZihzP3Aubm9kZU5hbWUudG9Mb3dlckNhc2UoKT09PXY6MT09PXAubm9kZVR5cGUpcmV0dXJuITE7aD1nPSJvbmx5Ij09PWUmJiFoJiYibmV4dFNpYmxpbmcifXJldHVybiEwfWlmKGg9W2E/eS5maXJzdENoaWxkOnkubGFzdENoaWxkXSxhJiZtKXt4PShkPShsPShjPShmPShwPXkpW2JdfHwocFtiXT17fSkpW3AudW5pcXVlSURdfHwoZltwLnVuaXF1ZUlEXT17fSkpW2VdfHxbXSlbMF09PT1UJiZsWzFdKSYmbFsyXSxwPWQmJnkuY2hpbGROb2Rlc1tkXTt3aGlsZShwPSsrZCYmcCYmcFtnXXx8KHg9ZD0wKXx8aC5wb3AoKSlpZigxPT09cC5ub2RlVHlwZSYmKyt4JiZwPT09dCl7Y1tlXT1bVCxkLHhdO2JyZWFrfX1lbHNlIGlmKG0mJih4PWQ9KGw9KGM9KGY9KHA9dClbYl18fChwW2JdPXt9KSlbcC51bmlxdWVJRF18fChmW3AudW5pcXVlSURdPXt9KSlbZV18fFtdKVswXT09PVQmJmxbMV0pLCExPT09eCl3aGlsZShwPSsrZCYmcCYmcFtnXXx8KHg9ZD0wKXx8aC5wb3AoKSlpZigocz9wLm5vZGVOYW1lLnRvTG93ZXJDYXNlKCk9PT12OjE9PT1wLm5vZGVUeXBlKSYmKyt4JiYobSYmKChjPShmPXBbYl18fChwW2JdPXt9KSlbcC51bmlxdWVJRF18fChmW3AudW5pcXVlSURdPXt9KSlbZV09W1QseF0pLHA9PT10KSlicmVhaztyZXR1cm4oeC09aSk9PT1yfHx4JXI9PTAmJngvcj49MH19fSxQU0VVRE86ZnVuY3Rpb24oZSx0KXt2YXIgbixpPXIucHNldWRvc1tlXXx8ci5zZXRGaWx0ZXJzW2UudG9Mb3dlckNhc2UoKV18fG9lLmVycm9yKCJ1bnN1cHBvcnRlZCBwc2V1ZG86ICIrZSk7cmV0dXJuIGlbYl0/aSh0KTppLmxlbmd0aD4xPyhuPVtlLGUsIiIsdF0sci5zZXRGaWx0ZXJzLmhhc093blByb3BlcnR5KGUudG9Mb3dlckNhc2UoKSk/c2UoZnVuY3Rpb24oZSxuKXt2YXIgcixvPWkoZSx0KSxhPW8ubGVuZ3RoO3doaWxlKGEtLSllW3I9TyhlLG9bYV0pXT0hKG5bcl09b1thXSl9KTpmdW5jdGlvbihlKXtyZXR1cm4gaShlLDAsbil9KTppfX0scHNldWRvczp7bm90OnNlKGZ1bmN0aW9uKGUpe3ZhciB0PVtdLG49W10scj1zKGUucmVwbGFjZShCLCIkMSIpKTtyZXR1cm4gcltiXT9zZShmdW5jdGlvbihlLHQsbixpKXt2YXIgbyxhPXIoZSxudWxsLGksW10pLHM9ZS5sZW5ndGg7d2hpbGUocy0tKShvPWFbc10pJiYoZVtzXT0hKHRbc109bykpfSk6ZnVuY3Rpb24oZSxpLG8pe3JldHVybiB0WzBdPWUscih0LG51bGwsbyxuKSx0WzBdPW51bGwsIW4ucG9wKCl9fSksaGFzOnNlKGZ1bmN0aW9uKGUpe3JldHVybiBmdW5jdGlvbih0KXtyZXR1cm4gb2UoZSx0KS5sZW5ndGg+MH19KSxjb250YWluczpzZShmdW5jdGlvbihlKXtyZXR1cm4gZT1lLnJlcGxhY2UoWixlZSksZnVuY3Rpb24odCl7cmV0dXJuKHQudGV4dENvbnRlbnR8fHQuaW5uZXJUZXh0fHxpKHQpKS5pbmRleE9mKGUpPi0xfX0pLGxhbmc6c2UoZnVuY3Rpb24oZSl7cmV0dXJuIFUudGVzdChlfHwiIil8fG9lLmVycm9yKCJ1bnN1cHBvcnRlZCBsYW5nOiAiK2UpLGU9ZS5yZXBsYWNlKFosZWUpLnRvTG93ZXJDYXNlKCksZnVuY3Rpb24odCl7dmFyIG47ZG97aWYobj1nP3QubGFuZzp0LmdldEF0dHJpYnV0ZSgieG1sOmxhbmciKXx8dC5nZXRBdHRyaWJ1dGUoImxhbmciKSlyZXR1cm4obj1uLnRvTG93ZXJDYXNlKCkpPT09ZXx8MD09PW4uaW5kZXhPZihlKyItIil9d2hpbGUoKHQ9dC5wYXJlbnROb2RlKSYmMT09PXQubm9kZVR5cGUpO3JldHVybiExfX0pLHRhcmdldDpmdW5jdGlvbih0KXt2YXIgbj1lLmxvY2F0aW9uJiZlLmxvY2F0aW9uLmhhc2g7cmV0dXJuIG4mJm4uc2xpY2UoMSk9PT10LmlkfSxyb290OmZ1bmN0aW9uKGUpe3JldHVybiBlPT09aH0sZm9jdXM6ZnVuY3Rpb24oZSl7cmV0dXJuIGU9PT1kLmFjdGl2ZUVsZW1lbnQmJighZC5oYXNGb2N1c3x8ZC5oYXNGb2N1cygpKSYmISEoZS50eXBlfHxlLmhyZWZ8fH5lLnRhYkluZGV4KX0sZW5hYmxlZDpkZSghMSksZGlzYWJsZWQ6ZGUoITApLGNoZWNrZWQ6ZnVuY3Rpb24oZSl7dmFyIHQ9ZS5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpO3JldHVybiJpbnB1dCI9PT10JiYhIWUuY2hlY2tlZHx8Im9wdGlvbiI9PT10JiYhIWUuc2VsZWN0ZWR9LHNlbGVjdGVkOmZ1bmN0aW9uKGUpe3JldHVybiBlLnBhcmVudE5vZGUmJmUucGFyZW50Tm9kZS5zZWxlY3RlZEluZGV4LCEwPT09ZS5zZWxlY3RlZH0sZW1wdHk6ZnVuY3Rpb24oZSl7Zm9yKGU9ZS5maXJzdENoaWxkO2U7ZT1lLm5leHRTaWJsaW5nKWlmKGUubm9kZVR5cGU8NilyZXR1cm4hMTtyZXR1cm4hMH0scGFyZW50OmZ1bmN0aW9uKGUpe3JldHVybiFyLnBzZXVkb3MuZW1wdHkoZSl9LGhlYWRlcjpmdW5jdGlvbihlKXtyZXR1cm4gWS50ZXN0KGUubm9kZU5hbWUpfSxpbnB1dDpmdW5jdGlvbihlKXtyZXR1cm4gRy50ZXN0KGUubm9kZU5hbWUpfSxidXR0b246ZnVuY3Rpb24oZSl7dmFyIHQ9ZS5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpO3JldHVybiJpbnB1dCI9PT10JiYiYnV0dG9uIj09PWUudHlwZXx8ImJ1dHRvbiI9PT10fSx0ZXh0OmZ1bmN0aW9uKGUpe3ZhciB0O3JldHVybiJpbnB1dCI9PT1lLm5vZGVOYW1lLnRvTG93ZXJDYXNlKCkmJiJ0ZXh0Ij09PWUudHlwZSYmKG51bGw9PSh0PWUuZ2V0QXR0cmlidXRlKCJ0eXBlIikpfHwidGV4dCI9PT10LnRvTG93ZXJDYXNlKCkpfSxmaXJzdDpoZShmdW5jdGlvbigpe3JldHVyblswXX0pLGxhc3Q6aGUoZnVuY3Rpb24oZSx0KXtyZXR1cm5bdC0xXX0pLGVxOmhlKGZ1bmN0aW9uKGUsdCxuKXtyZXR1cm5bbjwwP24rdDpuXX0pLGV2ZW46aGUoZnVuY3Rpb24oZSx0KXtmb3IodmFyIG49MDtuPHQ7bis9MillLnB1c2gobik7cmV0dXJuIGV9KSxvZGQ6aGUoZnVuY3Rpb24oZSx0KXtmb3IodmFyIG49MTtuPHQ7bis9MillLnB1c2gobik7cmV0dXJuIGV9KSxsdDpoZShmdW5jdGlvbihlLHQsbil7Zm9yKHZhciByPW48MD9uK3Q6bjstLXI+PTA7KWUucHVzaChyKTtyZXR1cm4gZX0pLGd0OmhlKGZ1bmN0aW9uKGUsdCxuKXtmb3IodmFyIHI9bjwwP24rdDpuOysrcjx0OyllLnB1c2gocik7cmV0dXJuIGV9KX19KS5wc2V1ZG9zLm50aD1yLnBzZXVkb3MuZXE7Zm9yKHQgaW57cmFkaW86ITAsY2hlY2tib3g6ITAsZmlsZTohMCxwYXNzd29yZDohMCxpbWFnZTohMH0pci5wc2V1ZG9zW3RdPWZlKHQpO2Zvcih0IGlue3N1Ym1pdDohMCxyZXNldDohMH0pci5wc2V1ZG9zW3RdPXBlKHQpO2Z1bmN0aW9uIHllKCl7fXllLnByb3RvdHlwZT1yLmZpbHRlcnM9ci5wc2V1ZG9zLHIuc2V0RmlsdGVycz1uZXcgeWUsYT1vZS50b2tlbml6ZT1mdW5jdGlvbihlLHQpe3ZhciBuLGksbyxhLHMsdSxsLGM9a1tlKyIgIl07aWYoYylyZXR1cm4gdD8wOmMuc2xpY2UoMCk7cz1lLHU9W10sbD1yLnByZUZpbHRlcjt3aGlsZShzKXtuJiYhKGk9Ri5leGVjKHMpKXx8KGkmJihzPXMuc2xpY2UoaVswXS5sZW5ndGgpfHxzKSx1LnB1c2gobz1bXSkpLG49ITEsKGk9Xy5leGVjKHMpKSYmKG49aS5zaGlmdCgpLG8ucHVzaCh7dmFsdWU6bix0eXBlOmlbMF0ucmVwbGFjZShCLCIgIil9KSxzPXMuc2xpY2Uobi5sZW5ndGgpKTtmb3IoYSBpbiByLmZpbHRlcikhKGk9VlthXS5leGVjKHMpKXx8bFthXSYmIShpPWxbYV0oaSkpfHwobj1pLnNoaWZ0KCksby5wdXNoKHt2YWx1ZTpuLHR5cGU6YSxtYXRjaGVzOml9KSxzPXMuc2xpY2Uobi5sZW5ndGgpKTtpZighbilicmVha31yZXR1cm4gdD9zLmxlbmd0aDpzP29lLmVycm9yKGUpOmsoZSx1KS5zbGljZSgwKX07ZnVuY3Rpb24gdmUoZSl7Zm9yKHZhciB0PTAsbj1lLmxlbmd0aCxyPSIiO3Q8bjt0Kyspcis9ZVt0XS52YWx1ZTtyZXR1cm4gcn1mdW5jdGlvbiBtZShlLHQsbil7dmFyIHI9dC5kaXIsaT10Lm5leHQsbz1pfHxyLGE9biYmInBhcmVudE5vZGUiPT09byxzPUMrKztyZXR1cm4gdC5maXJzdD9mdW5jdGlvbih0LG4saSl7d2hpbGUodD10W3JdKWlmKDE9PT10Lm5vZGVUeXBlfHxhKXJldHVybiBlKHQsbixpKTtyZXR1cm4hMX06ZnVuY3Rpb24odCxuLHUpe3ZhciBsLGMsZixwPVtULHNdO2lmKHUpe3doaWxlKHQ9dFtyXSlpZigoMT09PXQubm9kZVR5cGV8fGEpJiZlKHQsbix1KSlyZXR1cm4hMH1lbHNlIHdoaWxlKHQ9dFtyXSlpZigxPT09dC5ub2RlVHlwZXx8YSlpZihmPXRbYl18fCh0W2JdPXt9KSxjPWZbdC51bmlxdWVJRF18fChmW3QudW5pcXVlSURdPXt9KSxpJiZpPT09dC5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpKXQ9dFtyXXx8dDtlbHNle2lmKChsPWNbb10pJiZsWzBdPT09VCYmbFsxXT09PXMpcmV0dXJuIHBbMl09bFsyXTtpZihjW29dPXAscFsyXT1lKHQsbix1KSlyZXR1cm4hMH1yZXR1cm4hMX19ZnVuY3Rpb24geGUoZSl7cmV0dXJuIGUubGVuZ3RoPjE/ZnVuY3Rpb24odCxuLHIpe3ZhciBpPWUubGVuZ3RoO3doaWxlKGktLSlpZighZVtpXSh0LG4scikpcmV0dXJuITE7cmV0dXJuITB9OmVbMF19ZnVuY3Rpb24gYmUoZSx0LG4pe2Zvcih2YXIgcj0wLGk9dC5sZW5ndGg7cjxpO3IrKylvZShlLHRbcl0sbik7cmV0dXJuIG59ZnVuY3Rpb24gd2UoZSx0LG4scixpKXtmb3IodmFyIG8sYT1bXSxzPTAsdT1lLmxlbmd0aCxsPW51bGwhPXQ7czx1O3MrKykobz1lW3NdKSYmKG4mJiFuKG8scixpKXx8KGEucHVzaChvKSxsJiZ0LnB1c2gocykpKTtyZXR1cm4gYX1mdW5jdGlvbiBUZShlLHQsbixyLGksbyl7cmV0dXJuIHImJiFyW2JdJiYocj1UZShyKSksaSYmIWlbYl0mJihpPVRlKGksbykpLHNlKGZ1bmN0aW9uKG8sYSxzLHUpe3ZhciBsLGMsZixwPVtdLGQ9W10saD1hLmxlbmd0aCxnPW98fGJlKHR8fCIqIixzLm5vZGVUeXBlP1tzXTpzLFtdKSx5PSFlfHwhbyYmdD9nOndlKGcscCxlLHMsdSksdj1uP2l8fChvP2U6aHx8cik/W106YTp5O2lmKG4mJm4oeSx2LHMsdSkscil7bD13ZSh2LGQpLHIobCxbXSxzLHUpLGM9bC5sZW5ndGg7d2hpbGUoYy0tKShmPWxbY10pJiYodltkW2NdXT0hKHlbZFtjXV09ZikpfWlmKG8pe2lmKGl8fGUpe2lmKGkpe2w9W10sYz12Lmxlbmd0aDt3aGlsZShjLS0pKGY9dltjXSkmJmwucHVzaCh5W2NdPWYpO2kobnVsbCx2PVtdLGwsdSl9Yz12Lmxlbmd0aDt3aGlsZShjLS0pKGY9dltjXSkmJihsPWk/TyhvLGYpOnBbY10pPi0xJiYob1tsXT0hKGFbbF09ZikpfX1lbHNlIHY9d2Uodj09PWE/di5zcGxpY2UoaCx2Lmxlbmd0aCk6diksaT9pKG51bGwsYSx2LHUpOkwuYXBwbHkoYSx2KX0pfWZ1bmN0aW9uIENlKGUpe2Zvcih2YXIgdCxuLGksbz1lLmxlbmd0aCxhPXIucmVsYXRpdmVbZVswXS50eXBlXSxzPWF8fHIucmVsYXRpdmVbIiAiXSx1PWE/MTowLGM9bWUoZnVuY3Rpb24oZSl7cmV0dXJuIGU9PT10fSxzLCEwKSxmPW1lKGZ1bmN0aW9uKGUpe3JldHVybiBPKHQsZSk+LTF9LHMsITApLHA9W2Z1bmN0aW9uKGUsbixyKXt2YXIgaT0hYSYmKHJ8fG4hPT1sKXx8KCh0PW4pLm5vZGVUeXBlP2MoZSxuLHIpOmYoZSxuLHIpKTtyZXR1cm4gdD1udWxsLGl9XTt1PG87dSsrKWlmKG49ci5yZWxhdGl2ZVtlW3VdLnR5cGVdKXA9W21lKHhlKHApLG4pXTtlbHNle2lmKChuPXIuZmlsdGVyW2VbdV0udHlwZV0uYXBwbHkobnVsbCxlW3VdLm1hdGNoZXMpKVtiXSl7Zm9yKGk9Kyt1O2k8bztpKyspaWYoci5yZWxhdGl2ZVtlW2ldLnR5cGVdKWJyZWFrO3JldHVybiBUZSh1PjEmJnhlKHApLHU+MSYmdmUoZS5zbGljZSgwLHUtMSkuY29uY2F0KHt2YWx1ZToiICI9PT1lW3UtMl0udHlwZT8iKiI6IiJ9KSkucmVwbGFjZShCLCIkMSIpLG4sdTxpJiZDZShlLnNsaWNlKHUsaSkpLGk8byYmQ2UoZT1lLnNsaWNlKGkpKSxpPG8mJnZlKGUpKX1wLnB1c2gobil9cmV0dXJuIHhlKHApfWZ1bmN0aW9uIEVlKGUsdCl7dmFyIG49dC5sZW5ndGg+MCxpPWUubGVuZ3RoPjAsbz1mdW5jdGlvbihvLGEscyx1LGMpe3ZhciBmLGgseSx2PTAsbT0iMCIseD1vJiZbXSxiPVtdLHc9bCxDPW98fGkmJnIuZmluZC5UQUcoIioiLGMpLEU9VCs9bnVsbD09dz8xOk1hdGgucmFuZG9tKCl8fC4xLGs9Qy5sZW5ndGg7Zm9yKGMmJihsPWE9PT1kfHxhfHxjKTttIT09ayYmbnVsbCE9KGY9Q1ttXSk7bSsrKXtpZihpJiZmKXtoPTAsYXx8Zi5vd25lckRvY3VtZW50PT09ZHx8KHAoZikscz0hZyk7d2hpbGUoeT1lW2grK10paWYoeShmLGF8fGQscykpe3UucHVzaChmKTticmVha31jJiYoVD1FKX1uJiYoKGY9IXkmJmYpJiZ2LS0sbyYmeC5wdXNoKGYpKX1pZih2Kz1tLG4mJm0hPT12KXtoPTA7d2hpbGUoeT10W2grK10peSh4LGIsYSxzKTtpZihvKXtpZih2PjApd2hpbGUobS0tKXhbbV18fGJbbV18fChiW21dPWouY2FsbCh1KSk7Yj13ZShiKX1MLmFwcGx5KHUsYiksYyYmIW8mJmIubGVuZ3RoPjAmJnYrdC5sZW5ndGg+MSYmb2UudW5pcXVlU29ydCh1KX1yZXR1cm4gYyYmKFQ9RSxsPXcpLHh9O3JldHVybiBuP3NlKG8pOm99cmV0dXJuIHM9b2UuY29tcGlsZT1mdW5jdGlvbihlLHQpe3ZhciBuLHI9W10saT1bXSxvPVNbZSsiICJdO2lmKCFvKXt0fHwodD1hKGUpKSxuPXQubGVuZ3RoO3doaWxlKG4tLSkobz1DZSh0W25dKSlbYl0/ci5wdXNoKG8pOmkucHVzaChvKTsobz1TKGUsRWUoaSxyKSkpLnNlbGVjdG9yPWV9cmV0dXJuIG99LHU9b2Uuc2VsZWN0PWZ1bmN0aW9uKGUsdCxuLGkpe3ZhciBvLHUsbCxjLGYscD0iZnVuY3Rpb24iPT10eXBlb2YgZSYmZSxkPSFpJiZhKGU9cC5zZWxlY3Rvcnx8ZSk7aWYobj1ufHxbXSwxPT09ZC5sZW5ndGgpe2lmKCh1PWRbMF09ZFswXS5zbGljZSgwKSkubGVuZ3RoPjImJiJJRCI9PT0obD11WzBdKS50eXBlJiY5PT09dC5ub2RlVHlwZSYmZyYmci5yZWxhdGl2ZVt1WzFdLnR5cGVdKXtpZighKHQ9KHIuZmluZC5JRChsLm1hdGNoZXNbMF0ucmVwbGFjZShaLGVlKSx0KXx8W10pWzBdKSlyZXR1cm4gbjtwJiYodD10LnBhcmVudE5vZGUpLGU9ZS5zbGljZSh1LnNoaWZ0KCkudmFsdWUubGVuZ3RoKX1vPVYubmVlZHNDb250ZXh0LnRlc3QoZSk/MDp1Lmxlbmd0aDt3aGlsZShvLS0pe2lmKGw9dVtvXSxyLnJlbGF0aXZlW2M9bC50eXBlXSlicmVhaztpZigoZj1yLmZpbmRbY10pJiYoaT1mKGwubWF0Y2hlc1swXS5yZXBsYWNlKFosZWUpLEsudGVzdCh1WzBdLnR5cGUpJiZnZSh0LnBhcmVudE5vZGUpfHx0KSkpe2lmKHUuc3BsaWNlKG8sMSksIShlPWkubGVuZ3RoJiZ2ZSh1KSkpcmV0dXJuIEwuYXBwbHkobixpKSxuO2JyZWFrfX19cmV0dXJuKHB8fHMoZSxkKSkoaSx0LCFnLG4sIXR8fEsudGVzdChlKSYmZ2UodC5wYXJlbnROb2RlKXx8dCksbn0sbi5zb3J0U3RhYmxlPWIuc3BsaXQoIiIpLnNvcnQoRCkuam9pbigiIik9PT1iLG4uZGV0ZWN0RHVwbGljYXRlcz0hIWYscCgpLG4uc29ydERldGFjaGVkPXVlKGZ1bmN0aW9uKGUpe3JldHVybiAxJmUuY29tcGFyZURvY3VtZW50UG9zaXRpb24oZC5jcmVhdGVFbGVtZW50KCJmaWVsZHNldCIpKX0pLHVlKGZ1bmN0aW9uKGUpe3JldHVybiBlLmlubmVySFRNTD0iPGEgaHJlZj0nIyc+PC9hPiIsIiMiPT09ZS5maXJzdENoaWxkLmdldEF0dHJpYnV0ZSgiaHJlZiIpfSl8fGxlKCJ0eXBlfGhyZWZ8aGVpZ2h0fHdpZHRoIixmdW5jdGlvbihlLHQsbil7aWYoIW4pcmV0dXJuIGUuZ2V0QXR0cmlidXRlKHQsInR5cGUiPT09dC50b0xvd2VyQ2FzZSgpPzE6Mil9KSxuLmF0dHJpYnV0ZXMmJnVlKGZ1bmN0aW9uKGUpe3JldHVybiBlLmlubmVySFRNTD0iPGlucHV0Lz4iLGUuZmlyc3RDaGlsZC5zZXRBdHRyaWJ1dGUoInZhbHVlIiwiIiksIiI9PT1lLmZpcnN0Q2hpbGQuZ2V0QXR0cmlidXRlKCJ2YWx1ZSIpfSl8fGxlKCJ2YWx1ZSIsZnVuY3Rpb24oZSx0LG4pe2lmKCFuJiYiaW5wdXQiPT09ZS5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpKXJldHVybiBlLmRlZmF1bHRWYWx1ZX0pLHVlKGZ1bmN0aW9uKGUpe3JldHVybiBudWxsPT1lLmdldEF0dHJpYnV0ZSgiZGlzYWJsZWQiKX0pfHxsZShQLGZ1bmN0aW9uKGUsdCxuKXt2YXIgcjtpZighbilyZXR1cm4hMD09PWVbdF0/dC50b0xvd2VyQ2FzZSgpOihyPWUuZ2V0QXR0cmlidXRlTm9kZSh0KSkmJnIuc3BlY2lmaWVkP3IudmFsdWU6bnVsbH0pLG9lfShlKTt3LmZpbmQ9RSx3LmV4cHI9RS5zZWxlY3RvcnMsdy5leHByWyI6Il09dy5leHByLnBzZXVkb3Msdy51bmlxdWVTb3J0PXcudW5pcXVlPUUudW5pcXVlU29ydCx3LnRleHQ9RS5nZXRUZXh0LHcuaXNYTUxEb2M9RS5pc1hNTCx3LmNvbnRhaW5zPUUuY29udGFpbnMsdy5lc2NhcGVTZWxlY3Rvcj1FLmVzY2FwZTt2YXIgaz1mdW5jdGlvbihlLHQsbil7dmFyIHI9W10saT12b2lkIDAhPT1uO3doaWxlKChlPWVbdF0pJiY5IT09ZS5ub2RlVHlwZSlpZigxPT09ZS5ub2RlVHlwZSl7aWYoaSYmdyhlKS5pcyhuKSlicmVhaztyLnB1c2goZSl9cmV0dXJuIHJ9LFM9ZnVuY3Rpb24oZSx0KXtmb3IodmFyIG49W107ZTtlPWUubmV4dFNpYmxpbmcpMT09PWUubm9kZVR5cGUmJmUhPT10JiZuLnB1c2goZSk7cmV0dXJuIG59LEQ9dy5leHByLm1hdGNoLm5lZWRzQ29udGV4dDtmdW5jdGlvbiBOKGUsdCl7cmV0dXJuIGUubm9kZU5hbWUmJmUubm9kZU5hbWUudG9Mb3dlckNhc2UoKT09PXQudG9Mb3dlckNhc2UoKX12YXIgQT0vXjwoW2Etel1bXlwvXDA+Olx4MjBcdFxyXG5cZl0qKVtceDIwXHRcclxuXGZdKlwvPz4oPzo8XC9cMT58KSQvaTtmdW5jdGlvbiBqKGUsdCxuKXtyZXR1cm4gZyh0KT93LmdyZXAoZSxmdW5jdGlvbihlLHIpe3JldHVybiEhdC5jYWxsKGUscixlKSE9PW59KTp0Lm5vZGVUeXBlP3cuZ3JlcChlLGZ1bmN0aW9uKGUpe3JldHVybiBlPT09dCE9PW59KToic3RyaW5nIiE9dHlwZW9mIHQ/dy5ncmVwKGUsZnVuY3Rpb24oZSl7cmV0dXJuIHUuY2FsbCh0LGUpPi0xIT09bn0pOncuZmlsdGVyKHQsZSxuKX13LmZpbHRlcj1mdW5jdGlvbihlLHQsbil7dmFyIHI9dFswXTtyZXR1cm4gbiYmKGU9Ijpub3QoIitlKyIpIiksMT09PXQubGVuZ3RoJiYxPT09ci5ub2RlVHlwZT93LmZpbmQubWF0Y2hlc1NlbGVjdG9yKHIsZSk/W3JdOltdOncuZmluZC5tYXRjaGVzKGUsdy5ncmVwKHQsZnVuY3Rpb24oZSl7cmV0dXJuIDE9PT1lLm5vZGVUeXBlfSkpfSx3LmZuLmV4dGVuZCh7ZmluZDpmdW5jdGlvbihlKXt2YXIgdCxuLHI9dGhpcy5sZW5ndGgsaT10aGlzO2lmKCJzdHJpbmciIT10eXBlb2YgZSlyZXR1cm4gdGhpcy5wdXNoU3RhY2sodyhlKS5maWx0ZXIoZnVuY3Rpb24oKXtmb3IodD0wO3Q8cjt0KyspaWYody5jb250YWlucyhpW3RdLHRoaXMpKXJldHVybiEwfSkpO2ZvcihuPXRoaXMucHVzaFN0YWNrKFtdKSx0PTA7dDxyO3QrKyl3LmZpbmQoZSxpW3RdLG4pO3JldHVybiByPjE/dy51bmlxdWVTb3J0KG4pOm59LGZpbHRlcjpmdW5jdGlvbihlKXtyZXR1cm4gdGhpcy5wdXNoU3RhY2soaih0aGlzLGV8fFtdLCExKSl9LG5vdDpmdW5jdGlvbihlKXtyZXR1cm4gdGhpcy5wdXNoU3RhY2soaih0aGlzLGV8fFtdLCEwKSl9LGlzOmZ1bmN0aW9uKGUpe3JldHVybiEhaih0aGlzLCJzdHJpbmciPT10eXBlb2YgZSYmRC50ZXN0KGUpP3coZSk6ZXx8W10sITEpLmxlbmd0aH19KTt2YXIgcSxMPS9eKD86XHMqKDxbXHdcV10rPilbXj5dKnwjKFtcdy1dKykpJC87KHcuZm4uaW5pdD1mdW5jdGlvbihlLHQsbil7dmFyIGksbztpZighZSlyZXR1cm4gdGhpcztpZihuPW58fHEsInN0cmluZyI9PXR5cGVvZiBlKXtpZighKGk9IjwiPT09ZVswXSYmIj4iPT09ZVtlLmxlbmd0aC0xXSYmZS5sZW5ndGg+PTM/W251bGwsZSxudWxsXTpMLmV4ZWMoZSkpfHwhaVsxXSYmdClyZXR1cm4hdHx8dC5qcXVlcnk/KHR8fG4pLmZpbmQoZSk6dGhpcy5jb25zdHJ1Y3Rvcih0KS5maW5kKGUpO2lmKGlbMV0pe2lmKHQ9dCBpbnN0YW5jZW9mIHc/dFswXTp0LHcubWVyZ2UodGhpcyx3LnBhcnNlSFRNTChpWzFdLHQmJnQubm9kZVR5cGU/dC5vd25lckRvY3VtZW50fHx0OnIsITApKSxBLnRlc3QoaVsxXSkmJncuaXNQbGFpbk9iamVjdCh0KSlmb3IoaSBpbiB0KWcodGhpc1tpXSk/dGhpc1tpXSh0W2ldKTp0aGlzLmF0dHIoaSx0W2ldKTtyZXR1cm4gdGhpc31yZXR1cm4obz1yLmdldEVsZW1lbnRCeUlkKGlbMl0pKSYmKHRoaXNbMF09byx0aGlzLmxlbmd0aD0xKSx0aGlzfXJldHVybiBlLm5vZGVUeXBlPyh0aGlzWzBdPWUsdGhpcy5sZW5ndGg9MSx0aGlzKTpnKGUpP3ZvaWQgMCE9PW4ucmVhZHk/bi5yZWFkeShlKTplKHcpOncubWFrZUFycmF5KGUsdGhpcyl9KS5wcm90b3R5cGU9dy5mbixxPXcocik7dmFyIEg9L14oPzpwYXJlbnRzfHByZXYoPzpVbnRpbHxBbGwpKS8sTz17Y2hpbGRyZW46ITAsY29udGVudHM6ITAsbmV4dDohMCxwcmV2OiEwfTt3LmZuLmV4dGVuZCh7aGFzOmZ1bmN0aW9uKGUpe3ZhciB0PXcoZSx0aGlzKSxuPXQubGVuZ3RoO3JldHVybiB0aGlzLmZpbHRlcihmdW5jdGlvbigpe2Zvcih2YXIgZT0wO2U8bjtlKyspaWYody5jb250YWlucyh0aGlzLHRbZV0pKXJldHVybiEwfSl9LGNsb3Nlc3Q6ZnVuY3Rpb24oZSx0KXt2YXIgbixyPTAsaT10aGlzLmxlbmd0aCxvPVtdLGE9InN0cmluZyIhPXR5cGVvZiBlJiZ3KGUpO2lmKCFELnRlc3QoZSkpZm9yKDtyPGk7cisrKWZvcihuPXRoaXNbcl07biYmbiE9PXQ7bj1uLnBhcmVudE5vZGUpaWYobi5ub2RlVHlwZTwxMSYmKGE/YS5pbmRleChuKT4tMToxPT09bi5ub2RlVHlwZSYmdy5maW5kLm1hdGNoZXNTZWxlY3RvcihuLGUpKSl7by5wdXNoKG4pO2JyZWFrfXJldHVybiB0aGlzLnB1c2hTdGFjayhvLmxlbmd0aD4xP3cudW5pcXVlU29ydChvKTpvKX0saW5kZXg6ZnVuY3Rpb24oZSl7cmV0dXJuIGU/InN0cmluZyI9PXR5cGVvZiBlP3UuY2FsbCh3KGUpLHRoaXNbMF0pOnUuY2FsbCh0aGlzLGUuanF1ZXJ5P2VbMF06ZSk6dGhpc1swXSYmdGhpc1swXS5wYXJlbnROb2RlP3RoaXMuZmlyc3QoKS5wcmV2QWxsKCkubGVuZ3RoOi0xfSxhZGQ6ZnVuY3Rpb24oZSx0KXtyZXR1cm4gdGhpcy5wdXNoU3RhY2sody51bmlxdWVTb3J0KHcubWVyZ2UodGhpcy5nZXQoKSx3KGUsdCkpKSl9LGFkZEJhY2s6ZnVuY3Rpb24oZSl7cmV0dXJuIHRoaXMuYWRkKG51bGw9PWU/dGhpcy5wcmV2T2JqZWN0OnRoaXMucHJldk9iamVjdC5maWx0ZXIoZSkpfX0pO2Z1bmN0aW9uIFAoZSx0KXt3aGlsZSgoZT1lW3RdKSYmMSE9PWUubm9kZVR5cGUpO3JldHVybiBlfXcuZWFjaCh7cGFyZW50OmZ1bmN0aW9uKGUpe3ZhciB0PWUucGFyZW50Tm9kZTtyZXR1cm4gdCYmMTEhPT10Lm5vZGVUeXBlP3Q6bnVsbH0scGFyZW50czpmdW5jdGlvbihlKXtyZXR1cm4gayhlLCJwYXJlbnROb2RlIil9LHBhcmVudHNVbnRpbDpmdW5jdGlvbihlLHQsbil7cmV0dXJuIGsoZSwicGFyZW50Tm9kZSIsbil9LG5leHQ6ZnVuY3Rpb24oZSl7cmV0dXJuIFAoZSwibmV4dFNpYmxpbmciKX0scHJldjpmdW5jdGlvbihlKXtyZXR1cm4gUChlLCJwcmV2aW91c1NpYmxpbmciKX0sbmV4dEFsbDpmdW5jdGlvbihlKXtyZXR1cm4gayhlLCJuZXh0U2libGluZyIpfSxwcmV2QWxsOmZ1bmN0aW9uKGUpe3JldHVybiBrKGUsInByZXZpb3VzU2libGluZyIpfSxuZXh0VW50aWw6ZnVuY3Rpb24oZSx0LG4pe3JldHVybiBrKGUsIm5leHRTaWJsaW5nIixuKX0scHJldlVudGlsOmZ1bmN0aW9uKGUsdCxuKXtyZXR1cm4gayhlLCJwcmV2aW91c1NpYmxpbmciLG4pfSxzaWJsaW5nczpmdW5jdGlvbihlKXtyZXR1cm4gUygoZS5wYXJlbnROb2RlfHx7fSkuZmlyc3RDaGlsZCxlKX0sY2hpbGRyZW46ZnVuY3Rpb24oZSl7cmV0dXJuIFMoZS5maXJzdENoaWxkKX0sY29udGVudHM6ZnVuY3Rpb24oZSl7cmV0dXJuIE4oZSwiaWZyYW1lIik/ZS5jb250ZW50RG9jdW1lbnQ6KE4oZSwidGVtcGxhdGUiKSYmKGU9ZS5jb250ZW50fHxlKSx3Lm1lcmdlKFtdLGUuY2hpbGROb2RlcykpfX0sZnVuY3Rpb24oZSx0KXt3LmZuW2VdPWZ1bmN0aW9uKG4scil7dmFyIGk9dy5tYXAodGhpcyx0LG4pO3JldHVybiJVbnRpbCIhPT1lLnNsaWNlKC01KSYmKHI9biksciYmInN0cmluZyI9PXR5cGVvZiByJiYoaT13LmZpbHRlcihyLGkpKSx0aGlzLmxlbmd0aD4xJiYoT1tlXXx8dy51bmlxdWVTb3J0KGkpLEgudGVzdChlKSYmaS5yZXZlcnNlKCkpLHRoaXMucHVzaFN0YWNrKGkpfX0pO3ZhciBNPS9bXlx4MjBcdFxyXG5cZl0rL2c7ZnVuY3Rpb24gUihlKXt2YXIgdD17fTtyZXR1cm4gdy5lYWNoKGUubWF0Y2goTSl8fFtdLGZ1bmN0aW9uKGUsbil7dFtuXT0hMH0pLHR9dy5DYWxsYmFja3M9ZnVuY3Rpb24oZSl7ZT0ic3RyaW5nIj09dHlwZW9mIGU/UihlKTp3LmV4dGVuZCh7fSxlKTt2YXIgdCxuLHIsaSxvPVtdLGE9W10scz0tMSx1PWZ1bmN0aW9uKCl7Zm9yKGk9aXx8ZS5vbmNlLHI9dD0hMDthLmxlbmd0aDtzPS0xKXtuPWEuc2hpZnQoKTt3aGlsZSgrK3M8by5sZW5ndGgpITE9PT1vW3NdLmFwcGx5KG5bMF0sblsxXSkmJmUuc3RvcE9uRmFsc2UmJihzPW8ubGVuZ3RoLG49ITEpfWUubWVtb3J5fHwobj0hMSksdD0hMSxpJiYobz1uP1tdOiIiKX0sbD17YWRkOmZ1bmN0aW9uKCl7cmV0dXJuIG8mJihuJiYhdCYmKHM9by5sZW5ndGgtMSxhLnB1c2gobikpLGZ1bmN0aW9uIHQobil7dy5lYWNoKG4sZnVuY3Rpb24obixyKXtnKHIpP2UudW5pcXVlJiZsLmhhcyhyKXx8by5wdXNoKHIpOnImJnIubGVuZ3RoJiYic3RyaW5nIiE9PXgocikmJnQocil9KX0oYXJndW1lbnRzKSxuJiYhdCYmdSgpKSx0aGlzfSxyZW1vdmU6ZnVuY3Rpb24oKXtyZXR1cm4gdy5lYWNoKGFyZ3VtZW50cyxmdW5jdGlvbihlLHQpe3ZhciBuO3doaWxlKChuPXcuaW5BcnJheSh0LG8sbikpPi0xKW8uc3BsaWNlKG4sMSksbjw9cyYmcy0tfSksdGhpc30saGFzOmZ1bmN0aW9uKGUpe3JldHVybiBlP3cuaW5BcnJheShlLG8pPi0xOm8ubGVuZ3RoPjB9LGVtcHR5OmZ1bmN0aW9uKCl7cmV0dXJuIG8mJihvPVtdKSx0aGlzfSxkaXNhYmxlOmZ1bmN0aW9uKCl7cmV0dXJuIGk9YT1bXSxvPW49IiIsdGhpc30sZGlzYWJsZWQ6ZnVuY3Rpb24oKXtyZXR1cm4hb30sbG9jazpmdW5jdGlvbigpe3JldHVybiBpPWE9W10sbnx8dHx8KG89bj0iIiksdGhpc30sbG9ja2VkOmZ1bmN0aW9uKCl7cmV0dXJuISFpfSxmaXJlV2l0aDpmdW5jdGlvbihlLG4pe3JldHVybiBpfHwobj1bZSwobj1ufHxbXSkuc2xpY2U/bi5zbGljZSgpOm5dLGEucHVzaChuKSx0fHx1KCkpLHRoaXN9LGZpcmU6ZnVuY3Rpb24oKXtyZXR1cm4gbC5maXJlV2l0aCh0aGlzLGFyZ3VtZW50cyksdGhpc30sZmlyZWQ6ZnVuY3Rpb24oKXtyZXR1cm4hIXJ9fTtyZXR1cm4gbH07ZnVuY3Rpb24gSShlKXtyZXR1cm4gZX1mdW5jdGlvbiBXKGUpe3Rocm93IGV9ZnVuY3Rpb24gJChlLHQsbixyKXt2YXIgaTt0cnl7ZSYmZyhpPWUucHJvbWlzZSk/aS5jYWxsKGUpLmRvbmUodCkuZmFpbChuKTplJiZnKGk9ZS50aGVuKT9pLmNhbGwoZSx0LG4pOnQuYXBwbHkodm9pZCAwLFtlXS5zbGljZShyKSl9Y2F0Y2goZSl7bi5hcHBseSh2b2lkIDAsW2VdKX19dy5leHRlbmQoe0RlZmVycmVkOmZ1bmN0aW9uKHQpe3ZhciBuPVtbIm5vdGlmeSIsInByb2dyZXNzIix3LkNhbGxiYWNrcygibWVtb3J5Iiksdy5DYWxsYmFja3MoIm1lbW9yeSIpLDJdLFsicmVzb2x2ZSIsImRvbmUiLHcuQ2FsbGJhY2tzKCJvbmNlIG1lbW9yeSIpLHcuQ2FsbGJhY2tzKCJvbmNlIG1lbW9yeSIpLDAsInJlc29sdmVkIl0sWyJyZWplY3QiLCJmYWlsIix3LkNhbGxiYWNrcygib25jZSBtZW1vcnkiKSx3LkNhbGxiYWNrcygib25jZSBtZW1vcnkiKSwxLCJyZWplY3RlZCJdXSxyPSJwZW5kaW5nIixpPXtzdGF0ZTpmdW5jdGlvbigpe3JldHVybiByfSxhbHdheXM6ZnVuY3Rpb24oKXtyZXR1cm4gby5kb25lKGFyZ3VtZW50cykuZmFpbChhcmd1bWVudHMpLHRoaXN9LCJjYXRjaCI6ZnVuY3Rpb24oZSl7cmV0dXJuIGkudGhlbihudWxsLGUpfSxwaXBlOmZ1bmN0aW9uKCl7dmFyIGU9YXJndW1lbnRzO3JldHVybiB3LkRlZmVycmVkKGZ1bmN0aW9uKHQpe3cuZWFjaChuLGZ1bmN0aW9uKG4scil7dmFyIGk9ZyhlW3JbNF1dKSYmZVtyWzRdXTtvW3JbMV1dKGZ1bmN0aW9uKCl7dmFyIGU9aSYmaS5hcHBseSh0aGlzLGFyZ3VtZW50cyk7ZSYmZyhlLnByb21pc2UpP2UucHJvbWlzZSgpLnByb2dyZXNzKHQubm90aWZ5KS5kb25lKHQucmVzb2x2ZSkuZmFpbCh0LnJlamVjdCk6dFtyWzBdKyJXaXRoIl0odGhpcyxpP1tlXTphcmd1bWVudHMpfSl9KSxlPW51bGx9KS5wcm9taXNlKCl9LHRoZW46ZnVuY3Rpb24odCxyLGkpe3ZhciBvPTA7ZnVuY3Rpb24gYSh0LG4scixpKXtyZXR1cm4gZnVuY3Rpb24oKXt2YXIgcz10aGlzLHU9YXJndW1lbnRzLGw9ZnVuY3Rpb24oKXt2YXIgZSxsO2lmKCEodDxvKSl7aWYoKGU9ci5hcHBseShzLHUpKT09PW4ucHJvbWlzZSgpKXRocm93IG5ldyBUeXBlRXJyb3IoIlRoZW5hYmxlIHNlbGYtcmVzb2x1dGlvbiIpO2w9ZSYmKCJvYmplY3QiPT10eXBlb2YgZXx8ImZ1bmN0aW9uIj09dHlwZW9mIGUpJiZlLnRoZW4sZyhsKT9pP2wuY2FsbChlLGEobyxuLEksaSksYShvLG4sVyxpKSk6KG8rKyxsLmNhbGwoZSxhKG8sbixJLGkpLGEobyxuLFcsaSksYShvLG4sSSxuLm5vdGlmeVdpdGgpKSk6KHIhPT1JJiYocz12b2lkIDAsdT1bZV0pLChpfHxuLnJlc29sdmVXaXRoKShzLHUpKX19LGM9aT9sOmZ1bmN0aW9uKCl7dHJ5e2woKX1jYXRjaChlKXt3LkRlZmVycmVkLmV4Y2VwdGlvbkhvb2smJncuRGVmZXJyZWQuZXhjZXB0aW9uSG9vayhlLGMuc3RhY2tUcmFjZSksdCsxPj1vJiYociE9PVcmJihzPXZvaWQgMCx1PVtlXSksbi5yZWplY3RXaXRoKHMsdSkpfX07dD9jKCk6KHcuRGVmZXJyZWQuZ2V0U3RhY2tIb29rJiYoYy5zdGFja1RyYWNlPXcuRGVmZXJyZWQuZ2V0U3RhY2tIb29rKCkpLGUuc2V0VGltZW91dChjKSl9fXJldHVybiB3LkRlZmVycmVkKGZ1bmN0aW9uKGUpe25bMF1bM10uYWRkKGEoMCxlLGcoaSk/aTpJLGUubm90aWZ5V2l0aCkpLG5bMV1bM10uYWRkKGEoMCxlLGcodCk/dDpJKSksblsyXVszXS5hZGQoYSgwLGUsZyhyKT9yOlcpKX0pLnByb21pc2UoKX0scHJvbWlzZTpmdW5jdGlvbihlKXtyZXR1cm4gbnVsbCE9ZT93LmV4dGVuZChlLGkpOml9fSxvPXt9O3JldHVybiB3LmVhY2gobixmdW5jdGlvbihlLHQpe3ZhciBhPXRbMl0scz10WzVdO2lbdFsxXV09YS5hZGQscyYmYS5hZGQoZnVuY3Rpb24oKXtyPXN9LG5bMy1lXVsyXS5kaXNhYmxlLG5bMy1lXVszXS5kaXNhYmxlLG5bMF1bMl0ubG9jayxuWzBdWzNdLmxvY2spLGEuYWRkKHRbM10uZmlyZSksb1t0WzBdXT1mdW5jdGlvbigpe3JldHVybiBvW3RbMF0rIldpdGgiXSh0aGlzPT09bz92b2lkIDA6dGhpcyxhcmd1bWVudHMpLHRoaXN9LG9bdFswXSsiV2l0aCJdPWEuZmlyZVdpdGh9KSxpLnByb21pc2UobyksdCYmdC5jYWxsKG8sbyksb30sd2hlbjpmdW5jdGlvbihlKXt2YXIgdD1hcmd1bWVudHMubGVuZ3RoLG49dCxyPUFycmF5KG4pLGk9by5jYWxsKGFyZ3VtZW50cyksYT13LkRlZmVycmVkKCkscz1mdW5jdGlvbihlKXtyZXR1cm4gZnVuY3Rpb24obil7cltlXT10aGlzLGlbZV09YXJndW1lbnRzLmxlbmd0aD4xP28uY2FsbChhcmd1bWVudHMpOm4sLS10fHxhLnJlc29sdmVXaXRoKHIsaSl9fTtpZih0PD0xJiYoJChlLGEuZG9uZShzKG4pKS5yZXNvbHZlLGEucmVqZWN0LCF0KSwicGVuZGluZyI9PT1hLnN0YXRlKCl8fGcoaVtuXSYmaVtuXS50aGVuKSkpcmV0dXJuIGEudGhlbigpO3doaWxlKG4tLSkkKGlbbl0scyhuKSxhLnJlamVjdCk7cmV0dXJuIGEucHJvbWlzZSgpfX0pO3ZhciBCPS9eKEV2YWx8SW50ZXJuYWx8UmFuZ2V8UmVmZXJlbmNlfFN5bnRheHxUeXBlfFVSSSlFcnJvciQvO3cuRGVmZXJyZWQuZXhjZXB0aW9uSG9vaz1mdW5jdGlvbih0LG4pe2UuY29uc29sZSYmZS5jb25zb2xlLndhcm4mJnQmJkIudGVzdCh0Lm5hbWUpJiZlLmNvbnNvbGUud2FybigialF1ZXJ5LkRlZmVycmVkIGV4Y2VwdGlvbjogIit0Lm1lc3NhZ2UsdC5zdGFjayxuKX0sdy5yZWFkeUV4Y2VwdGlvbj1mdW5jdGlvbih0KXtlLnNldFRpbWVvdXQoZnVuY3Rpb24oKXt0aHJvdyB0fSl9O3ZhciBGPXcuRGVmZXJyZWQoKTt3LmZuLnJlYWR5PWZ1bmN0aW9uKGUpe3JldHVybiBGLnRoZW4oZSlbImNhdGNoIl0oZnVuY3Rpb24oZSl7dy5yZWFkeUV4Y2VwdGlvbihlKX0pLHRoaXN9LHcuZXh0ZW5kKHtpc1JlYWR5OiExLHJlYWR5V2FpdDoxLHJlYWR5OmZ1bmN0aW9uKGUpeyghMD09PWU/LS13LnJlYWR5V2FpdDp3LmlzUmVhZHkpfHwody5pc1JlYWR5PSEwLCEwIT09ZSYmLS13LnJlYWR5V2FpdD4wfHxGLnJlc29sdmVXaXRoKHIsW3ddKSl9fSksdy5yZWFkeS50aGVuPUYudGhlbjtmdW5jdGlvbiBfKCl7ci5yZW1vdmVFdmVudExpc3RlbmVyKCJET01Db250ZW50TG9hZGVkIixfKSxlLnJlbW92ZUV2ZW50TGlzdGVuZXIoImxvYWQiLF8pLHcucmVhZHkoKX0iY29tcGxldGUiPT09ci5yZWFkeVN0YXRlfHwibG9hZGluZyIhPT1yLnJlYWR5U3RhdGUmJiFyLmRvY3VtZW50RWxlbWVudC5kb1Njcm9sbD9lLnNldFRpbWVvdXQody5yZWFkeSk6KHIuYWRkRXZlbnRMaXN0ZW5lcigiRE9NQ29udGVudExvYWRlZCIsXyksZS5hZGRFdmVudExpc3RlbmVyKCJsb2FkIixfKSk7dmFyIHo9ZnVuY3Rpb24oZSx0LG4scixpLG8sYSl7dmFyIHM9MCx1PWUubGVuZ3RoLGw9bnVsbD09bjtpZigib2JqZWN0Ij09PXgobikpe2k9ITA7Zm9yKHMgaW4gbil6KGUsdCxzLG5bc10sITAsbyxhKX1lbHNlIGlmKHZvaWQgMCE9PXImJihpPSEwLGcocil8fChhPSEwKSxsJiYoYT8odC5jYWxsKGUsciksdD1udWxsKToobD10LHQ9ZnVuY3Rpb24oZSx0LG4pe3JldHVybiBsLmNhbGwodyhlKSxuKX0pKSx0KSlmb3IoO3M8dTtzKyspdChlW3NdLG4sYT9yOnIuY2FsbChlW3NdLHMsdChlW3NdLG4pKSk7cmV0dXJuIGk/ZTpsP3QuY2FsbChlKTp1P3QoZVswXSxuKTpvfSxYPS9eLW1zLS8sVT0vLShbYS16XSkvZztmdW5jdGlvbiBWKGUsdCl7cmV0dXJuIHQudG9VcHBlckNhc2UoKX1mdW5jdGlvbiBHKGUpe3JldHVybiBlLnJlcGxhY2UoWCwibXMtIikucmVwbGFjZShVLFYpfXZhciBZPWZ1bmN0aW9uKGUpe3JldHVybiAxPT09ZS5ub2RlVHlwZXx8OT09PWUubm9kZVR5cGV8fCErZS5ub2RlVHlwZX07ZnVuY3Rpb24gUSgpe3RoaXMuZXhwYW5kbz13LmV4cGFuZG8rUS51aWQrK31RLnVpZD0xLFEucHJvdG90eXBlPXtjYWNoZTpmdW5jdGlvbihlKXt2YXIgdD1lW3RoaXMuZXhwYW5kb107cmV0dXJuIHR8fCh0PXt9LFkoZSkmJihlLm5vZGVUeXBlP2VbdGhpcy5leHBhbmRvXT10Ok9iamVjdC5kZWZpbmVQcm9wZXJ0eShlLHRoaXMuZXhwYW5kbyx7dmFsdWU6dCxjb25maWd1cmFibGU6ITB9KSkpLHR9LHNldDpmdW5jdGlvbihlLHQsbil7dmFyIHIsaT10aGlzLmNhY2hlKGUpO2lmKCJzdHJpbmciPT10eXBlb2YgdClpW0codCldPW47ZWxzZSBmb3IociBpbiB0KWlbRyhyKV09dFtyXTtyZXR1cm4gaX0sZ2V0OmZ1bmN0aW9uKGUsdCl7cmV0dXJuIHZvaWQgMD09PXQ/dGhpcy5jYWNoZShlKTplW3RoaXMuZXhwYW5kb10mJmVbdGhpcy5leHBhbmRvXVtHKHQpXX0sYWNjZXNzOmZ1bmN0aW9uKGUsdCxuKXtyZXR1cm4gdm9pZCAwPT09dHx8dCYmInN0cmluZyI9PXR5cGVvZiB0JiZ2b2lkIDA9PT1uP3RoaXMuZ2V0KGUsdCk6KHRoaXMuc2V0KGUsdCxuKSx2b2lkIDAhPT1uP246dCl9LHJlbW92ZTpmdW5jdGlvbihlLHQpe3ZhciBuLHI9ZVt0aGlzLmV4cGFuZG9dO2lmKHZvaWQgMCE9PXIpe2lmKHZvaWQgMCE9PXQpe249KHQ9QXJyYXkuaXNBcnJheSh0KT90Lm1hcChHKToodD1HKHQpKWluIHI/W3RdOnQubWF0Y2goTSl8fFtdKS5sZW5ndGg7d2hpbGUobi0tKWRlbGV0ZSByW3Rbbl1dfSh2b2lkIDA9PT10fHx3LmlzRW1wdHlPYmplY3QocikpJiYoZS5ub2RlVHlwZT9lW3RoaXMuZXhwYW5kb109dm9pZCAwOmRlbGV0ZSBlW3RoaXMuZXhwYW5kb10pfX0saGFzRGF0YTpmdW5jdGlvbihlKXt2YXIgdD1lW3RoaXMuZXhwYW5kb107cmV0dXJuIHZvaWQgMCE9PXQmJiF3LmlzRW1wdHlPYmplY3QodCl9fTt2YXIgSj1uZXcgUSxLPW5ldyBRLFo9L14oPzpce1tcd1xXXSpcfXxcW1tcd1xXXSpcXSkkLyxlZT0vW0EtWl0vZztmdW5jdGlvbiB0ZShlKXtyZXR1cm4idHJ1ZSI9PT1lfHwiZmFsc2UiIT09ZSYmKCJudWxsIj09PWU/bnVsbDplPT09K2UrIiI/K2U6Wi50ZXN0KGUpP0pTT04ucGFyc2UoZSk6ZSl9ZnVuY3Rpb24gbmUoZSx0LG4pe3ZhciByO2lmKHZvaWQgMD09PW4mJjE9PT1lLm5vZGVUeXBlKWlmKHI9ImRhdGEtIit0LnJlcGxhY2UoZWUsIi0kJiIpLnRvTG93ZXJDYXNlKCksInN0cmluZyI9PXR5cGVvZihuPWUuZ2V0QXR0cmlidXRlKHIpKSl7dHJ5e249dGUobil9Y2F0Y2goZSl7fUsuc2V0KGUsdCxuKX1lbHNlIG49dm9pZCAwO3JldHVybiBufXcuZXh0ZW5kKHtoYXNEYXRhOmZ1bmN0aW9uKGUpe3JldHVybiBLLmhhc0RhdGEoZSl8fEouaGFzRGF0YShlKX0sZGF0YTpmdW5jdGlvbihlLHQsbil7cmV0dXJuIEsuYWNjZXNzKGUsdCxuKX0scmVtb3ZlRGF0YTpmdW5jdGlvbihlLHQpe0sucmVtb3ZlKGUsdCl9LF9kYXRhOmZ1bmN0aW9uKGUsdCxuKXtyZXR1cm4gSi5hY2Nlc3MoZSx0LG4pfSxfcmVtb3ZlRGF0YTpmdW5jdGlvbihlLHQpe0oucmVtb3ZlKGUsdCl9fSksdy5mbi5leHRlbmQoe2RhdGE6ZnVuY3Rpb24oZSx0KXt2YXIgbixyLGksbz10aGlzWzBdLGE9byYmby5hdHRyaWJ1dGVzO2lmKHZvaWQgMD09PWUpe2lmKHRoaXMubGVuZ3RoJiYoaT1LLmdldChvKSwxPT09by5ub2RlVHlwZSYmIUouZ2V0KG8sImhhc0RhdGFBdHRycyIpKSl7bj1hLmxlbmd0aDt3aGlsZShuLS0pYVtuXSYmMD09PShyPWFbbl0ubmFtZSkuaW5kZXhPZigiZGF0YS0iKSYmKHI9RyhyLnNsaWNlKDUpKSxuZShvLHIsaVtyXSkpO0ouc2V0KG8sImhhc0RhdGFBdHRycyIsITApfXJldHVybiBpfXJldHVybiJvYmplY3QiPT10eXBlb2YgZT90aGlzLmVhY2goZnVuY3Rpb24oKXtLLnNldCh0aGlzLGUpfSk6eih0aGlzLGZ1bmN0aW9uKHQpe3ZhciBuO2lmKG8mJnZvaWQgMD09PXQpe2lmKHZvaWQgMCE9PShuPUsuZ2V0KG8sZSkpKXJldHVybiBuO2lmKHZvaWQgMCE9PShuPW5lKG8sZSkpKXJldHVybiBufWVsc2UgdGhpcy5lYWNoKGZ1bmN0aW9uKCl7Sy5zZXQodGhpcyxlLHQpfSl9LG51bGwsdCxhcmd1bWVudHMubGVuZ3RoPjEsbnVsbCwhMCl9LHJlbW92ZURhdGE6ZnVuY3Rpb24oZSl7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe0sucmVtb3ZlKHRoaXMsZSl9KX19KSx3LmV4dGVuZCh7cXVldWU6ZnVuY3Rpb24oZSx0LG4pe3ZhciByO2lmKGUpcmV0dXJuIHQ9KHR8fCJmeCIpKyJxdWV1ZSIscj1KLmdldChlLHQpLG4mJighcnx8QXJyYXkuaXNBcnJheShuKT9yPUouYWNjZXNzKGUsdCx3Lm1ha2VBcnJheShuKSk6ci5wdXNoKG4pKSxyfHxbXX0sZGVxdWV1ZTpmdW5jdGlvbihlLHQpe3Q9dHx8ImZ4Ijt2YXIgbj13LnF1ZXVlKGUsdCkscj1uLmxlbmd0aCxpPW4uc2hpZnQoKSxvPXcuX3F1ZXVlSG9va3MoZSx0KSxhPWZ1bmN0aW9uKCl7dy5kZXF1ZXVlKGUsdCl9OyJpbnByb2dyZXNzIj09PWkmJihpPW4uc2hpZnQoKSxyLS0pLGkmJigiZngiPT09dCYmbi51bnNoaWZ0KCJpbnByb2dyZXNzIiksZGVsZXRlIG8uc3RvcCxpLmNhbGwoZSxhLG8pKSwhciYmbyYmby5lbXB0eS5maXJlKCl9LF9xdWV1ZUhvb2tzOmZ1bmN0aW9uKGUsdCl7dmFyIG49dCsicXVldWVIb29rcyI7cmV0dXJuIEouZ2V0KGUsbil8fEouYWNjZXNzKGUsbix7ZW1wdHk6dy5DYWxsYmFja3MoIm9uY2UgbWVtb3J5IikuYWRkKGZ1bmN0aW9uKCl7Si5yZW1vdmUoZSxbdCsicXVldWUiLG5dKX0pfSl9fSksdy5mbi5leHRlbmQoe3F1ZXVlOmZ1bmN0aW9uKGUsdCl7dmFyIG49MjtyZXR1cm4ic3RyaW5nIiE9dHlwZW9mIGUmJih0PWUsZT0iZngiLG4tLSksYXJndW1lbnRzLmxlbmd0aDxuP3cucXVldWUodGhpc1swXSxlKTp2b2lkIDA9PT10P3RoaXM6dGhpcy5lYWNoKGZ1bmN0aW9uKCl7dmFyIG49dy5xdWV1ZSh0aGlzLGUsdCk7dy5fcXVldWVIb29rcyh0aGlzLGUpLCJmeCI9PT1lJiYiaW5wcm9ncmVzcyIhPT1uWzBdJiZ3LmRlcXVldWUodGhpcyxlKX0pfSxkZXF1ZXVlOmZ1bmN0aW9uKGUpe3JldHVybiB0aGlzLmVhY2goZnVuY3Rpb24oKXt3LmRlcXVldWUodGhpcyxlKX0pfSxjbGVhclF1ZXVlOmZ1bmN0aW9uKGUpe3JldHVybiB0aGlzLnF1ZXVlKGV8fCJmeCIsW10pfSxwcm9taXNlOmZ1bmN0aW9uKGUsdCl7dmFyIG4scj0xLGk9dy5EZWZlcnJlZCgpLG89dGhpcyxhPXRoaXMubGVuZ3RoLHM9ZnVuY3Rpb24oKXstLXJ8fGkucmVzb2x2ZVdpdGgobyxbb10pfTsic3RyaW5nIiE9dHlwZW9mIGUmJih0PWUsZT12b2lkIDApLGU9ZXx8ImZ4Ijt3aGlsZShhLS0pKG49Si5nZXQob1thXSxlKyJxdWV1ZUhvb2tzIikpJiZuLmVtcHR5JiYocisrLG4uZW1wdHkuYWRkKHMpKTtyZXR1cm4gcygpLGkucHJvbWlzZSh0KX19KTt2YXIgcmU9L1srLV0/KD86XGQqXC58KVxkKyg/OltlRV1bKy1dP1xkK3wpLy5zb3VyY2UsaWU9bmV3IFJlZ0V4cCgiXig/OihbKy1dKT18KSgiK3JlKyIpKFthLXolXSopJCIsImkiKSxvZT1bIlRvcCIsIlJpZ2h0IiwiQm90dG9tIiwiTGVmdCJdLGFlPWZ1bmN0aW9uKGUsdCl7cmV0dXJuIm5vbmUiPT09KGU9dHx8ZSkuc3R5bGUuZGlzcGxheXx8IiI9PT1lLnN0eWxlLmRpc3BsYXkmJncuY29udGFpbnMoZS5vd25lckRvY3VtZW50LGUpJiYibm9uZSI9PT13LmNzcyhlLCJkaXNwbGF5Iil9LHNlPWZ1bmN0aW9uKGUsdCxuLHIpe3ZhciBpLG8sYT17fTtmb3IobyBpbiB0KWFbb109ZS5zdHlsZVtvXSxlLnN0eWxlW29dPXRbb107aT1uLmFwcGx5KGUscnx8W10pO2ZvcihvIGluIHQpZS5zdHlsZVtvXT1hW29dO3JldHVybiBpfTtmdW5jdGlvbiB1ZShlLHQsbixyKXt2YXIgaSxvLGE9MjAscz1yP2Z1bmN0aW9uKCl7cmV0dXJuIHIuY3VyKCl9OmZ1bmN0aW9uKCl7cmV0dXJuIHcuY3NzKGUsdCwiIil9LHU9cygpLGw9biYmblszXXx8KHcuY3NzTnVtYmVyW3RdPyIiOiJweCIpLGM9KHcuY3NzTnVtYmVyW3RdfHwicHgiIT09bCYmK3UpJiZpZS5leGVjKHcuY3NzKGUsdCkpO2lmKGMmJmNbM10hPT1sKXt1Lz0yLGw9bHx8Y1szXSxjPSt1fHwxO3doaWxlKGEtLSl3LnN0eWxlKGUsdCxjK2wpLCgxLW8pKigxLShvPXMoKS91fHwuNSkpPD0wJiYoYT0wKSxjLz1vO2MqPTIsdy5zdHlsZShlLHQsYytsKSxuPW58fFtdfXJldHVybiBuJiYoYz0rY3x8K3V8fDAsaT1uWzFdP2MrKG5bMV0rMSkqblsyXTorblsyXSxyJiYoci51bml0PWwsci5zdGFydD1jLHIuZW5kPWkpKSxpfXZhciBsZT17fTtmdW5jdGlvbiBjZShlKXt2YXIgdCxuPWUub3duZXJEb2N1bWVudCxyPWUubm9kZU5hbWUsaT1sZVtyXTtyZXR1cm4gaXx8KHQ9bi5ib2R5LmFwcGVuZENoaWxkKG4uY3JlYXRlRWxlbWVudChyKSksaT13LmNzcyh0LCJkaXNwbGF5IiksdC5wYXJlbnROb2RlLnJlbW92ZUNoaWxkKHQpLCJub25lIj09PWkmJihpPSJibG9jayIpLGxlW3JdPWksaSl9ZnVuY3Rpb24gZmUoZSx0KXtmb3IodmFyIG4scixpPVtdLG89MCxhPWUubGVuZ3RoO288YTtvKyspKHI9ZVtvXSkuc3R5bGUmJihuPXIuc3R5bGUuZGlzcGxheSx0Pygibm9uZSI9PT1uJiYoaVtvXT1KLmdldChyLCJkaXNwbGF5Iil8fG51bGwsaVtvXXx8KHIuc3R5bGUuZGlzcGxheT0iIikpLCIiPT09ci5zdHlsZS5kaXNwbGF5JiZhZShyKSYmKGlbb109Y2UocikpKToibm9uZSIhPT1uJiYoaVtvXT0ibm9uZSIsSi5zZXQociwiZGlzcGxheSIsbikpKTtmb3Iobz0wO288YTtvKyspbnVsbCE9aVtvXSYmKGVbb10uc3R5bGUuZGlzcGxheT1pW29dKTtyZXR1cm4gZX13LmZuLmV4dGVuZCh7c2hvdzpmdW5jdGlvbigpe3JldHVybiBmZSh0aGlzLCEwKX0saGlkZTpmdW5jdGlvbigpe3JldHVybiBmZSh0aGlzKX0sdG9nZ2xlOmZ1bmN0aW9uKGUpe3JldHVybiJib29sZWFuIj09dHlwZW9mIGU/ZT90aGlzLnNob3coKTp0aGlzLmhpZGUoKTp0aGlzLmVhY2goZnVuY3Rpb24oKXthZSh0aGlzKT93KHRoaXMpLnNob3coKTp3KHRoaXMpLmhpZGUoKX0pfX0pO3ZhciBwZT0vXig/OmNoZWNrYm94fHJhZGlvKSQvaSxkZT0vPChbYS16XVteXC9cMD5ceDIwXHRcclxuXGZdKykvaSxoZT0vXiR8Xm1vZHVsZSR8XC8oPzpqYXZhfGVjbWEpc2NyaXB0L2ksZ2U9e29wdGlvbjpbMSwiPHNlbGVjdCBtdWx0aXBsZT0nbXVsdGlwbGUnPiIsIjwvc2VsZWN0PiJdLHRoZWFkOlsxLCI8dGFibGU+IiwiPC90YWJsZT4iXSxjb2w6WzIsIjx0YWJsZT48Y29sZ3JvdXA+IiwiPC9jb2xncm91cD48L3RhYmxlPiJdLHRyOlsyLCI8dGFibGU+PHRib2R5PiIsIjwvdGJvZHk+PC90YWJsZT4iXSx0ZDpbMywiPHRhYmxlPjx0Ym9keT48dHI+IiwiPC90cj48L3Rib2R5PjwvdGFibGU+Il0sX2RlZmF1bHQ6WzAsIiIsIiJdfTtnZS5vcHRncm91cD1nZS5vcHRpb24sZ2UudGJvZHk9Z2UudGZvb3Q9Z2UuY29sZ3JvdXA9Z2UuY2FwdGlvbj1nZS50aGVhZCxnZS50aD1nZS50ZDtmdW5jdGlvbiB5ZShlLHQpe3ZhciBuO3JldHVybiBuPSJ1bmRlZmluZWQiIT10eXBlb2YgZS5nZXRFbGVtZW50c0J5VGFnTmFtZT9lLmdldEVsZW1lbnRzQnlUYWdOYW1lKHR8fCIqIik6InVuZGVmaW5lZCIhPXR5cGVvZiBlLnF1ZXJ5U2VsZWN0b3JBbGw/ZS5xdWVyeVNlbGVjdG9yQWxsKHR8fCIqIik6W10sdm9pZCAwPT09dHx8dCYmTihlLHQpP3cubWVyZ2UoW2VdLG4pOm59ZnVuY3Rpb24gdmUoZSx0KXtmb3IodmFyIG49MCxyPWUubGVuZ3RoO248cjtuKyspSi5zZXQoZVtuXSwiZ2xvYmFsRXZhbCIsIXR8fEouZ2V0KHRbbl0sImdsb2JhbEV2YWwiKSl9dmFyIG1lPS88fCYjP1x3KzsvO2Z1bmN0aW9uIHhlKGUsdCxuLHIsaSl7Zm9yKHZhciBvLGEscyx1LGwsYyxmPXQuY3JlYXRlRG9jdW1lbnRGcmFnbWVudCgpLHA9W10sZD0wLGg9ZS5sZW5ndGg7ZDxoO2QrKylpZigobz1lW2RdKXx8MD09PW8paWYoIm9iamVjdCI9PT14KG8pKXcubWVyZ2UocCxvLm5vZGVUeXBlP1tvXTpvKTtlbHNlIGlmKG1lLnRlc3Qobykpe2E9YXx8Zi5hcHBlbmRDaGlsZCh0LmNyZWF0ZUVsZW1lbnQoImRpdiIpKSxzPShkZS5leGVjKG8pfHxbIiIsIiJdKVsxXS50b0xvd2VyQ2FzZSgpLHU9Z2Vbc118fGdlLl9kZWZhdWx0LGEuaW5uZXJIVE1MPXVbMV0rdy5odG1sUHJlZmlsdGVyKG8pK3VbMl0sYz11WzBdO3doaWxlKGMtLSlhPWEubGFzdENoaWxkO3cubWVyZ2UocCxhLmNoaWxkTm9kZXMpLChhPWYuZmlyc3RDaGlsZCkudGV4dENvbnRlbnQ9IiJ9ZWxzZSBwLnB1c2godC5jcmVhdGVUZXh0Tm9kZShvKSk7Zi50ZXh0Q29udGVudD0iIixkPTA7d2hpbGUobz1wW2QrK10paWYociYmdy5pbkFycmF5KG8scik+LTEpaSYmaS5wdXNoKG8pO2Vsc2UgaWYobD13LmNvbnRhaW5zKG8ub3duZXJEb2N1bWVudCxvKSxhPXllKGYuYXBwZW5kQ2hpbGQobyksInNjcmlwdCIpLGwmJnZlKGEpLG4pe2M9MDt3aGlsZShvPWFbYysrXSloZS50ZXN0KG8udHlwZXx8IiIpJiZuLnB1c2gobyl9cmV0dXJuIGZ9IWZ1bmN0aW9uKCl7dmFyIGU9ci5jcmVhdGVEb2N1bWVudEZyYWdtZW50KCkuYXBwZW5kQ2hpbGQoci5jcmVhdGVFbGVtZW50KCJkaXYiKSksdD1yLmNyZWF0ZUVsZW1lbnQoImlucHV0Iik7dC5zZXRBdHRyaWJ1dGUoInR5cGUiLCJyYWRpbyIpLHQuc2V0QXR0cmlidXRlKCJjaGVja2VkIiwiY2hlY2tlZCIpLHQuc2V0QXR0cmlidXRlKCJuYW1lIiwidCIpLGUuYXBwZW5kQ2hpbGQodCksaC5jaGVja0Nsb25lPWUuY2xvbmVOb2RlKCEwKS5jbG9uZU5vZGUoITApLmxhc3RDaGlsZC5jaGVja2VkLGUuaW5uZXJIVE1MPSI8dGV4dGFyZWE+eDwvdGV4dGFyZWE+IixoLm5vQ2xvbmVDaGVja2VkPSEhZS5jbG9uZU5vZGUoITApLmxhc3RDaGlsZC5kZWZhdWx0VmFsdWV9KCk7dmFyIGJlPXIuZG9jdW1lbnRFbGVtZW50LHdlPS9ea2V5LyxUZT0vXig/Om1vdXNlfHBvaW50ZXJ8Y29udGV4dG1lbnV8ZHJhZ3xkcm9wKXxjbGljay8sQ2U9L14oW14uXSopKD86XC4oLispfCkvO2Z1bmN0aW9uIEVlKCl7cmV0dXJuITB9ZnVuY3Rpb24ga2UoKXtyZXR1cm4hMX1mdW5jdGlvbiBTZSgpe3RyeXtyZXR1cm4gci5hY3RpdmVFbGVtZW50fWNhdGNoKGUpe319ZnVuY3Rpb24gRGUoZSx0LG4scixpLG8pe3ZhciBhLHM7aWYoIm9iamVjdCI9PXR5cGVvZiB0KXsic3RyaW5nIiE9dHlwZW9mIG4mJihyPXJ8fG4sbj12b2lkIDApO2ZvcihzIGluIHQpRGUoZSxzLG4scix0W3NdLG8pO3JldHVybiBlfWlmKG51bGw9PXImJm51bGw9PWk/KGk9bixyPW49dm9pZCAwKTpudWxsPT1pJiYoInN0cmluZyI9PXR5cGVvZiBuPyhpPXIscj12b2lkIDApOihpPXIscj1uLG49dm9pZCAwKSksITE9PT1pKWk9a2U7ZWxzZSBpZighaSlyZXR1cm4gZTtyZXR1cm4gMT09PW8mJihhPWksKGk9ZnVuY3Rpb24oZSl7cmV0dXJuIHcoKS5vZmYoZSksYS5hcHBseSh0aGlzLGFyZ3VtZW50cyl9KS5ndWlkPWEuZ3VpZHx8KGEuZ3VpZD13Lmd1aWQrKykpLGUuZWFjaChmdW5jdGlvbigpe3cuZXZlbnQuYWRkKHRoaXMsdCxpLHIsbil9KX13LmV2ZW50PXtnbG9iYWw6e30sYWRkOmZ1bmN0aW9uKGUsdCxuLHIsaSl7dmFyIG8sYSxzLHUsbCxjLGYscCxkLGgsZyx5PUouZ2V0KGUpO2lmKHkpe24uaGFuZGxlciYmKG49KG89bikuaGFuZGxlcixpPW8uc2VsZWN0b3IpLGkmJncuZmluZC5tYXRjaGVzU2VsZWN0b3IoYmUsaSksbi5ndWlkfHwobi5ndWlkPXcuZ3VpZCsrKSwodT15LmV2ZW50cyl8fCh1PXkuZXZlbnRzPXt9KSwoYT15LmhhbmRsZSl8fChhPXkuaGFuZGxlPWZ1bmN0aW9uKHQpe3JldHVybiJ1bmRlZmluZWQiIT10eXBlb2YgdyYmdy5ldmVudC50cmlnZ2VyZWQhPT10LnR5cGU/dy5ldmVudC5kaXNwYXRjaC5hcHBseShlLGFyZ3VtZW50cyk6dm9pZCAwfSksbD0odD0odHx8IiIpLm1hdGNoKE0pfHxbIiJdKS5sZW5ndGg7d2hpbGUobC0tKWQ9Zz0ocz1DZS5leGVjKHRbbF0pfHxbXSlbMV0saD0oc1syXXx8IiIpLnNwbGl0KCIuIikuc29ydCgpLGQmJihmPXcuZXZlbnQuc3BlY2lhbFtkXXx8e30sZD0oaT9mLmRlbGVnYXRlVHlwZTpmLmJpbmRUeXBlKXx8ZCxmPXcuZXZlbnQuc3BlY2lhbFtkXXx8e30sYz13LmV4dGVuZCh7dHlwZTpkLG9yaWdUeXBlOmcsZGF0YTpyLGhhbmRsZXI6bixndWlkOm4uZ3VpZCxzZWxlY3RvcjppLG5lZWRzQ29udGV4dDppJiZ3LmV4cHIubWF0Y2gubmVlZHNDb250ZXh0LnRlc3QoaSksbmFtZXNwYWNlOmguam9pbigiLiIpfSxvKSwocD11W2RdKXx8KChwPXVbZF09W10pLmRlbGVnYXRlQ291bnQ9MCxmLnNldHVwJiYhMSE9PWYuc2V0dXAuY2FsbChlLHIsaCxhKXx8ZS5hZGRFdmVudExpc3RlbmVyJiZlLmFkZEV2ZW50TGlzdGVuZXIoZCxhKSksZi5hZGQmJihmLmFkZC5jYWxsKGUsYyksYy5oYW5kbGVyLmd1aWR8fChjLmhhbmRsZXIuZ3VpZD1uLmd1aWQpKSxpP3Auc3BsaWNlKHAuZGVsZWdhdGVDb3VudCsrLDAsYyk6cC5wdXNoKGMpLHcuZXZlbnQuZ2xvYmFsW2RdPSEwKX19LHJlbW92ZTpmdW5jdGlvbihlLHQsbixyLGkpe3ZhciBvLGEscyx1LGwsYyxmLHAsZCxoLGcseT1KLmhhc0RhdGEoZSkmJkouZ2V0KGUpO2lmKHkmJih1PXkuZXZlbnRzKSl7bD0odD0odHx8IiIpLm1hdGNoKE0pfHxbIiJdKS5sZW5ndGg7d2hpbGUobC0tKWlmKHM9Q2UuZXhlYyh0W2xdKXx8W10sZD1nPXNbMV0saD0oc1syXXx8IiIpLnNwbGl0KCIuIikuc29ydCgpLGQpe2Y9dy5ldmVudC5zcGVjaWFsW2RdfHx7fSxwPXVbZD0ocj9mLmRlbGVnYXRlVHlwZTpmLmJpbmRUeXBlKXx8ZF18fFtdLHM9c1syXSYmbmV3IFJlZ0V4cCgiKF58XFwuKSIraC5qb2luKCJcXC4oPzouKlxcLnwpIikrIihcXC58JCkiKSxhPW89cC5sZW5ndGg7d2hpbGUoby0tKWM9cFtvXSwhaSYmZyE9PWMub3JpZ1R5cGV8fG4mJm4uZ3VpZCE9PWMuZ3VpZHx8cyYmIXMudGVzdChjLm5hbWVzcGFjZSl8fHImJnIhPT1jLnNlbGVjdG9yJiYoIioqIiE9PXJ8fCFjLnNlbGVjdG9yKXx8KHAuc3BsaWNlKG8sMSksYy5zZWxlY3RvciYmcC5kZWxlZ2F0ZUNvdW50LS0sZi5yZW1vdmUmJmYucmVtb3ZlLmNhbGwoZSxjKSk7YSYmIXAubGVuZ3RoJiYoZi50ZWFyZG93biYmITEhPT1mLnRlYXJkb3duLmNhbGwoZSxoLHkuaGFuZGxlKXx8dy5yZW1vdmVFdmVudChlLGQseS5oYW5kbGUpLGRlbGV0ZSB1W2RdKX1lbHNlIGZvcihkIGluIHUpdy5ldmVudC5yZW1vdmUoZSxkK3RbbF0sbixyLCEwKTt3LmlzRW1wdHlPYmplY3QodSkmJkoucmVtb3ZlKGUsImhhbmRsZSBldmVudHMiKX19LGRpc3BhdGNoOmZ1bmN0aW9uKGUpe3ZhciB0PXcuZXZlbnQuZml4KGUpLG4scixpLG8sYSxzLHU9bmV3IEFycmF5KGFyZ3VtZW50cy5sZW5ndGgpLGw9KEouZ2V0KHRoaXMsImV2ZW50cyIpfHx7fSlbdC50eXBlXXx8W10sYz13LmV2ZW50LnNwZWNpYWxbdC50eXBlXXx8e307Zm9yKHVbMF09dCxuPTE7bjxhcmd1bWVudHMubGVuZ3RoO24rKyl1W25dPWFyZ3VtZW50c1tuXTtpZih0LmRlbGVnYXRlVGFyZ2V0PXRoaXMsIWMucHJlRGlzcGF0Y2h8fCExIT09Yy5wcmVEaXNwYXRjaC5jYWxsKHRoaXMsdCkpe3M9dy5ldmVudC5oYW5kbGVycy5jYWxsKHRoaXMsdCxsKSxuPTA7d2hpbGUoKG89c1tuKytdKSYmIXQuaXNQcm9wYWdhdGlvblN0b3BwZWQoKSl7dC5jdXJyZW50VGFyZ2V0PW8uZWxlbSxyPTA7d2hpbGUoKGE9by5oYW5kbGVyc1tyKytdKSYmIXQuaXNJbW1lZGlhdGVQcm9wYWdhdGlvblN0b3BwZWQoKSl0LnJuYW1lc3BhY2UmJiF0LnJuYW1lc3BhY2UudGVzdChhLm5hbWVzcGFjZSl8fCh0LmhhbmRsZU9iaj1hLHQuZGF0YT1hLmRhdGEsdm9pZCAwIT09KGk9KCh3LmV2ZW50LnNwZWNpYWxbYS5vcmlnVHlwZV18fHt9KS5oYW5kbGV8fGEuaGFuZGxlcikuYXBwbHkoby5lbGVtLHUpKSYmITE9PT0odC5yZXN1bHQ9aSkmJih0LnByZXZlbnREZWZhdWx0KCksdC5zdG9wUHJvcGFnYXRpb24oKSkpfXJldHVybiBjLnBvc3REaXNwYXRjaCYmYy5wb3N0RGlzcGF0Y2guY2FsbCh0aGlzLHQpLHQucmVzdWx0fX0saGFuZGxlcnM6ZnVuY3Rpb24oZSx0KXt2YXIgbixyLGksbyxhLHM9W10sdT10LmRlbGVnYXRlQ291bnQsbD1lLnRhcmdldDtpZih1JiZsLm5vZGVUeXBlJiYhKCJjbGljayI9PT1lLnR5cGUmJmUuYnV0dG9uPj0xKSlmb3IoO2whPT10aGlzO2w9bC5wYXJlbnROb2RlfHx0aGlzKWlmKDE9PT1sLm5vZGVUeXBlJiYoImNsaWNrIiE9PWUudHlwZXx8ITAhPT1sLmRpc2FibGVkKSl7Zm9yKG89W10sYT17fSxuPTA7bjx1O24rKyl2b2lkIDA9PT1hW2k9KHI9dFtuXSkuc2VsZWN0b3IrIiAiXSYmKGFbaV09ci5uZWVkc0NvbnRleHQ/dyhpLHRoaXMpLmluZGV4KGwpPi0xOncuZmluZChpLHRoaXMsbnVsbCxbbF0pLmxlbmd0aCksYVtpXSYmby5wdXNoKHIpO28ubGVuZ3RoJiZzLnB1c2goe2VsZW06bCxoYW5kbGVyczpvfSl9cmV0dXJuIGw9dGhpcyx1PHQubGVuZ3RoJiZzLnB1c2goe2VsZW06bCxoYW5kbGVyczp0LnNsaWNlKHUpfSksc30sYWRkUHJvcDpmdW5jdGlvbihlLHQpe09iamVjdC5kZWZpbmVQcm9wZXJ0eSh3LkV2ZW50LnByb3RvdHlwZSxlLHtlbnVtZXJhYmxlOiEwLGNvbmZpZ3VyYWJsZTohMCxnZXQ6Zyh0KT9mdW5jdGlvbigpe2lmKHRoaXMub3JpZ2luYWxFdmVudClyZXR1cm4gdCh0aGlzLm9yaWdpbmFsRXZlbnQpfTpmdW5jdGlvbigpe2lmKHRoaXMub3JpZ2luYWxFdmVudClyZXR1cm4gdGhpcy5vcmlnaW5hbEV2ZW50W2VdfSxzZXQ6ZnVuY3Rpb24odCl7T2JqZWN0LmRlZmluZVByb3BlcnR5KHRoaXMsZSx7ZW51bWVyYWJsZTohMCxjb25maWd1cmFibGU6ITAsd3JpdGFibGU6ITAsdmFsdWU6dH0pfX0pfSxmaXg6ZnVuY3Rpb24oZSl7cmV0dXJuIGVbdy5leHBhbmRvXT9lOm5ldyB3LkV2ZW50KGUpfSxzcGVjaWFsOntsb2FkOntub0J1YmJsZTohMH0sZm9jdXM6e3RyaWdnZXI6ZnVuY3Rpb24oKXtpZih0aGlzIT09U2UoKSYmdGhpcy5mb2N1cylyZXR1cm4gdGhpcy5mb2N1cygpLCExfSxkZWxlZ2F0ZVR5cGU6ImZvY3VzaW4ifSxibHVyOnt0cmlnZ2VyOmZ1bmN0aW9uKCl7aWYodGhpcz09PVNlKCkmJnRoaXMuYmx1cilyZXR1cm4gdGhpcy5ibHVyKCksITF9LGRlbGVnYXRlVHlwZToiZm9jdXNvdXQifSxjbGljazp7dHJpZ2dlcjpmdW5jdGlvbigpe2lmKCJjaGVja2JveCI9PT10aGlzLnR5cGUmJnRoaXMuY2xpY2smJk4odGhpcywiaW5wdXQiKSlyZXR1cm4gdGhpcy5jbGljaygpLCExfSxfZGVmYXVsdDpmdW5jdGlvbihlKXtyZXR1cm4gTihlLnRhcmdldCwiYSIpfX0sYmVmb3JldW5sb2FkOntwb3N0RGlzcGF0Y2g6ZnVuY3Rpb24oZSl7dm9pZCAwIT09ZS5yZXN1bHQmJmUub3JpZ2luYWxFdmVudCYmKGUub3JpZ2luYWxFdmVudC5yZXR1cm5WYWx1ZT1lLnJlc3VsdCl9fX19LHcucmVtb3ZlRXZlbnQ9ZnVuY3Rpb24oZSx0LG4pe2UucmVtb3ZlRXZlbnRMaXN0ZW5lciYmZS5yZW1vdmVFdmVudExpc3RlbmVyKHQsbil9LHcuRXZlbnQ9ZnVuY3Rpb24oZSx0KXtpZighKHRoaXMgaW5zdGFuY2VvZiB3LkV2ZW50KSlyZXR1cm4gbmV3IHcuRXZlbnQoZSx0KTtlJiZlLnR5cGU/KHRoaXMub3JpZ2luYWxFdmVudD1lLHRoaXMudHlwZT1lLnR5cGUsdGhpcy5pc0RlZmF1bHRQcmV2ZW50ZWQ9ZS5kZWZhdWx0UHJldmVudGVkfHx2b2lkIDA9PT1lLmRlZmF1bHRQcmV2ZW50ZWQmJiExPT09ZS5yZXR1cm5WYWx1ZT9FZTprZSx0aGlzLnRhcmdldD1lLnRhcmdldCYmMz09PWUudGFyZ2V0Lm5vZGVUeXBlP2UudGFyZ2V0LnBhcmVudE5vZGU6ZS50YXJnZXQsdGhpcy5jdXJyZW50VGFyZ2V0PWUuY3VycmVudFRhcmdldCx0aGlzLnJlbGF0ZWRUYXJnZXQ9ZS5yZWxhdGVkVGFyZ2V0KTp0aGlzLnR5cGU9ZSx0JiZ3LmV4dGVuZCh0aGlzLHQpLHRoaXMudGltZVN0YW1wPWUmJmUudGltZVN0YW1wfHxEYXRlLm5vdygpLHRoaXNbdy5leHBhbmRvXT0hMH0sdy5FdmVudC5wcm90b3R5cGU9e2NvbnN0cnVjdG9yOncuRXZlbnQsaXNEZWZhdWx0UHJldmVudGVkOmtlLGlzUHJvcGFnYXRpb25TdG9wcGVkOmtlLGlzSW1tZWRpYXRlUHJvcGFnYXRpb25TdG9wcGVkOmtlLGlzU2ltdWxhdGVkOiExLHByZXZlbnREZWZhdWx0OmZ1bmN0aW9uKCl7dmFyIGU9dGhpcy5vcmlnaW5hbEV2ZW50O3RoaXMuaXNEZWZhdWx0UHJldmVudGVkPUVlLGUmJiF0aGlzLmlzU2ltdWxhdGVkJiZlLnByZXZlbnREZWZhdWx0KCl9LHN0b3BQcm9wYWdhdGlvbjpmdW5jdGlvbigpe3ZhciBlPXRoaXMub3JpZ2luYWxFdmVudDt0aGlzLmlzUHJvcGFnYXRpb25TdG9wcGVkPUVlLGUmJiF0aGlzLmlzU2ltdWxhdGVkJiZlLnN0b3BQcm9wYWdhdGlvbigpfSxzdG9wSW1tZWRpYXRlUHJvcGFnYXRpb246ZnVuY3Rpb24oKXt2YXIgZT10aGlzLm9yaWdpbmFsRXZlbnQ7dGhpcy5pc0ltbWVkaWF0ZVByb3BhZ2F0aW9uU3RvcHBlZD1FZSxlJiYhdGhpcy5pc1NpbXVsYXRlZCYmZS5zdG9wSW1tZWRpYXRlUHJvcGFnYXRpb24oKSx0aGlzLnN0b3BQcm9wYWdhdGlvbigpfX0sdy5lYWNoKHthbHRLZXk6ITAsYnViYmxlczohMCxjYW5jZWxhYmxlOiEwLGNoYW5nZWRUb3VjaGVzOiEwLGN0cmxLZXk6ITAsZGV0YWlsOiEwLGV2ZW50UGhhc2U6ITAsbWV0YUtleTohMCxwYWdlWDohMCxwYWdlWTohMCxzaGlmdEtleTohMCx2aWV3OiEwLCJjaGFyIjohMCxjaGFyQ29kZTohMCxrZXk6ITAsa2V5Q29kZTohMCxidXR0b246ITAsYnV0dG9uczohMCxjbGllbnRYOiEwLGNsaWVudFk6ITAsb2Zmc2V0WDohMCxvZmZzZXRZOiEwLHBvaW50ZXJJZDohMCxwb2ludGVyVHlwZTohMCxzY3JlZW5YOiEwLHNjcmVlblk6ITAsdGFyZ2V0VG91Y2hlczohMCx0b0VsZW1lbnQ6ITAsdG91Y2hlczohMCx3aGljaDpmdW5jdGlvbihlKXt2YXIgdD1lLmJ1dHRvbjtyZXR1cm4gbnVsbD09ZS53aGljaCYmd2UudGVzdChlLnR5cGUpP251bGwhPWUuY2hhckNvZGU/ZS5jaGFyQ29kZTplLmtleUNvZGU6IWUud2hpY2gmJnZvaWQgMCE9PXQmJlRlLnRlc3QoZS50eXBlKT8xJnQ/MToyJnQ/Mzo0JnQ/MjowOmUud2hpY2h9fSx3LmV2ZW50LmFkZFByb3ApLHcuZWFjaCh7bW91c2VlbnRlcjoibW91c2VvdmVyIixtb3VzZWxlYXZlOiJtb3VzZW91dCIscG9pbnRlcmVudGVyOiJwb2ludGVyb3ZlciIscG9pbnRlcmxlYXZlOiJwb2ludGVyb3V0In0sZnVuY3Rpb24oZSx0KXt3LmV2ZW50LnNwZWNpYWxbZV09e2RlbGVnYXRlVHlwZTp0LGJpbmRUeXBlOnQsaGFuZGxlOmZ1bmN0aW9uKGUpe3ZhciBuLHI9dGhpcyxpPWUucmVsYXRlZFRhcmdldCxvPWUuaGFuZGxlT2JqO3JldHVybiBpJiYoaT09PXJ8fHcuY29udGFpbnMocixpKSl8fChlLnR5cGU9by5vcmlnVHlwZSxuPW8uaGFuZGxlci5hcHBseSh0aGlzLGFyZ3VtZW50cyksZS50eXBlPXQpLG59fX0pLHcuZm4uZXh0ZW5kKHtvbjpmdW5jdGlvbihlLHQsbixyKXtyZXR1cm4gRGUodGhpcyxlLHQsbixyKX0sb25lOmZ1bmN0aW9uKGUsdCxuLHIpe3JldHVybiBEZSh0aGlzLGUsdCxuLHIsMSl9LG9mZjpmdW5jdGlvbihlLHQsbil7dmFyIHIsaTtpZihlJiZlLnByZXZlbnREZWZhdWx0JiZlLmhhbmRsZU9iailyZXR1cm4gcj1lLmhhbmRsZU9iaix3KGUuZGVsZWdhdGVUYXJnZXQpLm9mZihyLm5hbWVzcGFjZT9yLm9yaWdUeXBlKyIuIityLm5hbWVzcGFjZTpyLm9yaWdUeXBlLHIuc2VsZWN0b3Isci5oYW5kbGVyKSx0aGlzO2lmKCJvYmplY3QiPT10eXBlb2YgZSl7Zm9yKGkgaW4gZSl0aGlzLm9mZihpLHQsZVtpXSk7cmV0dXJuIHRoaXN9cmV0dXJuITEhPT10JiYiZnVuY3Rpb24iIT10eXBlb2YgdHx8KG49dCx0PXZvaWQgMCksITE9PT1uJiYobj1rZSksdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dy5ldmVudC5yZW1vdmUodGhpcyxlLG4sdCl9KX19KTt2YXIgTmU9LzwoPyFhcmVhfGJyfGNvbHxlbWJlZHxocnxpbWd8aW5wdXR8bGlua3xtZXRhfHBhcmFtKSgoW2Etel1bXlwvXDA+XHgyMFx0XHJcblxmXSopW14+XSopXC8+L2dpLEFlPS88c2NyaXB0fDxzdHlsZXw8bGluay9pLGplPS9jaGVja2VkXHMqKD86W149XXw9XHMqLmNoZWNrZWQuKS9pLHFlPS9eXHMqPCEoPzpcW0NEQVRBXFt8LS0pfCg/OlxdXF18LS0pPlxzKiQvZztmdW5jdGlvbiBMZShlLHQpe3JldHVybiBOKGUsInRhYmxlIikmJk4oMTEhPT10Lm5vZGVUeXBlP3Q6dC5maXJzdENoaWxkLCJ0ciIpP3coZSkuY2hpbGRyZW4oInRib2R5IilbMF18fGU6ZX1mdW5jdGlvbiBIZShlKXtyZXR1cm4gZS50eXBlPShudWxsIT09ZS5nZXRBdHRyaWJ1dGUoInR5cGUiKSkrIi8iK2UudHlwZSxlfWZ1bmN0aW9uIE9lKGUpe3JldHVybiJ0cnVlLyI9PT0oZS50eXBlfHwiIikuc2xpY2UoMCw1KT9lLnR5cGU9ZS50eXBlLnNsaWNlKDUpOmUucmVtb3ZlQXR0cmlidXRlKCJ0eXBlIiksZX1mdW5jdGlvbiBQZShlLHQpe3ZhciBuLHIsaSxvLGEscyx1LGw7aWYoMT09PXQubm9kZVR5cGUpe2lmKEouaGFzRGF0YShlKSYmKG89Si5hY2Nlc3MoZSksYT1KLnNldCh0LG8pLGw9by5ldmVudHMpKXtkZWxldGUgYS5oYW5kbGUsYS5ldmVudHM9e307Zm9yKGkgaW4gbClmb3Iobj0wLHI9bFtpXS5sZW5ndGg7bjxyO24rKyl3LmV2ZW50LmFkZCh0LGksbFtpXVtuXSl9Sy5oYXNEYXRhKGUpJiYocz1LLmFjY2VzcyhlKSx1PXcuZXh0ZW5kKHt9LHMpLEsuc2V0KHQsdSkpfX1mdW5jdGlvbiBNZShlLHQpe3ZhciBuPXQubm9kZU5hbWUudG9Mb3dlckNhc2UoKTsiaW5wdXQiPT09biYmcGUudGVzdChlLnR5cGUpP3QuY2hlY2tlZD1lLmNoZWNrZWQ6ImlucHV0IiE9PW4mJiJ0ZXh0YXJlYSIhPT1ufHwodC5kZWZhdWx0VmFsdWU9ZS5kZWZhdWx0VmFsdWUpfWZ1bmN0aW9uIFJlKGUsdCxuLHIpe3Q9YS5hcHBseShbXSx0KTt2YXIgaSxvLHMsdSxsLGMsZj0wLHA9ZS5sZW5ndGgsZD1wLTEseT10WzBdLHY9Zyh5KTtpZih2fHxwPjEmJiJzdHJpbmciPT10eXBlb2YgeSYmIWguY2hlY2tDbG9uZSYmamUudGVzdCh5KSlyZXR1cm4gZS5lYWNoKGZ1bmN0aW9uKGkpe3ZhciBvPWUuZXEoaSk7diYmKHRbMF09eS5jYWxsKHRoaXMsaSxvLmh0bWwoKSkpLFJlKG8sdCxuLHIpfSk7aWYocCYmKGk9eGUodCxlWzBdLm93bmVyRG9jdW1lbnQsITEsZSxyKSxvPWkuZmlyc3RDaGlsZCwxPT09aS5jaGlsZE5vZGVzLmxlbmd0aCYmKGk9byksb3x8cikpe2Zvcih1PShzPXcubWFwKHllKGksInNjcmlwdCIpLEhlKSkubGVuZ3RoO2Y8cDtmKyspbD1pLGYhPT1kJiYobD13LmNsb25lKGwsITAsITApLHUmJncubWVyZ2Uocyx5ZShsLCJzY3JpcHQiKSkpLG4uY2FsbChlW2ZdLGwsZik7aWYodSlmb3IoYz1zW3MubGVuZ3RoLTFdLm93bmVyRG9jdW1lbnQsdy5tYXAocyxPZSksZj0wO2Y8dTtmKyspbD1zW2ZdLGhlLnRlc3QobC50eXBlfHwiIikmJiFKLmFjY2VzcyhsLCJnbG9iYWxFdmFsIikmJncuY29udGFpbnMoYyxsKSYmKGwuc3JjJiYibW9kdWxlIiE9PShsLnR5cGV8fCIiKS50b0xvd2VyQ2FzZSgpP3cuX2V2YWxVcmwmJncuX2V2YWxVcmwobC5zcmMpOm0obC50ZXh0Q29udGVudC5yZXBsYWNlKHFlLCIiKSxjLGwpKX1yZXR1cm4gZX1mdW5jdGlvbiBJZShlLHQsbil7Zm9yKHZhciByLGk9dD93LmZpbHRlcih0LGUpOmUsbz0wO251bGwhPShyPWlbb10pO28rKylufHwxIT09ci5ub2RlVHlwZXx8dy5jbGVhbkRhdGEoeWUocikpLHIucGFyZW50Tm9kZSYmKG4mJncuY29udGFpbnMoci5vd25lckRvY3VtZW50LHIpJiZ2ZSh5ZShyLCJzY3JpcHQiKSksci5wYXJlbnROb2RlLnJlbW92ZUNoaWxkKHIpKTtyZXR1cm4gZX13LmV4dGVuZCh7aHRtbFByZWZpbHRlcjpmdW5jdGlvbihlKXtyZXR1cm4gZS5yZXBsYWNlKE5lLCI8JDE+PC8kMj4iKX0sY2xvbmU6ZnVuY3Rpb24oZSx0LG4pe3ZhciByLGksbyxhLHM9ZS5jbG9uZU5vZGUoITApLHU9dy5jb250YWlucyhlLm93bmVyRG9jdW1lbnQsZSk7aWYoIShoLm5vQ2xvbmVDaGVja2VkfHwxIT09ZS5ub2RlVHlwZSYmMTEhPT1lLm5vZGVUeXBlfHx3LmlzWE1MRG9jKGUpKSlmb3IoYT15ZShzKSxyPTAsaT0obz15ZShlKSkubGVuZ3RoO3I8aTtyKyspTWUob1tyXSxhW3JdKTtpZih0KWlmKG4pZm9yKG89b3x8eWUoZSksYT1hfHx5ZShzKSxyPTAsaT1vLmxlbmd0aDtyPGk7cisrKVBlKG9bcl0sYVtyXSk7ZWxzZSBQZShlLHMpO3JldHVybihhPXllKHMsInNjcmlwdCIpKS5sZW5ndGg+MCYmdmUoYSwhdSYmeWUoZSwic2NyaXB0IikpLHN9LGNsZWFuRGF0YTpmdW5jdGlvbihlKXtmb3IodmFyIHQsbixyLGk9dy5ldmVudC5zcGVjaWFsLG89MDt2b2lkIDAhPT0obj1lW29dKTtvKyspaWYoWShuKSl7aWYodD1uW0ouZXhwYW5kb10pe2lmKHQuZXZlbnRzKWZvcihyIGluIHQuZXZlbnRzKWlbcl0/dy5ldmVudC5yZW1vdmUobixyKTp3LnJlbW92ZUV2ZW50KG4scix0LmhhbmRsZSk7bltKLmV4cGFuZG9dPXZvaWQgMH1uW0suZXhwYW5kb10mJihuW0suZXhwYW5kb109dm9pZCAwKX19fSksdy5mbi5leHRlbmQoe2RldGFjaDpmdW5jdGlvbihlKXtyZXR1cm4gSWUodGhpcyxlLCEwKX0scmVtb3ZlOmZ1bmN0aW9uKGUpe3JldHVybiBJZSh0aGlzLGUpfSx0ZXh0OmZ1bmN0aW9uKGUpe3JldHVybiB6KHRoaXMsZnVuY3Rpb24oZSl7cmV0dXJuIHZvaWQgMD09PWU/dy50ZXh0KHRoaXMpOnRoaXMuZW1wdHkoKS5lYWNoKGZ1bmN0aW9uKCl7MSE9PXRoaXMubm9kZVR5cGUmJjExIT09dGhpcy5ub2RlVHlwZSYmOSE9PXRoaXMubm9kZVR5cGV8fCh0aGlzLnRleHRDb250ZW50PWUpfSl9LG51bGwsZSxhcmd1bWVudHMubGVuZ3RoKX0sYXBwZW5kOmZ1bmN0aW9uKCl7cmV0dXJuIFJlKHRoaXMsYXJndW1lbnRzLGZ1bmN0aW9uKGUpezEhPT10aGlzLm5vZGVUeXBlJiYxMSE9PXRoaXMubm9kZVR5cGUmJjkhPT10aGlzLm5vZGVUeXBlfHxMZSh0aGlzLGUpLmFwcGVuZENoaWxkKGUpfSl9LHByZXBlbmQ6ZnVuY3Rpb24oKXtyZXR1cm4gUmUodGhpcyxhcmd1bWVudHMsZnVuY3Rpb24oZSl7aWYoMT09PXRoaXMubm9kZVR5cGV8fDExPT09dGhpcy5ub2RlVHlwZXx8OT09PXRoaXMubm9kZVR5cGUpe3ZhciB0PUxlKHRoaXMsZSk7dC5pbnNlcnRCZWZvcmUoZSx0LmZpcnN0Q2hpbGQpfX0pfSxiZWZvcmU6ZnVuY3Rpb24oKXtyZXR1cm4gUmUodGhpcyxhcmd1bWVudHMsZnVuY3Rpb24oZSl7dGhpcy5wYXJlbnROb2RlJiZ0aGlzLnBhcmVudE5vZGUuaW5zZXJ0QmVmb3JlKGUsdGhpcyl9KX0sYWZ0ZXI6ZnVuY3Rpb24oKXtyZXR1cm4gUmUodGhpcyxhcmd1bWVudHMsZnVuY3Rpb24oZSl7dGhpcy5wYXJlbnROb2RlJiZ0aGlzLnBhcmVudE5vZGUuaW5zZXJ0QmVmb3JlKGUsdGhpcy5uZXh0U2libGluZyl9KX0sZW1wdHk6ZnVuY3Rpb24oKXtmb3IodmFyIGUsdD0wO251bGwhPShlPXRoaXNbdF0pO3QrKykxPT09ZS5ub2RlVHlwZSYmKHcuY2xlYW5EYXRhKHllKGUsITEpKSxlLnRleHRDb250ZW50PSIiKTtyZXR1cm4gdGhpc30sY2xvbmU6ZnVuY3Rpb24oZSx0KXtyZXR1cm4gZT1udWxsIT1lJiZlLHQ9bnVsbD09dD9lOnQsdGhpcy5tYXAoZnVuY3Rpb24oKXtyZXR1cm4gdy5jbG9uZSh0aGlzLGUsdCl9KX0saHRtbDpmdW5jdGlvbihlKXtyZXR1cm4geih0aGlzLGZ1bmN0aW9uKGUpe3ZhciB0PXRoaXNbMF18fHt9LG49MCxyPXRoaXMubGVuZ3RoO2lmKHZvaWQgMD09PWUmJjE9PT10Lm5vZGVUeXBlKXJldHVybiB0LmlubmVySFRNTDtpZigic3RyaW5nIj09dHlwZW9mIGUmJiFBZS50ZXN0KGUpJiYhZ2VbKGRlLmV4ZWMoZSl8fFsiIiwiIl0pWzFdLnRvTG93ZXJDYXNlKCldKXtlPXcuaHRtbFByZWZpbHRlcihlKTt0cnl7Zm9yKDtuPHI7bisrKTE9PT0odD10aGlzW25dfHx7fSkubm9kZVR5cGUmJih3LmNsZWFuRGF0YSh5ZSh0LCExKSksdC5pbm5lckhUTUw9ZSk7dD0wfWNhdGNoKGUpe319dCYmdGhpcy5lbXB0eSgpLmFwcGVuZChlKX0sbnVsbCxlLGFyZ3VtZW50cy5sZW5ndGgpfSxyZXBsYWNlV2l0aDpmdW5jdGlvbigpe3ZhciBlPVtdO3JldHVybiBSZSh0aGlzLGFyZ3VtZW50cyxmdW5jdGlvbih0KXt2YXIgbj10aGlzLnBhcmVudE5vZGU7dy5pbkFycmF5KHRoaXMsZSk8MCYmKHcuY2xlYW5EYXRhKHllKHRoaXMpKSxuJiZuLnJlcGxhY2VDaGlsZCh0LHRoaXMpKX0sZSl9fSksdy5lYWNoKHthcHBlbmRUbzoiYXBwZW5kIixwcmVwZW5kVG86InByZXBlbmQiLGluc2VydEJlZm9yZToiYmVmb3JlIixpbnNlcnRBZnRlcjoiYWZ0ZXIiLHJlcGxhY2VBbGw6InJlcGxhY2VXaXRoIn0sZnVuY3Rpb24oZSx0KXt3LmZuW2VdPWZ1bmN0aW9uKGUpe2Zvcih2YXIgbixyPVtdLGk9dyhlKSxvPWkubGVuZ3RoLTEsYT0wO2E8PW87YSsrKW49YT09PW8/dGhpczp0aGlzLmNsb25lKCEwKSx3KGlbYV0pW3RdKG4pLHMuYXBwbHkocixuLmdldCgpKTtyZXR1cm4gdGhpcy5wdXNoU3RhY2socil9fSk7dmFyIFdlPW5ldyBSZWdFeHAoIl4oIityZSsiKSg/IXB4KVthLXolXSskIiwiaSIpLCRlPWZ1bmN0aW9uKHQpe3ZhciBuPXQub3duZXJEb2N1bWVudC5kZWZhdWx0VmlldztyZXR1cm4gbiYmbi5vcGVuZXJ8fChuPWUpLG4uZ2V0Q29tcHV0ZWRTdHlsZSh0KX0sQmU9bmV3IFJlZ0V4cChvZS5qb2luKCJ8IiksImkiKTshZnVuY3Rpb24oKXtmdW5jdGlvbiB0KCl7aWYoYyl7bC5zdHlsZS5jc3NUZXh0PSJwb3NpdGlvbjphYnNvbHV0ZTtsZWZ0Oi0xMTExMXB4O3dpZHRoOjYwcHg7bWFyZ2luLXRvcDoxcHg7cGFkZGluZzowO2JvcmRlcjowIixjLnN0eWxlLmNzc1RleHQ9InBvc2l0aW9uOnJlbGF0aXZlO2Rpc3BsYXk6YmxvY2s7Ym94LXNpemluZzpib3JkZXItYm94O292ZXJmbG93OnNjcm9sbDttYXJnaW46YXV0bztib3JkZXI6MXB4O3BhZGRpbmc6MXB4O3dpZHRoOjYwJTt0b3A6MSUiLGJlLmFwcGVuZENoaWxkKGwpLmFwcGVuZENoaWxkKGMpO3ZhciB0PWUuZ2V0Q29tcHV0ZWRTdHlsZShjKTtpPSIxJSIhPT10LnRvcCx1PTEyPT09bih0Lm1hcmdpbkxlZnQpLGMuc3R5bGUucmlnaHQ9IjYwJSIscz0zNj09PW4odC5yaWdodCksbz0zNj09PW4odC53aWR0aCksYy5zdHlsZS5wb3NpdGlvbj0iYWJzb2x1dGUiLGE9MzY9PT1jLm9mZnNldFdpZHRofHwiYWJzb2x1dGUiLGJlLnJlbW92ZUNoaWxkKGwpLGM9bnVsbH19ZnVuY3Rpb24gbihlKXtyZXR1cm4gTWF0aC5yb3VuZChwYXJzZUZsb2F0KGUpKX12YXIgaSxvLGEscyx1LGw9ci5jcmVhdGVFbGVtZW50KCJkaXYiKSxjPXIuY3JlYXRlRWxlbWVudCgiZGl2Iik7Yy5zdHlsZSYmKGMuc3R5bGUuYmFja2dyb3VuZENsaXA9ImNvbnRlbnQtYm94IixjLmNsb25lTm9kZSghMCkuc3R5bGUuYmFja2dyb3VuZENsaXA9IiIsaC5jbGVhckNsb25lU3R5bGU9ImNvbnRlbnQtYm94Ij09PWMuc3R5bGUuYmFja2dyb3VuZENsaXAsdy5leHRlbmQoaCx7Ym94U2l6aW5nUmVsaWFibGU6ZnVuY3Rpb24oKXtyZXR1cm4gdCgpLG99LHBpeGVsQm94U3R5bGVzOmZ1bmN0aW9uKCl7cmV0dXJuIHQoKSxzfSxwaXhlbFBvc2l0aW9uOmZ1bmN0aW9uKCl7cmV0dXJuIHQoKSxpfSxyZWxpYWJsZU1hcmdpbkxlZnQ6ZnVuY3Rpb24oKXtyZXR1cm4gdCgpLHV9LHNjcm9sbGJveFNpemU6ZnVuY3Rpb24oKXtyZXR1cm4gdCgpLGF9fSkpfSgpO2Z1bmN0aW9uIEZlKGUsdCxuKXt2YXIgcixpLG8sYSxzPWUuc3R5bGU7cmV0dXJuKG49bnx8JGUoZSkpJiYoIiIhPT0oYT1uLmdldFByb3BlcnR5VmFsdWUodCl8fG5bdF0pfHx3LmNvbnRhaW5zKGUub3duZXJEb2N1bWVudCxlKXx8KGE9dy5zdHlsZShlLHQpKSwhaC5waXhlbEJveFN0eWxlcygpJiZXZS50ZXN0KGEpJiZCZS50ZXN0KHQpJiYocj1zLndpZHRoLGk9cy5taW5XaWR0aCxvPXMubWF4V2lkdGgscy5taW5XaWR0aD1zLm1heFdpZHRoPXMud2lkdGg9YSxhPW4ud2lkdGgscy53aWR0aD1yLHMubWluV2lkdGg9aSxzLm1heFdpZHRoPW8pKSx2b2lkIDAhPT1hP2ErIiI6YX1mdW5jdGlvbiBfZShlLHQpe3JldHVybntnZXQ6ZnVuY3Rpb24oKXtpZighZSgpKXJldHVybih0aGlzLmdldD10KS5hcHBseSh0aGlzLGFyZ3VtZW50cyk7ZGVsZXRlIHRoaXMuZ2V0fX19dmFyIHplPS9eKG5vbmV8dGFibGUoPyEtY1tlYV0pLispLyxYZT0vXi0tLyxVZT17cG9zaXRpb246ImFic29sdXRlIix2aXNpYmlsaXR5OiJoaWRkZW4iLGRpc3BsYXk6ImJsb2NrIn0sVmU9e2xldHRlclNwYWNpbmc6IjAiLGZvbnRXZWlnaHQ6IjQwMCJ9LEdlPVsiV2Via2l0IiwiTW96IiwibXMiXSxZZT1yLmNyZWF0ZUVsZW1lbnQoImRpdiIpLnN0eWxlO2Z1bmN0aW9uIFFlKGUpe2lmKGUgaW4gWWUpcmV0dXJuIGU7dmFyIHQ9ZVswXS50b1VwcGVyQ2FzZSgpK2Uuc2xpY2UoMSksbj1HZS5sZW5ndGg7d2hpbGUobi0tKWlmKChlPUdlW25dK3QpaW4gWWUpcmV0dXJuIGV9ZnVuY3Rpb24gSmUoZSl7dmFyIHQ9dy5jc3NQcm9wc1tlXTtyZXR1cm4gdHx8KHQ9dy5jc3NQcm9wc1tlXT1RZShlKXx8ZSksdH1mdW5jdGlvbiBLZShlLHQsbil7dmFyIHI9aWUuZXhlYyh0KTtyZXR1cm4gcj9NYXRoLm1heCgwLHJbMl0tKG58fDApKSsoclszXXx8InB4Iik6dH1mdW5jdGlvbiBaZShlLHQsbixyLGksbyl7dmFyIGE9IndpZHRoIj09PXQ/MTowLHM9MCx1PTA7aWYobj09PShyPyJib3JkZXIiOiJjb250ZW50IikpcmV0dXJuIDA7Zm9yKDthPDQ7YSs9MikibWFyZ2luIj09PW4mJih1Kz13LmNzcyhlLG4rb2VbYV0sITAsaSkpLHI/KCJjb250ZW50Ij09PW4mJih1LT13LmNzcyhlLCJwYWRkaW5nIitvZVthXSwhMCxpKSksIm1hcmdpbiIhPT1uJiYodS09dy5jc3MoZSwiYm9yZGVyIitvZVthXSsiV2lkdGgiLCEwLGkpKSk6KHUrPXcuY3NzKGUsInBhZGRpbmciK29lW2FdLCEwLGkpLCJwYWRkaW5nIiE9PW4/dSs9dy5jc3MoZSwiYm9yZGVyIitvZVthXSsiV2lkdGgiLCEwLGkpOnMrPXcuY3NzKGUsImJvcmRlciIrb2VbYV0rIldpZHRoIiwhMCxpKSk7cmV0dXJuIXImJm8+PTAmJih1Kz1NYXRoLm1heCgwLE1hdGguY2VpbChlWyJvZmZzZXQiK3RbMF0udG9VcHBlckNhc2UoKSt0LnNsaWNlKDEpXS1vLXUtcy0uNSkpKSx1fWZ1bmN0aW9uIGV0KGUsdCxuKXt2YXIgcj0kZShlKSxpPUZlKGUsdCxyKSxvPSJib3JkZXItYm94Ij09PXcuY3NzKGUsImJveFNpemluZyIsITEsciksYT1vO2lmKFdlLnRlc3QoaSkpe2lmKCFuKXJldHVybiBpO2k9ImF1dG8ifXJldHVybiBhPWEmJihoLmJveFNpemluZ1JlbGlhYmxlKCl8fGk9PT1lLnN0eWxlW3RdKSwoImF1dG8iPT09aXx8IXBhcnNlRmxvYXQoaSkmJiJpbmxpbmUiPT09dy5jc3MoZSwiZGlzcGxheSIsITEscikpJiYoaT1lWyJvZmZzZXQiK3RbMF0udG9VcHBlckNhc2UoKSt0LnNsaWNlKDEpXSxhPSEwKSwoaT1wYXJzZUZsb2F0KGkpfHwwKStaZShlLHQsbnx8KG8/ImJvcmRlciI6ImNvbnRlbnQiKSxhLHIsaSkrInB4In13LmV4dGVuZCh7Y3NzSG9va3M6e29wYWNpdHk6e2dldDpmdW5jdGlvbihlLHQpe2lmKHQpe3ZhciBuPUZlKGUsIm9wYWNpdHkiKTtyZXR1cm4iIj09PW4/IjEiOm59fX19LGNzc051bWJlcjp7YW5pbWF0aW9uSXRlcmF0aW9uQ291bnQ6ITAsY29sdW1uQ291bnQ6ITAsZmlsbE9wYWNpdHk6ITAsZmxleEdyb3c6ITAsZmxleFNocmluazohMCxmb250V2VpZ2h0OiEwLGxpbmVIZWlnaHQ6ITAsb3BhY2l0eTohMCxvcmRlcjohMCxvcnBoYW5zOiEwLHdpZG93czohMCx6SW5kZXg6ITAsem9vbTohMH0sY3NzUHJvcHM6e30sc3R5bGU6ZnVuY3Rpb24oZSx0LG4scil7aWYoZSYmMyE9PWUubm9kZVR5cGUmJjghPT1lLm5vZGVUeXBlJiZlLnN0eWxlKXt2YXIgaSxvLGEscz1HKHQpLHU9WGUudGVzdCh0KSxsPWUuc3R5bGU7aWYodXx8KHQ9SmUocykpLGE9dy5jc3NIb29rc1t0XXx8dy5jc3NIb29rc1tzXSx2b2lkIDA9PT1uKXJldHVybiBhJiYiZ2V0ImluIGEmJnZvaWQgMCE9PShpPWEuZ2V0KGUsITEscikpP2k6bFt0XTsic3RyaW5nIj09KG89dHlwZW9mIG4pJiYoaT1pZS5leGVjKG4pKSYmaVsxXSYmKG49dWUoZSx0LGkpLG89Im51bWJlciIpLG51bGwhPW4mJm49PT1uJiYoIm51bWJlciI9PT1vJiYobis9aSYmaVszXXx8KHcuY3NzTnVtYmVyW3NdPyIiOiJweCIpKSxoLmNsZWFyQ2xvbmVTdHlsZXx8IiIhPT1ufHwwIT09dC5pbmRleE9mKCJiYWNrZ3JvdW5kIil8fChsW3RdPSJpbmhlcml0IiksYSYmInNldCJpbiBhJiZ2b2lkIDA9PT0obj1hLnNldChlLG4scikpfHwodT9sLnNldFByb3BlcnR5KHQsbik6bFt0XT1uKSl9fSxjc3M6ZnVuY3Rpb24oZSx0LG4scil7dmFyIGksbyxhLHM9Ryh0KTtyZXR1cm4gWGUudGVzdCh0KXx8KHQ9SmUocykpLChhPXcuY3NzSG9va3NbdF18fHcuY3NzSG9va3Nbc10pJiYiZ2V0ImluIGEmJihpPWEuZ2V0KGUsITAsbikpLHZvaWQgMD09PWkmJihpPUZlKGUsdCxyKSksIm5vcm1hbCI9PT1pJiZ0IGluIFZlJiYoaT1WZVt0XSksIiI9PT1ufHxuPyhvPXBhcnNlRmxvYXQoaSksITA9PT1ufHxpc0Zpbml0ZShvKT9vfHwwOmkpOml9fSksdy5lYWNoKFsiaGVpZ2h0Iiwid2lkdGgiXSxmdW5jdGlvbihlLHQpe3cuY3NzSG9va3NbdF09e2dldDpmdW5jdGlvbihlLG4scil7aWYobilyZXR1cm4hemUudGVzdCh3LmNzcyhlLCJkaXNwbGF5IikpfHxlLmdldENsaWVudFJlY3RzKCkubGVuZ3RoJiZlLmdldEJvdW5kaW5nQ2xpZW50UmVjdCgpLndpZHRoP2V0KGUsdCxyKTpzZShlLFVlLGZ1bmN0aW9uKCl7cmV0dXJuIGV0KGUsdCxyKX0pfSxzZXQ6ZnVuY3Rpb24oZSxuLHIpe3ZhciBpLG89JGUoZSksYT0iYm9yZGVyLWJveCI9PT13LmNzcyhlLCJib3hTaXppbmciLCExLG8pLHM9ciYmWmUoZSx0LHIsYSxvKTtyZXR1cm4gYSYmaC5zY3JvbGxib3hTaXplKCk9PT1vLnBvc2l0aW9uJiYocy09TWF0aC5jZWlsKGVbIm9mZnNldCIrdFswXS50b1VwcGVyQ2FzZSgpK3Quc2xpY2UoMSldLXBhcnNlRmxvYXQob1t0XSktWmUoZSx0LCJib3JkZXIiLCExLG8pLS41KSkscyYmKGk9aWUuZXhlYyhuKSkmJiJweCIhPT0oaVszXXx8InB4IikmJihlLnN0eWxlW3RdPW4sbj13LmNzcyhlLHQpKSxLZShlLG4scyl9fX0pLHcuY3NzSG9va3MubWFyZ2luTGVmdD1fZShoLnJlbGlhYmxlTWFyZ2luTGVmdCxmdW5jdGlvbihlLHQpe2lmKHQpcmV0dXJuKHBhcnNlRmxvYXQoRmUoZSwibWFyZ2luTGVmdCIpKXx8ZS5nZXRCb3VuZGluZ0NsaWVudFJlY3QoKS5sZWZ0LXNlKGUse21hcmdpbkxlZnQ6MH0sZnVuY3Rpb24oKXtyZXR1cm4gZS5nZXRCb3VuZGluZ0NsaWVudFJlY3QoKS5sZWZ0fSkpKyJweCJ9KSx3LmVhY2goe21hcmdpbjoiIixwYWRkaW5nOiIiLGJvcmRlcjoiV2lkdGgifSxmdW5jdGlvbihlLHQpe3cuY3NzSG9va3NbZSt0XT17ZXhwYW5kOmZ1bmN0aW9uKG4pe2Zvcih2YXIgcj0wLGk9e30sbz0ic3RyaW5nIj09dHlwZW9mIG4/bi5zcGxpdCgiICIpOltuXTtyPDQ7cisrKWlbZStvZVtyXSt0XT1vW3JdfHxvW3ItMl18fG9bMF07cmV0dXJuIGl9fSwibWFyZ2luIiE9PWUmJih3LmNzc0hvb2tzW2UrdF0uc2V0PUtlKX0pLHcuZm4uZXh0ZW5kKHtjc3M6ZnVuY3Rpb24oZSx0KXtyZXR1cm4geih0aGlzLGZ1bmN0aW9uKGUsdCxuKXt2YXIgcixpLG89e30sYT0wO2lmKEFycmF5LmlzQXJyYXkodCkpe2ZvcihyPSRlKGUpLGk9dC5sZW5ndGg7YTxpO2ErKylvW3RbYV1dPXcuY3NzKGUsdFthXSwhMSxyKTtyZXR1cm4gb31yZXR1cm4gdm9pZCAwIT09bj93LnN0eWxlKGUsdCxuKTp3LmNzcyhlLHQpfSxlLHQsYXJndW1lbnRzLmxlbmd0aD4xKX19KTtmdW5jdGlvbiB0dChlLHQsbixyLGkpe3JldHVybiBuZXcgdHQucHJvdG90eXBlLmluaXQoZSx0LG4scixpKX13LlR3ZWVuPXR0LHR0LnByb3RvdHlwZT17Y29uc3RydWN0b3I6dHQsaW5pdDpmdW5jdGlvbihlLHQsbixyLGksbyl7dGhpcy5lbGVtPWUsdGhpcy5wcm9wPW4sdGhpcy5lYXNpbmc9aXx8dy5lYXNpbmcuX2RlZmF1bHQsdGhpcy5vcHRpb25zPXQsdGhpcy5zdGFydD10aGlzLm5vdz10aGlzLmN1cigpLHRoaXMuZW5kPXIsdGhpcy51bml0PW98fCh3LmNzc051bWJlcltuXT8iIjoicHgiKX0sY3VyOmZ1bmN0aW9uKCl7dmFyIGU9dHQucHJvcEhvb2tzW3RoaXMucHJvcF07cmV0dXJuIGUmJmUuZ2V0P2UuZ2V0KHRoaXMpOnR0LnByb3BIb29rcy5fZGVmYXVsdC5nZXQodGhpcyl9LHJ1bjpmdW5jdGlvbihlKXt2YXIgdCxuPXR0LnByb3BIb29rc1t0aGlzLnByb3BdO3JldHVybiB0aGlzLm9wdGlvbnMuZHVyYXRpb24/dGhpcy5wb3M9dD13LmVhc2luZ1t0aGlzLmVhc2luZ10oZSx0aGlzLm9wdGlvbnMuZHVyYXRpb24qZSwwLDEsdGhpcy5vcHRpb25zLmR1cmF0aW9uKTp0aGlzLnBvcz10PWUsdGhpcy5ub3c9KHRoaXMuZW5kLXRoaXMuc3RhcnQpKnQrdGhpcy5zdGFydCx0aGlzLm9wdGlvbnMuc3RlcCYmdGhpcy5vcHRpb25zLnN0ZXAuY2FsbCh0aGlzLmVsZW0sdGhpcy5ub3csdGhpcyksbiYmbi5zZXQ/bi5zZXQodGhpcyk6dHQucHJvcEhvb2tzLl9kZWZhdWx0LnNldCh0aGlzKSx0aGlzfX0sdHQucHJvdG90eXBlLmluaXQucHJvdG90eXBlPXR0LnByb3RvdHlwZSx0dC5wcm9wSG9va3M9e19kZWZhdWx0OntnZXQ6ZnVuY3Rpb24oZSl7dmFyIHQ7cmV0dXJuIDEhPT1lLmVsZW0ubm9kZVR5cGV8fG51bGwhPWUuZWxlbVtlLnByb3BdJiZudWxsPT1lLmVsZW0uc3R5bGVbZS5wcm9wXT9lLmVsZW1bZS5wcm9wXToodD13LmNzcyhlLmVsZW0sZS5wcm9wLCIiKSkmJiJhdXRvIiE9PXQ/dDowfSxzZXQ6ZnVuY3Rpb24oZSl7dy5meC5zdGVwW2UucHJvcF0/dy5meC5zdGVwW2UucHJvcF0oZSk6MSE9PWUuZWxlbS5ub2RlVHlwZXx8bnVsbD09ZS5lbGVtLnN0eWxlW3cuY3NzUHJvcHNbZS5wcm9wXV0mJiF3LmNzc0hvb2tzW2UucHJvcF0/ZS5lbGVtW2UucHJvcF09ZS5ub3c6dy5zdHlsZShlLmVsZW0sZS5wcm9wLGUubm93K2UudW5pdCl9fX0sdHQucHJvcEhvb2tzLnNjcm9sbFRvcD10dC5wcm9wSG9va3Muc2Nyb2xsTGVmdD17c2V0OmZ1bmN0aW9uKGUpe2UuZWxlbS5ub2RlVHlwZSYmZS5lbGVtLnBhcmVudE5vZGUmJihlLmVsZW1bZS5wcm9wXT1lLm5vdyl9fSx3LmVhc2luZz17bGluZWFyOmZ1bmN0aW9uKGUpe3JldHVybiBlfSxzd2luZzpmdW5jdGlvbihlKXtyZXR1cm4uNS1NYXRoLmNvcyhlKk1hdGguUEkpLzJ9LF9kZWZhdWx0OiJzd2luZyJ9LHcuZng9dHQucHJvdG90eXBlLmluaXQsdy5meC5zdGVwPXt9O3ZhciBudCxydCxpdD0vXig/OnRvZ2dsZXxzaG93fGhpZGUpJC8sb3Q9L3F1ZXVlSG9va3MkLztmdW5jdGlvbiBhdCgpe3J0JiYoITE9PT1yLmhpZGRlbiYmZS5yZXF1ZXN0QW5pbWF0aW9uRnJhbWU/ZS5yZXF1ZXN0QW5pbWF0aW9uRnJhbWUoYXQpOmUuc2V0VGltZW91dChhdCx3LmZ4LmludGVydmFsKSx3LmZ4LnRpY2soKSl9ZnVuY3Rpb24gc3QoKXtyZXR1cm4gZS5zZXRUaW1lb3V0KGZ1bmN0aW9uKCl7bnQ9dm9pZCAwfSksbnQ9RGF0ZS5ub3coKX1mdW5jdGlvbiB1dChlLHQpe3ZhciBuLHI9MCxpPXtoZWlnaHQ6ZX07Zm9yKHQ9dD8xOjA7cjw0O3IrPTItdClpWyJtYXJnaW4iKyhuPW9lW3JdKV09aVsicGFkZGluZyIrbl09ZTtyZXR1cm4gdCYmKGkub3BhY2l0eT1pLndpZHRoPWUpLGl9ZnVuY3Rpb24gbHQoZSx0LG4pe2Zvcih2YXIgcixpPShwdC50d2VlbmVyc1t0XXx8W10pLmNvbmNhdChwdC50d2VlbmVyc1siKiJdKSxvPTAsYT1pLmxlbmd0aDtvPGE7bysrKWlmKHI9aVtvXS5jYWxsKG4sdCxlKSlyZXR1cm4gcn1mdW5jdGlvbiBjdChlLHQsbil7dmFyIHIsaSxvLGEscyx1LGwsYyxmPSJ3aWR0aCJpbiB0fHwiaGVpZ2h0ImluIHQscD10aGlzLGQ9e30saD1lLnN0eWxlLGc9ZS5ub2RlVHlwZSYmYWUoZSkseT1KLmdldChlLCJmeHNob3ciKTtuLnF1ZXVlfHwobnVsbD09KGE9dy5fcXVldWVIb29rcyhlLCJmeCIpKS51bnF1ZXVlZCYmKGEudW5xdWV1ZWQ9MCxzPWEuZW1wdHkuZmlyZSxhLmVtcHR5LmZpcmU9ZnVuY3Rpb24oKXthLnVucXVldWVkfHxzKCl9KSxhLnVucXVldWVkKysscC5hbHdheXMoZnVuY3Rpb24oKXtwLmFsd2F5cyhmdW5jdGlvbigpe2EudW5xdWV1ZWQtLSx3LnF1ZXVlKGUsImZ4IikubGVuZ3RofHxhLmVtcHR5LmZpcmUoKX0pfSkpO2ZvcihyIGluIHQpaWYoaT10W3JdLGl0LnRlc3QoaSkpe2lmKGRlbGV0ZSB0W3JdLG89b3x8InRvZ2dsZSI9PT1pLGk9PT0oZz8iaGlkZSI6InNob3ciKSl7aWYoInNob3ciIT09aXx8IXl8fHZvaWQgMD09PXlbcl0pY29udGludWU7Zz0hMH1kW3JdPXkmJnlbcl18fHcuc3R5bGUoZSxyKX1pZigodT0hdy5pc0VtcHR5T2JqZWN0KHQpKXx8IXcuaXNFbXB0eU9iamVjdChkKSl7ZiYmMT09PWUubm9kZVR5cGUmJihuLm92ZXJmbG93PVtoLm92ZXJmbG93LGgub3ZlcmZsb3dYLGgub3ZlcmZsb3dZXSxudWxsPT0obD15JiZ5LmRpc3BsYXkpJiYobD1KLmdldChlLCJkaXNwbGF5IikpLCJub25lIj09PShjPXcuY3NzKGUsImRpc3BsYXkiKSkmJihsP2M9bDooZmUoW2VdLCEwKSxsPWUuc3R5bGUuZGlzcGxheXx8bCxjPXcuY3NzKGUsImRpc3BsYXkiKSxmZShbZV0pKSksKCJpbmxpbmUiPT09Y3x8ImlubGluZS1ibG9jayI9PT1jJiZudWxsIT1sKSYmIm5vbmUiPT09dy5jc3MoZSwiZmxvYXQiKSYmKHV8fChwLmRvbmUoZnVuY3Rpb24oKXtoLmRpc3BsYXk9bH0pLG51bGw9PWwmJihjPWguZGlzcGxheSxsPSJub25lIj09PWM/IiI6YykpLGguZGlzcGxheT0iaW5saW5lLWJsb2NrIikpLG4ub3ZlcmZsb3cmJihoLm92ZXJmbG93PSJoaWRkZW4iLHAuYWx3YXlzKGZ1bmN0aW9uKCl7aC5vdmVyZmxvdz1uLm92ZXJmbG93WzBdLGgub3ZlcmZsb3dYPW4ub3ZlcmZsb3dbMV0saC5vdmVyZmxvd1k9bi5vdmVyZmxvd1syXX0pKSx1PSExO2ZvcihyIGluIGQpdXx8KHk/ImhpZGRlbiJpbiB5JiYoZz15LmhpZGRlbik6eT1KLmFjY2VzcyhlLCJmeHNob3ciLHtkaXNwbGF5Omx9KSxvJiYoeS5oaWRkZW49IWcpLGcmJmZlKFtlXSwhMCkscC5kb25lKGZ1bmN0aW9uKCl7Z3x8ZmUoW2VdKSxKLnJlbW92ZShlLCJmeHNob3ciKTtmb3IociBpbiBkKXcuc3R5bGUoZSxyLGRbcl0pfSkpLHU9bHQoZz95W3JdOjAscixwKSxyIGluIHl8fCh5W3JdPXUuc3RhcnQsZyYmKHUuZW5kPXUuc3RhcnQsdS5zdGFydD0wKSl9fWZ1bmN0aW9uIGZ0KGUsdCl7dmFyIG4scixpLG8sYTtmb3IobiBpbiBlKWlmKHI9RyhuKSxpPXRbcl0sbz1lW25dLEFycmF5LmlzQXJyYXkobykmJihpPW9bMV0sbz1lW25dPW9bMF0pLG4hPT1yJiYoZVtyXT1vLGRlbGV0ZSBlW25dKSwoYT13LmNzc0hvb2tzW3JdKSYmImV4cGFuZCJpbiBhKXtvPWEuZXhwYW5kKG8pLGRlbGV0ZSBlW3JdO2ZvcihuIGluIG8pbiBpbiBlfHwoZVtuXT1vW25dLHRbbl09aSl9ZWxzZSB0W3JdPWl9ZnVuY3Rpb24gcHQoZSx0LG4pe3ZhciByLGksbz0wLGE9cHQucHJlZmlsdGVycy5sZW5ndGgscz13LkRlZmVycmVkKCkuYWx3YXlzKGZ1bmN0aW9uKCl7ZGVsZXRlIHUuZWxlbX0pLHU9ZnVuY3Rpb24oKXtpZihpKXJldHVybiExO2Zvcih2YXIgdD1udHx8c3QoKSxuPU1hdGgubWF4KDAsbC5zdGFydFRpbWUrbC5kdXJhdGlvbi10KSxyPTEtKG4vbC5kdXJhdGlvbnx8MCksbz0wLGE9bC50d2VlbnMubGVuZ3RoO288YTtvKyspbC50d2VlbnNbb10ucnVuKHIpO3JldHVybiBzLm5vdGlmeVdpdGgoZSxbbCxyLG5dKSxyPDEmJmE/bjooYXx8cy5ub3RpZnlXaXRoKGUsW2wsMSwwXSkscy5yZXNvbHZlV2l0aChlLFtsXSksITEpfSxsPXMucHJvbWlzZSh7ZWxlbTplLHByb3BzOncuZXh0ZW5kKHt9LHQpLG9wdHM6dy5leHRlbmQoITAse3NwZWNpYWxFYXNpbmc6e30sZWFzaW5nOncuZWFzaW5nLl9kZWZhdWx0fSxuKSxvcmlnaW5hbFByb3BlcnRpZXM6dCxvcmlnaW5hbE9wdGlvbnM6bixzdGFydFRpbWU6bnR8fHN0KCksZHVyYXRpb246bi5kdXJhdGlvbix0d2VlbnM6W10sY3JlYXRlVHdlZW46ZnVuY3Rpb24odCxuKXt2YXIgcj13LlR3ZWVuKGUsbC5vcHRzLHQsbixsLm9wdHMuc3BlY2lhbEVhc2luZ1t0XXx8bC5vcHRzLmVhc2luZyk7cmV0dXJuIGwudHdlZW5zLnB1c2gocikscn0sc3RvcDpmdW5jdGlvbih0KXt2YXIgbj0wLHI9dD9sLnR3ZWVucy5sZW5ndGg6MDtpZihpKXJldHVybiB0aGlzO2ZvcihpPSEwO248cjtuKyspbC50d2VlbnNbbl0ucnVuKDEpO3JldHVybiB0PyhzLm5vdGlmeVdpdGgoZSxbbCwxLDBdKSxzLnJlc29sdmVXaXRoKGUsW2wsdF0pKTpzLnJlamVjdFdpdGgoZSxbbCx0XSksdGhpc319KSxjPWwucHJvcHM7Zm9yKGZ0KGMsbC5vcHRzLnNwZWNpYWxFYXNpbmcpO288YTtvKyspaWYocj1wdC5wcmVmaWx0ZXJzW29dLmNhbGwobCxlLGMsbC5vcHRzKSlyZXR1cm4gZyhyLnN0b3ApJiYody5fcXVldWVIb29rcyhsLmVsZW0sbC5vcHRzLnF1ZXVlKS5zdG9wPXIuc3RvcC5iaW5kKHIpKSxyO3JldHVybiB3Lm1hcChjLGx0LGwpLGcobC5vcHRzLnN0YXJ0KSYmbC5vcHRzLnN0YXJ0LmNhbGwoZSxsKSxsLnByb2dyZXNzKGwub3B0cy5wcm9ncmVzcykuZG9uZShsLm9wdHMuZG9uZSxsLm9wdHMuY29tcGxldGUpLmZhaWwobC5vcHRzLmZhaWwpLmFsd2F5cyhsLm9wdHMuYWx3YXlzKSx3LmZ4LnRpbWVyKHcuZXh0ZW5kKHUse2VsZW06ZSxhbmltOmwscXVldWU6bC5vcHRzLnF1ZXVlfSkpLGx9dy5BbmltYXRpb249dy5leHRlbmQocHQse3R3ZWVuZXJzOnsiKiI6W2Z1bmN0aW9uKGUsdCl7dmFyIG49dGhpcy5jcmVhdGVUd2VlbihlLHQpO3JldHVybiB1ZShuLmVsZW0sZSxpZS5leGVjKHQpLG4pLG59XX0sdHdlZW5lcjpmdW5jdGlvbihlLHQpe2coZSk/KHQ9ZSxlPVsiKiJdKTplPWUubWF0Y2goTSk7Zm9yKHZhciBuLHI9MCxpPWUubGVuZ3RoO3I8aTtyKyspbj1lW3JdLHB0LnR3ZWVuZXJzW25dPXB0LnR3ZWVuZXJzW25dfHxbXSxwdC50d2VlbmVyc1tuXS51bnNoaWZ0KHQpfSxwcmVmaWx0ZXJzOltjdF0scHJlZmlsdGVyOmZ1bmN0aW9uKGUsdCl7dD9wdC5wcmVmaWx0ZXJzLnVuc2hpZnQoZSk6cHQucHJlZmlsdGVycy5wdXNoKGUpfX0pLHcuc3BlZWQ9ZnVuY3Rpb24oZSx0LG4pe3ZhciByPWUmJiJvYmplY3QiPT10eXBlb2YgZT93LmV4dGVuZCh7fSxlKTp7Y29tcGxldGU6bnx8IW4mJnR8fGcoZSkmJmUsZHVyYXRpb246ZSxlYXNpbmc6biYmdHx8dCYmIWcodCkmJnR9O3JldHVybiB3LmZ4Lm9mZj9yLmR1cmF0aW9uPTA6Im51bWJlciIhPXR5cGVvZiByLmR1cmF0aW9uJiYoci5kdXJhdGlvbiBpbiB3LmZ4LnNwZWVkcz9yLmR1cmF0aW9uPXcuZnguc3BlZWRzW3IuZHVyYXRpb25dOnIuZHVyYXRpb249dy5meC5zcGVlZHMuX2RlZmF1bHQpLG51bGwhPXIucXVldWUmJiEwIT09ci5xdWV1ZXx8KHIucXVldWU9ImZ4Iiksci5vbGQ9ci5jb21wbGV0ZSxyLmNvbXBsZXRlPWZ1bmN0aW9uKCl7ZyhyLm9sZCkmJnIub2xkLmNhbGwodGhpcyksci5xdWV1ZSYmdy5kZXF1ZXVlKHRoaXMsci5xdWV1ZSl9LHJ9LHcuZm4uZXh0ZW5kKHtmYWRlVG86ZnVuY3Rpb24oZSx0LG4scil7cmV0dXJuIHRoaXMuZmlsdGVyKGFlKS5jc3MoIm9wYWNpdHkiLDApLnNob3coKS5lbmQoKS5hbmltYXRlKHtvcGFjaXR5OnR9LGUsbixyKX0sYW5pbWF0ZTpmdW5jdGlvbihlLHQsbixyKXt2YXIgaT13LmlzRW1wdHlPYmplY3QoZSksbz13LnNwZWVkKHQsbixyKSxhPWZ1bmN0aW9uKCl7dmFyIHQ9cHQodGhpcyx3LmV4dGVuZCh7fSxlKSxvKTsoaXx8Si5nZXQodGhpcywiZmluaXNoIikpJiZ0LnN0b3AoITApfTtyZXR1cm4gYS5maW5pc2g9YSxpfHwhMT09PW8ucXVldWU/dGhpcy5lYWNoKGEpOnRoaXMucXVldWUoby5xdWV1ZSxhKX0sc3RvcDpmdW5jdGlvbihlLHQsbil7dmFyIHI9ZnVuY3Rpb24oZSl7dmFyIHQ9ZS5zdG9wO2RlbGV0ZSBlLnN0b3AsdChuKX07cmV0dXJuInN0cmluZyIhPXR5cGVvZiBlJiYobj10LHQ9ZSxlPXZvaWQgMCksdCYmITEhPT1lJiZ0aGlzLnF1ZXVlKGV8fCJmeCIsW10pLHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciB0PSEwLGk9bnVsbCE9ZSYmZSsicXVldWVIb29rcyIsbz13LnRpbWVycyxhPUouZ2V0KHRoaXMpO2lmKGkpYVtpXSYmYVtpXS5zdG9wJiZyKGFbaV0pO2Vsc2UgZm9yKGkgaW4gYSlhW2ldJiZhW2ldLnN0b3AmJm90LnRlc3QoaSkmJnIoYVtpXSk7Zm9yKGk9by5sZW5ndGg7aS0tOylvW2ldLmVsZW0hPT10aGlzfHxudWxsIT1lJiZvW2ldLnF1ZXVlIT09ZXx8KG9baV0uYW5pbS5zdG9wKG4pLHQ9ITEsby5zcGxpY2UoaSwxKSk7IXQmJm58fHcuZGVxdWV1ZSh0aGlzLGUpfSl9LGZpbmlzaDpmdW5jdGlvbihlKXtyZXR1cm4hMSE9PWUmJihlPWV8fCJmeCIpLHRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciB0LG49Si5nZXQodGhpcykscj1uW2UrInF1ZXVlIl0saT1uW2UrInF1ZXVlSG9va3MiXSxvPXcudGltZXJzLGE9cj9yLmxlbmd0aDowO2ZvcihuLmZpbmlzaD0hMCx3LnF1ZXVlKHRoaXMsZSxbXSksaSYmaS5zdG9wJiZpLnN0b3AuY2FsbCh0aGlzLCEwKSx0PW8ubGVuZ3RoO3QtLTspb1t0XS5lbGVtPT09dGhpcyYmb1t0XS5xdWV1ZT09PWUmJihvW3RdLmFuaW0uc3RvcCghMCksby5zcGxpY2UodCwxKSk7Zm9yKHQ9MDt0PGE7dCsrKXJbdF0mJnJbdF0uZmluaXNoJiZyW3RdLmZpbmlzaC5jYWxsKHRoaXMpO2RlbGV0ZSBuLmZpbmlzaH0pfX0pLHcuZWFjaChbInRvZ2dsZSIsInNob3ciLCJoaWRlIl0sZnVuY3Rpb24oZSx0KXt2YXIgbj13LmZuW3RdO3cuZm5bdF09ZnVuY3Rpb24oZSxyLGkpe3JldHVybiBudWxsPT1lfHwiYm9vbGVhbiI9PXR5cGVvZiBlP24uYXBwbHkodGhpcyxhcmd1bWVudHMpOnRoaXMuYW5pbWF0ZSh1dCh0LCEwKSxlLHIsaSl9fSksdy5lYWNoKHtzbGlkZURvd246dXQoInNob3ciKSxzbGlkZVVwOnV0KCJoaWRlIiksc2xpZGVUb2dnbGU6dXQoInRvZ2dsZSIpLGZhZGVJbjp7b3BhY2l0eToic2hvdyJ9LGZhZGVPdXQ6e29wYWNpdHk6ImhpZGUifSxmYWRlVG9nZ2xlOntvcGFjaXR5OiJ0b2dnbGUifX0sZnVuY3Rpb24oZSx0KXt3LmZuW2VdPWZ1bmN0aW9uKGUsbixyKXtyZXR1cm4gdGhpcy5hbmltYXRlKHQsZSxuLHIpfX0pLHcudGltZXJzPVtdLHcuZngudGljaz1mdW5jdGlvbigpe3ZhciBlLHQ9MCxuPXcudGltZXJzO2ZvcihudD1EYXRlLm5vdygpO3Q8bi5sZW5ndGg7dCsrKShlPW5bdF0pKCl8fG5bdF0hPT1lfHxuLnNwbGljZSh0LS0sMSk7bi5sZW5ndGh8fHcuZnguc3RvcCgpLG50PXZvaWQgMH0sdy5meC50aW1lcj1mdW5jdGlvbihlKXt3LnRpbWVycy5wdXNoKGUpLHcuZnguc3RhcnQoKX0sdy5meC5pbnRlcnZhbD0xMyx3LmZ4LnN0YXJ0PWZ1bmN0aW9uKCl7cnR8fChydD0hMCxhdCgpKX0sdy5meC5zdG9wPWZ1bmN0aW9uKCl7cnQ9bnVsbH0sdy5meC5zcGVlZHM9e3Nsb3c6NjAwLGZhc3Q6MjAwLF9kZWZhdWx0OjQwMH0sdy5mbi5kZWxheT1mdW5jdGlvbih0LG4pe3JldHVybiB0PXcuZng/dy5meC5zcGVlZHNbdF18fHQ6dCxuPW58fCJmeCIsdGhpcy5xdWV1ZShuLGZ1bmN0aW9uKG4scil7dmFyIGk9ZS5zZXRUaW1lb3V0KG4sdCk7ci5zdG9wPWZ1bmN0aW9uKCl7ZS5jbGVhclRpbWVvdXQoaSl9fSl9LGZ1bmN0aW9uKCl7dmFyIGU9ci5jcmVhdGVFbGVtZW50KCJpbnB1dCIpLHQ9ci5jcmVhdGVFbGVtZW50KCJzZWxlY3QiKS5hcHBlbmRDaGlsZChyLmNyZWF0ZUVsZW1lbnQoIm9wdGlvbiIpKTtlLnR5cGU9ImNoZWNrYm94IixoLmNoZWNrT249IiIhPT1lLnZhbHVlLGgub3B0U2VsZWN0ZWQ9dC5zZWxlY3RlZCwoZT1yLmNyZWF0ZUVsZW1lbnQoImlucHV0IikpLnZhbHVlPSJ0IixlLnR5cGU9InJhZGlvIixoLnJhZGlvVmFsdWU9InQiPT09ZS52YWx1ZX0oKTt2YXIgZHQsaHQ9dy5leHByLmF0dHJIYW5kbGU7dy5mbi5leHRlbmQoe2F0dHI6ZnVuY3Rpb24oZSx0KXtyZXR1cm4geih0aGlzLHcuYXR0cixlLHQsYXJndW1lbnRzLmxlbmd0aD4xKX0scmVtb3ZlQXR0cjpmdW5jdGlvbihlKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7dy5yZW1vdmVBdHRyKHRoaXMsZSl9KX19KSx3LmV4dGVuZCh7YXR0cjpmdW5jdGlvbihlLHQsbil7dmFyIHIsaSxvPWUubm9kZVR5cGU7aWYoMyE9PW8mJjghPT1vJiYyIT09bylyZXR1cm4idW5kZWZpbmVkIj09dHlwZW9mIGUuZ2V0QXR0cmlidXRlP3cucHJvcChlLHQsbik6KDE9PT1vJiZ3LmlzWE1MRG9jKGUpfHwoaT13LmF0dHJIb29rc1t0LnRvTG93ZXJDYXNlKCldfHwody5leHByLm1hdGNoLmJvb2wudGVzdCh0KT9kdDp2b2lkIDApKSx2b2lkIDAhPT1uP251bGw9PT1uP3ZvaWQgdy5yZW1vdmVBdHRyKGUsdCk6aSYmInNldCJpbiBpJiZ2b2lkIDAhPT0ocj1pLnNldChlLG4sdCkpP3I6KGUuc2V0QXR0cmlidXRlKHQsbisiIiksbik6aSYmImdldCJpbiBpJiZudWxsIT09KHI9aS5nZXQoZSx0KSk/cjpudWxsPT0ocj13LmZpbmQuYXR0cihlLHQpKT92b2lkIDA6cil9LGF0dHJIb29rczp7dHlwZTp7c2V0OmZ1bmN0aW9uKGUsdCl7aWYoIWgucmFkaW9WYWx1ZSYmInJhZGlvIj09PXQmJk4oZSwiaW5wdXQiKSl7dmFyIG49ZS52YWx1ZTtyZXR1cm4gZS5zZXRBdHRyaWJ1dGUoInR5cGUiLHQpLG4mJihlLnZhbHVlPW4pLHR9fX19LHJlbW92ZUF0dHI6ZnVuY3Rpb24oZSx0KXt2YXIgbixyPTAsaT10JiZ0Lm1hdGNoKE0pO2lmKGkmJjE9PT1lLm5vZGVUeXBlKXdoaWxlKG49aVtyKytdKWUucmVtb3ZlQXR0cmlidXRlKG4pfX0pLGR0PXtzZXQ6ZnVuY3Rpb24oZSx0LG4pe3JldHVybiExPT09dD93LnJlbW92ZUF0dHIoZSxuKTplLnNldEF0dHJpYnV0ZShuLG4pLG59fSx3LmVhY2gody5leHByLm1hdGNoLmJvb2wuc291cmNlLm1hdGNoKC9cdysvZyksZnVuY3Rpb24oZSx0KXt2YXIgbj1odFt0XXx8dy5maW5kLmF0dHI7aHRbdF09ZnVuY3Rpb24oZSx0LHIpe3ZhciBpLG8sYT10LnRvTG93ZXJDYXNlKCk7cmV0dXJuIHJ8fChvPWh0W2FdLGh0W2FdPWksaT1udWxsIT1uKGUsdCxyKT9hOm51bGwsaHRbYV09byksaX19KTt2YXIgZ3Q9L14oPzppbnB1dHxzZWxlY3R8dGV4dGFyZWF8YnV0dG9uKSQvaSx5dD0vXig/OmF8YXJlYSkkL2k7dy5mbi5leHRlbmQoe3Byb3A6ZnVuY3Rpb24oZSx0KXtyZXR1cm4geih0aGlzLHcucHJvcCxlLHQsYXJndW1lbnRzLmxlbmd0aD4xKX0scmVtb3ZlUHJvcDpmdW5jdGlvbihlKXtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKCl7ZGVsZXRlIHRoaXNbdy5wcm9wRml4W2VdfHxlXX0pfX0pLHcuZXh0ZW5kKHtwcm9wOmZ1bmN0aW9uKGUsdCxuKXt2YXIgcixpLG89ZS5ub2RlVHlwZTtpZigzIT09byYmOCE9PW8mJjIhPT1vKXJldHVybiAxPT09byYmdy5pc1hNTERvYyhlKXx8KHQ9dy5wcm9wRml4W3RdfHx0LGk9dy5wcm9wSG9va3NbdF0pLHZvaWQgMCE9PW4/aSYmInNldCJpbiBpJiZ2b2lkIDAhPT0ocj1pLnNldChlLG4sdCkpP3I6ZVt0XT1uOmkmJiJnZXQiaW4gaSYmbnVsbCE9PShyPWkuZ2V0KGUsdCkpP3I6ZVt0XX0scHJvcEhvb2tzOnt0YWJJbmRleDp7Z2V0OmZ1bmN0aW9uKGUpe3ZhciB0PXcuZmluZC5hdHRyKGUsInRhYmluZGV4Iik7cmV0dXJuIHQ/cGFyc2VJbnQodCwxMCk6Z3QudGVzdChlLm5vZGVOYW1lKXx8eXQudGVzdChlLm5vZGVOYW1lKSYmZS5ocmVmPzA6LTF9fX0scHJvcEZpeDp7ImZvciI6Imh0bWxGb3IiLCJjbGFzcyI6ImNsYXNzTmFtZSJ9fSksaC5vcHRTZWxlY3RlZHx8KHcucHJvcEhvb2tzLnNlbGVjdGVkPXtnZXQ6ZnVuY3Rpb24oZSl7dmFyIHQ9ZS5wYXJlbnROb2RlO3JldHVybiB0JiZ0LnBhcmVudE5vZGUmJnQucGFyZW50Tm9kZS5zZWxlY3RlZEluZGV4LG51bGx9LHNldDpmdW5jdGlvbihlKXt2YXIgdD1lLnBhcmVudE5vZGU7dCYmKHQuc2VsZWN0ZWRJbmRleCx0LnBhcmVudE5vZGUmJnQucGFyZW50Tm9kZS5zZWxlY3RlZEluZGV4KX19KSx3LmVhY2goWyJ0YWJJbmRleCIsInJlYWRPbmx5IiwibWF4TGVuZ3RoIiwiY2VsbFNwYWNpbmciLCJjZWxsUGFkZGluZyIsInJvd1NwYW4iLCJjb2xTcGFuIiwidXNlTWFwIiwiZnJhbWVCb3JkZXIiLCJjb250ZW50RWRpdGFibGUiXSxmdW5jdGlvbigpe3cucHJvcEZpeFt0aGlzLnRvTG93ZXJDYXNlKCldPXRoaXN9KTtmdW5jdGlvbiB2dChlKXtyZXR1cm4oZS5tYXRjaChNKXx8W10pLmpvaW4oIiAiKX1mdW5jdGlvbiBtdChlKXtyZXR1cm4gZS5nZXRBdHRyaWJ1dGUmJmUuZ2V0QXR0cmlidXRlKCJjbGFzcyIpfHwiIn1mdW5jdGlvbiB4dChlKXtyZXR1cm4gQXJyYXkuaXNBcnJheShlKT9lOiJzdHJpbmciPT10eXBlb2YgZT9lLm1hdGNoKE0pfHxbXTpbXX13LmZuLmV4dGVuZCh7YWRkQ2xhc3M6ZnVuY3Rpb24oZSl7dmFyIHQsbixyLGksbyxhLHMsdT0wO2lmKGcoZSkpcmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbih0KXt3KHRoaXMpLmFkZENsYXNzKGUuY2FsbCh0aGlzLHQsbXQodGhpcykpKX0pO2lmKCh0PXh0KGUpKS5sZW5ndGgpd2hpbGUobj10aGlzW3UrK10paWYoaT1tdChuKSxyPTE9PT1uLm5vZGVUeXBlJiYiICIrdnQoaSkrIiAiKXthPTA7d2hpbGUobz10W2ErK10pci5pbmRleE9mKCIgIitvKyIgIik8MCYmKHIrPW8rIiAiKTtpIT09KHM9dnQocikpJiZuLnNldEF0dHJpYnV0ZSgiY2xhc3MiLHMpfXJldHVybiB0aGlzfSxyZW1vdmVDbGFzczpmdW5jdGlvbihlKXt2YXIgdCxuLHIsaSxvLGEscyx1PTA7aWYoZyhlKSlyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKHQpe3codGhpcykucmVtb3ZlQ2xhc3MoZS5jYWxsKHRoaXMsdCxtdCh0aGlzKSkpfSk7aWYoIWFyZ3VtZW50cy5sZW5ndGgpcmV0dXJuIHRoaXMuYXR0cigiY2xhc3MiLCIiKTtpZigodD14dChlKSkubGVuZ3RoKXdoaWxlKG49dGhpc1t1KytdKWlmKGk9bXQobikscj0xPT09bi5ub2RlVHlwZSYmIiAiK3Z0KGkpKyIgIil7YT0wO3doaWxlKG89dFthKytdKXdoaWxlKHIuaW5kZXhPZigiICIrbysiICIpPi0xKXI9ci5yZXBsYWNlKCIgIitvKyIgIiwiICIpO2khPT0ocz12dChyKSkmJm4uc2V0QXR0cmlidXRlKCJjbGFzcyIscyl9cmV0dXJuIHRoaXN9LHRvZ2dsZUNsYXNzOmZ1bmN0aW9uKGUsdCl7dmFyIG49dHlwZW9mIGUscj0ic3RyaW5nIj09PW58fEFycmF5LmlzQXJyYXkoZSk7cmV0dXJuImJvb2xlYW4iPT10eXBlb2YgdCYmcj90P3RoaXMuYWRkQ2xhc3MoZSk6dGhpcy5yZW1vdmVDbGFzcyhlKTpnKGUpP3RoaXMuZWFjaChmdW5jdGlvbihuKXt3KHRoaXMpLnRvZ2dsZUNsYXNzKGUuY2FsbCh0aGlzLG4sbXQodGhpcyksdCksdCl9KTp0aGlzLmVhY2goZnVuY3Rpb24oKXt2YXIgdCxpLG8sYTtpZihyKXtpPTAsbz13KHRoaXMpLGE9eHQoZSk7d2hpbGUodD1hW2krK10pby5oYXNDbGFzcyh0KT9vLnJlbW92ZUNsYXNzKHQpOm8uYWRkQ2xhc3ModCl9ZWxzZSB2b2lkIDAhPT1lJiYiYm9vbGVhbiIhPT1ufHwoKHQ9bXQodGhpcykpJiZKLnNldCh0aGlzLCJfX2NsYXNzTmFtZV9fIix0KSx0aGlzLnNldEF0dHJpYnV0ZSYmdGhpcy5zZXRBdHRyaWJ1dGUoImNsYXNzIix0fHwhMT09PWU/IiI6Si5nZXQodGhpcywiX19jbGFzc05hbWVfXyIpfHwiIikpfSl9LGhhc0NsYXNzOmZ1bmN0aW9uKGUpe3ZhciB0LG4scj0wO3Q9IiAiK2UrIiAiO3doaWxlKG49dGhpc1tyKytdKWlmKDE9PT1uLm5vZGVUeXBlJiYoIiAiK3Z0KG10KG4pKSsiICIpLmluZGV4T2YodCk+LTEpcmV0dXJuITA7cmV0dXJuITF9fSk7dmFyIGJ0PS9cci9nO3cuZm4uZXh0ZW5kKHt2YWw6ZnVuY3Rpb24oZSl7dmFyIHQsbixyLGk9dGhpc1swXTt7aWYoYXJndW1lbnRzLmxlbmd0aClyZXR1cm4gcj1nKGUpLHRoaXMuZWFjaChmdW5jdGlvbihuKXt2YXIgaTsxPT09dGhpcy5ub2RlVHlwZSYmKG51bGw9PShpPXI/ZS5jYWxsKHRoaXMsbix3KHRoaXMpLnZhbCgpKTplKT9pPSIiOiJudW1iZXIiPT10eXBlb2YgaT9pKz0iIjpBcnJheS5pc0FycmF5KGkpJiYoaT13Lm1hcChpLGZ1bmN0aW9uKGUpe3JldHVybiBudWxsPT1lPyIiOmUrIiJ9KSksKHQ9dy52YWxIb29rc1t0aGlzLnR5cGVdfHx3LnZhbEhvb2tzW3RoaXMubm9kZU5hbWUudG9Mb3dlckNhc2UoKV0pJiYic2V0ImluIHQmJnZvaWQgMCE9PXQuc2V0KHRoaXMsaSwidmFsdWUiKXx8KHRoaXMudmFsdWU9aSkpfSk7aWYoaSlyZXR1cm4odD13LnZhbEhvb2tzW2kudHlwZV18fHcudmFsSG9va3NbaS5ub2RlTmFtZS50b0xvd2VyQ2FzZSgpXSkmJiJnZXQiaW4gdCYmdm9pZCAwIT09KG49dC5nZXQoaSwidmFsdWUiKSk/bjoic3RyaW5nIj09dHlwZW9mKG49aS52YWx1ZSk/bi5yZXBsYWNlKGJ0LCIiKTpudWxsPT1uPyIiOm59fX0pLHcuZXh0ZW5kKHt2YWxIb29rczp7b3B0aW9uOntnZXQ6ZnVuY3Rpb24oZSl7dmFyIHQ9dy5maW5kLmF0dHIoZSwidmFsdWUiKTtyZXR1cm4gbnVsbCE9dD90OnZ0KHcudGV4dChlKSl9fSxzZWxlY3Q6e2dldDpmdW5jdGlvbihlKXt2YXIgdCxuLHIsaT1lLm9wdGlvbnMsbz1lLnNlbGVjdGVkSW5kZXgsYT0ic2VsZWN0LW9uZSI9PT1lLnR5cGUscz1hP251bGw6W10sdT1hP28rMTppLmxlbmd0aDtmb3Iocj1vPDA/dTphP286MDtyPHU7cisrKWlmKCgobj1pW3JdKS5zZWxlY3RlZHx8cj09PW8pJiYhbi5kaXNhYmxlZCYmKCFuLnBhcmVudE5vZGUuZGlzYWJsZWR8fCFOKG4ucGFyZW50Tm9kZSwib3B0Z3JvdXAiKSkpe2lmKHQ9dyhuKS52YWwoKSxhKXJldHVybiB0O3MucHVzaCh0KX1yZXR1cm4gc30sc2V0OmZ1bmN0aW9uKGUsdCl7dmFyIG4scixpPWUub3B0aW9ucyxvPXcubWFrZUFycmF5KHQpLGE9aS5sZW5ndGg7d2hpbGUoYS0tKSgocj1pW2FdKS5zZWxlY3RlZD13LmluQXJyYXkody52YWxIb29rcy5vcHRpb24uZ2V0KHIpLG8pPi0xKSYmKG49ITApO3JldHVybiBufHwoZS5zZWxlY3RlZEluZGV4PS0xKSxvfX19fSksdy5lYWNoKFsicmFkaW8iLCJjaGVja2JveCJdLGZ1bmN0aW9uKCl7dy52YWxIb29rc1t0aGlzXT17c2V0OmZ1bmN0aW9uKGUsdCl7aWYoQXJyYXkuaXNBcnJheSh0KSlyZXR1cm4gZS5jaGVja2VkPXcuaW5BcnJheSh3KGUpLnZhbCgpLHQpPi0xfX0saC5jaGVja09ufHwody52YWxIb29rc1t0aGlzXS5nZXQ9ZnVuY3Rpb24oZSl7cmV0dXJuIG51bGw9PT1lLmdldEF0dHJpYnV0ZSgidmFsdWUiKT8ib24iOmUudmFsdWV9KX0pLGguZm9jdXNpbj0ib25mb2N1c2luImluIGU7dmFyIHd0PS9eKD86Zm9jdXNpbmZvY3VzfGZvY3Vzb3V0Ymx1cikkLyxUdD1mdW5jdGlvbihlKXtlLnN0b3BQcm9wYWdhdGlvbigpfTt3LmV4dGVuZCh3LmV2ZW50LHt0cmlnZ2VyOmZ1bmN0aW9uKHQsbixpLG8pe3ZhciBhLHMsdSxsLGMscCxkLGgsdj1baXx8cl0sbT1mLmNhbGwodCwidHlwZSIpP3QudHlwZTp0LHg9Zi5jYWxsKHQsIm5hbWVzcGFjZSIpP3QubmFtZXNwYWNlLnNwbGl0KCIuIik6W107aWYocz1oPXU9aT1pfHxyLDMhPT1pLm5vZGVUeXBlJiY4IT09aS5ub2RlVHlwZSYmIXd0LnRlc3QobSt3LmV2ZW50LnRyaWdnZXJlZCkmJihtLmluZGV4T2YoIi4iKT4tMSYmKG09KHg9bS5zcGxpdCgiLiIpKS5zaGlmdCgpLHguc29ydCgpKSxjPW0uaW5kZXhPZigiOiIpPDAmJiJvbiIrbSx0PXRbdy5leHBhbmRvXT90Om5ldyB3LkV2ZW50KG0sIm9iamVjdCI9PXR5cGVvZiB0JiZ0KSx0LmlzVHJpZ2dlcj1vPzI6Myx0Lm5hbWVzcGFjZT14LmpvaW4oIi4iKSx0LnJuYW1lc3BhY2U9dC5uYW1lc3BhY2U/bmV3IFJlZ0V4cCgiKF58XFwuKSIreC5qb2luKCJcXC4oPzouKlxcLnwpIikrIihcXC58JCkiKTpudWxsLHQucmVzdWx0PXZvaWQgMCx0LnRhcmdldHx8KHQudGFyZ2V0PWkpLG49bnVsbD09bj9bdF06dy5tYWtlQXJyYXkobixbdF0pLGQ9dy5ldmVudC5zcGVjaWFsW21dfHx7fSxvfHwhZC50cmlnZ2VyfHwhMSE9PWQudHJpZ2dlci5hcHBseShpLG4pKSl7aWYoIW8mJiFkLm5vQnViYmxlJiYheShpKSl7Zm9yKGw9ZC5kZWxlZ2F0ZVR5cGV8fG0sd3QudGVzdChsK20pfHwocz1zLnBhcmVudE5vZGUpO3M7cz1zLnBhcmVudE5vZGUpdi5wdXNoKHMpLHU9czt1PT09KGkub3duZXJEb2N1bWVudHx8cikmJnYucHVzaCh1LmRlZmF1bHRWaWV3fHx1LnBhcmVudFdpbmRvd3x8ZSl9YT0wO3doaWxlKChzPXZbYSsrXSkmJiF0LmlzUHJvcGFnYXRpb25TdG9wcGVkKCkpaD1zLHQudHlwZT1hPjE/bDpkLmJpbmRUeXBlfHxtLChwPShKLmdldChzLCJldmVudHMiKXx8e30pW3QudHlwZV0mJkouZ2V0KHMsImhhbmRsZSIpKSYmcC5hcHBseShzLG4pLChwPWMmJnNbY10pJiZwLmFwcGx5JiZZKHMpJiYodC5yZXN1bHQ9cC5hcHBseShzLG4pLCExPT09dC5yZXN1bHQmJnQucHJldmVudERlZmF1bHQoKSk7cmV0dXJuIHQudHlwZT1tLG98fHQuaXNEZWZhdWx0UHJldmVudGVkKCl8fGQuX2RlZmF1bHQmJiExIT09ZC5fZGVmYXVsdC5hcHBseSh2LnBvcCgpLG4pfHwhWShpKXx8YyYmZyhpW21dKSYmIXkoaSkmJigodT1pW2NdKSYmKGlbY109bnVsbCksdy5ldmVudC50cmlnZ2VyZWQ9bSx0LmlzUHJvcGFnYXRpb25TdG9wcGVkKCkmJmguYWRkRXZlbnRMaXN0ZW5lcihtLFR0KSxpW21dKCksdC5pc1Byb3BhZ2F0aW9uU3RvcHBlZCgpJiZoLnJlbW92ZUV2ZW50TGlzdGVuZXIobSxUdCksdy5ldmVudC50cmlnZ2VyZWQ9dm9pZCAwLHUmJihpW2NdPXUpKSx0LnJlc3VsdH19LHNpbXVsYXRlOmZ1bmN0aW9uKGUsdCxuKXt2YXIgcj13LmV4dGVuZChuZXcgdy5FdmVudCxuLHt0eXBlOmUsaXNTaW11bGF0ZWQ6ITB9KTt3LmV2ZW50LnRyaWdnZXIocixudWxsLHQpfX0pLHcuZm4uZXh0ZW5kKHt0cmlnZ2VyOmZ1bmN0aW9uKGUsdCl7cmV0dXJuIHRoaXMuZWFjaChmdW5jdGlvbigpe3cuZXZlbnQudHJpZ2dlcihlLHQsdGhpcyl9KX0sdHJpZ2dlckhhbmRsZXI6ZnVuY3Rpb24oZSx0KXt2YXIgbj10aGlzWzBdO2lmKG4pcmV0dXJuIHcuZXZlbnQudHJpZ2dlcihlLHQsbiwhMCl9fSksaC5mb2N1c2lufHx3LmVhY2goe2ZvY3VzOiJmb2N1c2luIixibHVyOiJmb2N1c291dCJ9LGZ1bmN0aW9uKGUsdCl7dmFyIG49ZnVuY3Rpb24oZSl7dy5ldmVudC5zaW11bGF0ZSh0LGUudGFyZ2V0LHcuZXZlbnQuZml4KGUpKX07dy5ldmVudC5zcGVjaWFsW3RdPXtzZXR1cDpmdW5jdGlvbigpe3ZhciByPXRoaXMub3duZXJEb2N1bWVudHx8dGhpcyxpPUouYWNjZXNzKHIsdCk7aXx8ci5hZGRFdmVudExpc3RlbmVyKGUsbiwhMCksSi5hY2Nlc3Mocix0LChpfHwwKSsxKX0sdGVhcmRvd246ZnVuY3Rpb24oKXt2YXIgcj10aGlzLm93bmVyRG9jdW1lbnR8fHRoaXMsaT1KLmFjY2VzcyhyLHQpLTE7aT9KLmFjY2VzcyhyLHQsaSk6KHIucmVtb3ZlRXZlbnRMaXN0ZW5lcihlLG4sITApLEoucmVtb3ZlKHIsdCkpfX19KTt2YXIgQ3Q9ZS5sb2NhdGlvbixFdD1EYXRlLm5vdygpLGt0PS9cPy87dy5wYXJzZVhNTD1mdW5jdGlvbih0KXt2YXIgbjtpZighdHx8InN0cmluZyIhPXR5cGVvZiB0KXJldHVybiBudWxsO3RyeXtuPShuZXcgZS5ET01QYXJzZXIpLnBhcnNlRnJvbVN0cmluZyh0LCJ0ZXh0L3htbCIpfWNhdGNoKGUpe249dm9pZCAwfXJldHVybiBuJiYhbi5nZXRFbGVtZW50c0J5VGFnTmFtZSgicGFyc2VyZXJyb3IiKS5sZW5ndGh8fHcuZXJyb3IoIkludmFsaWQgWE1MOiAiK3QpLG59O3ZhciBTdD0vXFtcXSQvLER0PS9ccj9cbi9nLE50PS9eKD86c3VibWl0fGJ1dHRvbnxpbWFnZXxyZXNldHxmaWxlKSQvaSxBdD0vXig/OmlucHV0fHNlbGVjdHx0ZXh0YXJlYXxrZXlnZW4pL2k7ZnVuY3Rpb24ganQoZSx0LG4scil7dmFyIGk7aWYoQXJyYXkuaXNBcnJheSh0KSl3LmVhY2godCxmdW5jdGlvbih0LGkpe258fFN0LnRlc3QoZSk/cihlLGkpOmp0KGUrIlsiKygib2JqZWN0Ij09dHlwZW9mIGkmJm51bGwhPWk/dDoiIikrIl0iLGksbixyKX0pO2Vsc2UgaWYobnx8Im9iamVjdCIhPT14KHQpKXIoZSx0KTtlbHNlIGZvcihpIGluIHQpanQoZSsiWyIraSsiXSIsdFtpXSxuLHIpfXcucGFyYW09ZnVuY3Rpb24oZSx0KXt2YXIgbixyPVtdLGk9ZnVuY3Rpb24oZSx0KXt2YXIgbj1nKHQpP3QoKTp0O3Jbci5sZW5ndGhdPWVuY29kZVVSSUNvbXBvbmVudChlKSsiPSIrZW5jb2RlVVJJQ29tcG9uZW50KG51bGw9PW4/IiI6bil9O2lmKEFycmF5LmlzQXJyYXkoZSl8fGUuanF1ZXJ5JiYhdy5pc1BsYWluT2JqZWN0KGUpKXcuZWFjaChlLGZ1bmN0aW9uKCl7aSh0aGlzLm5hbWUsdGhpcy52YWx1ZSl9KTtlbHNlIGZvcihuIGluIGUpanQobixlW25dLHQsaSk7cmV0dXJuIHIuam9pbigiJiIpfSx3LmZuLmV4dGVuZCh7c2VyaWFsaXplOmZ1bmN0aW9uKCl7cmV0dXJuIHcucGFyYW0odGhpcy5zZXJpYWxpemVBcnJheSgpKX0sc2VyaWFsaXplQXJyYXk6ZnVuY3Rpb24oKXtyZXR1cm4gdGhpcy5tYXAoZnVuY3Rpb24oKXt2YXIgZT13LnByb3AodGhpcywiZWxlbWVudHMiKTtyZXR1cm4gZT93Lm1ha2VBcnJheShlKTp0aGlzfSkuZmlsdGVyKGZ1bmN0aW9uKCl7dmFyIGU9dGhpcy50eXBlO3JldHVybiB0aGlzLm5hbWUmJiF3KHRoaXMpLmlzKCI6ZGlzYWJsZWQiKSYmQXQudGVzdCh0aGlzLm5vZGVOYW1lKSYmIU50LnRlc3QoZSkmJih0aGlzLmNoZWNrZWR8fCFwZS50ZXN0KGUpKX0pLm1hcChmdW5jdGlvbihlLHQpe3ZhciBuPXcodGhpcykudmFsKCk7cmV0dXJuIG51bGw9PW4/bnVsbDpBcnJheS5pc0FycmF5KG4pP3cubWFwKG4sZnVuY3Rpb24oZSl7cmV0dXJue25hbWU6dC5uYW1lLHZhbHVlOmUucmVwbGFjZShEdCwiXHJcbiIpfX0pOntuYW1lOnQubmFtZSx2YWx1ZTpuLnJlcGxhY2UoRHQsIlxyXG4iKX19KS5nZXQoKX19KTt2YXIgcXQ9LyUyMC9nLEx0PS8jLiokLyxIdD0vKFs/Jl0pXz1bXiZdKi8sT3Q9L14oLio/KTpbIFx0XSooW15cclxuXSopJC9nbSxQdD0vXig/OmFib3V0fGFwcHxhcHAtc3RvcmFnZXwuKy1leHRlbnNpb258ZmlsZXxyZXN8d2lkZ2V0KTokLyxNdD0vXig/OkdFVHxIRUFEKSQvLFJ0PS9eXC9cLy8sSXQ9e30sV3Q9e30sJHQ9IiovIi5jb25jYXQoIioiKSxCdD1yLmNyZWF0ZUVsZW1lbnQoImEiKTtCdC5ocmVmPUN0LmhyZWY7ZnVuY3Rpb24gRnQoZSl7cmV0dXJuIGZ1bmN0aW9uKHQsbil7InN0cmluZyIhPXR5cGVvZiB0JiYobj10LHQ9IioiKTt2YXIgcixpPTAsbz10LnRvTG93ZXJDYXNlKCkubWF0Y2goTSl8fFtdO2lmKGcobikpd2hpbGUocj1vW2krK10pIisiPT09clswXT8ocj1yLnNsaWNlKDEpfHwiKiIsKGVbcl09ZVtyXXx8W10pLnVuc2hpZnQobikpOihlW3JdPWVbcl18fFtdKS5wdXNoKG4pfX1mdW5jdGlvbiBfdChlLHQsbixyKXt2YXIgaT17fSxvPWU9PT1XdDtmdW5jdGlvbiBhKHMpe3ZhciB1O3JldHVybiBpW3NdPSEwLHcuZWFjaChlW3NdfHxbXSxmdW5jdGlvbihlLHMpe3ZhciBsPXModCxuLHIpO3JldHVybiJzdHJpbmciIT10eXBlb2YgbHx8b3x8aVtsXT9vPyEodT1sKTp2b2lkIDA6KHQuZGF0YVR5cGVzLnVuc2hpZnQobCksYShsKSwhMSl9KSx1fXJldHVybiBhKHQuZGF0YVR5cGVzWzBdKXx8IWlbIioiXSYmYSgiKiIpfWZ1bmN0aW9uIHp0KGUsdCl7dmFyIG4scixpPXcuYWpheFNldHRpbmdzLmZsYXRPcHRpb25zfHx7fTtmb3IobiBpbiB0KXZvaWQgMCE9PXRbbl0mJigoaVtuXT9lOnJ8fChyPXt9KSlbbl09dFtuXSk7cmV0dXJuIHImJncuZXh0ZW5kKCEwLGUsciksZX1mdW5jdGlvbiBYdChlLHQsbil7dmFyIHIsaSxvLGEscz1lLmNvbnRlbnRzLHU9ZS5kYXRhVHlwZXM7d2hpbGUoIioiPT09dVswXSl1LnNoaWZ0KCksdm9pZCAwPT09ciYmKHI9ZS5taW1lVHlwZXx8dC5nZXRSZXNwb25zZUhlYWRlcigiQ29udGVudC1UeXBlIikpO2lmKHIpZm9yKGkgaW4gcylpZihzW2ldJiZzW2ldLnRlc3Qocikpe3UudW5zaGlmdChpKTticmVha31pZih1WzBdaW4gbilvPXVbMF07ZWxzZXtmb3IoaSBpbiBuKXtpZighdVswXXx8ZS5jb252ZXJ0ZXJzW2krIiAiK3VbMF1dKXtvPWk7YnJlYWt9YXx8KGE9aSl9bz1vfHxhfWlmKG8pcmV0dXJuIG8hPT11WzBdJiZ1LnVuc2hpZnQobyksbltvXX1mdW5jdGlvbiBVdChlLHQsbixyKXt2YXIgaSxvLGEscyx1LGw9e30sYz1lLmRhdGFUeXBlcy5zbGljZSgpO2lmKGNbMV0pZm9yKGEgaW4gZS5jb252ZXJ0ZXJzKWxbYS50b0xvd2VyQ2FzZSgpXT1lLmNvbnZlcnRlcnNbYV07bz1jLnNoaWZ0KCk7d2hpbGUobylpZihlLnJlc3BvbnNlRmllbGRzW29dJiYobltlLnJlc3BvbnNlRmllbGRzW29dXT10KSwhdSYmciYmZS5kYXRhRmlsdGVyJiYodD1lLmRhdGFGaWx0ZXIodCxlLmRhdGFUeXBlKSksdT1vLG89Yy5zaGlmdCgpKWlmKCIqIj09PW8pbz11O2Vsc2UgaWYoIioiIT09dSYmdSE9PW8pe2lmKCEoYT1sW3UrIiAiK29dfHxsWyIqICIrb10pKWZvcihpIGluIGwpaWYoKHM9aS5zcGxpdCgiICIpKVsxXT09PW8mJihhPWxbdSsiICIrc1swXV18fGxbIiogIitzWzBdXSkpeyEwPT09YT9hPWxbaV06ITAhPT1sW2ldJiYobz1zWzBdLGMudW5zaGlmdChzWzFdKSk7YnJlYWt9aWYoITAhPT1hKWlmKGEmJmVbInRocm93cyJdKXQ9YSh0KTtlbHNlIHRyeXt0PWEodCl9Y2F0Y2goZSl7cmV0dXJue3N0YXRlOiJwYXJzZXJlcnJvciIsZXJyb3I6YT9lOiJObyBjb252ZXJzaW9uIGZyb20gIit1KyIgdG8gIitvfX19cmV0dXJue3N0YXRlOiJzdWNjZXNzIixkYXRhOnR9fXcuZXh0ZW5kKHthY3RpdmU6MCxsYXN0TW9kaWZpZWQ6e30sZXRhZzp7fSxhamF4U2V0dGluZ3M6e3VybDpDdC5ocmVmLHR5cGU6IkdFVCIsaXNMb2NhbDpQdC50ZXN0KEN0LnByb3RvY29sKSxnbG9iYWw6ITAscHJvY2Vzc0RhdGE6ITAsYXN5bmM6ITAsY29udGVudFR5cGU6ImFwcGxpY2F0aW9uL3gtd3d3LWZvcm0tdXJsZW5jb2RlZDsgY2hhcnNldD1VVEYtOCIsYWNjZXB0czp7IioiOiR0LHRleHQ6InRleHQvcGxhaW4iLGh0bWw6InRleHQvaHRtbCIseG1sOiJhcHBsaWNhdGlvbi94bWwsIHRleHQveG1sIixqc29uOiJhcHBsaWNhdGlvbi9qc29uLCB0ZXh0L2phdmFzY3JpcHQifSxjb250ZW50czp7eG1sOi9cYnhtbFxiLyxodG1sOi9cYmh0bWwvLGpzb246L1xianNvblxiL30scmVzcG9uc2VGaWVsZHM6e3htbDoicmVzcG9uc2VYTUwiLHRleHQ6InJlc3BvbnNlVGV4dCIsanNvbjoicmVzcG9uc2VKU09OIn0sY29udmVydGVyczp7IiogdGV4dCI6U3RyaW5nLCJ0ZXh0IGh0bWwiOiEwLCJ0ZXh0IGpzb24iOkpTT04ucGFyc2UsInRleHQgeG1sIjp3LnBhcnNlWE1MfSxmbGF0T3B0aW9uczp7dXJsOiEwLGNvbnRleHQ6ITB9fSxhamF4U2V0dXA6ZnVuY3Rpb24oZSx0KXtyZXR1cm4gdD96dCh6dChlLHcuYWpheFNldHRpbmdzKSx0KTp6dCh3LmFqYXhTZXR0aW5ncyxlKX0sYWpheFByZWZpbHRlcjpGdChJdCksYWpheFRyYW5zcG9ydDpGdChXdCksYWpheDpmdW5jdGlvbih0LG4peyJvYmplY3QiPT10eXBlb2YgdCYmKG49dCx0PXZvaWQgMCksbj1ufHx7fTt2YXIgaSxvLGEscyx1LGwsYyxmLHAsZCxoPXcuYWpheFNldHVwKHt9LG4pLGc9aC5jb250ZXh0fHxoLHk9aC5jb250ZXh0JiYoZy5ub2RlVHlwZXx8Zy5qcXVlcnkpP3coZyk6dy5ldmVudCx2PXcuRGVmZXJyZWQoKSxtPXcuQ2FsbGJhY2tzKCJvbmNlIG1lbW9yeSIpLHg9aC5zdGF0dXNDb2RlfHx7fSxiPXt9LFQ9e30sQz0iY2FuY2VsZWQiLEU9e3JlYWR5U3RhdGU6MCxnZXRSZXNwb25zZUhlYWRlcjpmdW5jdGlvbihlKXt2YXIgdDtpZihjKXtpZighcyl7cz17fTt3aGlsZSh0PU90LmV4ZWMoYSkpc1t0WzFdLnRvTG93ZXJDYXNlKCldPXRbMl19dD1zW2UudG9Mb3dlckNhc2UoKV19cmV0dXJuIG51bGw9PXQ/bnVsbDp0fSxnZXRBbGxSZXNwb25zZUhlYWRlcnM6ZnVuY3Rpb24oKXtyZXR1cm4gYz9hOm51bGx9LHNldFJlcXVlc3RIZWFkZXI6ZnVuY3Rpb24oZSx0KXtyZXR1cm4gbnVsbD09YyYmKGU9VFtlLnRvTG93ZXJDYXNlKCldPVRbZS50b0xvd2VyQ2FzZSgpXXx8ZSxiW2VdPXQpLHRoaXN9LG92ZXJyaWRlTWltZVR5cGU6ZnVuY3Rpb24oZSl7cmV0dXJuIG51bGw9PWMmJihoLm1pbWVUeXBlPWUpLHRoaXN9LHN0YXR1c0NvZGU6ZnVuY3Rpb24oZSl7dmFyIHQ7aWYoZSlpZihjKUUuYWx3YXlzKGVbRS5zdGF0dXNdKTtlbHNlIGZvcih0IGluIGUpeFt0XT1beFt0XSxlW3RdXTtyZXR1cm4gdGhpc30sYWJvcnQ6ZnVuY3Rpb24oZSl7dmFyIHQ9ZXx8QztyZXR1cm4gaSYmaS5hYm9ydCh0KSxrKDAsdCksdGhpc319O2lmKHYucHJvbWlzZShFKSxoLnVybD0oKHR8fGgudXJsfHxDdC5ocmVmKSsiIikucmVwbGFjZShSdCxDdC5wcm90b2NvbCsiLy8iKSxoLnR5cGU9bi5tZXRob2R8fG4udHlwZXx8aC5tZXRob2R8fGgudHlwZSxoLmRhdGFUeXBlcz0oaC5kYXRhVHlwZXx8IioiKS50b0xvd2VyQ2FzZSgpLm1hdGNoKE0pfHxbIiJdLG51bGw9PWguY3Jvc3NEb21haW4pe2w9ci5jcmVhdGVFbGVtZW50KCJhIik7dHJ5e2wuaHJlZj1oLnVybCxsLmhyZWY9bC5ocmVmLGguY3Jvc3NEb21haW49QnQucHJvdG9jb2wrIi8vIitCdC5ob3N0IT1sLnByb3RvY29sKyIvLyIrbC5ob3N0fWNhdGNoKGUpe2guY3Jvc3NEb21haW49ITB9fWlmKGguZGF0YSYmaC5wcm9jZXNzRGF0YSYmInN0cmluZyIhPXR5cGVvZiBoLmRhdGEmJihoLmRhdGE9dy5wYXJhbShoLmRhdGEsaC50cmFkaXRpb25hbCkpLF90KEl0LGgsbixFKSxjKXJldHVybiBFOyhmPXcuZXZlbnQmJmguZ2xvYmFsKSYmMD09dy5hY3RpdmUrKyYmdy5ldmVudC50cmlnZ2VyKCJhamF4U3RhcnQiKSxoLnR5cGU9aC50eXBlLnRvVXBwZXJDYXNlKCksaC5oYXNDb250ZW50PSFNdC50ZXN0KGgudHlwZSksbz1oLnVybC5yZXBsYWNlKEx0LCIiKSxoLmhhc0NvbnRlbnQ/aC5kYXRhJiZoLnByb2Nlc3NEYXRhJiYwPT09KGguY29udGVudFR5cGV8fCIiKS5pbmRleE9mKCJhcHBsaWNhdGlvbi94LXd3dy1mb3JtLXVybGVuY29kZWQiKSYmKGguZGF0YT1oLmRhdGEucmVwbGFjZShxdCwiKyIpKTooZD1oLnVybC5zbGljZShvLmxlbmd0aCksaC5kYXRhJiYoaC5wcm9jZXNzRGF0YXx8InN0cmluZyI9PXR5cGVvZiBoLmRhdGEpJiYobys9KGt0LnRlc3Qobyk/IiYiOiI/IikraC5kYXRhLGRlbGV0ZSBoLmRhdGEpLCExPT09aC5jYWNoZSYmKG89by5yZXBsYWNlKEh0LCIkMSIpLGQ9KGt0LnRlc3Qobyk/IiYiOiI/IikrIl89IitFdCsrK2QpLGgudXJsPW8rZCksaC5pZk1vZGlmaWVkJiYody5sYXN0TW9kaWZpZWRbb10mJkUuc2V0UmVxdWVzdEhlYWRlcigiSWYtTW9kaWZpZWQtU2luY2UiLHcubGFzdE1vZGlmaWVkW29dKSx3LmV0YWdbb10mJkUuc2V0UmVxdWVzdEhlYWRlcigiSWYtTm9uZS1NYXRjaCIsdy5ldGFnW29dKSksKGguZGF0YSYmaC5oYXNDb250ZW50JiYhMSE9PWguY29udGVudFR5cGV8fG4uY29udGVudFR5cGUpJiZFLnNldFJlcXVlc3RIZWFkZXIoIkNvbnRlbnQtVHlwZSIsaC5jb250ZW50VHlwZSksRS5zZXRSZXF1ZXN0SGVhZGVyKCJBY2NlcHQiLGguZGF0YVR5cGVzWzBdJiZoLmFjY2VwdHNbaC5kYXRhVHlwZXNbMF1dP2guYWNjZXB0c1toLmRhdGFUeXBlc1swXV0rKCIqIiE9PWguZGF0YVR5cGVzWzBdPyIsICIrJHQrIjsgcT0wLjAxIjoiIik6aC5hY2NlcHRzWyIqIl0pO2ZvcihwIGluIGguaGVhZGVycylFLnNldFJlcXVlc3RIZWFkZXIocCxoLmhlYWRlcnNbcF0pO2lmKGguYmVmb3JlU2VuZCYmKCExPT09aC5iZWZvcmVTZW5kLmNhbGwoZyxFLGgpfHxjKSlyZXR1cm4gRS5hYm9ydCgpO2lmKEM9ImFib3J0IixtLmFkZChoLmNvbXBsZXRlKSxFLmRvbmUoaC5zdWNjZXNzKSxFLmZhaWwoaC5lcnJvciksaT1fdChXdCxoLG4sRSkpe2lmKEUucmVhZHlTdGF0ZT0xLGYmJnkudHJpZ2dlcigiYWpheFNlbmQiLFtFLGhdKSxjKXJldHVybiBFO2guYXN5bmMmJmgudGltZW91dD4wJiYodT1lLnNldFRpbWVvdXQoZnVuY3Rpb24oKXtFLmFib3J0KCJ0aW1lb3V0Iil9LGgudGltZW91dCkpO3RyeXtjPSExLGkuc2VuZChiLGspfWNhdGNoKGUpe2lmKGMpdGhyb3cgZTtrKC0xLGUpfX1lbHNlIGsoLTEsIk5vIFRyYW5zcG9ydCIpO2Z1bmN0aW9uIGsodCxuLHIscyl7dmFyIGwscCxkLGIsVCxDPW47Y3x8KGM9ITAsdSYmZS5jbGVhclRpbWVvdXQodSksaT12b2lkIDAsYT1zfHwiIixFLnJlYWR5U3RhdGU9dD4wPzQ6MCxsPXQ+PTIwMCYmdDwzMDB8fDMwND09PXQsciYmKGI9WHQoaCxFLHIpKSxiPVV0KGgsYixFLGwpLGw/KGguaWZNb2RpZmllZCYmKChUPUUuZ2V0UmVzcG9uc2VIZWFkZXIoIkxhc3QtTW9kaWZpZWQiKSkmJih3Lmxhc3RNb2RpZmllZFtvXT1UKSwoVD1FLmdldFJlc3BvbnNlSGVhZGVyKCJldGFnIikpJiYody5ldGFnW29dPVQpKSwyMDQ9PT10fHwiSEVBRCI9PT1oLnR5cGU/Qz0ibm9jb250ZW50IjozMDQ9PT10P0M9Im5vdG1vZGlmaWVkIjooQz1iLnN0YXRlLHA9Yi5kYXRhLGw9IShkPWIuZXJyb3IpKSk6KGQ9QywhdCYmQ3x8KEM9ImVycm9yIix0PDAmJih0PTApKSksRS5zdGF0dXM9dCxFLnN0YXR1c1RleHQ9KG58fEMpKyIiLGw/di5yZXNvbHZlV2l0aChnLFtwLEMsRV0pOnYucmVqZWN0V2l0aChnLFtFLEMsZF0pLEUuc3RhdHVzQ29kZSh4KSx4PXZvaWQgMCxmJiZ5LnRyaWdnZXIobD8iYWpheFN1Y2Nlc3MiOiJhamF4RXJyb3IiLFtFLGgsbD9wOmRdKSxtLmZpcmVXaXRoKGcsW0UsQ10pLGYmJih5LnRyaWdnZXIoImFqYXhDb21wbGV0ZSIsW0UsaF0pLC0tdy5hY3RpdmV8fHcuZXZlbnQudHJpZ2dlcigiYWpheFN0b3AiKSkpfXJldHVybiBFfSxnZXRKU09OOmZ1bmN0aW9uKGUsdCxuKXtyZXR1cm4gdy5nZXQoZSx0LG4sImpzb24iKX0sZ2V0U2NyaXB0OmZ1bmN0aW9uKGUsdCl7cmV0dXJuIHcuZ2V0KGUsdm9pZCAwLHQsInNjcmlwdCIpfX0pLHcuZWFjaChbImdldCIsInBvc3QiXSxmdW5jdGlvbihlLHQpe3dbdF09ZnVuY3Rpb24oZSxuLHIsaSl7cmV0dXJuIGcobikmJihpPWl8fHIscj1uLG49dm9pZCAwKSx3LmFqYXgody5leHRlbmQoe3VybDplLHR5cGU6dCxkYXRhVHlwZTppLGRhdGE6bixzdWNjZXNzOnJ9LHcuaXNQbGFpbk9iamVjdChlKSYmZSkpfX0pLHcuX2V2YWxVcmw9ZnVuY3Rpb24oZSl7cmV0dXJuIHcuYWpheCh7dXJsOmUsdHlwZToiR0VUIixkYXRhVHlwZToic2NyaXB0IixjYWNoZTohMCxhc3luYzohMSxnbG9iYWw6ITEsInRocm93cyI6ITB9KX0sdy5mbi5leHRlbmQoe3dyYXBBbGw6ZnVuY3Rpb24oZSl7dmFyIHQ7cmV0dXJuIHRoaXNbMF0mJihnKGUpJiYoZT1lLmNhbGwodGhpc1swXSkpLHQ9dyhlLHRoaXNbMF0ub3duZXJEb2N1bWVudCkuZXEoMCkuY2xvbmUoITApLHRoaXNbMF0ucGFyZW50Tm9kZSYmdC5pbnNlcnRCZWZvcmUodGhpc1swXSksdC5tYXAoZnVuY3Rpb24oKXt2YXIgZT10aGlzO3doaWxlKGUuZmlyc3RFbGVtZW50Q2hpbGQpZT1lLmZpcnN0RWxlbWVudENoaWxkO3JldHVybiBlfSkuYXBwZW5kKHRoaXMpKSx0aGlzfSx3cmFwSW5uZXI6ZnVuY3Rpb24oZSl7cmV0dXJuIGcoZSk/dGhpcy5lYWNoKGZ1bmN0aW9uKHQpe3codGhpcykud3JhcElubmVyKGUuY2FsbCh0aGlzLHQpKX0pOnRoaXMuZWFjaChmdW5jdGlvbigpe3ZhciB0PXcodGhpcyksbj10LmNvbnRlbnRzKCk7bi5sZW5ndGg/bi53cmFwQWxsKGUpOnQuYXBwZW5kKGUpfSl9LHdyYXA6ZnVuY3Rpb24oZSl7dmFyIHQ9ZyhlKTtyZXR1cm4gdGhpcy5lYWNoKGZ1bmN0aW9uKG4pe3codGhpcykud3JhcEFsbCh0P2UuY2FsbCh0aGlzLG4pOmUpfSl9LHVud3JhcDpmdW5jdGlvbihlKXtyZXR1cm4gdGhpcy5wYXJlbnQoZSkubm90KCJib2R5IikuZWFjaChmdW5jdGlvbigpe3codGhpcykucmVwbGFjZVdpdGgodGhpcy5jaGlsZE5vZGVzKX0pLHRoaXN9fSksdy5leHByLnBzZXVkb3MuaGlkZGVuPWZ1bmN0aW9uKGUpe3JldHVybiF3LmV4cHIucHNldWRvcy52aXNpYmxlKGUpfSx3LmV4cHIucHNldWRvcy52aXNpYmxlPWZ1bmN0aW9uKGUpe3JldHVybiEhKGUub2Zmc2V0V2lkdGh8fGUub2Zmc2V0SGVpZ2h0fHxlLmdldENsaWVudFJlY3RzKCkubGVuZ3RoKX0sdy5hamF4U2V0dGluZ3MueGhyPWZ1bmN0aW9uKCl7dHJ5e3JldHVybiBuZXcgZS5YTUxIdHRwUmVxdWVzdH1jYXRjaChlKXt9fTt2YXIgVnQ9ezA6MjAwLDEyMjM6MjA0fSxHdD13LmFqYXhTZXR0aW5ncy54aHIoKTtoLmNvcnM9ISFHdCYmIndpdGhDcmVkZW50aWFscyJpbiBHdCxoLmFqYXg9R3Q9ISFHdCx3LmFqYXhUcmFuc3BvcnQoZnVuY3Rpb24odCl7dmFyIG4scjtpZihoLmNvcnN8fEd0JiYhdC5jcm9zc0RvbWFpbilyZXR1cm57c2VuZDpmdW5jdGlvbihpLG8pe3ZhciBhLHM9dC54aHIoKTtpZihzLm9wZW4odC50eXBlLHQudXJsLHQuYXN5bmMsdC51c2VybmFtZSx0LnBhc3N3b3JkKSx0LnhockZpZWxkcylmb3IoYSBpbiB0LnhockZpZWxkcylzW2FdPXQueGhyRmllbGRzW2FdO3QubWltZVR5cGUmJnMub3ZlcnJpZGVNaW1lVHlwZSYmcy5vdmVycmlkZU1pbWVUeXBlKHQubWltZVR5cGUpLHQuY3Jvc3NEb21haW58fGlbIlgtUmVxdWVzdGVkLVdpdGgiXXx8KGlbIlgtUmVxdWVzdGVkLVdpdGgiXT0iWE1MSHR0cFJlcXVlc3QiKTtmb3IoYSBpbiBpKXMuc2V0UmVxdWVzdEhlYWRlcihhLGlbYV0pO249ZnVuY3Rpb24oZSl7cmV0dXJuIGZ1bmN0aW9uKCl7biYmKG49cj1zLm9ubG9hZD1zLm9uZXJyb3I9cy5vbmFib3J0PXMub250aW1lb3V0PXMub25yZWFkeXN0YXRlY2hhbmdlPW51bGwsImFib3J0Ij09PWU/cy5hYm9ydCgpOiJlcnJvciI9PT1lPyJudW1iZXIiIT10eXBlb2Ygcy5zdGF0dXM/bygwLCJlcnJvciIpOm8ocy5zdGF0dXMscy5zdGF0dXNUZXh0KTpvKFZ0W3Muc3RhdHVzXXx8cy5zdGF0dXMscy5zdGF0dXNUZXh0LCJ0ZXh0IiE9PShzLnJlc3BvbnNlVHlwZXx8InRleHQiKXx8InN0cmluZyIhPXR5cGVvZiBzLnJlc3BvbnNlVGV4dD97YmluYXJ5OnMucmVzcG9uc2V9Ont0ZXh0OnMucmVzcG9uc2VUZXh0fSxzLmdldEFsbFJlc3BvbnNlSGVhZGVycygpKSl9fSxzLm9ubG9hZD1uKCkscj1zLm9uZXJyb3I9cy5vbnRpbWVvdXQ9bigiZXJyb3IiKSx2b2lkIDAhPT1zLm9uYWJvcnQ/cy5vbmFib3J0PXI6cy5vbnJlYWR5c3RhdGVjaGFuZ2U9ZnVuY3Rpb24oKXs0PT09cy5yZWFkeVN0YXRlJiZlLnNldFRpbWVvdXQoZnVuY3Rpb24oKXtuJiZyKCl9KX0sbj1uKCJhYm9ydCIpO3RyeXtzLnNlbmQodC5oYXNDb250ZW50JiZ0LmRhdGF8fG51bGwpfWNhdGNoKGUpe2lmKG4pdGhyb3cgZX19LGFib3J0OmZ1bmN0aW9uKCl7biYmbigpfX19KSx3LmFqYXhQcmVmaWx0ZXIoZnVuY3Rpb24oZSl7ZS5jcm9zc0RvbWFpbiYmKGUuY29udGVudHMuc2NyaXB0PSExKX0pLHcuYWpheFNldHVwKHthY2NlcHRzOntzY3JpcHQ6InRleHQvamF2YXNjcmlwdCwgYXBwbGljYXRpb24vamF2YXNjcmlwdCwgYXBwbGljYXRpb24vZWNtYXNjcmlwdCwgYXBwbGljYXRpb24veC1lY21hc2NyaXB0In0sY29udGVudHM6e3NjcmlwdDovXGIoPzpqYXZhfGVjbWEpc2NyaXB0XGIvfSxjb252ZXJ0ZXJzOnsidGV4dCBzY3JpcHQiOmZ1bmN0aW9uKGUpe3JldHVybiB3Lmdsb2JhbEV2YWwoZSksZX19fSksdy5hamF4UHJlZmlsdGVyKCJzY3JpcHQiLGZ1bmN0aW9uKGUpe3ZvaWQgMD09PWUuY2FjaGUmJihlLmNhY2hlPSExKSxlLmNyb3NzRG9tYWluJiYoZS50eXBlPSJHRVQiKX0pLHcuYWpheFRyYW5zcG9ydCgic2NyaXB0IixmdW5jdGlvbihlKXtpZihlLmNyb3NzRG9tYWluKXt2YXIgdCxuO3JldHVybntzZW5kOmZ1bmN0aW9uKGksbyl7dD13KCI8c2NyaXB0PiIpLnByb3Aoe2NoYXJzZXQ6ZS5zY3JpcHRDaGFyc2V0LHNyYzplLnVybH0pLm9uKCJsb2FkIGVycm9yIixuPWZ1bmN0aW9uKGUpe3QucmVtb3ZlKCksbj1udWxsLGUmJm8oImVycm9yIj09PWUudHlwZT80MDQ6MjAwLGUudHlwZSl9KSxyLmhlYWQuYXBwZW5kQ2hpbGQodFswXSl9LGFib3J0OmZ1bmN0aW9uKCl7biYmbigpfX19fSk7dmFyIFl0PVtdLFF0PS8oPSlcPyg/PSZ8JCl8XD9cPy87dy5hamF4U2V0dXAoe2pzb25wOiJjYWxsYmFjayIsanNvbnBDYWxsYmFjazpmdW5jdGlvbigpe3ZhciBlPVl0LnBvcCgpfHx3LmV4cGFuZG8rIl8iK0V0Kys7cmV0dXJuIHRoaXNbZV09ITAsZX19KSx3LmFqYXhQcmVmaWx0ZXIoImpzb24ganNvbnAiLGZ1bmN0aW9uKHQsbixyKXt2YXIgaSxvLGEscz0hMSE9PXQuanNvbnAmJihRdC50ZXN0KHQudXJsKT8idXJsIjoic3RyaW5nIj09dHlwZW9mIHQuZGF0YSYmMD09PSh0LmNvbnRlbnRUeXBlfHwiIikuaW5kZXhPZigiYXBwbGljYXRpb24veC13d3ctZm9ybS11cmxlbmNvZGVkIikmJlF0LnRlc3QodC5kYXRhKSYmImRhdGEiKTtpZihzfHwianNvbnAiPT09dC5kYXRhVHlwZXNbMF0pcmV0dXJuIGk9dC5qc29ucENhbGxiYWNrPWcodC5qc29ucENhbGxiYWNrKT90Lmpzb25wQ2FsbGJhY2soKTp0Lmpzb25wQ2FsbGJhY2sscz90W3NdPXRbc10ucmVwbGFjZShRdCwiJDEiK2kpOiExIT09dC5qc29ucCYmKHQudXJsKz0oa3QudGVzdCh0LnVybCk/IiYiOiI/IikrdC5qc29ucCsiPSIraSksdC5jb252ZXJ0ZXJzWyJzY3JpcHQganNvbiJdPWZ1bmN0aW9uKCl7cmV0dXJuIGF8fHcuZXJyb3IoaSsiIHdhcyBub3QgY2FsbGVkIiksYVswXX0sdC5kYXRhVHlwZXNbMF09Impzb24iLG89ZVtpXSxlW2ldPWZ1bmN0aW9uKCl7YT1hcmd1bWVudHN9LHIuYWx3YXlzKGZ1bmN0aW9uKCl7dm9pZCAwPT09bz93KGUpLnJlbW92ZVByb3AoaSk6ZVtpXT1vLHRbaV0mJih0Lmpzb25wQ2FsbGJhY2s9bi5qc29ucENhbGxiYWNrLFl0LnB1c2goaSkpLGEmJmcobykmJm8oYVswXSksYT1vPXZvaWQgMH0pLCJzY3JpcHQifSksaC5jcmVhdGVIVE1MRG9jdW1lbnQ9ZnVuY3Rpb24oKXt2YXIgZT1yLmltcGxlbWVudGF0aW9uLmNyZWF0ZUhUTUxEb2N1bWVudCgiIikuYm9keTtyZXR1cm4gZS5pbm5lckhUTUw9Ijxmb3JtPjwvZm9ybT48Zm9ybT48L2Zvcm0+IiwyPT09ZS5jaGlsZE5vZGVzLmxlbmd0aH0oKSx3LnBhcnNlSFRNTD1mdW5jdGlvbihlLHQsbil7aWYoInN0cmluZyIhPXR5cGVvZiBlKXJldHVybltdOyJib29sZWFuIj09dHlwZW9mIHQmJihuPXQsdD0hMSk7dmFyIGksbyxhO3JldHVybiB0fHwoaC5jcmVhdGVIVE1MRG9jdW1lbnQ/KChpPSh0PXIuaW1wbGVtZW50YXRpb24uY3JlYXRlSFRNTERvY3VtZW50KCIiKSkuY3JlYXRlRWxlbWVudCgiYmFzZSIpKS5ocmVmPXIubG9jYXRpb24uaHJlZix0LmhlYWQuYXBwZW5kQ2hpbGQoaSkpOnQ9ciksbz1BLmV4ZWMoZSksYT0hbiYmW10sbz9bdC5jcmVhdGVFbGVtZW50KG9bMV0pXToobz14ZShbZV0sdCxhKSxhJiZhLmxlbmd0aCYmdyhhKS5yZW1vdmUoKSx3Lm1lcmdlKFtdLG8uY2hpbGROb2RlcykpfSx3LmZuLmxvYWQ9ZnVuY3Rpb24oZSx0LG4pe3ZhciByLGksbyxhPXRoaXMscz1lLmluZGV4T2YoIiAiKTtyZXR1cm4gcz4tMSYmKHI9dnQoZS5zbGljZShzKSksZT1lLnNsaWNlKDAscykpLGcodCk/KG49dCx0PXZvaWQgMCk6dCYmIm9iamVjdCI9PXR5cGVvZiB0JiYoaT0iUE9TVCIpLGEubGVuZ3RoPjAmJncuYWpheCh7dXJsOmUsdHlwZTppfHwiR0VUIixkYXRhVHlwZToiaHRtbCIsZGF0YTp0fSkuZG9uZShmdW5jdGlvbihlKXtvPWFyZ3VtZW50cyxhLmh0bWwocj93KCI8ZGl2PiIpLmFwcGVuZCh3LnBhcnNlSFRNTChlKSkuZmluZChyKTplKX0pLmFsd2F5cyhuJiZmdW5jdGlvbihlLHQpe2EuZWFjaChmdW5jdGlvbigpe24uYXBwbHkodGhpcyxvfHxbZS5yZXNwb25zZVRleHQsdCxlXSl9KX0pLHRoaXN9LHcuZWFjaChbImFqYXhTdGFydCIsImFqYXhTdG9wIiwiYWpheENvbXBsZXRlIiwiYWpheEVycm9yIiwiYWpheFN1Y2Nlc3MiLCJhamF4U2VuZCJdLGZ1bmN0aW9uKGUsdCl7dy5mblt0XT1mdW5jdGlvbihlKXtyZXR1cm4gdGhpcy5vbih0LGUpfX0pLHcuZXhwci5wc2V1ZG9zLmFuaW1hdGVkPWZ1bmN0aW9uKGUpe3JldHVybiB3LmdyZXAody50aW1lcnMsZnVuY3Rpb24odCl7cmV0dXJuIGU9PT10LmVsZW19KS5sZW5ndGh9LHcub2Zmc2V0PXtzZXRPZmZzZXQ6ZnVuY3Rpb24oZSx0LG4pe3ZhciByLGksbyxhLHMsdSxsLGM9dy5jc3MoZSwicG9zaXRpb24iKSxmPXcoZSkscD17fTsic3RhdGljIj09PWMmJihlLnN0eWxlLnBvc2l0aW9uPSJyZWxhdGl2ZSIpLHM9Zi5vZmZzZXQoKSxvPXcuY3NzKGUsInRvcCIpLHU9dy5jc3MoZSwibGVmdCIpLChsPSgiYWJzb2x1dGUiPT09Y3x8ImZpeGVkIj09PWMpJiYobyt1KS5pbmRleE9mKCJhdXRvIik+LTEpPyhhPShyPWYucG9zaXRpb24oKSkudG9wLGk9ci5sZWZ0KTooYT1wYXJzZUZsb2F0KG8pfHwwLGk9cGFyc2VGbG9hdCh1KXx8MCksZyh0KSYmKHQ9dC5jYWxsKGUsbix3LmV4dGVuZCh7fSxzKSkpLG51bGwhPXQudG9wJiYocC50b3A9dC50b3Atcy50b3ArYSksbnVsbCE9dC5sZWZ0JiYocC5sZWZ0PXQubGVmdC1zLmxlZnQraSksInVzaW5nImluIHQ/dC51c2luZy5jYWxsKGUscCk6Zi5jc3MocCl9fSx3LmZuLmV4dGVuZCh7b2Zmc2V0OmZ1bmN0aW9uKGUpe2lmKGFyZ3VtZW50cy5sZW5ndGgpcmV0dXJuIHZvaWQgMD09PWU/dGhpczp0aGlzLmVhY2goZnVuY3Rpb24odCl7dy5vZmZzZXQuc2V0T2Zmc2V0KHRoaXMsZSx0KX0pO3ZhciB0LG4scj10aGlzWzBdO2lmKHIpcmV0dXJuIHIuZ2V0Q2xpZW50UmVjdHMoKS5sZW5ndGg/KHQ9ci5nZXRCb3VuZGluZ0NsaWVudFJlY3QoKSxuPXIub3duZXJEb2N1bWVudC5kZWZhdWx0Vmlldyx7dG9wOnQudG9wK24ucGFnZVlPZmZzZXQsbGVmdDp0LmxlZnQrbi5wYWdlWE9mZnNldH0pOnt0b3A6MCxsZWZ0OjB9fSxwb3NpdGlvbjpmdW5jdGlvbigpe2lmKHRoaXNbMF0pe3ZhciBlLHQsbixyPXRoaXNbMF0saT17dG9wOjAsbGVmdDowfTtpZigiZml4ZWQiPT09dy5jc3MociwicG9zaXRpb24iKSl0PXIuZ2V0Qm91bmRpbmdDbGllbnRSZWN0KCk7ZWxzZXt0PXRoaXMub2Zmc2V0KCksbj1yLm93bmVyRG9jdW1lbnQsZT1yLm9mZnNldFBhcmVudHx8bi5kb2N1bWVudEVsZW1lbnQ7d2hpbGUoZSYmKGU9PT1uLmJvZHl8fGU9PT1uLmRvY3VtZW50RWxlbWVudCkmJiJzdGF0aWMiPT09dy5jc3MoZSwicG9zaXRpb24iKSllPWUucGFyZW50Tm9kZTtlJiZlIT09ciYmMT09PWUubm9kZVR5cGUmJigoaT13KGUpLm9mZnNldCgpKS50b3ArPXcuY3NzKGUsImJvcmRlclRvcFdpZHRoIiwhMCksaS5sZWZ0Kz13LmNzcyhlLCJib3JkZXJMZWZ0V2lkdGgiLCEwKSl9cmV0dXJue3RvcDp0LnRvcC1pLnRvcC13LmNzcyhyLCJtYXJnaW5Ub3AiLCEwKSxsZWZ0OnQubGVmdC1pLmxlZnQtdy5jc3MociwibWFyZ2luTGVmdCIsITApfX19LG9mZnNldFBhcmVudDpmdW5jdGlvbigpe3JldHVybiB0aGlzLm1hcChmdW5jdGlvbigpe3ZhciBlPXRoaXMub2Zmc2V0UGFyZW50O3doaWxlKGUmJiJzdGF0aWMiPT09dy5jc3MoZSwicG9zaXRpb24iKSllPWUub2Zmc2V0UGFyZW50O3JldHVybiBlfHxiZX0pfX0pLHcuZWFjaCh7c2Nyb2xsTGVmdDoicGFnZVhPZmZzZXQiLHNjcm9sbFRvcDoicGFnZVlPZmZzZXQifSxmdW5jdGlvbihlLHQpe3ZhciBuPSJwYWdlWU9mZnNldCI9PT10O3cuZm5bZV09ZnVuY3Rpb24ocil7cmV0dXJuIHoodGhpcyxmdW5jdGlvbihlLHIsaSl7dmFyIG87aWYoeShlKT9vPWU6OT09PWUubm9kZVR5cGUmJihvPWUuZGVmYXVsdFZpZXcpLHZvaWQgMD09PWkpcmV0dXJuIG8/b1t0XTplW3JdO28/by5zY3JvbGxUbyhuP28ucGFnZVhPZmZzZXQ6aSxuP2k6by5wYWdlWU9mZnNldCk6ZVtyXT1pfSxlLHIsYXJndW1lbnRzLmxlbmd0aCl9fSksdy5lYWNoKFsidG9wIiwibGVmdCJdLGZ1bmN0aW9uKGUsdCl7dy5jc3NIb29rc1t0XT1fZShoLnBpeGVsUG9zaXRpb24sZnVuY3Rpb24oZSxuKXtpZihuKXJldHVybiBuPUZlKGUsdCksV2UudGVzdChuKT93KGUpLnBvc2l0aW9uKClbdF0rInB4IjpufSl9KSx3LmVhY2goe0hlaWdodDoiaGVpZ2h0IixXaWR0aDoid2lkdGgifSxmdW5jdGlvbihlLHQpe3cuZWFjaCh7cGFkZGluZzoiaW5uZXIiK2UsY29udGVudDp0LCIiOiJvdXRlciIrZX0sZnVuY3Rpb24obixyKXt3LmZuW3JdPWZ1bmN0aW9uKGksbyl7dmFyIGE9YXJndW1lbnRzLmxlbmd0aCYmKG58fCJib29sZWFuIiE9dHlwZW9mIGkpLHM9bnx8KCEwPT09aXx8ITA9PT1vPyJtYXJnaW4iOiJib3JkZXIiKTtyZXR1cm4geih0aGlzLGZ1bmN0aW9uKHQsbixpKXt2YXIgbztyZXR1cm4geSh0KT8wPT09ci5pbmRleE9mKCJvdXRlciIpP3RbImlubmVyIitlXTp0LmRvY3VtZW50LmRvY3VtZW50RWxlbWVudFsiY2xpZW50IitlXTo5PT09dC5ub2RlVHlwZT8obz10LmRvY3VtZW50RWxlbWVudCxNYXRoLm1heCh0LmJvZHlbInNjcm9sbCIrZV0sb1sic2Nyb2xsIitlXSx0LmJvZHlbIm9mZnNldCIrZV0sb1sib2Zmc2V0IitlXSxvWyJjbGllbnQiK2VdKSk6dm9pZCAwPT09aT93LmNzcyh0LG4scyk6dy5zdHlsZSh0LG4saSxzKX0sdCxhP2k6dm9pZCAwLGEpfX0pfSksdy5lYWNoKCJibHVyIGZvY3VzIGZvY3VzaW4gZm9jdXNvdXQgcmVzaXplIHNjcm9sbCBjbGljayBkYmxjbGljayBtb3VzZWRvd24gbW91c2V1cCBtb3VzZW1vdmUgbW91c2VvdmVyIG1vdXNlb3V0IG1vdXNlZW50ZXIgbW91c2VsZWF2ZSBjaGFuZ2Ugc2VsZWN0IHN1Ym1pdCBrZXlkb3duIGtleXByZXNzIGtleXVwIGNvbnRleHRtZW51Ii5zcGxpdCgiICIpLGZ1bmN0aW9uKGUsdCl7dy5mblt0XT1mdW5jdGlvbihlLG4pe3JldHVybiBhcmd1bWVudHMubGVuZ3RoPjA/dGhpcy5vbih0LG51bGwsZSxuKTp0aGlzLnRyaWdnZXIodCl9fSksdy5mbi5leHRlbmQoe2hvdmVyOmZ1bmN0aW9uKGUsdCl7cmV0dXJuIHRoaXMubW91c2VlbnRlcihlKS5tb3VzZWxlYXZlKHR8fGUpfX0pLHcuZm4uZXh0ZW5kKHtiaW5kOmZ1bmN0aW9uKGUsdCxuKXtyZXR1cm4gdGhpcy5vbihlLG51bGwsdCxuKX0sdW5iaW5kOmZ1bmN0aW9uKGUsdCl7cmV0dXJuIHRoaXMub2ZmKGUsbnVsbCx0KX0sZGVsZWdhdGU6ZnVuY3Rpb24oZSx0LG4scil7cmV0dXJuIHRoaXMub24odCxlLG4scil9LHVuZGVsZWdhdGU6ZnVuY3Rpb24oZSx0LG4pe3JldHVybiAxPT09YXJndW1lbnRzLmxlbmd0aD90aGlzLm9mZihlLCIqKiIpOnRoaXMub2ZmKHQsZXx8IioqIixuKX19KSx3LnByb3h5PWZ1bmN0aW9uKGUsdCl7dmFyIG4scixpO2lmKCJzdHJpbmciPT10eXBlb2YgdCYmKG49ZVt0XSx0PWUsZT1uKSxnKGUpKXJldHVybiByPW8uY2FsbChhcmd1bWVudHMsMiksaT1mdW5jdGlvbigpe3JldHVybiBlLmFwcGx5KHR8fHRoaXMsci5jb25jYXQoby5jYWxsKGFyZ3VtZW50cykpKX0saS5ndWlkPWUuZ3VpZD1lLmd1aWR8fHcuZ3VpZCsrLGl9LHcuaG9sZFJlYWR5PWZ1bmN0aW9uKGUpe2U/dy5yZWFkeVdhaXQrKzp3LnJlYWR5KCEwKX0sdy5pc0FycmF5PUFycmF5LmlzQXJyYXksdy5wYXJzZUpTT049SlNPTi5wYXJzZSx3Lm5vZGVOYW1lPU4sdy5pc0Z1bmN0aW9uPWcsdy5pc1dpbmRvdz15LHcuY2FtZWxDYXNlPUcsdy50eXBlPXgsdy5ub3c9RGF0ZS5ub3csdy5pc051bWVyaWM9ZnVuY3Rpb24oZSl7dmFyIHQ9dy50eXBlKGUpO3JldHVybigibnVtYmVyIj09PXR8fCJzdHJpbmciPT09dCkmJiFpc05hTihlLXBhcnNlRmxvYXQoZSkpfSwiZnVuY3Rpb24iPT10eXBlb2YgZGVmaW5lJiZkZWZpbmUuYW1kJiZkZWZpbmUoImpxdWVyeSIsW10sZnVuY3Rpb24oKXtyZXR1cm4gd30pO3ZhciBKdD1lLmpRdWVyeSxLdD1lLiQ7cmV0dXJuIHcubm9Db25mbGljdD1mdW5jdGlvbih0KXtyZXR1cm4gZS4kPT09dyYmKGUuJD1LdCksdCYmZS5qUXVlcnk9PT13JiYoZS5qUXVlcnk9SnQpLHd9LHR8fChlLmpRdWVyeT1lLiQ9dyksd30pOwo="></script>
	<script type="text/javascript">
		var XSRF = getCookie('__xsrf');
		var VERSION = document.querySelector('html').getAttribute('version');
		var CLIPBOARD = false;
		var DO_ACTION = false;
		var MAX_UPLOAD_SIZE = 0 | parseInt(document.querySelector('.maxSize').innerHTML);
		var menu_options = document.querySelector('.options');

		(function($){
			$.fn.autoSort = function() {
				var $table = this;
				var fe_sort = (getCookie('fe_sort') ? getCookie('fe_sort') : '0|sort_asc' ).split('|');

				if(fe_sort.length){
					$table.sortBy( parseInt(fe_sort[0]), (fe_sort[1] == 'sort_desc') );
				}

				this.find('.tH').click(function() {
					$table.sortBy( $(this).index(), $(this).hasClass('sort_asc') );
				});
				return this;
			}
			$.fn.sortBy = function(idx, direction) {
				var sBy = direction ? 'sort_desc' : 'sort_asc';

				setCookie('fe_sort', idx +'|'+ sBy, 30);
				function data_sort(a) {
					var a_val = $(a).find('.tD:nth-child('+ (idx+1) +')').attr('data-sort');
					return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
				}
				this.find('.tH').removeClass('sort_asc sort_desc');
				this.find('.tHead .tH:eq('+ idx +')').addClass(sBy);

				$rows = this.find('.item').not('.tHead');
				$rows.sort(function(a, b){
					var a_val = data_sort(a), b_val = data_sort(b);
					return (a_val < b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
				});
				for(var i = 0; i < $rows.length; i++)
					this.append( $rows[i] );
				return this;
			}

			$list = $('main');
			$(window).on('hashchange', list).trigger('hashchange');
			$('input').prop('autocomplete', 'off').prop('spellcheck', false);

			$(document).on('click', '.refresh', function(e) {
				CLIPBOARD = false;
				DO_ACTION = false;
				modal('off');
				hide_option_menu();
				$('.toast').css('opacity',0);
				$(window).trigger('hashchange');
			});


			/* DRAG DROP and CHOOSE FILES
			*******************************************/
			document.querySelector('.maxSize').innerHTML = formatFileSize(MAX_UPLOAD_SIZE);
			$(window).on('dragover', function(e){
				e.preventDefault();
				e.stopPropagation();
				modal('on', '#uploadModal');
			});

			$(window).on('drop', function(e){
				e.preventDefault();
				e.stopPropagation();
				modal('off');
			});

			$('#drop_area').on('dragover', function(e){
				e.preventDefault();
				e.stopPropagation();
				$(this).addClass('hover');
			});

			$('#drop_area').on('dragleave', function(e){
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('hover');
			});

			$('#drop_area').on('drop', function(e){
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('hover');
				var files = e.originalEvent.dataTransfer.files;

				files.length &&modal('on', '#progressModal');
				$.each(files, function(index, file) {
					uploadFile(file, index);
				});
			});

			$('input[type=file]').change(function(e) {
				e.preventDefault();
				this.files.length && modal('on', '#progressModal');

				$.each(this.files, function(index, file) {
					uploadFile(file, index);
				});
			});



			/* UPLOADING FILES
			*******************************************/
			function uploadFile(file, index) {
				$modal = $('#progressModal');
				var folder = decodeURIComponent(window.location.hash.substr(1));
				if(file.size > MAX_UPLOAD_SIZE) {
					$modal.find('.body').append( renderFileError(file, index) );
					return false;
				}

				var len = $modal.find('.uploading').length;
				docTitle(len + ' uploads in progress');
				$modal.find('.title').text(document.title);
				$modal.find('.body').append( renderFileUpload(file, index) );

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
						if( progress == 100 )
							$modal.find('li.upload_'+ index).attr('title', 'Finalizing...').find('.progress span').css('width', '100%');
						else
							$modal.find('li.upload_'+ index).attr('title', 'Uploading '+ progress +'%').find('.progress span').css('width', progress + '%');
					}
				};

				XHR.upload.onload = function() {
					$modal.find('li.upload_'+index).removeClass('uploading').removeAttr('title');

					var len = $modal.find('.uploading').length;
					docTitle(len + ' uploads in progress');
					$modal.find('.title').text(document.title);
					if( len < 1 ){
						window.setTimeout(function(){
							list();
							docTitle();
							modal('off');
							toast('Files Uploaded Successfully', '#070');
							$modal.find('.body').empty();
						}, 2000);
					}
				};

				XHR.upload.onabort = function () {
					docTitle('Files Aborted');
					$modal.find('li.uploading.upload_'+ index).addClass('error').removeClass('uploading').removeAttr('title', 'Upload Aborted');

					window.setTimeout(function(){
						list();
						docTitle();
						modal('off');
						toast('Files Aborted', '#B00');
						$modal.find('.body').empty();
					}, 5000);
				};

				XHR.send(fd);

				$(document).on('click', '#progressModal button', function(e){     
					XHR.abort();
				});
			}
			function docTitle(str = null){
				document.title = typeof str == 'string' ? str : 'File Explorer v' + VERSION;
			}
			function renderFileUpload(file, index) {
				return $('<li class="upload_'+ index +' uploading">')
				.attr('title', 'Starting Upload...')
				.attr('data-size', formatFileSize(file.size))
				.append( $('<label>').text(file.name) )
				.append( $('<div class="progress"><span></span></div>') )
			}
			function renderFileError(file, index) {
				return $('<li class="upload_'+ index +' error">')
				.attr('title', 'Exceeds max upload size of ' + formatFileSize(MAX_UPLOAD_SIZE))
				.attr('data-size', formatFileSize(file.size))
				.append( $('<label>').text(file.name) )
				.append('<div class="progress"><span style="width: 100%;"></span></div>')
			}



			/* CREATE NEW DIRECTORY
			*******************************************/
			$('#newDirModal form').submit(function(e) {
				$form = $(this);
				e.preventDefault();
				var HASHVAL = decodeURIComponent(window.location.hash.substr(1));
				var dirname = $form.find('#dirname').val().trim();
				toast('Creating...', '', 'wait');

				dirname.length && $.post('', {do: 'mkdir', dirname: dirname, path: HASHVAL, xsrf: XSRF}, function(data){
					list();
					modal('off');
					toast(data.response, data.flag == true ? '#070' : '#B00');
					$form.find('input').val('');
				}, 'json');
			});



			/* CREATE NEW FILE
			*******************************************/
			$('#newFileModal form').submit(function(e) {
				$form = $(this);
				e.preventDefault();
				var HASHVAL = decodeURIComponent(window.location.hash.substr(1));
				var filename = $form.find('#filename').val().trim();
				toast('Creating...', '', 'wait');

				filename.length && $.post('', {do: 'nwfile', filename: filename, path: HASHVAL, xsrf: XSRF}, function(data){
					list();
					modal('off');
					toast(data.response, data.flag == true ? '#070' : '#B00');
					$form.find('input').val('');
				}, 'json');
			});



			/* RENAME FILE and FOLDER
			*******************************************/ 
			$(document).on('click', '.rename', function(e) {
				var path = $(this).closest('.options[data-path]').attr('data-path').trim();
				var name = $(this).closest('.options[data-name]').attr('data-name').trim();

				modal('on', '#renameModal');
				$modal = $('#renameModal');
				$modal.find('#path').val(path);
				$modal.find('#newname').val(name).attr('placeholder', name).focus();
			});

			$('#renameModal form').submit(function(e) {
				$form = $(this);
				e.preventDefault();

				var path = $form.find('#path').val().trim();
				var newn = $form.find('#newname').val().trim();
				toast('Renaming...', '', 'wait');

				path.length && newn.length && $.post('', {do: 'rename', newname: newn, path: path, xsrf: XSRF}, function(data){
					list();
					modal('off');
					toast(data.response, data.flag == true ? '#070' : '#B00');
				}, 'json');
			});




			/* COPY and MOVE FILES
			*******************************************/ 
			$(document).on('click', '.copy', function(e) {
				CLIPBOARD = [];
				DO_ACTION = 'copy';

				$('main .selected').each(function(){
					CLIPBOARD.push( $(this).find('a[data-real_path]').attr('data-real_path').trim() );
				});

				hide_option_menu();
				toast('Choose Copy Location', '', 'stay');
			});

			$(document).on('click', '.move', function(e) {
				CLIPBOARD = [];
				DO_ACTION = 'move';

				$('main .selected').each(function(){
					CLIPBOARD.push( $(this).find('a[data-real_path]').attr('data-real_path').trim() );
				});

				hide_option_menu();
				toast('Choose Move Location', '', 'stay');
			});

			$(document).on('click', '.paste', function(e) {
				var HASHVAL = decodeURIComponent(window.location.hash.substr(1));
				hide_option_menu();

				if( DO_ACTION == 'copy' ){
					toast('Copying...', '', 'wait');
				}
				else if( DO_ACTION == 'move' ){
					toast('Moving...', '', 'wait');
				}

				$.post('', {do: DO_ACTION, ways: CLIPBOARD, path: HASHVAL, xsrf: XSRF}, function(data){
					list();
					toast(data.response, data.flag == true ? '#070' : '#B00');
					CLIPBOARD = false;
					DO_ACTION = false;
				}, 'json');
			});




			/* DELETE FILE
			*******************************************/
			$(document).on('click', '.delete', function() {
				CLIPBOARD = [];
				DO_ACTION = 'trash';

				$('main .selected').each(function(){
					CLIPBOARD.push( $(this).find('a[data-real_path]').attr('data-real_path').trim() );
				});

				!CLIPBOARD.length && CLIPBOARD.push( $(this).closest('.options[data-real_path]').attr('data-real_path').trim() );

				hide_option_menu();
				if( confirm('Do you want to Delete it ?') ){
					toast('Deleting...', '', 'wait');

					var HASHVAL = decodeURIComponent(window.location.hash.substr(1));
					$.post('', {do: DO_ACTION, ways: CLIPBOARD, path: HASHVAL, xsrf: XSRF}, function(data){
						list();
						toast(data.response, data.flag == true ? '#070' : '#B00');
						CLIPBOARD = false;
						DO_ACTION = false;
					}, 'json');
				}
				else {
					toast('Oh! Thanks God all is safe.');
				}
			});




			/* COMPRESS DIRECTORY
			*******************************************/
			$(document).on('click', '.cmprss', function() {
				modal('off');
				toast('Compressing...', '', 'wait');
				var path = $(this).closest('.options[data-path]').attr('data-path').trim();

				$.post('', {do: 'compress', path: path, xsrf: XSRF}, function(data){
					if (data.flag == true) {
						list();
						toast(data.response, '#070');
					}
					else {
						toast(data.response, '#B00');
					}
				}, 'json');
			});




			/* EXTRACT ZIP FILE
			*******************************************/
			$(document).on('click', '.extrct', function() {
				modal('off');
				toast('Extracting...', '', 'wait');
				var path = $(this).closest('.options[data-path]').attr('data-path').trim();

				$.post('', {do: 'extract', path: path, xsrf: XSRF}, function(data){
					if (data.flag == true) {
						list();
						toast(data.response, '#070');
					}
					else {
						toast(data.response, '#B00');
					}
				}, 'json');
			});



			/* CHANGE PERMISSIONS
			*******************************************/ 
			$(document).on('change', '#permitModal input[type=checkbox]', function() {
				var perm = 0;
				perm += $('#ownRead').prop('checked') ? 256 : 0;
				perm += $('#ownWrit').prop('checked') ? 128 : 0;
				perm += $('#ownExec').prop('checked') ?  64 : 0;
				perm += $('#grpRead').prop('checked') ?  32 : 0;
				perm += $('#grpWrit').prop('checked') ?  16 : 0;
				perm += $('#grpExec').prop('checked') ?   8 : 0;
				perm += $('#pubRead').prop('checked') ?   4 : 0;
				perm += $('#pubWrit').prop('checked') ?   2 : 0;
				perm += $('#pubExec').prop('checked') ?   1 : 0;

				$('#perm_code').val( '0' + perm.toString(8) );
			});

			$(document).on('paste keyup keydown click', '#perm_code', function() {
				var val = 0 | parseInt($(this).val().trim(), 8);

				$('#ownRead').prop('checked', !!(256 & val) );
				$('#ownWrit').prop('checked', !!(128 & val) );
				$('#ownExec').prop('checked', !!( 64 & val) );
				$('#grpRead').prop('checked', !!( 32 & val) );
				$('#grpWrit').prop('checked', !!( 16 & val) );
				$('#grpExec').prop('checked', !!(  8 & val) );
				$('#pubRead').prop('checked', !!(  4 & val) );
				$('#pubWrit').prop('checked', !!(  2 & val) );
				$('#pubExec').prop('checked', !!(  1 & val) );
			});

			$(document).on('change', '#perm_recursive_chk', function() {
				$('#permitModal input[type=radio]').prop('disabled', !$(this).prop('checked'));
			});

			$(document).on('click', '.permit', function(e) {
				$modal = $('#permitModal');
				var path = $(this).closest('.options[data-path]').attr('data-path');
				var perm = $(this).closest('.options[data-perm]').attr('data-perm');
				var is_dir = $(this).closest('.options[data-is_dir]').attr('data-is_dir') == 'true';

				$modal.find('#perm_path').val(path);
				$modal.find('#perm_code').val(perm).trigger('keydown');
				$modal.find('.inputs.recurse').prop('hidden', !is_dir);
				modal('on', '#permitModal');
			});

			$('#permitModal form').submit(function(e) {
				e.preventDefault();
				var path = $(this).find('#perm_path').val();
				var perm = $(this).find('#perm_code').val();
				var rcrs = $(this).find('[name="recurse"]:checked').val();
				rcrs = typeof rcrs == 'string' ? rcrs : '';
				toast('Changing...', '', 'wait');

				path.length && perm.length && $.post('', {do:'permit', path:path, perm:perm, recurse:rcrs, xsrf:XSRF}, function(data){
					list();
					modal('off');
					toast(data.response, data.flag == true ? '#070' : '#B00');
					$('#perm_recursive_chk').prop('checked', false);
					$('[name="recurse"]').prop('checked', false).prop('disabled', false);
				}, 'json');
			});




			/* VIEW DETAILS
			*******************************************/
			$(document).on('click', '.info', function() {
				var obj = {};

				$.each( $(this).closest('.options')[0].attributes, function(index, attr) {
					if( attr.name.indexOf('data-') > -1 ){
						obj[ attr.name.replace('data-', '') ] = attr.value;
					}
				});
				modal('on', '#detailModal');
				$modal = $('#detailModal');
				$modal.find('.name' ).text( obj.name );
				$modal.find('.path' ).text( window.location.origin + window.location.pathname + obj.path );
				$modal.find('.type' ).text( obj.type );
				$modal.find('.size' ).text( obj.is_dir == 'true' ? '---' : formatFileSize(obj.size) );
				$modal.find('.ownr' ).text( obj.ownr_ok +' ('+ obj.ownr +')' );
				$modal.find('.perm' ).text( formatFilePerm(obj.perm, obj.is_dir == 'true') + ' (' + obj.perm +')' );
				$modal.find('.atime').text( timedate(obj.atime) );
				$modal.find('.ctime').text( timedate(obj.ctime) );
				$modal.find('.mtime').text( timedate(obj.mtime) );
			});


			/* TOGGLE VIEW
			*******************************************/
			$(document).on('click', '.toggle_view', function(e) {
				e.preventDefault();
				var fe_view = $('main').hasClass('listView') ? 'gridView'  : 'listView';
				var vw_text = $('main').hasClass('listView') ? 'List View' : 'Grid View';

				$(this).attr('title', vw_text);
				$('body').addClass('loading');
				setCookie('fe_view', fe_view, 30);
				setTimeout(function(){
					$('main').attr('class', fe_view);
					list();
				}, 500);
			});


			/* PASSWORD and SETTINGS PANEL
			*******************************************/
			$('#configModal .pwdeye').on('click', function(e){
				e.preventDefault();
				$(this).toggleClass('off');
				$pass = $('#configModal #pass');
				$pass.attr('type') == 'password' ? $pass.attr('type', 'text') : $pass.attr('type', 'password');
			});

			$('#configModal form').submit(function(e) {
				e.preventDefault();
				var hdfl = $(this).find('#hdfl').prop('checked').toString();
				var pass = $(this).find('#pass').val().trim();
				toast('Updating Settings...', '', 'wait');

				$.post('', {do: 'config', hdfl: hdfl, pass: pass, xsrf: XSRF}, function(data){
					modal('off');
					toast(data.response, data.flag == true ? '#070' : '#B00', 'stay');
					window.setTimeout(function(){
						window.location.reload();
					}, 1000);
				}, 'json');
			});




			/* LOGOUT SESSION AND COOKIE
			*******************************************/
			$(document).on('click', '.logout', function() {
				modal('off');

				if( confirm('Are you sure to logout this session?') ){
					toast('Please Wait...', '', 'stay');

					$.post('', {do: 'logout', xsrf: XSRF}, function(data){
						modal('off');
						toast(data.response, data.flag == true ? '#070' : '#B00', 'stay');
						window.setTimeout(function(){
							window.location.reload();
						}, 1000);
					}, 'json');
				}
			});




			/* UPDATE CORE VERSION
			*******************************************/
			$(document).on('click', '.upgrade', function() {
				modal('off');

				if( confirm('Are you sure to upgrade?') ){
					toast('Upgrading...', '', 'stay');

					$.post('', {do: 'upgrade', xsrf: XSRF}, function(data){
						if (data.flag == true) {
							toast(data.response, '#070', 'stay');
							window.setTimeout(function(){
								window.location.reload();
							}, 1000);
						}
						else {
							toast(data.response, '#B00', 10000);
						}
					}, 'json');
				}
			});




			/* MULTI SELECTION
			*******************************************/
			$(document).on('keydown', function(e) {
				if( (e.ctrlKey || e.metaKey) && e.keyCode == 65 ){
					e.preventDefault();
					$('main .item').addClass('selected');
				}
				if( e.keyCode == 27 ){
					e.preventDefault();
					if( $('.modal.on').not('#progressModal').length ){
						modal('off');
					}
					else {
						$('main .item').removeClass('selected');
					}
				}
			});

			$(document).on('click', '.item', function (e) {
				e.preventDefault();
				if (e.ctrlKey || e.metaKey) {
					$(this).toggleClass('selected');
				}
				else if(e.shiftKey){
					$(this).toggleClass('selected');
					if( $('main .selected').length > 1 ){
						$('main .selected:first').nextUntil('main .selected:last').addClass('selected');
					}
				} 
				else {
					$('main .item').removeClass('selected');
				}
			});

			$(document).on('click', '.item a', function(e){
				e.preventDefault();
			});

			$(document).on('dblclick', '.item a', function(e){
				var href = $(this).attr('href');
				if( $(this).hasClass('is_dir') ){
					window.location.href = href;
				}
				else {
					window.open(href, '_blank');
				}
				return false;
			});




			/* MENUS BEHAVIOUR
			*******************************************/
			$(window).on('focus', hide_option_menu);
			$(document).on('click', 'main .item a  span', hide_option_menu);
			$(document).on('click', 'main .item a .icon', hide_option_menu);
			$(document).on('click', 'main .item a .more', itemContextMenu);
			$(document).on('contextmenu', 'main .item a', itemContextMenu);
			$(document).on('contextmenu', function(e) {
				e.preventDefault();
			});

			$(document).on('click', function(e) {
				$container = $('.options');
				// ONLY if not clicked on self AND not clicked with in container AND not clicked with in card
				if ( !$container.is(e.target) && !$container.has(e.target).length && !$('main .item a').has(e.target).length ){
					hide_option_menu();
				}

				if ( !$('main .item').has(e.target).length ){
					$('main .item').removeClass('selected');
				}
			});
			
			$(document).on('contextmenu', function(e) {
				e.preventDefault();
				var opt = '';
				var is_selected = !!$('main .selected').length;

				opt += '<a class="refresh" title="Refresh">Refresh</a>';
				opt += '<a onclick="modal(\'on\', \'#uploadModal\' )" title="Upload">Upload</a>';
				opt += '<a onclick="modal(\'on\', \'#newDirModal\' )" title="New Folder">Create Folder</a>';
				opt += '<a onclick="modal(\'on\', \'#newFileModal\')" title="New File">Create File</a>';

				if( is_selected ) opt += '<a class="copy" title="Copy">Copy</a>';
				if( is_selected ) opt += '<a class="move" title="Move">Move</a>';
				if( is_selected ) opt += '<a class="delete" title="Delete">Delete</a>';
				if(   CLIPBOARD ) opt += '<a class="paste" title="Paste">Paste</a>';

				// ONLY if clicked with <main> tag AND not clicked with in item or modal
				if ( !($('.item').has(e.target).length || $('.modal.on').has(e.target).length) ){
					show_option_menu(e, opt);
				}
			});

			function itemContextMenu(e) {
				e.preventDefault();
				var obj = {};
				var opt = '';
				var is_selected = !!$('main .selected').length;
				$item = $(this).closest('.item');
				$item.addClass('hover').siblings().not('.tHead').removeClass('hover');

				$.each( $item.find('a')[0].attributes, function(index, attr) {
					if( attr.name.indexOf('data-') > -1 ){
						menu_options.setAttribute(attr.name, attr.value);
						obj[ attr.name.replace('data-', '') ] = attr.value;
					}
				});

				var menu = {
					open   : '<a href="#'+ obj.path +'" title="Open">Open</a>',
					runit  : '<a href="'+ obj.path +'" target="_blank" title="View">Run</a>',
					dwnld  : '<a href="?do=download&path='+ encodeURIComponent(obj.path) +'" title="Download">Download</a>',
					edit   : '<a href="?do=edit&path='+ encodeURIComponent(obj.path) +'" target="_blank" title="Edit">View / Edit</a>',
					copy   : '<a class="copy" title="Copy">Copy</a>',
					move   : '<a class="move" title="Move">Move</a>',
					rename : '<a class="rename" title="Rename">Rename</a>',
					delete : '<a class="delete" title="Delete">Delete</a>',
					cmprss : '<a class="cmprss" title="Compress">Compress</a>',
					extrct : '<a class="extrct" title="Extract">Extract</a>',
					permit : '<a class="permit" title="Permissions">Permissions</a>',
					info   : '<a class="info" title="Info">View Details</a>',
				}
				if( !is_selected ){
					opt += obj.is_dir      == 'true' ? menu.open : menu.runit;
					opt += obj.is_dir      == 'true' ? ''        : menu.dwnld;
					opt += obj.is_editable == 'true' ? menu.edit : '';
				}
				opt += obj.is_recursable == 'true' ? menu.copy   : '';
				opt += obj.is_recursable == 'true' ? menu.move   : '';
				opt += obj.is_deletable == 'true' ? menu.delete : '';
				if( !is_selected ){
					opt += obj.is_writable  == 'true' ? menu.rename : '';
					opt += obj.is_zipable   == 'true' ? menu.cmprss : '';
					opt += obj.is_zip       == 'true' ? menu.extrct : '';
					opt += obj.is_writable  == 'true' ? menu.permit : '';
					opt += menu.info;
				}

				if( e.ctrlKey || e.metaKey || e.shiftKey ){
					$item.addClass('selected');
				}

				if( $item.hasClass('selected') && $('main .selected').length > 1 ){
					show_option_menu(e, opt);
				}
				else {
					$('main .item').removeClass('selected');
					show_option_menu(e, opt);
				}
			}




			/* LISTINGS
			*******************************************/
			function list() {
				$('body').addClass('loading');
				$('.toast.error').css('opacity', 0);
				var HASHVAL = decodeURIComponent( window.location.hash.substr(1) );
				$.post('', {do: 'list', path: HASHVAL, xsrf: XSRF}, function(data) {
					$list.empty();
					$('#breadcrumb').empty().html(renderBreadcrumbs(HASHVAL)).animate({scrollLeft:'+=5000'});
					if(data.flag == true && Array.isArray(data.response)) {
						$('main').hasClass('listView') && $list.html('<div class="item tHead"><span class="tH name">Name</span><span class="tH size">Size</span><span class="tH time">Modified</span><span class="tH perm">Permission</span><span class="tH ownr">Owner</span></div>');
						$.each(data.response, function(index, value){
							$list.append(renderList(value));
						});
					}
					else {
						console.warn(data.response);
						toast(data.response, '', 'error');
					}

					$list.autoSort();
					$('body').removeClass('loading');
				}, 'json');
			}

			function renderList(data) {
				var dataAttr = {};
				$.each(data, function(key, value) {
					dataAttr['data-' + key] = value;
				});

				var fileSize = data.is_dir ? '---' : formatFileSize(data.size);
				$link = $('<a>')
				.addClass(data.is_dir ? 'is_dir' : 'is_file')
				.attr('href', data.is_dir ? '#' + data.path : data.path)
				.attr('target', data.is_dir ? '_self' : '_blank' )
				.attr('title', data.name)
				.attr(dataAttr);

				if( $('main').hasClass('listView') ){
					$link
					.append($('<span>').addClass('tD name').attr('data-sort', data.sort ).attr('title', data.name).text(data.name).prepend($(svgIcons(data.ext)).addClass('icon')))
					.append($('<span>').addClass('tD size').attr('data-sort', data.size ).attr('title', fileSize).text(fileSize))
					.append($('<span>').addClass('tD time').attr('data-sort', data.mtime).attr('title', timedate(data.mtime)).text(time_ago(data.mtime)))
					.append($('<span>').addClass('tD perm').attr('data-sort', data.perm).attr('title', formatFilePerm(data.perm)).text(data.perm))
					.append($('<span>').addClass('tD ownr').attr('data-ownr', data.perm).attr('title', data.ownr_ok).text(data.ownr_ok))
					.append('<svg class="more" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>');
				}
				else {
					$link
					.append( $(svgIcons(data.ext)).addClass('icon') )
					.append( $('<span>').attr('rel', fileSize).text(data.name) )
					.append('<svg class="more" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>');
				}
				return $('<div class="item">').html($link);
			}

			function renderBreadcrumbs(path) {
				var base = '', crumb = '<a href="#"><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>';
				$.each(path.split('/'), function(index, value){
					if(value) {
						crumb = crumb + '<a href="#' + base + value + '">' + value + '</a>';
						base += value + '/'
					}
				});
				return crumb;
			}
		})(jQuery);

		function svgIcons(ext = ''){
			switch (ext) {
				case '---':
				return '<svg viewBox="0 0 24 24"><path fill="#FA4" d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>'; break;

				case 'aac': case 'aif': case 'aiff': case 'flac': case 'm4a': case 'm4p': case 'mp3': case 'wav': case 'wma' :
				return '<svg viewBox="0 0 24 24"><path fill="#08F" d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.2-1.75 4.45-4H15V6h4V3h-7z"/></svg>'; break;

				case 'ai': case 'eps': case 'gif': case 'jpg': case 'jpeg': case 'png': case 'ps': case 'psd': case 'svg': case 'tif': case 'tiff' :
				return '<svg viewBox="0 0 24 24"><path fill="#080" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>'; break;

				case '3gp': case 'avi': case 'flv': case 'm4u': case 'mkv': case 'mov': case 'mp4': case 'mpg': case 'mpeg': case 'vob': case 'webm': case 'wmv' :
				return '<svg viewBox="0 0 24 24"><path fill="#E00" d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>'; break;

				case 'sh': case 'c': case 'cfm': case 'cpp': case 'class': case 'java': case 'jsp': case 'asp': case 'aspx': case 'rb': case 'pl': case 'py': case 'sql': case 'php': case 'phps': case 'phpx': case 'htm': case 'html': case 'whtml': case 'xhtml': case 'mht': case 'js': case 'json': case 'css': case 'xml' :
				return '<svg viewBox="0 0 24 24"><path fill="#E13" d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>'; break;

				case '7z': case 'gz': case 'gzip': case 'rar': case 'tar': case 'tgz': case 'zip' :
				return '<svg viewBox="0 0 24 24"><path fill="#700" d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>'; break;

				case 'csv': case 'doc': case 'docx': case 'xlr': case 'xls': case 'xlsx': case 'pdf': case 'pps': case 'ppt': case 'pptx': case 'rtf': case 'odt': case 'txt': case 'text': case 'log' :
				return '<svg viewBox="0 0 24 24"><path fill="#789" d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>'; break;

				default:
				return '<svg viewBox="0 0 24 24"><path fill="#DDD" d="M6 2c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6H6zm7 7V3.5L18.5 9H13z"/></svg>';
			}
		}

		document.querySelector('.overlay').addEventListener('click', function(e){
			try {
				if(document.querySelector('.modal.on').getAttribute('id') != 'progressModal'){
					modal('off');
				}
			}
			catch(err) {}
		});

		function modal(act, selector = null){
			try {
				hide_option_menu();
				document.querySelector('.modal.on').classList.remove('on');
			}
			catch(err) {}

			if( act == 'on' ){
				document.querySelector('body').classList.add('modal_on');
				document.querySelector(selector).classList.add('on');
				try {
					document.querySelector(selector + ' input').focus();
				}
				catch(err) {}
			}
			if( act == 'off' ){	
				document.querySelector('body').classList.remove('modal_on');
			}
		}

		function hide_option_menu(e = '', clear = true){
			if( clear ){
				[...menu_options.attributes].forEach(function(attr){
					if( attr.name.indexOf('data-') > -1 ){
						menu_options.removeAttribute(attr.name)
					}
				});
			}

			menu_options.style.height     = 0;
			menu_options.style.opacity    = 0;
			menu_options.style.visibility = 'hidden';
		}

		function show_option_menu(e, html = ''){
			e.preventDefault();
			hide_option_menu(e, false);

			menu_options.innerHTML = html;
			menu_options.style.height = 'auto';

			var offsetWidth = menu_options.offsetWidth;
			var offsetHeight = menu_options.offsetHeight;

			menu_options.style.height = 0;

			var isOutsideX = document.body.scrollWidth  < (e.clientX + offsetWidth );
			var isOutsideY = document.body.scrollHeight < (e.clientY + offsetHeight);
			var posX = isOutsideX ? e.clientX - offsetWidth  : e.clientX;
			var posY = isOutsideY ? e.clientY - offsetHeight : e.clientY;

			menu_options.style.top        = posY + 'px';
			menu_options.style.left       = posX + 'px';
			menu_options.style.opacity    = 1;
			menu_options.style.visibility = 'visible';
			menu_options.style.height     = offsetHeight + 'px';
		}


		function toast(message, color = '', time = 5000) {
			var toasts = document.querySelectorAll('.toast');
			for(var i = 0; i < toasts.length; i++){
				toasts[i].dismiss();
			}

			var toast = document.createElement('div');
			toast.className = 'toast';
			typeof time != 'number' && toast.classList.add(time);
			time == 'wait' && document.querySelector('body').classList.add('toast_on');
			toast.dismiss = function() {
				this.style.bottom = '-10rem';
				this.style.opacity = 0;
				document.querySelector('body').classList.remove('toast_on');
			};

			var text = document.createTextNode(message);
			toast.appendChild(text);

			document.body.appendChild(toast);
			getComputedStyle(toast).bottom;
			getComputedStyle(toast).opacity;
			toast.style.backgroundColor = color;
			toast.style.bottom = document.body.scrollWidth > 576 ? '2rem' : '3.6rem';
			toast.style.opacity = 1;

			if(typeof time == 'number'){
				setTimeout(function() {
					toast.dismiss();
				}, time);
			}

			toast.addEventListener('transitionend', function(event, elapsed) {
				if( event.propertyName === 'opacity' && this.style.opacity == 0 ){
					this.parentElement.removeChild(this);
				}
			}.bind(toast));
		}

		function formatFilePerm(val, dir = false){
			val = 0 | parseInt(val, 8);
			var perm = !!( dir )  ? 'd' : '-';
			perm += !!(256 & val) ? 'r' : '-';
			perm += !!(128 & val) ? 'w' : '-';
			perm += !!( 64 & val) ? 'x' : '-';
			perm += !!( 32 & val) ? 'r' : '-';
			perm += !!( 16 & val) ? 'w' : '-';
			perm += !!(  8 & val) ? 'x' : '-';
			perm += !!(  4 & val) ? 'r' : '-';
			perm += !!(  2 & val) ? 'w' : '-';
			perm += !!(  1 & val) ? 'x' : '-';
			return perm;
		}

		function formatFileSize(bytes, round = 2) {
			if(bytes < 0) return 'Too Large';
			var units, power, size;
			units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
			bytes = Math.max(0, bytes);
			power = Math.floor( (bytes ? Math.log(bytes) : 0) / Math.log(1024) );
			power = Math.min(power, (units.length - 1));
			bytes /= Math.pow(1024, power);
			return Number(bytes.toFixed(round)) + ' ' + units[power];
		}

		function timedate(time){
			var date = new Date(parseInt(time) * 1000);
			return date.toString().replace(/(\s*)GMT(.*)/, '');
		}

		function time_ago(time) {
			time = Date.now() - (parseInt(time) * 1000);
			var periods = {
				'decade': 60 * 60 * 24 * 30 * 12 * 10,
				'year'  : 60 * 60 * 24 * 30 * 12,
				'month' : 60 * 60 * 24 * 30,
				'week'  : 60 * 60 * 24 * 7,
				'day'   : 60 * 60 * 24,
				'hr'    : 60 * 60,
				'min'   : 60,
				'sec'   : 1,
			};

			for(var unit in periods){
				var seconds = periods[unit] * 1000;
				if (time < seconds) {
					continue;
				}

				number = Math.floor(time / seconds);
				plural = (number > 1) ? 's ago' : ' ago';
				return number + ' ' + unit + plural;
			}
		}

		function setCookie(cname, cvalue, exdays = 1) {
			var d = new Date();
			d.setTime(d.getTime() + (exdays*24*60*60*1000));
			var expires = "expires="+ d.toUTCString();
			document.cookie = cname + "=" + cvalue + ";" + expires + "; path=/";
		}

		function getCookie(cname) {
			var name = cname + '=';
			var ca = decodeURIComponent(document.cookie).split(';');
			for(var i = 0; i < ca.length; i++) {
				var c = ca[i];
				while (c.charAt(0) == ' ') {
					c = c.substring(1);
				}
				if (c.indexOf(name) == 0) {
					return c.substring(name.length, c.length);
				}
			}
			return '';
		}

		function _GET(name, url) {
			if (!url) url = window.location.href;
			name = name.replace(/[\[\]]/g, "\\$&");
			var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
			results = regex.exec(url);
			if (!results) return null;
			if (!results[2]) return '';
			return decodeURIComponent(results[2].replace(/\+/g, " "));
		}

		function copy(str){
			var flag = false;
			try {
				var save = function(e) {
					e.clipboardData.setData('text/plain', str);
					e.preventDefault();
				}
				document.addEventListener('copy', save);
				document.execCommand('copy');
				document.removeEventListener('copy', save);
				flag = true;
			}
			catch(e) {
				console.warn('Sorry, Unable to Copy');
			}
			return flag;
		}

		function nonce(){
			return Math.random().toString(36).substring(2);
		}
	</script>
</body>
</html>
<?php
function html_setup($phpInfo){?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<title>File Explorer v<?= VERSION; ?></title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
		<style>
		*,
		*::before,
		*::after {
			outline: none;
			margin: 0;
			padding: 0;
			border: 0;
			color: inherit;
			font: inherit;
			font-size: 100%;
			line-height: 1.5;
			vertical-align: baseline;
			-webkit-box-sizing: border-box;
			box-sizing: border-box;
			-webkit-user-select: none;
			-moz-user-select: none;
			 -ms-user-select: none;
			     user-select: none;
			-webkit-transition: 0.2s;
			-o-transition: 0.2s;
			transition: 0.2s;
			-webkit-animation-timing-function: cubic-bezier(0.52, 1.64, 0.37, 0.66) !important;
			animation-timing-function: cubic-bezier(0.52, 1.64, 0.37, 0.66) !important;
		}
		html {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', Helvetica, Arial, sans-serif;
			font-size: 14px;
		}
		a {
			text-decoration: none;
		}
		body {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-align: center;
			    -ms-flex-align: center;
			        align-items: center;
			-webkit-box-pack: center;
			    -ms-flex-pack: center;
			        justify-content: center;
			background-color: #269;
			height: 100vh;
			overflow: hidden;
		}
		svg {
			width: 2rem;
			height: 2rem;
		}
		.green {color: #383; fill: #383; stroke: #383; stroke-width: 2px; font-weight: 700;}
		.red   {color: #D00; fill: #D00; stroke: #D00; stroke-width: 2px; font-weight: 700;}

		main {
			-webkit-box-flex: 1;
			    -ms-flex: 1;
			        flex: 1;
			min-width: 260px;
			max-width: 380px;
			background-color: #FFF;
			margin: 1rem;
			padding: 2rem;
			border-radius: 4px;
			-webkit-box-shadow: 0 16px 24px 2px rgba(0,0,0,0.14), 0 6px 30px 5px rgba(0,0,0,0.12), 0 8px 10px -5px rgba(0,0,0,0.3);
			        box-shadow: 0 16px 24px 2px rgba(0,0,0,0.14), 0 6px 30px 5px rgba(0,0,0,0.12), 0 8px 10px -5px rgba(0,0,0,0.3);
			-webkit-transform: scale(0) rotate(45deg);
			-ms-transform: scale(0) rotate(45deg);
			    transform: scale(0) rotate(45deg);
			-webkit-animation: anim 1s 0.5s forwards;
			        animation: anim 1s 0.5s forwards;
		}
		form {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-orient: vertical;
			-webkit-box-direction: normal;
			    -ms-flex-direction: column;
			        flex-direction: column;
			-webkit-box-pack: center;
			    -ms-flex-pack: center;
			        justify-content: center;
		}
		ul {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-orient: vertical;
			-webkit-box-direction: normal;
			    -ms-flex-direction: column;
			        flex-direction: column;
			-webkit-box-pack: center;
			    -ms-flex-pack: center;
			        justify-content: center;
			list-style-type: none;
		}
		ul li {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-align: center;
			    -ms-flex-align: center;
			        align-items: center;
			-webkit-box-pack: justify;
			    -ms-flex-pack: justify;
			        justify-content: space-between;
			padding-bottom: 1.75rem;
			position: relative;
		}
		ul li span {
			color: #222;
		}
		ul li small {
			position: absolute;
			bottom: 0.75rem;
			font-size: 0.75rem;
			opacity: 0.6;
		}
		label {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-orient: vertical;
			-webkit-box-direction: normal;
			    -ms-flex-direction: column;
			        flex-direction: column;
			margin-bottom: 1rem;
			color: #444;
			font-weight: bold;
		}
		input {
			width: 100%;
			height: 2.5rem;
			margin: 0.25rem auto;
			padding: 0 0.75rem;
			border: 1px solid #CCC;
			font-weight: initial;
		}
		button {
			-webkit-box-flex: 1;
			    -ms-flex: 1;
			        flex: 1;
			height: 2.5rem;
			color: #FFF;
			background-color: #035;
			text-align: center;
			border: 0;
			border-radius: 4px;
			-webkit-box-shadow: 0 2px 2px #777;
			        box-shadow: 0 2px 2px #777;
			font-size: 1rem;
			cursor: pointer;
		}
		button:hover {
			-webkit-box-shadow: none;
			        box-shadow: none;
		}
		button[disabled] {
			background-color: #888; cursor: not-allowed; -webkit-box-shadow: none; box-shadow: none;
		}

		@-webkit-keyframes anim {
			to {transform: scale(1) rotate(0deg); -webkit-transform: scale(1) rotate(0deg);}
		}
		@keyframes anim {
			to {transform: scale(1) rotate(0deg); -webkit-transform: scale(1) rotate(0deg);}
		}
	</style>
</head>
<body>
	<main>
		<form method="POST" onsubmit="document.querySelector('main').style.opacity = 0; document.querySelector('body').style.backgroundColor = '#035';">
			<ul class="configuration">
				<?php foreach ($phpInfo as $key => $val) : ?>
					<li><span><?= $key; ?></span><?= $val; ?></li>
				<?php endforeach; ?>
			</ul>
			<label>Password <input type="text" name="pwd" placeholder="Enter the Password" value="admin"></label>
			<button name="do" value="install">INSTALL NOW</button>
		</form>
	</main>
</body>
</html>
<?php
}

function html_login($label){?>
	<!--============================================
	# File Explorer
	# Version: <?= VERSION; ?>
	# 
	# https://github.com/webcdn/File-Explorer
	# License: GNU
	=============================================-->
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<title>File Explorer v<?= VERSION; ?></title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
		<link rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEgAAABICAMAAABiM0N1AAAAwFBMVEVMaXEhZZlLoeZUrO0obqRUrO0hZZkob69UrO5UrO4iZpkhZZlTq+1Vf6wiZJohZphUrO5UrO4hZZkhZZlUrO4hZZkiZJpVrO0hZplFldNUq+48icRFltRUrO1UrO5Uq+0hZZlVq+4eZJohZphUrO5Mn94iZplVrO4hZZlJm9tXruwiZphDlNFWrO1UrO1Vq+1VrO5Uq+0veK8mbKFOouM2gbpRp+hTqus6h8IzfbZEldNAkc0jaJwsc6lHmtgiZpkio2ylAAAAMHRSTlMA/A039JHTB96uLZYdAyiRcviI29XoTc2+zepM0VaewKZfIZh89l/z8+wp9O9HgmUP4OQcAAABgElEQVR4Ae3UBXbdQBBE0RYzmNkOg7HF5tn/qsLtz6IKJ3cBb0ZSHdF/fyo/sc/UNG3Lfrkd0UBOoKllNrYHdnbVKsGgSwVqtWdD3o+mWqxTb4lq9Yb6slWrM596eqfabb2gflSXjRgNibN138FC7Xk78dGQ0AIHDYldBw2J5HuFNB8NieR7hezvFTobGSqq27LmGfsnqT40VNw1vIxrDAvlNa+wZgwJ3WS8kusQ2BHPe4fyhtt4fUMPNbfaJLAjXAK/l9jpESryWxb93tH9p9lmPI41FcpLHi+dhKqMAUc0tTeEIaGiZkgooWuGrEUSKhni0rfQPWNOJPTAmGMJ3TDmUkLXjLmS0B1jLiT0yJhTCZWM0SXUMGSfvoUKxuxIKGeMJ6GKMZaE0BmlEkJndCQhdEaGhNAZhd9C9xn8WyP6HjNy6RMT/GgyIzp/KKqGMa/ok0OGZSF9cpAx6pjou1zJ1ekL/T3YCekbfS8D3o+n08TB3uuGR3i7aRn0B/vvI/0jAVz6iypMAAAAAElFTkSuQmCC">
		<style>
		*,
		*::before,
		*::after {
			outline: none;
			padding: 0;
			margin: 0;
			border: 0;
			color: inherit;
			font: inherit;
			font-size: 100%;
			line-height: 1.5;
			vertical-align: baseline;
			-webkit-box-sizing: border-box;
			box-sizing: border-box;
			-webkit-user-select: none;
			-moz-user-select: none;
			 -ms-user-select: none;
			     user-select: none;
			-webkit-transition: 0.2s;
			-o-transition: 0.2s;
			transition: 0.2s;
		}
		html {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', Helvetica, Arial, sans-serif;
			font-size: 14px;
		}
		a {
			cursor: pointer;
			text-decoration: none;
		}
		body {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-align: center;
			    -ms-flex-align: center;
			        align-items: center;
			-webkit-box-pack: center;
			    -ms-flex-pack: center;
			        justify-content: center;
			background-color: #035;
			height: 100vh;
			overflow: hidden;
			padding-bottom: 4rem;
		}
		svg {
			width: 100%;
			height: 100%;
		}
		main {
			-webkit-box-flex: 1;
			    -ms-flex: 1;
			        flex: 1;
			min-width: 260px;
			max-width: 480px;
			margin: 0 0.5rem;
			padding: 1rem 1.5rem;
			text-align: left;
			background-color: #FFF;
			-webkit-box-shadow: 0 16px 24px 2px rgba(0,0,0,0.14), 0 6px 30px 5px rgba(0,0,0,0.12), 0 8px 10px -5px rgba(0,0,0,0.3);
			        box-shadow: 0 16px 24px 2px rgba(0,0,0,0.14), 0 6px 30px 5px rgba(0,0,0,0.12), 0 8px 10px -5px rgba(0,0,0,0.3);
			-webkit-transform: scale(0) rotate(45deg);
			-ms-transform: scale(0) rotate(45deg);
			    transform: scale(0) rotate(45deg);
			-webkit-animation: anim 1s 0.5s forwards;
			        animation: anim 1s 0.5s forwards;
		}
		form {
			-webkit-box-flex: 1;
			    -ms-flex: 1;
			        flex: 1;
			max-width: 100%;
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-orient: vertical;
			-webkit-box-direction: normal;
			    -ms-flex-direction: column;
			        flex-direction: column;
			overflow: hidden;
		}
		form * {
			-webkit-box-flex: 1;
			    -ms-flex: 1;
			        flex: 1;
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			height: inherit;
		}
		form label {
			color: #9e9e9e;
			font-size: 0.8rem;
		}
		form input {
			width: 100%;
			height: 3rem;
			font-size: 1.5rem;
		}
		form button {
			max-width: 3rem;
			height: 3rem;
			cursor: pointer;
			background-color: #F5F5F5;
			border-radius: 4px;
		}
		form button:hover path {
			fill: #369;
			d: path("M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z");
		}

		@-webkit-keyframes anim {
			to {transform: scale(1) rotate(0deg); -webkit-transform: scale(1) rotate(0deg);}
		}
		@keyframes anim {
			to {transform: scale(1) rotate(0deg); -webkit-transform: scale(1) rotate(0deg);}
		}

		footer {
			position: fixed;
			left: 0;
			right: 0;
			bottom: 0;
			z-index: 99;
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-align: center;
			    -ms-flex-align: center;
			        align-items: center;
			-webkit-box-pack: justify;
			    -ms-flex-pack: justify;
			        justify-content: space-between;
			height: 2rem;
			line-height: 2rem;
			padding: 0 1rem;
			color: #EEE;
			background-color: #035;
			-webkit-box-shadow: inset 0 2px 2rem rgba(0,0,0,0.5);
			        box-shadow: inset 0 2px 2rem rgba(0,0,0,0.5);
			font-size: 0.75rem;
		}
		footer * {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			height: inherit;
			line-height: inherit;
		}
		footer svg {
			max-width: 20px;
			max-height: 20px;
			margin: auto;
		}
		@media (max-width : 576px) {
			footer {
				-webkit-box-orient: vertical;
				-webkit-box-direction: normal;
				    -ms-flex-direction: column;
				        flex-direction: column;
				padding: 0.5rem;
				height: 3.6rem;
				line-height: 1.5rem;
			}
			footer > * {
				height: 1.5rem;
			}
		}
	</style>
</head>
<body>
	<main>
		<form method="POST" autocomplete="off" onsubmit="document.querySelector('main').style.opacity = 0; document.querySelector('body').style.backgroundColor = '#035';">
			<?= $label; ?>
			<div>
				<input id="auth" type="password" name="auth" placeholder="Enter Password" spellcheck="false" required="true" autofocus="true" />
				<button type="submit"><svg viewBox="0 0 24 24"><path fill="#999" d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></button>
			</div>
		</form>
	</main>
	<footer>
		<div>
			<span>File Explorer v<?= VERSION; ?></span>
			<b> &nbsp; &bull; &nbsp;</b>
			<span>Made with &nbsp;<svg viewBox="0 0 24 24"><path fill="#D00" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>&nbsp; By &nbsp;<a target="_blank" href="https://github.com/webcdn">WebCDN</a></span>
		</div>
		<div>
			<a target="_blank" href="https://github.com/webcdn/File-Explorer/issues">Report Bugs</a>
			<b> &nbsp; &bull; &nbsp;</b>
			<a target="_blank" href="https://github.com/webcdn/File-Explorer/issues/1">Suggestions / Feedback</a>
			<b> &nbsp; &bull; &nbsp;</b>
			<a target="_blank" href="https://gg.gg/contribute">Donate</a>
		</div>
	</footer>
</body>
</html>
<?php
}


function html_editor($file){?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<title>Edit {<?= basename($file); ?>}</title>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
		<link rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEgAAABICAMAAABiM0N1AAAAwFBMVEVMaXEhZZlLoeZUrO0obqRUrO0hZZkob69UrO5UrO4iZpkhZZlTq+1Vf6wiZJohZphUrO5UrO4hZZkhZZlUrO4hZZkiZJpVrO0hZplFldNUq+48icRFltRUrO1UrO5Uq+0hZZlVq+4eZJohZphUrO5Mn94iZplVrO4hZZlJm9tXruwiZphDlNFWrO1UrO1Vq+1VrO5Uq+0veK8mbKFOouM2gbpRp+hTqus6h8IzfbZEldNAkc0jaJwsc6lHmtgiZpkio2ylAAAAMHRSTlMA/A039JHTB96uLZYdAyiRcviI29XoTc2+zepM0VaewKZfIZh89l/z8+wp9O9HgmUP4OQcAAABgElEQVR4Ae3UBXbdQBBE0RYzmNkOg7HF5tn/qsLtz6IKJ3cBb0ZSHdF/fyo/sc/UNG3Lfrkd0UBOoKllNrYHdnbVKsGgSwVqtWdD3o+mWqxTb4lq9Yb6slWrM596eqfabb2gflSXjRgNibN138FC7Xk78dGQ0AIHDYldBw2J5HuFNB8NieR7hezvFTobGSqq27LmGfsnqT40VNw1vIxrDAvlNa+wZgwJ3WS8kusQ2BHPe4fyhtt4fUMPNbfaJLAjXAK/l9jpESryWxb93tH9p9lmPI41FcpLHi+dhKqMAUc0tTeEIaGiZkgooWuGrEUSKhni0rfQPWNOJPTAmGMJ3TDmUkLXjLmS0B1jLiT0yJhTCZWM0SXUMGSfvoUKxuxIKGeMJ6GKMZaE0BmlEkJndCQhdEaGhNAZhd9C9xn8WyP6HjNy6RMT/GgyIzp/KKqGMa/ok0OGZSF9cpAx6pjou1zJ1ekL/T3YCekbfS8D3o+n08TB3uuGR3i7aRn0B/vvI/0jAVz6iypMAAAAAElFTkSuQmCC">
		<style>
		*,
		*::before,
		*::after {
			outline: none;
			margin: 0;
			padding: 0;
			border: 0;
			color: inherit;
			font: inherit;
			font-size: 100%;
			line-height: 1.5;
			vertical-align: baseline;
			-webkit-box-sizing: border-box;
			box-sizing: border-box;
			-webkit-user-select: none;
			-moz-user-select: none;
			 -ms-user-select: none;
			     user-select: none;
			-webkit-transition: 0.2s;
			-o-transition: 0.2s;
			transition: 0.2s;
		}
		html {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', Helvetica, Arial, sans-serif;
			font-size: 14px;
			min-width: 280px;
		}
		body {
			position: relative;
			overflow: hidden;
			overflow-y: scroll;
			background-color: whitesmoke;
		}
		a {
			color: #FFF;
			cursor: pointer;
			text-decoration: none;
		}
		header {
			height: 4rem;
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-align: center;
			    -ms-flex-align: center;
			        align-items: center;
			-webkit-box-pack: justify;
			    -ms-flex-pack: justify;
			        justify-content: space-between;
			padding: 0.5rem 1rem;
			position: fixed;
			left: 0;
			right: 0;
			top: 0;
			z-index: 100;
			background-color: inherit;
			-webkit-box-shadow: 0 4px 5px 0 rgba(0,0,0,0.14), 0 1px 10px 0 rgba(0,0,0,0.12), 0 2px 4px -1px rgba(0,0,0,0.2);
			        box-shadow: 0 4px 5px 0 rgba(0,0,0,0.14), 0 1px 10px 0 rgba(0,0,0,0.12), 0 2px 4px -1px rgba(0,0,0,0.2);
		}
		label {
			-webkit-box-flex: 1;
			    -ms-flex: 1;
			        flex: 1;
			color: #666;
			font-weight: bold;
			font-size: 1.5rem;
			position: relative;
		}
		label[data-size] {
			margin-top: -1rem;
		}
		label[data-size]:after {
			content: attr(data-size);
			position: absolute;
			top: 2rem;
			left: 0;
			opacity: 0.5;
			font-size: 0.9rem;
		}
		.action {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-align: center;
			    -ms-flex-align: center;
			        align-items: center;
			-webkit-box-pack: end;
			    -ms-flex-pack: end;
			        justify-content: flex-end;
		}
		.btn {
			display: inline-block;
			height: 2.25rem;
			line-height: 2.25rem;
			padding: 0 1rem;
			margin: 0.25rem;
			border-radius: 2px;
			border: 1px solid transparent;
			color: #fff;
			background-color: #369;
			text-align: center;
			text-decoration: none;
			text-transform: uppercase;
			vertical-align: middle;
			letter-spacing: 0.5px;
			-webkit-transition: background-color 0.2s ease-out;
			-o-transition: background-color 0.2s ease-out;
			transition: background-color 0.2s ease-out;
			-webkit-tap-highlight-color: transparent;
			cursor: pointer;
			-webkit-box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2);
			        box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 0 3px 1px -2px rgba(0,0,0,0.12), 0 1px 5px 0 rgba(0,0,0,0.2);
		}
		.btn svg {
			fill: #FFF;
			width: 100%;
			height: 100%;
		}
		.btn.flat {
			border: 1px solid #DDD;
			color: #555;
			background-color: rgba(0,0,0,0.05);
			-webkit-box-shadow: none;
			        box-shadow: none;
		}
		.btn.flat svg {
			fill: #666;
		}

		form {
			margin: 5rem 1rem;
		}
		.inputs {
			position: relative;
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-orient: vertical;
			-webkit-box-direction: normal;
			    -ms-flex-direction: column;
			        flex-direction: column;
			margin: 1rem auto;
		}
		textarea {
			-webkit-box-flex: 1;
			    -ms-flex: 1;
			        flex: 1;
			display: inline-block;
			padding: 0.5rem;
			color: #222;
			background-color: white;
			font-size: 0.9rem;
			font-family: monospace;
			border-radius: 2px; 
			border: 1px solid #CCC;
			vertical-align: middle;
			-webkit-tap-highlight-color: transparent;
			resize: vertical;
		}
		textarea:focus {
			border: 1px solid darkcyan;
		}

		.toast {
			position: fixed;
			left: 0;
			right: 0;
			bottom: -10rem;
			z-index: 10000;
			opacity: 0;
			width: auto;
			height: auto;
			min-height: 3rem;
			line-height: 1.5;
			margin: 1rem;
			padding: 0.5rem 1rem;
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-align: center;
			    -ms-flex-align: center;
			        align-items: center;
			-webkit-box-pack: justify;
			    -ms-flex-pack: justify;
			        justify-content: space-between;
			color: #FFF;
			background-color: #222;
			border-radius: 2px;
			font-size: 0.9rem;
			font-weight: 400;
			letter-spacing: 0.2px;
			-webkit-box-shadow: 0 2px 2px 0 rgba(0, 0, 0, 0.14), 0 3px 1px -2px rgba(0, 0, 0, 0.12), 0 1px 5px 0 rgba(0, 0, 0, 0.2);
			box-shadow: 0 2px 2px 0 rgba(0, 0, 0, 0.14), 0 3px 1px -2px rgba(0, 0, 0, 0.12), 0 1px 5px 0 rgba(0, 0, 0, 0.2);
			-webkit-transition: 0.5s;
			-o-transition: 0.5s;
			transition: 0.5s;
		}
		.toast.wait {
			cursor: wait;
			padding-left: 4rem;
		}
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
		@-webkit-keyframes pulsate {
			0%		{ opacity: 0; transform: scale(0.1); -webkit-transform: scale(0.1);}
			50%		{ opacity: 1; }
			100%	{ opacity: 0; transform: scale(1.2); -webkit-transform: scale(1.2);}
		}
		@keyframes pulsate {
			0%		{ opacity: 0; transform: scale(0.1); -webkit-transform: scale(0.1);}
			50%		{ opacity: 1; }
			100%	{ opacity: 0; transform: scale(1.2); -webkit-transform: scale(1.2);}
		}
		@media (min-width: 576px) {
			.toast {
				min-width: 18rem;
				max-width: 30rem;
				right: auto;
			}
		}

		.overlay {
			overflow: hidden;
			position: fixed;
			z-index: 101;
			bottom: 0;
			right: 0;
			left: 0;
			top: 0;
			background-color: #000;
			visibility: hidden;
			opacity: 0;
		}
		body.toast_on .overlay {
			opacity: 0.7;
			visibility: visible;
		}

		footer {
			position: fixed;
			left: 0;
			right: 0;
			bottom: 0;
			z-index: 99;
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			-webkit-box-align: center;
			    -ms-flex-align: center;
			        align-items: center;
			-webkit-box-pack: justify;
			    -ms-flex-pack: justify;
			        justify-content: space-between;
			height: 2rem;
			line-height: 2rem;
			padding: 0 1rem;
			color: #EEE;
			background-color: #035;
			-webkit-box-shadow: 0 2px 2rem rgba(0,0,0,0.5);
			        box-shadow: 0 2px 2rem rgba(0,0,0,0.5);
			font-size: 0.75rem;
		}
		footer * {
			display: -webkit-box;
			display: -ms-flexbox;
			display: flex;
			height: inherit;
			line-height: inherit;
		}
		footer svg {
			max-width: 20px;
			max-height: 20px;
			margin: auto;
		}
		@media (max-width : 576px) {
			footer {
				-webkit-box-orient: vertical;
				-webkit-box-direction: normal;
				    -ms-flex-direction: column;
				        flex-direction: column;
				padding: 0.5rem;
				height: 3.6rem;
				line-height: 1.5rem;
			}
			footer > * {
				height: 1.5rem;
			}
		}
	</style>
</head>
<body>
	<div class="overlay"></div>
	<header>
		<label for="codedit" data-bytes="<?= @filesize($file); ?>"><?= basename($file); ?></label>
		<div class="action">
			<button type="submit" class="btn" form="editor">Save</button>
			<button class="btn flat" onclick="window.open('', '_self', '').close(); return false;">Close</button>
		</div>
	</header>
	<form method="POST" id="editor">
		<div class="inputs">
			<input type="hidden" id="xsrf" name="xsrf" value="<?= @$_COOKIE['__xsrf']; ?>">
			<textarea id="codedit" name="content" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?= htmlentities( mb_convert_encoding(getData($file), 'UTF-8', 'auto') ); ?></textarea>
		</div>
	</form>
	<footer>
		<div>
			<span>File Explorer v<?= VERSION; ?></span>
			<b> &nbsp; &bull; &nbsp;</b>
			<span>Made with &nbsp;<svg viewBox="0 0 24 24"><path fill="#D00" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>&nbsp; By &nbsp;<a target="_blank" href="https://github.com/webcdn">WebCDN</a></span>
		</div>
		<div>
			<a target="_blank" href="https://github.com/webcdn/File-Explorer/issues">Report Bugs</a>
			<b> &nbsp; &bull; &nbsp;</b>
			<a target="_blank" href="https://github.com/webcdn/File-Explorer/issues/1">Suggestions / Feedback</a>
			<b> &nbsp; &bull; &nbsp;</b>
			<a target="_blank" href="https://gg.gg/contribute">Donate</a>
		</div>
	</footer>
	<script type="text/javascript">
		var ajax = {};
		ajax.xhr = function () {
			if (typeof XMLHttpRequest != 'undefined') {
				return new XMLHttpRequest();
			}
			var _xhr, _ver = ["MSXML2.XmlHttp.6.0","MSXML2.XmlHttp.5.0","MSXML2.XmlHttp.4.0","MSXML2.XmlHttp.3.0","MSXML2.XmlHttp.2.0","Microsoft.XmlHttp"];
			for (var i in _ver) {
				try {
					_xhr = new ActiveXObject(_ver[i]);
					break;
				} catch (e) {}
			}
			return _xhr;
		};

		ajax.send = function (url, method, data, callback, async) {
			var xhr = ajax.xhr();
			xhr.open(method, url, typeof async == 'boolean' ? async : true);
			xhr.onreadystatechange = function(){
				xhr.readyState == 4 && callback(xhr.responseText);
			}
			method == 'POST' && xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
			xhr.send(data)
		};

		function submitFrom(e){
			e.preventDefault();
			toast('Saving...', '', 'wait');
			var formData = [];
			formData.push('do=save');
			formData.push('xsrf='+ document.querySelector('#xsrf').value);
			formData.push('nonce='+ Math.random().toString(36).substring(2));
			formData.push('content='+ encodeURIComponent(document.querySelector('#codedit').value));

			ajax.send('', 'POST', formData.join('&'), function(data){
				data = JSON.parse(data);
				toast(data.response, data.flag == true ? '#070' : '#B00');
			});
		}

		window.onbeforeunload = function() {
			return 'Sorry, changes might not saved.';
		}

		window.onload = function() {
			autosize('#codedit');
			var size = document.querySelector('[data-bytes]').getAttribute('data-bytes');
			document.querySelector('[data-bytes]').setAttribute('data-size', formatFileSize(size));
		}

		window.onresize = function() {
			autosize('#codedit');
		}
		
		document.querySelector('#editor').addEventListener('submit', submitFrom);
		document.querySelector('#codedit').addEventListener('keydown', autosize);
		document.querySelector('#codedit').addEventListener('paste', function(){
			setTimeout(function(e){
				autosize('#codedit');
			}, 0);
		});
		document.addEventListener('keydown', function(e) {
			if( (e.ctrlKey || e.metaKey) && e.keyCode == 83 ){
				submitFrom(e);
			}
		});
		
		function autosize(elm = null){
			elm = typeof elm == 'string' ? document.querySelector(elm) : this;
			var scrollPos = document.documentElement.scrollTop;
			elm.style.cssText = 'height: auto; padding: 0;';
			elm.style.cssText = 'height: ' + elm.scrollHeight + 'px; overflow: hidden;';
			document.documentElement.scrollTop = scrollPos;
		}

		function formatFileSize(bytes, round = 2) {
			if(bytes < 0) return 'Too Large';
			var units, power, size;
			units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
			bytes = Math.max(0, bytes);
			power = Math.floor( (bytes ? Math.log(bytes) : 0) / Math.log(1024) );
			power = Math.min(power, (units.length - 1));
			bytes /= Math.pow(1024, power);
			return Number(bytes.toFixed(round)) + ' ' + units[power];
		}

		function toast(message, color = '', time = 5000) {
			var toasts = document.querySelectorAll('.toast');
			for(var i = 0; i < toasts.length; i++){
				toasts[i].dismiss();
			}

			var toast = document.createElement('div');
			toast.className = 'toast';
			typeof time != 'number' && toast.classList.add(time);
			time == 'wait' && document.querySelector('body').classList.add('toast_on');
			toast.dismiss = function() {
				this.style.bottom = '-10rem';
				this.style.opacity = 0;
				document.querySelector('body').classList.remove('toast_on');
			};

			var text = document.createTextNode(message);
			toast.appendChild(text);

			document.body.appendChild(toast);
			getComputedStyle(toast).bottom;
			getComputedStyle(toast).opacity;
			toast.style.backgroundColor = color;
			toast.style.bottom = document.body.scrollWidth > 576 ? '2rem' : '3.6rem';
			toast.style.opacity = 1;

			if(typeof time == 'number'){
				setTimeout(function() {
					toast.dismiss();
				}, time);
			}

			toast.addEventListener('transitionend', function(event, elapsed) {
				if( event.propertyName === 'opacity' && this.style.opacity == 0 ){
					this.parentElement.removeChild(this);
				}
			}.bind(toast));
		}
	</script>
</body>
</html>
<?php
}