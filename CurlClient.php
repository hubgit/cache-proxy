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
    public function get($url, $headers = array(), $file = null, $tries = 0)
    {
        $this->report(sprintf('Fetching %s', $url));

        curl_setopt_array($this->curl, array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FILE => is_null($file) ? STDOUT : $file, // STDOUT if no file
        ));

        // reset the headers array
        $this->headers = array();

        $result = curl_exec($this->curl);

        $info = curl_getinfo($this->curl);

        // remove local details
        unset($info['local_ip']);
        unset($info['local_port']);

        $info['headers'] = $this->headers;

        return $info;
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

            $name = strtolower($name);
            $value = rtrim($value);

            if (isset($this->headers[$name])) {
                // append multiple headers with a comma separator
                $this->headers[$name] .= ', ' . $value;
            } else {
                $this->headers[$name] = $value;
            }
        }

        return strlen($header);
    }

    /**
     * Output messages to stderr
     */
    protected function report($output = '', $suffix = "\n") {
        file_put_contents('php://stderr', $output . $suffix);
    }
}
