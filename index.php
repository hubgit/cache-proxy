<?php

ini_set('display_errors', false);

require 'CurlClient.php';
require 'OAuthClient.php';

$method = $_SERVER['REQUEST_METHOD'];

// TODO: handle or reject relative and non-HTTP(S) URLs

switch ($method) {
  case 'OPTIONS':
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: accept, x-requested-with, content-type, vege-cache-control, vege-follow, vege-user-agent');
    exit();

  case 'GET':
  case 'HEAD':
  case 'POST':
  case 'PUT':
  case 'DELETE':
    break; // allowed

  default:
    http_status_code(406); // not allowed
    exit();
}

if (!isset($_GET['url'])) {
  throw new Exception('No URL');
}

$url = $_GET['url'];

$file = buildFilePath($method, $url);
//print "$file\n"; exit();

$headers = getallheaders();

$nocache = isset($headers['Vege-Cache-Control']) && ($headers['Vege-Cache-Control'] == 'no-cache');

if ($nocache || (!file_exists($file) || !file_exists($file . '.json') || filesize($file) < 50)) {
  /* pass through request headers */
  $requestHeaders = array_map(function($value, $key) {
    $key = strtolower($key);

    if ($key == 'vege-user-agent') {
        return 'User-Agent: ' . $value;
    }

    if (!in_array($key, array('origin', 'referer', 'connection', 'host', 'accept-encoding', 'dnt', 'vege-cache-control', 'vege-follow'))) {
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

  if (isset($config['headers'])) {
    foreach ($config['headers'] as $key => $value) {
      $requestHeaders[] = $key . ': ' . $value;
    }
  }

  switch ($method) {
    case 'HEAD':
    curl_setopt($client->curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
    curl_setopt($client->curl, CURLOPT_FOLLOWLOCATION, false);
    break;

    case 'POST':
    curl_setopt($client->curl, CURLOPT_POST, true);
    curl_setopt($client->curl, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    break;

    case 'DELETE':
    curl_setopt($client->curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    break;

    case 'PUT':
    // TODO
    break;
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
  'content-location',
  'x-status',
  'x-location',
  'x-ratelimit-limit',
  'x-ratelimit-remaining',
  'x-ratelimit-reset',
  'x-rate-limit-limit',
  'x-rate-limit-remaining',
  'x-rate-limit-reset',
);

if ($info['redirect_url']) {
  header('x-status: ' . $info['http_code']);
  header('x-location: ' . $info['redirect_url']);
  http_response_code(204);
} else {
  http_response_code($info['http_code']);
  header('Content-Type: ' . $info['content_type']);
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Expose-Headers: ' . implode(', ', $exposedHeaders));
header('Content-Location: ' . $info['url']);
//header('Content-Length: ' . filesize($file));

foreach ($info['headers'] as $key => $value) {
  if (in_array($key, $exposedHeaders)) {
    header($key . ': ' . $value);
  }
}

// remove the response file on failure. TODO: something better?
if ($info['http_code'] >= 400) {
  unlink($file);
  exit();
}

//if ($method === 'GET' || $method === 'POST') {
  readfile('compress.zlib://' . $file);
//}

function readConfig($url) {
  $configFile = __DIR__ . '/config.json';

  if (!file_exists($configFile)) {
    return null;
  }

  $configs = json_decode(file_get_contents($configFile), true);

  $host = parse_url($url, PHP_URL_HOST);

  return isset($configs[$host]) ? $configs[$host] : null;
}

function buildFilePath($method, $url) {
  $parts = parse_url($url);

  if (!in_array($parts['scheme'], array('http', 'https'))) {
    throw new Exception('URL must be HTTP or HTTPS');
  }

  $host = $parts['host'];

  if (isset($parts['port'])) {
    $host .= '-' . $parts['port'];
  }

  $host = preg_replace('/[^\w\.]/', '-', $host);

  $dir = __DIR__ . '/cache/' . $host . '/' . $method;

  if (!file_exists($dir)) {
    mkdir($dir, 0700, true);
  }

  if ($method == 'POST') {
    $url .= file_get_contents('php://input');
  }

  return $dir . '/' . hash('sha256', $url);
}
