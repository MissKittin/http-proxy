<?php
	// accept connection only from your remote server and loopback, log if denied
	if($_SERVER['REMOTE_ADDR'] != 'YOUR_PROVIDER_IP' && $_SERVER['REMOTE_ADDR'] != '127.0.0.1')
	{
		error_log('! Banned request from ' . $_SERVER['REMOTE_ADDR'], 0);
		http_response_code(403);
		exit();
	}

	// decrypt $_POST array - if you dont want this, change $post_encrypt to false
	// $post_encryption_key and $post_encryption_iv must be the same in client and server
	$post_encrypt=true;
	$post_encryption_key='CHANGE_THIS_TO_RANDOM_STRING';
	$post_encryption_iv='CHANGE_THIS_TO_RANDOM_STRING';
	if($post_encrypt)
		foreach($_POST as $i=>$x)
			if(is_array($_POST[$i]))
				foreach($_POST[$i] as $y=>$z)
					$_POST[$i][$y]=openssl_decrypt($z, 'aes128', $post_encryption_key, 0, $post_encryption_iv);
			else
				$_POST[$i]=openssl_decrypt($x, 'aes128', $post_encryption_key, 0, $post_encryption_iv);
	unset($post_encrypt); unset($post_encryption_key); unset($post_encryption_iv); unset($i); unset($x); unset($y); unset($z);

	// log incoming IP
	if(isset($_POST['SERVER']['REMOTE_ADDR']))
		error_log('i Proxy request from ' . $_POST['SERVER']['REMOTE_ADDR'], 0);

	// force https (if your hosting dont have https, comment if below)
	if($_SERVER['REMOTE_ADDR'] != '127.0.0.1')
		if(!isset($_POST['SERVER']['HTTPS']))
		{
			echo '<!DOCTYPE html><html lang="pl-PL"><head><title>Redirect...</title><meta http-equiv="refresh" content="0; url=https://' . $_POST['SERVER']['HTTP_HOST'] . $_POST['SERVER']['REQUEST_URI'] . '" /></head><body><h1>Redirecting...</h1></body></html>';
			exit();
		}

	// router cache
	$router_cache['strtok']=strtok($_SERVER['REQUEST_URI'], '?');

	// import custom .router.php
	$router_dirs=['mydirwithrouter1', 'mydirwithrouter2'];
	foreach($router_dirs as $router_dir)
	{
		$customrouter=explode('/', $router_cache['strtok']);
		if($customrouter[1] === $router_dir)
			if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $customrouter[1] . '/.router.php'))
				include $_SERVER['DOCUMENT_ROOT'] . '/' . $customrouter[1] . '/.router.php';
	}
	unset($router_dirs); unset($router_dir); unset($customrouter);

	// hide script - fake 404
	if(substr($router_cache['strtok'], strrpos($router_cache['strtok'], '/') + 1) === 'router.php')
	{
		echo '<!DOCTYPE html>
			<html>
				<head>
					<meta http-equiv="refresh" content="0; url=.">
				</head>
			</html>
		';
		exit();
	}

	// 404 handle - for files
	if(!file_exists($_SERVER['DOCUMENT_ROOT'] . $router_cache['strtok']))
	{
		if(substr(strtok($_SERVER['REQUEST_URI'], '?'), -1) === '/')
			$url='..';
		else
			$url='.';

		echo '<!DOCTYPE html>
			<html>
				<head>
					<meta http-equiv="refresh" content="0; url=' . $url . '">
				</head>
			</html>
		';
		exit();
	}

	// 404 handle - for dirs
	if(is_dir($_SERVER['DOCUMENT_ROOT'] . $router_cache['strtok']))
		if((file_exists($_SERVER['DOCUMENT_ROOT'] . $router_cache['strtok'] . '/index.php')) || (file_exists($_SERVER['DOCUMENT_ROOT'] . $router_cache['strtok'] . '/index.html')))
		{ /* everything is ok */ }
		else
		{
			if(substr(strtok($_SERVER['REQUEST_URI'], '?'), -1) === '/')
				$url='..';
			else
				$url='.';

			echo '<!DOCTYPE html>
				<html>
					<head>
						<meta http-equiv="refresh" content="0; url=' . $url . '">
					</head>
				</html>
			';
			exit();
		}

	// drop cache
	unset($router_cache);

	// abort script - load destination file
	return false;
?>

HTTP Proxy
24.08.2019
Dependencies: dyndns

Put this files on your remote server:
index.php:
===============================
<?php
	// PHP proxy server
	// 24.08.2019
	// $_SERVER => $_POST injection 14.09.2019
	// Cookies implementation 15.09.2019
	// User agent forwarding and variables cleaning 16.09.2019
	// $_POST encryption and content type implementation 18.09.2019
	// dyndns disable 31.10.2019

	// Note: You must set post_max_size to high value eg. 4120
	// with $_POST encryption ~ 6000
	// $post_encryption_key and $post_encryption_iv must be the same in client and server

	// Settings
	$ip=''; // not used if dyndns is enabled
	$port=PORT_OF_YOUR_HTTP_SERVER; //server port
	$protocol='http'; //server protocol
	$dyndns_enable=true; // enable/disable dyndns
	$dyndns_server_data='PATH_TO_DYNDNS/ip.txt';
	$post_encrypt=true; //enable $_POST encryption
	$post_encryption_key='CHANGE_THIS_TO_RANDOM_STRING';
	$post_encryption_iv='CHANGE_THIS_TO_RANDOM_STRING'; //must be 16 bytes long

	error_reporting(E_ERROR | E_PARSE); //disable warnings
	if($dyndns_enable)
		if(!$ip=file_get_contents($dyndns_server_data)) //get server ip
		{ //something is wrong in config
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Error</title>
		<meta charset="utf-8">
		<style type="text/css">
			body {
				background-color: #000;
				color: #fff;
			}
		</style>
	</head>
	<body>
		<h1>Server address not found</h1>
	</body>
</html>
<?php
		exit();
	}

	//prepare
	$dir=explode('/', $_SERVER['REQUEST_URI']); $dir='/' . $dir[1]; //extract directory
	$_POST['SERVER']=$_SERVER; //$_SERVER=>$_POST injection
	$cookies=''; foreach($_COOKIE as $i=>$x) $cookies=$cookies . $i . '=' . $x . '; '; //create cookies for context
	$user_agent=''; foreach(getallheaders() as $i=>$x) if($i==='User-Agent') { $user_agent="$x"; break; } //get user agent for context
	if($post_encrypt) foreach($_POST as $i=>$x) if(is_array($_POST[$i])) foreach($_POST[$i] as $y=>$z) $_POST[$i][$y]=openssl_encrypt($z, 'aes128', $post_encryption_key, 0, $post_encryption_iv); else $_POST[$i]=openssl_encrypt($x, 'aes128', $post_encryption_key, 0, $post_encryption_iv); //encrypt $_POST
	$content=stream_context_create(array('http'=>array('method'=>'POST', 'header'=>array('User-Agent: ' . $user_agent, 'Cookie: ' . $cookies, 'Content-Type: application/x-www-form-urlencoded'), 'content'=>http_build_query($_POST)))); //add POST array

	//send
	if($content=file_get_contents('http://' . $ip . ':' . $port . str_replace($dir, '', $_SERVER['REQUEST_URI']), false, $content))
	{
		//set received content type
		foreach($http_response_header as $i)
		{
			if(preg_match('/^Content-type:\s*([^;]+)/', $i, $x))
				header('Content-type:' . $x[1]);
			if(preg_match('/^Content-Type:\s*([^;]+)/', $i, $x))
				header('Content-Type:' . $x[1]);
			if(preg_match('/^Content-Length:\s*([^;]+)/', $i, $x))
				header('Content-Length:' . $x[1]);
			if(preg_match('/^Content-length:\s*([^;]+)/', $i, $x))
				header('Content-length:' . $x[1]);
		}
		
		//set received cookies
		$cookies=array();
		foreach($http_response_header as $i) if(preg_match('/^Set-Cookie:\s*([^;]+)/', $i, $x)) { parse_str($x[1], $y); $cookies+=$y; }
		foreach($cookies as $i=>$x) setcookie($i, $x, time() + (86400 * 30), '/');

		echo $content; //and display downloaded content
	}
	else
	{ //something is wrong with client
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Error</title>
		<meta charset="utf-8">
		<style type="text/css">
			body {
				background-color: #000;
				color: #fff;
			}
		</style>
	</head>
	<body>
		<h1>Server is down. Sorry ¯\_(ツ)_/¯</h1>
		<h3>Come back later</h3>
	</body>
</html>
<?php }
	/* Variables map:
	-----------	-$ip => proxy server address
			|$port => proxy server port
			|$protocol => proxy server protocol (http or https)
			|$dyndns_enable => enable/disable dyndns
	settings	|$dyndns_server_data
			|$post_encrypt => $_POST encryption switch
			|$post_encryption_key => openssl key for $_POST
			-$post_encryption_iv => openssl initialization vector for $_POST
	-----------	-$ip => content of file $dyndns_server_data
			|$dir => extracted directory
	auto		|$cookies => browser cookies
	generated	|$user_agent => browser ID
			-$content => file_get_contents() ->
	-----------	-$content => downloaded page <-
	downloaded	|$cookies => received cookies array <-
	-----------	-$http_response_header => from file_get_contents() <-
		Variables in loops: $i, $x, $y $z
	*/
?>
===============================

.htaccess
===============================
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule . index.php [L]
===============================
