<?php

class OAuthClient extends CurlClient {
	public function authorizationHeader($config) {
		if (!$config['access_token']) {
			$this->accessToken($config);
			http_response_code(500);
			exit();
		}

		return 'Authorization: Bearer ' . $config['access_token'];
	}

	protected function accessToken($config) {
		foreach (array('token_url', 'consumer_key', 'consumer_secret') as $option) {
			if (!isset($config[$option])) {
				http_response_code(500);
				$this->report(sprintf('Error: option "%s" not provided', $option));
				exit();
			}
		}

		$curl = curl_init();

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_URL => $config['token_url'],
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
				CURLOPT_HTTPHEADER => array(
					'Authorization: Basic ' . base64_encode(rawurlencode($config['consumer_key']) . ':' . rawurlencode($config['consumer_secret']))
				),
				CURLOPT_RETURNTRANSFER => true,
				//CURLOPT_VERBOSE => true,
			)
		);

		$result = curl_exec($curl);
		$response = json_decode($result, true);

		if (!isset($response['access_token'])) {
			$this->report('No access token found: ');
			$this->report(print_r($response, true));
			exit();
		}

		$this->report(sprintf('Access token: %s', $response['access_token']));
	}
}
