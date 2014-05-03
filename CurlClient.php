<?php

/**
 * A wrapper for cURL
 */
class CurlClient
{
    /** @var resource */
    public $curl;

    /** @var array */
    public $headers;

    /**
     * create a cURL instance
     */
    public function __construct($verbose = true)
    {
        $this->curl = curl_init();

        curl_setopt_array(
            $this->curl,
            array(
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_VERBOSE => $verbose,
                CURLOPT_HEADERFUNCTION => array($this, 'header'),
                CURLOPT_ENCODING => '',
            )
        );
    }

    /**
     * Make a GET request
     * Either save the response to a file, or return it
     *
     * @param string $url
     * @param array  $params
     * @param null   $file
     * @param int    $tries
     *
     * @return bool|mixed
     * @throws Exception
     */
    public function get($url, $params = array(), $headers = array(), $file = null, $tries = 0)
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        file_put_contents('php://stderr', sprintf("Fetching %s\n", $url));

        curl_setopt_array($this->curl, array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FILE => is_null($file) ? STDOUT : $file, // STDOUT if no file
        ));

        $this->headers = array();
        $result = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        switch ($info['http_code']) {
            case 200:
                return is_null($file) ? $result : $info;

            case 429: // rate limit
                if ($tries == 5) {
                    throw new Exception('Rate limited too many times');
                }

                $this->delay();

                return $this->get($url, array(), $headers, $file, ++$tries);

            default:
                $message = sprintf('Response not OK: %d %s', $info['http_code'], $result);

                throw new Exception($message);
        }
    }

    /**
     * Store response headers in an array
     *
     * @param $curl
     * @param $header
     *
     * @return int header length
     */
    protected function header($curl, $header) {
        $parts = preg_split('/:\s+/', $header, 2);

        if (isset($parts[1])) {
            list($name, $value) = $parts;
            $this->headers[strtolower($name)] = $value;
        }

        return strlen($header);
    }

    /**
     * Delay if rate limit is reached
     */
    protected function delay()
    {
        if (isset($this->headers['x-rate-limit-reset'])) {
            $delay = $this->headers['x-rate-limit-reset'] - time();

            if ($delay < 10) {
                $delay = 60 * 15; // 15 minute delay if the given delay seems unreasonably small (can be due to server time differences)
            }
        } else {
            //exit('Rate limited, but no rate limit header found');
            // http://developer.echonest.com/docs/v4/index.html#rate-limits
            $delay = 60; // 1 minute delay
        }

        file_put_contents('php://stderr', "\n");

        do {
            file_put_contents('php://stderr', "\r\e[K");
            file_put_contents('php://stderr', sprintf('Sleeping for %d seconds', $delay--));
            sleep(1);
        } while ($delay);
    }
}
