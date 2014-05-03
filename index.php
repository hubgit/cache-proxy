<?php

require 'CurlClient.php';
require 'OAuthClient.php';

// TODO: handle or reject relative and non-HTTP(S) URLs

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, OPTIONS');
  header('Access-Control-Allow-Headers: accept, x-requested-with, content-type');
  exit();
}

$url = $_GET['url'];
$file = __DIR__ . '/cache/' . hash('sha256', $url);

if (!file_exists($file) || !file_exists($file . '.json')) {
  $headers = getallheaders();

  $requestHeaders = array_map(function($value, $key) {
    if (!in_array($key, array('Origin', 'Referer', 'Connection', 'Host'))) {
      return $key . ':' . $value;
    }
  }, $headers, array_keys($headers));

  $output = fopen($file, 'w');

  $host = parse_url($url, PHP_URL_HOST);

  switch ($host) {
    // TODO: match against authorisers from JSON
    case 'api.twitter.com':
      $client = new OAuthClient;

      $auth = parse_ini_file(getenv('HOME') . '/.config/twitter.ini');
      $auth['token_url'] = 'https://api.twitter.com/oauth2/token';
      $requestHeaders[] = $client->authorizationHeader($auth);
      break;

    default:
      $client = new CurlClient;
      break;
  }

  // TODO: catch exceptions
  $info = $client->get($url, array(), $requestHeaders, $output);

  file_put_contents($file . '.json', json_encode($info, JSON_PRETTY_PRINT));
}

$info = json_decode(file_get_contents($file . '.json'), true);

http_response_code($info['http_code']);
header('Access-Control-Allow-Origin: *');
header('Content-Type: ' . $info['content_type']);
//header('Content-Length: ' . filesize($file));

readfile($file);
exit();

function fetch($url, $output) {
  $headers = getallheaders();

  $requestHeaders = array_map(function($value, $key) {
    if (!in_array($key, array('Origin', 'Referer', 'Connection', 'Host'))) {
      return $key . ':' . $value;
    }
  }, $headers, array_keys($headers));

  curl_setopt($curl, CURLOPT_HTTPHEADER, $requestHeaders);


  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_VERBOSE, true);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_ENCODING, '');
  curl_setopt($curl, CURLOPT_FILE, $output);
  curl_exec($curl);
  $info = curl_getinfo($curl);
  curl_close($curl);
  fclose($output);

  return $info;
}