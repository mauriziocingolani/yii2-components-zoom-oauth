<?php

namespace mauriziocingolani\yii2componentszoomoauth;

use yii\base\Component;

/**
 * Componente per la gestione delle funzionalità Zoom con il nuovo meccanismo di autenticazione OAuth.
 * @author Maurizio Cingolani <mauriziocingolani74@gmail.com>
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @version 1.0.5
 */
class ZoomOAuth extends Component {

    const URL = 'https://api.zoom.us/v2';
    const URL_OAUTH = 'https://zoom.us/oauth';

    public $clientId;
    public $clientSecret;
    public $redirectUri;

    public function init() {
        parent::init();
        if (!$this->clientId || !$this->clientSecret || !$this->redirectUri)
            throw new \yii\base\InvalidConfigException(__CLASS__ . ': the $clientId, $clientSecret and $redirectUri attributes must be set.');
    }

    /**
     * Direct the user to https://zoom.us/oauth/authorize with the following query parameters:
     * <ul>
     * <li>response_type: 'code'</li>
     * <li>redirect_uri: URI to handle successful user authorization (must match with Development or Production Redirect URI in your OAuth app settings)</li>
     * <li>client_id: OAuth application's Development or Production Client ID</li>
     * </ul>
     * Zoom will prompt the user to authorize access for your app.
     * If authorized, Zoom redirects the user to the redirect_uri with the authorization code in the code query parameter.
     * @see https://developers.zoom.us/docs/integrations/oauth/#step-1-request-user-authorization
     */
    public function authorize() {
        $params = [
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
        ];
        return self::URL_OAUTH . "/authorize?" . http_build_query($params);
    }

    /**
     * Once you have an authorization code, use it to request an access token.
     * Make a POST request to https://zoom.us/oauth/token with the following request headers and request body information:
     * <ul>
     * <li>code: The authorization code supplied to the callback by Zoom</li>
     * <li>grant_type: 'authorization_code'</li>
     * <li>redirect_uri: Your application's redirect URI</li>
     * </ul>
     * If successful, the response body is a JSON response containing the user's access token.
     * You can use this access token to make requests to the Zoom API.
     * Access tokens expire after one hour. 
     * @param string $code Authorization code
     * @see https://developers.zoom.us/docs/integrations/oauth/#step-2-request-access-token
     */
    public function getToken($code) {
        $curl = curl_init();
        $params = [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ];
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::URL_OAUTH . "/token?" . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                "authorization: Basic " . base64_encode("{$this->clientId}:{$this->clientSecret}"),
                "content-type: application/x-www-form-urlencoded",
            ],
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err)
            return $err;
        $responseJson = json_decode($response);
        if (isset($responseJson->error))
            return "$responseJson->error ($responseJson->reason)";
        return $responseJson;
    }

    /**
     * Access tokens expire after one hour. Once expired, you will have to refresh a user's access token.
     * Make a POST request to https://zoom.us/oauth/token with the following request headers and request body information:
     * <ul>
     * <li>grant_type: 'refresh_token'</li>
     * <li>refresh_token: Your refresh token</li>
     * </ul>
     * If successful, the response body will be a JSON representation of your user's refreshed access token.
     * Refresh tokens expire after 90 days.
     * The latest refresh token must always be used for the next refresh request.
     * @param string $refreshToken Your refresh token.
     * @see https://developers.zoom.us/docs/integrations/oauth/#refreshing-an-access-token
     */
    public function getRefreshedToken($refreshToken) {
        $curl = curl_init();
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::URL_OAUTH . "/token?" . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                "authorization: Basic " . base64_encode("{$this->clientId}:{$this->clientSecret}"),
                "content-type: application/x-www-form-urlencoded",
            ],
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err)
            return $err;
        $responseJson = json_decode($response);
        if (isset($responseJson->error))
            return "$responseJson->error ($responseJson->reason)";
        return $responseJson;
    }

    /**
     * Use this API to list your account's users.
     * @param $token Oauth token
     * @see https://developers.zoom.us/docs/api/rest/reference/zoom-api/methods/#operation/users
     */
    public function getUsers($token) {
        $curl = curl_init();
        $params = [
            'status' => 'active',
            'page_size' => 30,
            'page_number' => 1,
        ];
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::URL . "/users?" . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer $token",
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err)
            return $err;
        return json_decode($response);
    }

    /**
     * Use this API to list all cloud recordings of a user. 
     * @param string $token Oauth token
     * @param string $userid The user ID or email address of the user
     * @see https://developers.zoom.us/docs/api/rest/reference/zoom-api/methods/#operation/recordingsList
     */
    public function getRecordings($token, $userid) {
        $curl = curl_init();
        $params = [
            'page_size' => 300,
            'from' => '1970-01-01',
            'to' => date('Y-m-d'),
        ];
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::URL . "/users/$userid/recordings?" . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer $token",
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err)
            return $err;
        return json_decode($response);
    }

    /**
     * List a user's (meeting host) scheduled meetings.
     * @param string $token Oauth token
     * @param string $userid The user ID or email address of the user
     * @see https://developers.zoom.us/docs/api/rest/reference/zoom-api/methods/#operation/meetings
     */
    public function getMeetings($token, $userid) {
        $curl = curl_init();
        $params = [
            'page_size' => 300,
        ];
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::URL . "/users/$userid/meetings?" . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer $token",
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err)
            return $err;
        return json_decode($response);
    }

    /**
     * Retrieve the details of a meeting.
     * @param type $token Oauth token
     * @param type $meetingid The meeting's ID.
     * @see https://developers.zoom.us/docs/api/rest/reference/zoom-api/methods/#operation/meeting
     */
    public function getMeeting($token, $meetingid) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::URL . "/meetings/$meetingid",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer $token",
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err)
            return $err;
        return json_decode($response);
    }

    /**
     * Create a meeting for a user.
     * @param type $token Oauth token
     * @param type $userid The user ID or email address of the user
     * @param type $params 
     * @see https://developers.zoom.us/docs/api/rest/reference/zoom-api/methods/#operation/meetingCreate
     */
    public function createMeeting($token, $userid, $params) {
        $curl = curl_init();
        $params2 = array_merge([
            'type' => 2,
            'timezone' => 'Europe/Rome',
            'default_password' => true,
                ], $params);
        $params2['settings'] = array_merge($params['settings'] ?? [], [
            'host_video' => true,
            'participant_video' => true,
            'audio' => 'voip',
        ]);
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::URL . "/users/$userid/meetings",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params2),
            CURLOPT_HTTPHEADER => array(
                "authorization: Bearer $token",
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err)
            return $err;
        return json_decode($response);
    }

}
