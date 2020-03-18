<?php

class mdsHttpRequest
{
    public $curl;

    public $error = false;
    public $errorCode = 0;
    public $errorMessage = null;

    public $curlError = false;
    public $curlErrorCode = 0;
    public $curlErrorMessage = null;

    public $httpError = false;
    public $httpStatusCode = 0;

    public $url = null;

    public $rawResponse = null;
    private $headers = array();
    private $options = array();

    public $jsonDecoder = null;


    /**
     * Construct
     *
     * @param  $base_url
     * @throws Exception
     */
    public function __construct($base_url = null)
    {
        if (!extension_loaded('curl')) {
            throw new Exception('cURL library is not loaded');
        }

        $this->curl = curl_init();

        $this->init($base_url);
    }

    /**
     * Initialise
     * set default
     *
     * @param  $base_url
     */
    private function init($base_url)
    {
        $this->setOpt(CURLOPT_FAILONERROR, false);
        $this->setOpt(CURLOPT_RETURNTRANSFER, true);
        $this->setUrl($base_url);
    }

    /**
     * Set Url
     * add full api address if base was not provide when
     * @param $url
     */
    public function setUrl($url)
    {
        if ($url === null) {
            $this->url = (string) $url;
        } else {
            $this->url = $this->url . (string) $url;
        }
        $this->setOpt(CURLOPT_URL, $this->url);
    }

    /**
     * reSet Url
     * add full new api address
     * @param $url
     */
    public function reSetUrl($url)
    {
        $this->url = (string) $url;
        $this->setOpt(CURLOPT_URL, $this->url);
    }

    /**
     * Set Header
     *
     * Add extra header to include in the request.
     *
     * @param  $key
     * @param  $value
     */
    public function setHeader($key, $value)
    {
        $key = $this->caseFolding($key);
        $this->headers[$key] = $value;
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        $this->setOpt(CURLOPT_HTTPHEADER, $headers);

        return $this;
    }

    /**
     * Case Folding
     * for constant, all header keys must be uppercase
     * including key with hyphen between
     * @param  $str
     * @return false|string|string[]|null
     */
    private function caseFolding($str)
    {
        return mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
    }

    /**
     * Set curl Option
     *
     * @param  $option
     * @param  $value
     *
     * @return boolean
     */
    public function setOpt($option, $value)
    {
        $required_options = array(
            CURLOPT_RETURNTRANSFER => 'CURLOPT_RETURNTRANSFER',
        );

        if (in_array($option, array_keys($required_options), true) && $value !== true) {
            trigger_error($required_options[$option] . ' is a required option', E_USER_WARNING);
        }

        $success = curl_setopt($this->curl, $option, $value);
        if ($success) {
            $this->options[$option] = $value;
        }
        return $success;
    }

    /**
     * Post
     *
     * @param  $url
     * @param  $data
     *
     * @return mixed Returns the value provided by exec.
     */
    public function post($url, $data = '')
    {
        $this->setUrl($url);

        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'POST');

        $this->setOpt(CURLOPT_POST, true);
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));

        return $this->exec();
    }

    /**
     * Get
     *
     * @param  $url
     * @param  $data
     *
     * @return mixed Returns the value provided by exec.
     */
    public function get($url, $data = array())
    {
        $this->setUrl($url);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, 'GET');
        $this->setOpt(CURLOPT_HTTPGET, true);
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
        return $this->exec();
    }

    /**
     * Build Post Data
     *
     * @param  $data
     *
     * @return array|string
     */
    public function buildPostData($data)
    {
        // Return JSON-encoded string when the request's content-type is JSON.
        if (isset($this->headers['Content-Type'])
            && strpos( 'application/json',$this->headers['Content-Type']) !== false
            && is_array($data)) {
            $data = json_encode($data);
        }

        return $data;
    }

    /**
     * Exec
     * @return mixed Returns the value provided by parseResponse.
     */
    public function exec()
    {
        $this->rawResponse = curl_exec($this->curl);
        $this->curlErrorCode = curl_errno($this->curl);
        $this->curlErrorMessage = curl_error($this->curl);

        $this->curlError = $this->curlErrorCode !== 0;

        $this->httpStatusCode = $this->getInfo(CURLINFO_HTTP_CODE);
        $this->httpError = in_array(floor($this->httpStatusCode / 100), array(4, 5));
        $this->error = $this->curlError || $this->httpError;
        $this->errorCode = $this->error ? ($this->curlError ? $this->curlErrorCode : $this->httpStatusCode) : 0;

        $this->errorMessage = $this->curlError ? $this->curlErrorMessage : null;
        $this->execDone();
        if ($this->jsonDecoder === true) {
            return json_decode($this->rawResponse);
        }

        return $this->rawResponse;
    }
    /**
     * execDone
     */
    public function execDone()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }

    /**
     * Set JSON Decoder
     *
     */
    public function setJsonDecoder()
    {
        $this->jsonDecoder = true;
    }

    /**
     * Get Info
     *
     * @param  $opt
     *
     * @return mixed
     */
    public function getInfo($opt = null)
    {
        $args = array();
        $args[] = $this->curl;

        if (func_num_args()) {
            $args[] = $opt;
        }

        return call_user_func_array('curl_getinfo', $args);
    }

    /**
     * Determine Any Error
     * @return bool
     */
    public function isError()
    {
        return $this->error;
    }

    /**
     * Determine Http Error
     * @return bool
     */
    public function isHttpError()
    {
        return $this->httpError;
    }

    public function isCurlError()
    {
        return $this->curlError;
    }

    /**
     * Get Error Code
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Get Error Code
     * @return int
     */
    public function getCurlErrorCode()
    {
        return $this->curlErrorCode;
    }

    /**
     * Get Error Message
     * @return null|string
     */
    public function getCurlErrorMessage()
    {
        return $this->curlErrorMessage;
    }

    /**
     * Get Error Message
     * @return null|string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
    /**
     * Get Raw Response
     * @return null|string
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }


}