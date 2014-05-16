<?php

require 'CurlClient.php';
require 'OAuthClient.php';

// TODO: handle or reject relative and non-HTTP(S) URLs

switch ($_SERVER['REQUEST_METHOD']) {
  case 'OPTIONS':
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: accept, x-requested-with, content-type, vege-cache-control');
    exit();

  case 'GET':
    break; // allowed

  default:
    http_status_code(406); // not allowed
    exit();
}

$url = $_GET['url'];

$file = buildFilePath($url);

$headers = getallheaders();

$nocache = isset($headers['Vege-Cache-Control']) && ($headers['Vege-Cache-Control'] == 'no-cache');

if ($nocache || (!file_exists($file) || !file_exists($file . '.json') || !filesize($file))) {
  /* pass through request headers */
  $requestHeaders = array_map(function($value, $key) {
    $key = strtolower($key);

    if (!in_array($key, array('origin', 'referer', 'connection', 'host', 'vege-cache-control'))) {
      return $key . ': ' . $value;
    }
  }, $headers, array_keys($headers));

  /* host configuration */
  $config = readConfig($url);

  if (isset($config['oauth'])) {
    $client = new OAuthClient;
    $requestHeaders[] = $client->authorizationHeader($config['oauth']);
  } else {
    $client = new CurlClient;
  }

  if (isset($config['params'])) {
    $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($config['params']);
  }

  /* output file */
  $output = gzopen($file, 'w');

  // TODO: catch exceptions
  $info = $client->get($url, $requestHeaders, $output);

  gzclose($output);

  file_put_contents($file . '.json', json_encode($info, JSON_PRETTY_PRINT));
}

$info = json_decode(file_get_contents($file . '.json'), true);

$exposedHeaders = array(
  'link',
  'x-ratelimit-limit',
  'x-ratelimit-remaining',
  'x-ratelimit-reset',
  'x-rate-limit-limit',
  'x-rate-limit-remaining',
  'x-rate-limit-reset',
);

http_response_code($info['http_code']);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Expose-Headers: ' . implode(', ', $exposedHeaders));
header('Content-Type: ' . $info['content_type']);
//header('Content-Length: ' . filesize($file));

foreach ($info['headers'] as $key => $value) {
  if (in_array($key, $exposedHeaders)) {
    header($key . ': ' . $value);
  }
}

// remove the response file on failure. TODO: something better?
if ($info['http_code'] >= 300) {
  unlink($file);
  exit();
}

readfile('compress.zlib://' . $file);

function readConfig($url) {
  $configFile = __DIR__ . '/config.json';

  if (!file_exists($configFile)) {
    return null;
  }

  $configs = json_decode(file_get_contents($configFile), true);

  $host = parse_url($url, PHP_URL_HOST);

  return isset($configs[$host]) ? $configs[$host] : null;
}

function buildFilePath($url) {
  $parts = parse_url($url);

  $host = $parts['host'];

  if ($parts['port']) {
    $host .= '-' . $parts['port'];
  }

  $host = preg_replace('/[^\w\.]/', '-', $host);

  $dir = __DIR__ . '/cache/' . $host;

  if (!file_exists($dir)) {
    mkdir($dir, 0700, true);
  }

  return $dir . '/' . hash('sha256', $url);
}
