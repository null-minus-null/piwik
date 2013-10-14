<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package Login
 */
namespace Piwik\Plugins\Login;

use Exception;
use Piwik\Config;
use Piwik\Cookie;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\API;
use Piwik\Plugins\UsersManager\UsersManager;
use Piwik\ProxyHttp;
use Piwik\Session;

/**
 *
 * @package Login
 */
class Login extends \Piwik\Plugin
{
    /**
     * @see Piwik_Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        $hooks = array(
            'Request.initAuthenticationObject' => 'initAuthenticationObject',
            'User.isNotAuthorized'             => 'noAccess',
            'API.Request.authenticate'         => 'ApiRequestAuthenticate',
            'Login.initSession'                => 'initSession',
        );
        return $hooks;
    }

    /**
     * Redirects to Login form with error message.
     * Listens to User.isNotAuthorized hook.
     */
    public function noAccess(Exception $exception)
    {
        $exceptionMessage = $exception->getMessage();

        $controller = new Controller();
        $controller->login($exceptionMessage, '' /* $exception->getTraceAsString() */);
    }

    /**
     * Set login name and autehntication token for authentication request.
     * Listens to API.Request.authenticate hook.
     */
    public function ApiRequestAuthenticate($tokenAuth)
    {
        \Piwik\Registry::get('auth')->setLogin($login = null);
        \Piwik\Registry::get('auth')->setTokenAuth($tokenAuth);
    }

    /**
     * Initializes the authentication object.
     * Listens to Request.initAuthenticationObject hook.
     */
    function initAuthenticationObject($allowCookieAuthentication = false)
    {
        $auth = new Auth();
        \Piwik\Registry::set('auth', $auth);

        $action = Piwik::getAction();
        if (Piwik::getModule() === 'API'
            && (empty($action) || $action == 'index')
            && $allowCookieAuthentication !== true
        ) {
            return;
        }

        $authCookieName = Config::getInstance()->General['login_cookie_name'];
        $authCookieExpiry = 0;
        $authCookiePath = Config::getInstance()->General['login_cookie_path'];
        $authCookie = new Cookie($authCookieName, $authCookieExpiry, $authCookiePath);
        $defaultLogin = 'anonymous';
        $defaultTokenAuth = 'anonymous';
        if ($authCookie->isCookieFound()) {
            $defaultLogin = $authCookie->get('login');
            $defaultTokenAuth = $authCookie->get('token_auth');
        }
        $auth->setLogin($defaultLogin);
        $auth->setTokenAuth($defaultTokenAuth);
    }

    /**
     * Authenticate user and initializes the session.
     * Listens to Login.initSession hook.
     *
     * @throws Exception
     */
    public function initSession($info)
    {
        $login = $info['login'];
        $md5Password = $info['md5Password'];
        $rememberMe = $info['rememberMe'];

        $tokenAuth = API::getInstance()->getTokenAuth($login, $md5Password);

        $auth = \Piwik\Registry::get('auth');
        $auth->setLogin($login);
        $auth->setTokenAuth($tokenAuth);
        $authResult = $auth->authenticate();

        $authCookieName = Config::getInstance()->General['login_cookie_name'];
        $authCookieExpiry = $rememberMe ? time() + Config::getInstance()->General['login_cookie_expire'] : 0;
        $authCookiePath = Config::getInstance()->General['login_cookie_path'];
        $cookie = new Cookie($authCookieName, $authCookieExpiry, $authCookiePath);
        if (!$authResult->wasAuthenticationSuccessful()) {
            $cookie->delete();
            throw new Exception(Piwik::translate('Login_LoginPasswordNotCorrect'));
        }

        $cookie->set('login', $login);
        $cookie->set('token_auth', $auth->getHashTokenAuth($login, $authResult->getTokenAuth()));
        $cookie->setSecure(ProxyHttp::isHttps());
        $cookie->setHttpOnly(true);
        $cookie->save();

        @Session::regenerateId();

        // remove password reset entry if it exists
        self::removePasswordResetInfo($login);
    }

    /**
     * Stores password reset info for a specific login.
     *
     * @param string $login The user login for whom a password change was requested.
     * @param string $password The new password to set.
     */
    public static function savePasswordResetInfo($login, $password)
    {
        $optionName = self::getPasswordResetInfoOptionName($login);
        $optionData = UsersManager::getPasswordHash($password);

        Option::set($optionName, $optionData);
    }

    /**
     * Removes stored password reset info if it exists.
     *
     * @param string $login The user login to check for.
     */
    public static function removePasswordResetInfo($login)
    {
        $optionName = self::getPasswordResetInfoOptionName($login);
        Option::delete($optionName);
    }

    /**
     * Gets password hash stored in password reset info.
     *
     * @param string $login The user login to check for.
     * @return string|false The hashed password or false if no reset info exists.
     */
    public static function getPasswordToResetTo($login)
    {
        $optionName = self::getPasswordResetInfoOptionName($login);
        return Option::get($optionName);
    }

    /**
     * Gets the option name for the option that will store a user's password change
     * request.
     *
     * @param string $login The user login for whom a password change was requested.
     * @return string
     */
    public static function getPasswordResetInfoOptionName($login)
    {
        return $login . '_reset_password_info';
    }
}
