<?php
include_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "defines.php";
class LoginData{

    public $token;

    public $expirationTimestamp;

    public function __construct($token, $expirationTimestamp) {
        if (empty($token) || empty($expirationTimestamp)) {
            throw new InvalidArgumentException("Missing or empty token");
        }
        $this->token = $token;
        $this->expirationTimestamp = $expirationTimestamp;
    }

    public static function getFromCache(): ?LoginData
    {
        // Reads login data from disk, and checks for expiration of the token

        if( ! file_exists(LOGINFILE) ) {
            return null;
        }
        $logindata = file_get_contents(LOGINFILE);
        $cachedLogin = json_decode($logindata);

        // Read token
        if(empty($cachedLogin->token) || empty($cachedLogin->expirationTimestamp)) {
            LOGINF("File data error, no token found. Fallback to re-login");
            return null;
        }
        // Get date part of token
        $tokenexpires = $cachedLogin->expirationTimestamp;
        LOGDEB("Token expires: " . $tokenexpires . " (" . date('c', $tokenexpires) . ")");

        if( $tokenexpires > time()+10 ) {
            // Token is valid
            $token = $cachedLogin->token;
        } else {
            LOGINF("Token expired - refreshing token");
            return null;
        }
        return new LoginData($token, $tokenexpires);
    }

    public static function doViessmannLogin($user, $pass, $apikey): ?LoginData
    {
        $htmlRedirectWithSessionCode = LoginData::getViessmannAuthcode( authorize_URL."?client_id=".$apikey."&redirect_uri=".callback_uri."&code_challenge=2e21faa1-db2c-4d0b-a10f-575fd372bc8c-575fd372bc8c&"."&scope=IoT%20User%20offline_access"."&response_type=code", $user,$pass);
        LOGDEB($htmlRedirectWithSessionCode);

        //Redirect with session code, needs to filtered via regex
        preg_match ('/code=(.*)"/', $htmlRedirectWithSessionCode, $matches);
        if (count($matches) != 2) {
            LOGERR("Unable to get session code from the redirect: Session Code " . $htmlRedirectWithSessionCode );
            return null;
        }
        $code = $matches[1];

        $tokenResponse= LoginData::getViessmannToken( token_url, "client_id=".$apikey."&code_verifier=".client_secret."&code=".$code."&redirect_uri=".callback_uri."&grant_type=authorization_code");

        $loginJson = json_decode($tokenResponse);
        if ( empty($loginJson) ) {
            LOGERR("JSON error, or JSON is empty: Error code " . json_last_error() . " " . json_last_error_msg() );
            return null;
        }

        // Read token
        if( empty($loginJson->access_token) || $loginJson->token_type != "Bearer" || empty($loginJson->expires_in) ) {
            LOGERR("Data error, Invalid token found. Response: $tokenResponse");
            return null;
        }

        $result = new LoginData($loginJson->access_token, (time() + $loginJson->expires_in));
        if (!is_dir(dirname(LOGINFILE))) {
            mkdir(dirname(LOGINFILE),777, true);
        }
        // Write data to ramdisk
        file_put_contents(LOGINFILE, json_encode($result));
        return $result;
    }
    private static function getViessmannAuthcode( $url, $user, $pwd){
        $curl = curl_init();

        $header = [ ];

        array_push( $header, "Content-Type: application/x-www-form-urlencoded" );
        curl_setopt($curl, CURLOPT_POST, 1);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header );
        curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, 0);
        curl_setopt($curl, CURLOPT_USERPWD,"$user:$pwd");
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $response = curl_exec($curl);

        // Debugging
        $crlinf = curl_getinfo($curl);
        LOGDEB("Status GetAuthCode: " . $crlinf['http_code']);

        curl_close($curl);

        return $response;
    }

    private static function getViessmannToken( $url, $PostData ){
        $curl = curl_init();

        $header = [ ];

        array_push( $header, "Content-Type: application/x-www-form-urlencoded" );

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header );
        curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, 0);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_POSTFIELDS,$PostData);

        $response = curl_exec($curl);

        // Debugging
        $crlinf = curl_getinfo($curl);
        LOGDEB("Status GetToken: " . $crlinf['http_code']);

        curl_close($curl);

        return $response;

    }
}