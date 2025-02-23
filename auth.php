php
<?php
declare(strict_types=1);
require_once 'db_connect.php';

class Auth {
    private const RATE_LIMIT = 5;  // Max 5 attempts per hour
    private const RATE_WINDOW = 3600;  // 1 hour in seconds

    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public static function getUserId(): int {
        return $_SESSION['user_id'] ?? 0;
    }

    public static function getUserName(): string {
        if (self::isLoggedIn()) {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
            $stmt->execute(['id' => self::getUserId()]);
            return $stmt->fetchColumn() ?: '';
        }
        return '';
    }

    public static function isAdmin(): bool {
        if (self::isLoggedIn()) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $stmt->execute(['id' => self::getUserId()]);
            return $stmt->fetchColumn() === 'admin';
        }
        return false;
    }

    public static function login(string $email, string $password): bool {
        if (!self::checkRateLimit('login', 0)) {
            throw new Exception("Too many login attempts. Please try again later.");
        }

        $stmt = $pdo->prepare("SELECT id, password, name, role FROM users WHERE email = :email AND provider = 'email'");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            self::resetRateLimit('login', $user['id']); // Reset after successful login
            return true;
        }
        self::incrementRateLimit('login', 0); // Increment for failed attempt
        return false;
    }

    public static function logout(): void {
        session_destroy();
    }

    public static function register(string $email, string $password, string $name): bool {
        if (!self::checkRateLimit('register', 0)) {
            throw new Exception("Too many registration attempts. Please try again later.");
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password, name, provider) VALUES (:email, :password, :name, 'email')");
            $stmt->execute([
                'email' => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'name' => $name
            ]);
            self::resetRateLimit('register', $pdo->lastInsertId()); // Reset after successful registration
            return true;
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            self::incrementRateLimit('register', 0); // Increment for failed attempt
            return false;
        }
    }

    // Google Login
    public static function loginWithGoogle(): void {
        if (!self::checkRateLimit('login', 0)) {
            header('Location: login.php?error=too_many_attempts');
            exit;
        }
        require_once 'vendor/autoload.php';
        $client = new Google_Client();
        $client->setClientId('YOUR_GOOGLE_CLIENT_ID');
        $client->setClientSecret('YOUR_GOOGLE_CLIENT_SECRET');
        $client->setRedirectUri('https://yourdomain.com/login.php');
        $client->addScope('email profile');

        if (isset($_GET['code'])) {
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            $client->setAccessToken($token);
            $google_oauth = new Google_Service_Oauth2($client);
            $google_account_info = $google_oauth->userinfo->get();

            $email = $google_account_info->email;
            $name = $google_account_info->name;
            $provider_id = $google_account_info->id;

            self::handleSocialLogin('google', $email, $name, $provider_id);
        } else {
            header('Location: ' . $client->createAuthUrl());
            exit;
        }
    }

    // Facebook Login
    public static function loginWithFacebook(): void {
        if (!self::checkRateLimit('login', 0)) {
            header('Location: login.php?error=too_many_attempts');
            exit;
        }
        require_once 'vendor/autoload.php';
        $fb = new Facebook\Facebook([
            'app_id' => 'YOUR_FACEBOOK_APP_ID',
            'app_secret' => 'YOUR_FACEBOOK_APP_SECRET',
            'default_graph_version' => 'v12.0',
        ]);

        $helper = $fb->getRedirectLoginHelper();
        $permissions = ['email']; // Optional permissions
        $loginUrl = $helper->getLoginUrl('https://yourdomain.com/login.php', $permissions);

        if (isset($_GET['code'])) {
            try {
                $accessToken = $helper->getAccessToken();
                $fb->setDefaultAccessToken($accessToken);
                $response = $fb->get('/me?fields=email,name,id');
                $user = $response->getGraphUser();

                $email = $user['email'] ?? '';
                $name = $user['name'] ?? '';
                $provider_id = $user['id'] ?? '';

                self::handleSocialLogin('facebook', $email, $name, $provider_id);
            } catch (Facebook\Exceptions\FacebookResponseException $e) {
                error_log('Graph returned an error: ' . $e->getMessage());
                header('Location: login.php?error=facebook_login_failed');
                exit;
            } catch (Facebook\Exceptions\FacebookSDKException $e) {
                error_log('Facebook SDK returned an error: ' . $e->getMessage());
                header('Location: login.php?error=facebook_sdk_failed');
                exit;
            }
        } else {
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    private static function handleSocialLogin(string $provider, string $email, string $name, string $provider_id): void {
        if (!self::checkRateLimit('login', 0)) {
            header('Location: login.php?error=too_many_attempts');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE provider = :provider AND provider_id = :provider_id");
        $stmt->execute(['provider' => $provider, 'provider_id' => $provider_id]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            self::resetRateLimit('login', $user['id']);
            header('Location: dashboard.php');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (email, name, provider, provider_id) VALUES (:email, :name, :provider, :provider_id)");
                $stmt->execute(['email' => $email, 'name' => $name, 'provider' => $provider, 'provider_id' => $provider_id]);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['name'] = $name;
                $_SESSION['role'] = 'user';
                self::resetRateLimit('login', $_SESSION['user_id']);
                header('Location: dashboard.php');
            } catch (PDOException $e) {
                error_log("Social login registration error: " . $e->getMessage());
                self::incrementRateLimit('login', 0);
                header('Location: login.php?error=social_registration_failed');
                exit;
            }
        }
        exit;
    }

    private static function checkRateLimit(string $action, int $user_id): bool {
        $stmt = $pdo->prepare("SELECT attempt_count, last_attempt FROM rate_limits WHERE user_id = :user_id AND action_type = :action");
        $stmt->execute(['user_id' => $user_id, 'action' => $action]);
        $rate = $stmt->fetch();

        if ($rate) {
            $time_passed = time() - strtotime($rate['last_attempt']);
            if ($time_passed > self::RATE_WINDOW) {
                self::resetRateLimit($action, $user_id);
                return true;
            }
            return $rate['attempt_count'] < self::RATE_LIMIT;
        }
        return true;
    }

    private static function incrementRateLimit(string $action, int $user_id): void {
        $stmt = $pdo->prepare("SELECT attempt_count FROM rate_limits WHERE user_id = :user_id AND action_type = :action");
        $stmt->execute(['user_id' => $user_id, 'action' => $action]);
        $rate = $stmt->fetch();

        if ($rate) {
            $stmt = $pdo->prepare("UPDATE rate_limits SET attempt_count = attempt_count + 1, last_attempt = CURRENT_TIMESTAMP WHERE user_id = :user_id AND action_type = :action");
            $stmt->execute(['user_id' => $user_id, 'action' => $action]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO rate_limits (user_id, action_type, attempt_count) VALUES (:user_id, :action, 1)");
            $stmt->execute(['user_id' => $user_id, 'action' => $action]);
        }
    }

    private static function resetRateLimit(string $action, int $user_id): void {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE user_id = :user_id AND action_type = :action");
        $stmt->execute(['user_id' => $user_id, 'action' => $action]);
    }
}

session_start();
$pdo = null; // Ensure $pdo is available globally or passed via require_once
?>

