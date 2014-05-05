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

// TODO: watch for skip-cache request header (e.g. retries)
if (!file_exists($file) || !file_exists($file . '.json')) {
  /* pass through request headers */
  $headers = getallheaders();

  $requestHeaders = array_map(function($value, $key) {
    if (!in_array($key, array('Origin', 'Referer', 'Connection', 'Host'))) {
      return $key . ':' . $value;
    }
  }, $headers, array_keys($headers));

  /* host configuration */
  $config = readConfig($url);

  if (is_array($config['oauth'])) {
    $client = new OAuthClient;
    $requestHeaders[] = $client->authorizationHeader($config['oauth']);
  } else {
    $client = new CurlClient;
  }

  /* output file */
  $output = fopen($file, 'w');

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

function readConfig($url) {
  $configFile = __DIR__ . '/config.json';

  if (!file_exists($configFile)) {
    return null;
  }

  $configs = json_decode(file_get_contents($configFile), true);

  $host = parse_url($url, PHP_URL_HOST);

  return isset($configs[$host]) ? $configs[$host] : null;
}
