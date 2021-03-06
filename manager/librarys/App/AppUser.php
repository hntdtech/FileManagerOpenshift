<?php

    namespace Librarys\App;

    if (defined('LOADED') == false)
        exit;

    use Librarys\App\Config\AppConfig;
    use Librarys\App\Config\AppUserConfig;
    use Librarys\File\FileInfo;
    use Librarys\Http\Request;
    use Librarys\Http\Detection\SimpleDetect;
    use Librarys\Http\Secure\CFSRToken;
    use Librarys\Text\Encryption\StringEncryption;

    final class AppUser
    {

        private static $instance;

        private $id;
        private $userInfos;
        private $tokenValue;
        private $tokenUserAgent;
        private $tokenUserDevice;
        private $tokenUserOS;
        private $tokenUserBrowser;
        private $tokenUserIp;
        private $tokenUserLive;
        private $isLogin;

        const POSITION_BAND        = 0;
        const POSITION_USER        = 2;
        const POSTION_ADMIN        = 4;
        const POSTION_ADMINSTRATOR = 8;

        const USERNAME_VALIDATE = '\\/:*?"<>|\'';

        const TOKEN_ARRAY_KEY_USER_AGENT   = 'user_agent';
        const TOKEN_ARRAY_KEY_USER_DEVICE  = 'user_device';
        const TOKEN_ARRAY_KEY_USER_OS      = 'user_os';
        const TOKEN_ARRAY_KEY_USER_BROWSER = 'user_browser';
        const TOKEN_ARRAY_KEY_USER_IP      = 'user_ip';
        const TOKEN_ARRAY_KEY_USER_LIVE    = 'user_live';

        const USERNAME_CREATE_FIRST = 'Admin';
        const PASSWORD_CREATE_FIRST = '12345';

        const USER_DEVICE_MOBILE  = 'mobile';
        const USER_DEVICE_TABLET  = 'tablet';

        const TIME_SHOW_WARNING_PASSWORD_DEFAULT = 10;

        protected function __construct()
        {

        }

        protected function __wakeup()
        {

        }

        protected function __clone()
        {

        }

        public static function getInstance()
        {
            if (null === self::$instance)
                self::$instance = new AppUser();

            return self::$instance;
        }

        public function execute()
        {
            $this->isLogin = false;

            if ($this->checkUserLogin())
                $this->isLogin = true;

            return true;
        }

        public function createFirstUser()
        {
            if (AppUserConfig::getInstance()->hasEntryConfigArraySystem())
                return false;

            $username = self::USERNAME_CREATE_FIRST;
            $password = self::createPassword(self::PASSWORD_CREATE_FIRST);
            $position = self::POSTION_ADMINSTRATOR;
            $idUser   = md5(base64_encode(time() . rand(100000, 999999)));
            $symbol   = '.';

            if (
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_USERNAME,  $username) == false ||
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_EMAIL,     null)      == false ||
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_PASSWORD,  $password) == false ||
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_POSITION,  $position) == false ||
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_CREATE_AT, time())    == false ||
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_MODIFY_AT, 0)         == false ||
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_LOGIN_AT,  0)         == false ||
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_BAND_AT,   0)         == false ||
                    AppUserConfig::getInstance()->setSystem($idUser . $symbol . AppUserConfig::ARRAY_KEY_BAND_OF,   null)      == false
            ) {
                return false;
            }

            if (AppUserConfig::getInstance()->write() == false)
                return false;

            return true;
        }

        private function checkUserLogin()
        {
            if (Request::session()->has(env('app.login.session_login_name')) == false || Request::session()->get(env('app.login.session_token_name')) == false)
                return false;

            $id     = addslashes(Request::session()->get(env('app.login.session_login_name')));
            $token  = addslashes(Request::session()->get(env('app.login.session_token_name')));
            $arrays = AppUserConfig::getInstance()->getConfigArraySystem();

            if (is_array($arrays) == false || isset($arrays[$id]) == false)
                return false;

            $tokenDirectory = env('app.path.token');
            $tokenPath      = FileInfo::filterPaths($tokenDirectory . SP . $token);

            if (FileInfo::isTypeDirectory($tokenDirectory) == false || FileInfo::isTypeFile($tokenPath) == false)
                return false;

            $tokenBuffer = FileInfo::fileReadContents($tokenPath);
            $tokenArray  = @unserialize($tokenBuffer);

            if ($tokenArray === false)
                return false;

            $userAgent = Request::userAgent();
            $userIp    = Request::ip();
            $userLive  = time();

            $this->id               = $id;
            $this->userInfos        = $arrays[$id];
            $this->tokenValue       = $token;
            $this->tokenUserAgent   = $tokenArray[self::TOKEN_ARRAY_KEY_USER_AGENT];
            $this->tokenUserDevice  = $tokenArray[self::TOKEN_ARRAY_KEY_USER_DEVICE];
            $this->tokenUserOS      = $tokenArray[self::TOKEN_ARRAY_KEY_USER_OS];
            $this->tokenUserBrowser = $tokenArray[self::TOKEN_ARRAY_KEY_USER_BROWSER];
            $this->tokenUserIp      = $tokenArray[self::TOKEN_ARRAY_KEY_USER_IP];
            $this->tokenUserLive    = $tokenArray[self::TOKEN_ARRAY_KEY_USER_LIVE];

            if (strcmp($userAgent, $tokenArray[self::TOKEN_ARRAY_KEY_USER_AGENT]) !== 0)
                return false;

            if (strcmp($userIp, $tokenArray[self::TOKEN_ARRAY_KEY_USER_IP]) !== 0)
                return false;

            if ($userLive - intval($tokenArray[self::TOKEN_ARRAY_KEY_USER_LIVE]) >= AppConfig::getInstance()->get('login.time_login', 3600))
                return false;

            $tokenArray[self::TOKEN_ARRAY_KEY_USER_LIVE] = $userLive;
            $tokenBuffer                                 = @serialize($tokenArray);

            if ($tokenBuffer !== false)
                FileInfo::fileWriteContents($tokenPath, $tokenBuffer);

            return true;
        }

        public function checkUserIsUsePasswordDefault()
        {
            if ($this->isLogin == false || $this->id == null)
                return false;

            $timeNow   = time();
            $timeShow  = self::TIME_SHOW_WARNING_PASSWORD_DEFAULT;
            $checkTime = 0;

            if (isset($_SESSION[env('app.login.session_check_password_name')])) {
                $checkTime = intval($_SESSION[env('app.login.session_check_password_name')]);
                Request::session()->put(env('app.login.session_check_password_name'), $checkTime + 1);
            } else {
                Request::session()->put(env('app.login.session_check_password_name'), 0);
            }

            if ($timeNow + $checkTime >= $timeNow + $timeShow)
                return false;

            $password        = AppUserConfig::getInstance()->get($this->id . '.' . AppUserConfig::ARRAY_KEY_PASSWORD, null);
            $passwordDefault = self::PASSWORD_CREATE_FIRST;

            if ($this->checkPassword($password, $passwordDefault) == false) {
                $checkTime = $timeShow;
                return false;
            }

            return true;
        }

        public function get($key)
        {
            if ($this->isLogin() == false)
                return null;

            return AppUserConfig::getInstance()->get($this->id . '.' . $key);
        }

        public function setConfig($key, $value)
        {
            if ($this->isLogin() == false)
                return false;

            return AppUserConfig::getInstance()->setSystem($this->id . '.' . $key, $value);
        }

        public function writeConfig($exitUser = false)
        {
            if (AppUserConfig::getInstance()->write() == false)
                return false;

            if ($exitUser)
                $this->exitSession();

            return true;
        }

        public function getUserInfoKey($key)
        {
            if ($this->userInfos === null || is_array($this->userInfos) == false || isset($this->userInfos[$key]) == false)
                return null;

            return $this->userInfos[$key];
        }

        public function getId()
        {
            return $this->id;
        }

        public function getUsername()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_USERNAME);
        }

        public function getPassword()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_PASSWORD);
        }

        public function getEmail()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_EMAIL);
        }

        public function getPosition()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_POSITION);
        }

        public function getCreateAt()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_CREATE_AT);
        }

        public function getModifyAt()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_MODIFY_AT);
        }

        public function getLoginAt()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_LOGIN_AT);
        }

        public function getBandAt()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_BAND_AT);
        }

        public function getBandOf()
        {
            return $this->getUserInfoKey(AppUserConfig::ARRAY_KEY_BAND_OF);
        }

        public function getTokenValue()
        {
            return $this->tokenValue;
        }

        public function getTokenUserAgent()
        {
            return $this->tokenUserAgent;
        }

        public function getTokenIP()
        {
            return $this->tokenUserIp;
        }

        public function getTokenLive()
        {
            return $this->tokenUserLive;
        }

        public function isLogin()
        {
            return $this->isLogin;
        }

        public function isPositionUser()
        {
            return $this->getPosition() === self::POSITION_USER;
        }

        public function isPositionAdmin()
        {
            return $this->getPosition() === self::POSITION_ADMIN;
        }

        public function isPositionAdminstrator()
        {
            return $this->getPosition() === self::POSTION_ADMINSTRATOR;
        }

        public function isUser($username, $password)
        {
            $arrays = AppUserConfig::getInstance()->getConfigArraySystem();

            if (is_array($arrays) && count($arrays) > 0) {
                $username = strtolower($username);

                foreach ($arrays AS $id => $arrayUser) {
                    if (strcasecmp($arrayUser[AppUserConfig::ARRAY_KEY_USERNAME], $username) === 0 || strcasecmp($arrayUser[AppUserConfig::ARRAY_KEY_EMAIL], $username) === 0) {
                        if (self::checkPassword($arrayUser[AppUserConfig::ARRAY_KEY_PASSWORD], $password))
                            return $id;
                    }
                }
            }

            return false;
        }

        public function isUserBand($id = null, $exitUser = true)
        {
            if ($id == null)
                $id = $this->id;

            if ($id == null || empty($id))
                return false;

            $position = AppUserConfig::getInstance()->get($id . '.' . AppUserConfig::ARRAY_KEY_POSITION, false);

            if ($position === false || $position === 0) {
                if ($exitUser)
                    $this->exitSession();

                return true;
            }

            return false;
        }

        public function createSessionUser($id)
        {
            $id             = addslashes($id);
            $time           = time();
            $tokenPath      = null;
            $tokenGenerator = null;
            $tokenDirectory = env('app.path.token');

            if (FileInfo::isTypeDirectory($tokenDirectory) == false && FileInfo::mkdir($tokenDirectory, true) == false)
                return false;

            if (empty($id))
                return false;

            for ($i = 0; $i < 10; ++$i) {
                $tokenGenerator = CFSRToken::generator();
                $tokenPath      = FileInfo::filterPaths($tokenDirectory . SP . $tokenGenerator);

                if (FileInfo::fileExists($tokenPath) == false)
                    break;
            }

            $mobileDetect = SimpleDetect::getInstance();
            $userAgent    = Request::userAgent();
            $userDevice   = $mobileDetect->getDeviceType();
            $userOS       = $mobileDetect->getOS();
            $userBrowser  = $mobileDetect->getBrowser();
            $userIP       = Request::ip();
            $userLive     = time();
            $userPassword = AppUserConfig::getInstance()->get($id . '.' . AppUserConfig::ARRAY_KEY_PASSWORD);

            $tokenBuffer = @serialize([
                self::TOKEN_ARRAY_KEY_USER_AGENT   => $userAgent,
                self::TOKEN_ARRAY_KEY_USER_DEVICE  => $userDevice,
                self::TOKEN_ARRAY_KEY_USER_OS      => $userOS,
                self::TOKEN_ARRAY_KEY_USER_BROWSER => $userBrowser,
                self::TOKEN_ARRAY_KEY_USER_IP      => $userIP,
                self::TOKEN_ARRAY_KEY_USER_LIVE    => $userLive,
            ]);

            if (FileInfo::fileWriteContents($tokenPath, $tokenBuffer, $userPassword));

            if (AppUserConfig::getInstance()->setSystem($id . '.' . AppUserConfig::ARRAY_KEY_LOGIN_AT, $time) == false || AppUserConfig::getInstance()->write() == false)
                return false;

            Request::session()->put(env('app.login.session_login_name'), $id);
            Request::session()->put(env('app.login.session_token_name'), $tokenGenerator);

            if ($this->execute())
                return true;

            return false;
        }

        public static function isValidateUsername($username)
        {
            return @strpbrk($username, self::USERNAME_VALIDATE) == false;
        }

        public function exitSession()
        {
            if ($this->tokenValue !== null) {
                $tokenDirectory = env('app.path.token');
                $tokenPath      = FileInfo::filterPaths($tokenDirectory . SP . $this->tokenValue);

                if (FileInfo::isTypeFile($tokenPath))
                    FileInfo::unlink($tokenPath);
            }

            return Request::session()->destroy();
        }

        public static function createPassword($password, $salt = null)
        {
            return StringEncryption::createCrypt($password, $salt);
        }

        public static function checkPassword($passwordUser, $passwrodCheck)
        {
            return StringEncryption::hashEqualsString($passwordUser, $passwrodCheck);
        }
    }

?>