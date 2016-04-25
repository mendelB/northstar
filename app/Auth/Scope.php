<?php

namespace Northstar\Auth;

use League\OAuth2\Server\Exception\OAuthServerException;
use Northstar\Models\Client;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Scope
{
    /**
     * Available API Key scopes.
     * @var array
     */
    protected static $scopes = [
        'admin' => [
            'description' => 'Allows "administrative" actions that should not be user-accessible, like deleting user records.',
        ],
        'user' => [
            'description' => 'Allows actions to be made on a user\'s behalf.',
        ],
    ];

    /**
     * Return a list of all scopes & their descriptions.
     *
     * @return array
     */
    public static function all()
    {
        return static::$scopes;
    }

    /**
     * Validate if all the given scopes are valid.
     *
     * @param $scopes
     * @return bool
     */
    public static function validateScopes($scopes)
    {
        if (! is_array($scopes)) {
            return false;
        }

        return ! array_diff($scopes, array_keys(static::$scopes));
    }

    /**
     * Return whether a properly scoped API key is provided
     * with the current request.
     *
     * @param $scope - Required scope
     * @return bool
     */
    public static function allows($scope)
    {
        $oauthScopes = request()->attributes->get('oauth_scopes');

        // If scopes have been parsed from a provided JWT access token, check against
        // those. Otherwise, check the Client specified by the `X-DS-REST-API-Key` header.
        if (! is_null($oauthScopes)) {
            return in_array($scope, $oauthScopes);
        }

        $key = Client::current();

        return $key && $key->hasScope($scope);
    }

    /**
     * Throw an exception if a properly scoped API key is not
     * provided with the current request.
     *
     * @param $scope - Required scope
     * @throws OAuthServerException
     */
    public static function gate($scope)
    {
        if (! static::allows($scope)) {
            app('stathat')->ezCount('invalid API key error');

            // If scopes have been parsed from a provided JWT access token, use OAuth access
            // denied exception to return a 401 error.
            if (request()->attributes->has('oauth_scopes')) {
                throw OAuthServerException::accessDenied('Requires the `'.$scope.'` scope.');
            }

            // ...if we're using a legacy API key, return the expected 403 error.
            throw new AccessDeniedHttpException('You must be using an API key with "'.$scope.'" scope to do that.');
        }
    }
}