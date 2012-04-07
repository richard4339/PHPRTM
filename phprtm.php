<?php

/**
 * Remember the Milk API Class for the Remember the Milk API
 *
 * Provides an easy way for PHP users to implement the RTM API.
 * 
 * NOTE: In order to use this API you need an API key and shared secret. To get these go to: http://www.rememberthemilk.com/services/api/keys.rtm
 *
 * PHPRTM is a forked copy of an API by Tyler Johnson. Per his original code, this code can be modified, copied, and redistrbuted, but not sold. Donations accepted.
 * 
 *
 * @author              Richard Lynskey <richard@mozor.net>
 * @author		Tyler Johnson <tylerj@arcreate.net>
 * @link		https://github.com/richard4339/PHPRTM
 * @version		0.3
 */
// ------------------------------------------------------------------------

/**
 * RTM API Class Base
 *
 * The main two functions used by the developer.
 * 
 * @access public
 * @param	string		api_key
 * @param	string		shared secret
 * @param       string          token (optional)
 * @return mixed
 */
abstract class RTM_Base {

    /**
     * RTM provided API Key
     * 
     * @var string
     */
    var $apikey = '';

    /**
     * RTM provided secret
     * 
     * @var string
     */
    var $secret = '';

    /**
     * Token if using with only one client
     * 
     * @var string
     */
    var $token = '';

    /**
     * dates->yesterday and dates->today
     * 
     * @var object
     */
    var $dates;

    /**
     * Generate Authorization URL
     *
     * Generates the URL needed for the application to be authorized to access someones account.
     *
     * @access	public
     * @param	string		read, write, or delete
     * @return	string
     */
    function genAuthURL($perms) {
        $args['perms'] = $perms;
        $api_sig = md5($this->api_sig(false, $args));
        return 'http://www.rememberthemilk.com/services/auth/?api_key=' . $this->apikey . '&perms=' . $perms . '&api_sig=' . $api_sig;
    }

    /**
     * Generate Token and Check
     *
     * Generates a Token based on a Frob and checks for accuracy.
     *
     * @access	public
     * @param	string
     * @return	string or bool (if it is accurate it passes the token and false for failure)
     */
    function getToken($frob = '') {
        if (isset($_GET['frob']) && $_GET['frob'] != '' && $frob == '') {
            $frob = $_GET['frob'];
        }

        $args['frob'] = $frob;
        $ret = $this->doMethod('auth', 'getToken', $args);
        $xml = new SimpleXMLElement($ret);
        $token = (string) $xml->auth->token;

        if ($token == '' || !isset($token)) {
            return false;
        } else {
            $args['auth_token'] = $token;
            $ret = $this->doMethod('auth', 'checkToken', $args);
            $xml = new SimpleXMLElement($ret);
            $token = (string) $xml->auth->token;

            if ($token == '' || !isset($token)) {
                return false;
            } else {
                $this->token = $token;
                return $token;
            }
        }
    }

    /**
     * Set Global Token Variable
     *
     * @access	public
     * @param	string
     * @return	void
     */
    function setToken($token) {
        $this->token = $token;
    }

    /**
     * Do API Method
     *
     * Sends a get request to RTM and returns the xml. Use SimplXMLElement to read.
     *
     * @access	public
     * @param	string		method type
     * @param	string		method
     * @param	array		arguments
     * @return	string		format: xml
     */
    function doMethod($type, $method, $args = array()) {
        $method = $type . '.' . $method;
        if ($this->token != '') {
            $args['auth_token'] = $this->token;
        }
        $ret = $this->apiCall($method, 'get', $args, true);

        if (($type == 'tasks') && ($method == 'getLists')) {
            echo $ret;
        }
        return $ret;
    }

    /**
     * Generate API URL
     *
     * Generates the URL to make a get a request.
     *
     * @access	public
     * @param	string		method
     * @param	array		arguments
     * @param	bool		require signature
     * @return	string
     */
    function apiURL($rtm_method, $args = array(), $require_sig = true) {
        $api_url = 'http://api.rememberthemilk.com/services/rest/';
        $api_url .= '?method=rtm.' . $rtm_method . '&api_key=' . $this->apikey;
        if (is_array($args) && count($args) > 0) {
            $api_url .= '&' . http_build_query($args);
        }
        if ($require_sig) {
            $api_sig = $this->api_sig($rtm_method, $args);
            $api_sig = md5($api_sig);
            $api_url .= '&api_sig=' . $api_sig;
        }
        return $api_url;
    }

    /**
     * Generate API Signature
     *
     * Generates the signature needed to make certain requests.
     *
     * @access	public
     * @param	string		method
     * @param	array		arguments
     * @return	string
     */
    function api_sig($rtm_method, $args = array()) {
        $args['api_key'] = $this->apikey;
        if ($rtm_method) {
            $args['method'] = 'rtm.' . $rtm_method;
        }
        ksort($args);
        $api_sig = $this->secret;
        foreach ($args as $key => $value) {
            $api_sig .= $key . $value;
        }

        return $api_sig;
    }

    /**
     * Make the Get Request
     *
     * Makes the get request and returns the xml
     *
     * @access	public
     * @param	string		method
     * @param	string		curl method (always get unless RTM changes to post)
     * @param	array		arguments
     * @param	bool		require signature
     * @return	string
     */
    function apiCall($rtm_method, $http_method, $args = array(), $require_sig = true) {

        $api_url = $this->apiURL($rtm_method, $args, $require_sig);

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $api_url);
        if ($http_method == 'post') {
            curl_setopt($curl_handle, CURLOPT_POST, true);
        }
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Expect:'));
        $rtm_data = curl_exec($curl_handle);
        $this->http_status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        $this->last_api_call = $api_url;
        curl_close($curl_handle);
        return $rtm_data;
    }

    /**
     * Returns a DateTime object for use in defining the APIs predefined date objects
     * 
     * @param string $n String representation of date
     * @return datetime
     */
    private function buildDate($n = '') {
        if ($n == '') {
            $now = new DateTime();
        } else {
            $now = $n;
        }

        return $now;
    }

    /**
     * Returns the previous date for the date in argument $n
     * @param string $n String representation of date
     * @return datetime
     */
    protected function defineYesterday($n = '') {

        $now = $this->buildDate($n);

        $minus1 = new DateInterval('P1D');
        return $now->sub($minus1)->format('n/j/Y');
    }

    /**
     * Returns today's date for the date in argument $n
     * @param string $n String representation of date
     * @return datetime
     */
    protected function defineToday($n = '') {

        $now = $this->buildDate($n);
        return $now->format('n/j/Y');
    }

}

/**
 * Main RTM API Class
 *
 * Connections.
 * @access	public
 * @return	string
 */
class RTM extends RTM_Base {

    /**
     * Constructor function
     * 
     * @param string $apikey
     * @param string $secret
     * @param string $token
     */
    function __construct($apikey, $secret, $token = '') {
        $this->apikey = $apikey;
        $this->secret = $secret;
        $this->token = $token;

        //$this->dates = (object) array('yesterday' => '', 'today' => '');
        $this->dates->yesterday = $this->defineYesterday();
        $this->dates->today = $this->defineToday();
    }

}

?>
