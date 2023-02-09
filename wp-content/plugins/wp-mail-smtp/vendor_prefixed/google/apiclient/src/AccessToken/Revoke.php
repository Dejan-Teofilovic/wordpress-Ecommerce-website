<?php

/*
 * Copyright 2008 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace WPMailSMTP\Vendor\Google\AccessToken;

use WPMailSMTP\Vendor\Google\Auth\HttpHandler\HttpHandlerFactory;
use WPMailSMTP\Vendor\Google\Client;
use WPMailSMTP\Vendor\GuzzleHttp\ClientInterface;
use WPMailSMTP\Vendor\GuzzleHttp\Psr7;
use WPMailSMTP\Vendor\GuzzleHttp\Psr7\Request;
/**
 * Wrapper around Google Access Tokens which provides convenience functions
 *
 */
class Revoke
{
    /**
     * @var ClientInterface The http client
     */
    private $http;
    /**
     * Instantiates the class, but does not initiate the login flow, leaving it
     * to the discretion of the caller.
     */
    public function __construct(\WPMailSMTP\Vendor\GuzzleHttp\ClientInterface $http = null)
    {
        $this->http = $http;
    }
    /**
     * Revoke an OAuth2 access token or refresh token. This method will revoke the current access
     * token, if a token isn't provided.
     *
     * @param string|array $token The token (access token or a refresh token) that should be revoked.
     * @return boolean Returns True if the revocation was successful, otherwise False.
     */
    public function revokeToken($token)
    {
        if (\is_array($token)) {
            if (isset($token['refresh_token'])) {
                $token = $token['refresh_token'];
            } else {
                $token = $token['access_token'];
            }
        }
        $body = \WPMailSMTP\Vendor\GuzzleHttp\Psr7\Utils::streamFor(\http_build_query(array('token' => $token)));
        $request = new \WPMailSMTP\Vendor\GuzzleHttp\Psr7\Request('POST', \WPMailSMTP\Vendor\Google\Client::OAUTH2_REVOKE_URI, ['Cache-Control' => 'no-store', 'Content-Type' => 'application/x-www-form-urlencoded'], $body);
        $httpHandler = \WPMailSMTP\Vendor\Google\Auth\HttpHandler\HttpHandlerFactory::build($this->http);
        $response = $httpHandler($request);
        return $response->getStatusCode() == 200;
    }
}
