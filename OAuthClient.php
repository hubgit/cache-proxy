<?php

class OAuthClient extends CurlClient {
	public function authorizationHeader($config) {
		if (!$config['access_token']) {
			foreach (array('token_url', 'consumer_key', 'consumer_secret') as $option) {
				if (!isset($config[$option])) {
					printf("Error: option '%s' not provided\n", $option);
					exit();
				}
			}

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $config['token_url']);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_VERBOSE, true);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Authorization: Basic ' . base64_encode(rawurlencode($config['consumer_key']) . ':' . rawurlencode($config['consumer_secret']))
			));

			$result = curl_exec($curl);
			$response = json_decode($result, true);

			if (!isset($response['access_token'])) {
				exit('No access token found:' . print_r($response, true));
			}

			$config['access_token'] = $response['access_token'];
			printf("Access token: %s\n", $config['access_token']);
		}

		return 'Authorization: Bearer ' . $config['access_token'];
	}
}
