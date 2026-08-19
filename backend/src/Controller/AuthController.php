<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Transazja\MapaLotowApi\Security\AuthService;
use Transazja\MapaLotowApi\Service\SmtpMailer;

final class AuthController
{
    public function __construct(
        private PDO $pdo,
        private AuthService $auth,
        private SmtpMailer $mailer,
        private string $appUrl
    ) {
    }

    public function me(Request $request, Response $response): Response
    {
        $userId = $this->auth->userId();

        if ($userId === null) {
            return $this->json($response, [
                'status' => 'ok',
                'authenticated' => false,
                'user' => null,
            ]);
        }

        $user = $this->findUserById($userId);
        $user = $this->repairPublicSlug($user);

        return $this->json($response, [
            'status' => 'ok',
            'authenticated' => true,
            'user' => $this->publicUserPayload($user),
        ]);
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (!is_array($data)) {
            return $this->error($response, 'Niepoprawne dane formularza.', 400);
        }

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
        $nick = trim((string) ($data['nick'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $passwordRepeat = (string) ($data['password_repeat'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fieldError($response, 'Podaj poprawny adres e-mail.', 'email', 422);
        }

        if (mb_strlen($nick, 'UTF-8') < 2 || mb_strlen($nick, 'UTF-8') > 60) {
            return $this->fieldError($response, 'Nick musi mieć od 2 do 60 znaków.', 'nick', 422);
        }

        $passwordError = $this->passwordValidationError($password);
        if ($passwordError !== null) {
            return $this->fieldError($response, $passwordError, 'password', 422);
        }

        if ($password !== $passwordRepeat) {
            return $this->fieldError($response, 'Hasła nie są identyczne.', 'password_repeat', 422);
        }

        $existingByEmail = $this->findUserByEmail($email);

        if ($existingByEmail) {
            return $this->json($response, [
                'status' => 'error',
                'message' => empty($existingByEmail['password_hash'])
                    ? 'Ten adres należy do konta przeniesionego ze starej Mapy Lotów. Użyj opcji „Nie pamiętasz hasła?”, aby aktywować istniejące konto i zachować swoje loty.'
                    : 'Konto z tym adresem e-mail już istnieje.',
                'existing_account' => true,
                'field' => 'email',
            ], 409);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM ml_users WHERE LOWER(nick) = LOWER(:nick) LIMIT 1'
        );
        $stmt->execute(['nick' => $nick]);

        if ($stmt->fetch()) {
            return $this->fieldError($response, 'Ten nick jest już zajęty.', 'nick', 409);
        }

        $verificationToken = bin2hex(random_bytes(32));
        $verificationHash = hash('sha256', $verificationToken);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "
                INSERT INTO ml_users (
                    email,
                    nick,
                    password_hash,
                    email_verification_token_hash,
                    email_verification_expires_at,
                    privacy_mode,
                    session_version
                ) VALUES (
                    :email,
                    :nick,
                    :password_hash,
                    :verification_hash,
                    DATE_ADD(NOW(), INTERVAL 24 HOUR),
                    'private',
                    1
                )
                "
            );

            $stmt->execute([
                'email' => $email,
                'nick' => $nick,
                'password_hash' => $passwordHash,
                'verification_hash' => $verificationHash,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        $activationUrl = $this->appUrl
            . '/?konto=aktywacja&token='
            . rawurlencode($verificationToken);

        $this->mailer->send(
            $email,
            'Aktywuj konto w Mapie Lotów',
            $this->mailTemplate(
                'Aktywuj konto',
                'Dziękujemy za rejestrację w Mapie Lotów. Kliknij poniższy przycisk, aby aktywować konto.',
                'Aktywuj konto',
                $activationUrl,
                'Link aktywacyjny jest ważny przez 24 godziny.',
                'Otrzymujesz tę wiadomość, ponieważ utworzono konto w Mapie Lotów z użyciem tego adresu e-mail.'
            )
        );

        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Konto zostało utworzone. Sprawdź e-mail i aktywuj konto przed pierwszym logowaniem.',
            'captcha_planned' => true,
        ], 201);
    }

    public function activate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $token = is_array($data)
            ? trim((string) ($data['token'] ?? ''))
            : '';

        if ($token === '') {
            return $this->error($response, 'Brak tokenu aktywacyjnego.', 422);
        }

        $hash = hash('sha256', $token);

        $stmt = $this->pdo->prepare(
            "
            SELECT id, session_version
            FROM ml_users
            WHERE email_verification_token_hash = :hash
              AND email_verification_expires_at >= NOW()
            LIMIT 1
            "
        );
        $stmt->execute(['hash' => $hash]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->error($response, 'Link aktywacyjny jest nieprawidłowy lub wygasł.', 422);
        }

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET email_verified_at = NOW(),
                email_verification_token_hash = NULL,
                email_verification_expires_at = NULL
            WHERE id = :id
            "
        );
        $stmt->execute(['id' => (int) $user['id']]);

        $freshUser = $this->findUserById((int) $user['id']);

        $this->auth->login(
            (int) $freshUser['id'],
            (int) $freshUser['session_version'],
            false
        );

        $stmt = $this->pdo->prepare(
            'UPDATE ml_users SET last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => (int) $freshUser['id']]);

        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Konto zostało aktywowane. Jesteś już zalogowany.',
            'authenticated' => true,
            'user' => $this->publicUserPayload($freshUser),
        ]);
    }

    public function resendActivation(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = is_array($data)
            ? mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8')
            : '';

        $genericMessage = 'Jeżeli konto istnieje i nadal wymaga aktywacji, wysłaliśmy nowy link aktywacyjny.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, [
                'status' => 'ok',
                'message' => $genericMessage,
            ]);
        }

        $user = $this->findUserByEmail($email);

        if ($user && empty($user['email_verified_at']) && !empty($user['password_hash'])) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);

            $stmt = $this->pdo->prepare(
                "
                UPDATE ml_users
                SET email_verification_token_hash = :hash,
                    email_verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                WHERE id = :id
                "
            );
            $stmt->execute([
                'hash' => $hash,
                'id' => (int) $user['id'],
            ]);

            $activationUrl = $this->appUrl
                . '/?konto=aktywacja&token='
                . rawurlencode($token);

            $this->mailer->send(
                $email,
                'Nowy link aktywacyjny - Mapa Lotów',
                $this->mailTemplate(
                    'Aktywuj konto',
                    'Poprosiłeś o nowy link aktywacyjny. Kliknij poniższy przycisk, aby potwierdzić adres e-mail i aktywować konto.',
                    'Aktywuj konto',
                    $activationUrl,
                    'Link aktywacyjny jest ważny przez 24 godziny. Poprzedni link przestał działać.',
                    'Otrzymujesz tę wiadomość, ponieważ poproszono o ponowne wysłanie linku aktywacyjnego do konta w Mapie Lotów.'
                )
            );
        }

        return $this->json($response, [
            'status' => 'ok',
            'message' => $genericMessage,
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (!is_array($data)) {
            return $this->error($response, 'Niepoprawne dane logowania.', 400);
        }

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
        $password = (string) ($data['password'] ?? '');
        $remember = (bool) ($data['remember'] ?? false);

        $user = $this->findUserByEmail($email);

        if (!$user) {
            $failureKey = hash('sha256', $email);
            $unknownFailures = isset($_SESSION['unknown_login_failures'][$failureKey])
                ? (int) $_SESSION['unknown_login_failures'][$failureKey]
                : 0;

            $unknownFailures++;

            if (!isset($_SESSION['unknown_login_failures'])) {
                $_SESSION['unknown_login_failures'] = [];
            }

            $_SESSION['unknown_login_failures'][$failureKey] = $unknownFailures;

            return $this->json($response, [
                'status' => 'error',
                'message' => 'Nieprawidłowy e-mail lub hasło.',
                'captcha_required' => $unknownFailures >= 2,
                'captcha_planned' => $unknownFailures >= 2,
            ], 401);
        }

        if ((int) ($user['is_locked'] ?? 0) === 1) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za kilka minut.',
                'captcha_required' => true,
            ], 429);
        }

        if ((int) ($user['is_active'] ?? 1) !== 1) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'To konto jest nieaktywne.',
            ], 403);
        }

        $validPassword = !empty($user['password_hash'])
            && password_verify($password, (string) $user['password_hash']);

        if (!$validPassword) {
            $attempts = ((int) ($user['failed_login_attempts'] ?? 0)) + 1;

            $stmt = $this->pdo->prepare(
                "
                UPDATE ml_users
                SET failed_login_attempts = :attempts,
                    last_failed_login_at = NOW(),
                    locked_until = CASE
                        WHEN :attempts_for_lock >= 10
                        THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        ELSE NULL
                    END
                WHERE id = :id
                "
            );
            $stmt->execute([
                'attempts' => $attempts,
                'attempts_for_lock' => $attempts,
                'id' => (int) $user['id'],
            ]);

            return $this->json($response, [
                'status' => 'error',
                'message' => empty($user['password_hash'])
                    ? 'To konto zostało przeniesione ze starej Mapy Lotów. Użyj opcji „Nie pamiętasz hasła?”, aby ustawić nowe hasło.'
                    : 'Nieprawidłowy e-mail lub hasło.',
                'captcha_required' => $attempts >= 2,
                'captcha_planned' => $attempts >= 2,
            ], 401);
        }

        if (empty($user['email_verified_at'])) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Konto nie zostało jeszcze aktywowane przez e-mail.',
            ], 403);
        }

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET failed_login_attempts = 0,
                last_failed_login_at = NULL,
                locked_until = NULL,
                last_login_at = NOW()
            WHERE id = :id
            "
        );
        $stmt->execute(['id' => (int) $user['id']]);

        $failureKey = hash('sha256', $email);

        if (isset($_SESSION['unknown_login_failures'][$failureKey])) {
            unset($_SESSION['unknown_login_failures'][$failureKey]);
        }

        $this->auth->login(
            (int) $user['id'],
            (int) $user['session_version'],
            $remember
        );

        $freshUser = $this->findUserById((int) $user['id']);

        return $this->json($response, [
            'status' => 'ok',
            'authenticated' => true,
            'user' => $this->publicUserPayload($freshUser),
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();

        return $this->json($response, [
            'status' => 'ok',
            'authenticated' => false,
        ]);
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = is_array($data)
            ? mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8')
            : '';

        $genericMessage = 'Jeżeli podany adres jest przypisany do konta, wysłaliśmy wiadomość z dalszymi instrukcjami.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($response, [
                'status' => 'ok',
                'message' => $genericMessage,
                'captcha_planned' => true,
            ]);
        }

        $user = $this->findUserByEmail($email);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $claimingLegacy = empty($user['password_hash']) || empty($user['email_verified_at']);

            $stmt = $this->pdo->prepare(
                "
                UPDATE ml_users
                SET password_reset_token_hash = :hash,
                    password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE)
                WHERE id = :id
                "
            );
            $stmt->execute([
                'hash' => $hash,
                'id' => (int) $user['id'],
            ]);

            $resetUrl = $this->appUrl
                . '/?konto=reset&token='
                . rawurlencode($token);

            $this->mailer->send(
                $email,
                $claimingLegacy
                    ? 'Aktywuj istniejące konto w Mapie Lotów'
                    : 'Reset hasła w Mapie Lotów',
                $this->mailTemplate(
                    $claimingLegacy ? 'Aktywuj istniejące konto' : 'Ustaw nowe hasło',
                    $claimingLegacy
                        ? 'Twoje konto i historia lotów zostały przeniesione ze starej Mapy Lotów. Ustaw nowe hasło, aby odzyskać dostęp do swoich danych.'
                        : 'Otrzymaliśmy prośbę o zmianę hasła. Kliknij poniżej, aby ustawić nowe hasło.',
                    'Ustaw nowe hasło',
                    $resetUrl,
                    'Link jest jednorazowy i ważny przez 60 minut.',
                    $claimingLegacy
                        ? 'Otrzymujesz tę wiadomość, ponieważ Twoje istniejące konto zostało przeniesione do nowej Mapy Lotów i rozpoczęto procedurę odzyskania dostępu.'
                        : 'Otrzymujesz tę wiadomość, ponieważ poproszono o zmianę hasła do konta w Mapie Lotów.'
                )
            );
        }

        return $this->json($response, [
            'status' => 'ok',
            'message' => $genericMessage,
            'captcha_planned' => true,
        ]);
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (!is_array($data)) {
            return $this->error($response, 'Niepoprawne dane resetu hasła.', 400);
        }

        $token = trim((string) ($data['token'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $passwordRepeat = (string) ($data['password_repeat'] ?? '');

        if ($token === '') {
            return $this->error($response, 'Brak tokenu resetu hasła.', 422);
        }

        $passwordError = $this->passwordValidationError($password);
        if ($passwordError !== null) {
            return $this->fieldError($response, $passwordError, 'password', 422);
        }

        if ($password !== $passwordRepeat) {
            return $this->fieldError($response, 'Hasła nie są identyczne.', 'password_repeat', 422);
        }

        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            "
            SELECT id, session_version
            FROM ml_users
            WHERE password_reset_token_hash = :hash
              AND password_reset_expires_at >= NOW()
            LIMIT 1
            "
        );
        $stmt->execute(['hash' => $hash]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->error($response, 'Link do zmiany hasła jest nieprawidłowy lub wygasł.', 422);
        }

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET password_hash = :password_hash,
                password_reset_token_hash = NULL,
                password_reset_expires_at = NULL,
                email_verified_at = COALESCE(email_verified_at, NOW()),
                failed_login_attempts = 0,
                last_failed_login_at = NULL,
                locked_until = NULL,
                session_version = session_version + 1
            WHERE id = :id
            "
        );
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);

        $this->auth->logout();

        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Hasło zostało ustawione. Możesz się teraz zalogować.',
        ]);
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $userId = $this->authenticatedUserId($response);
        if ($userId instanceof Response) {
            return $userId;
        }

        $data = $request->getParsedBody();
        $nick = is_array($data)
            ? trim((string) ($data['nick'] ?? ''))
            : '';

        if (mb_strlen($nick, 'UTF-8') < 2 || mb_strlen($nick, 'UTF-8') > 60) {
            return $this->error($response, 'Nick musi mieć od 2 do 60 znaków.', 422);
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM ml_users WHERE LOWER(nick) = LOWER(:nick) AND id <> :id LIMIT 1'
        );
        $stmt->execute(['nick' => $nick, 'id' => $userId]);

        if ($stmt->fetch()) {
            return $this->error($response, 'Ten nick jest już zajęty.', 409);
        }

        $user = $this->findUserById($userId);
        $publicSlug = $user['public_slug'] ?: null;

        if ($publicSlug !== null) {
            $publicSlug = $this->uniqueSlug($nick, $userId);
        }

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET nick = :nick,
                public_slug = :public_slug
            WHERE id = :id
            "
        );
        $stmt->execute([
            'nick' => $nick,
            'public_slug' => $publicSlug,
            'id' => $userId,
        ]);

        return $this->json($response, [
            'status' => 'ok',
            'user' => $this->publicUserPayload($this->findUserById($userId)),
        ]);
    }

    public function updatePrivacy(Request $request, Response $response): Response
    {
        $userId = $this->authenticatedUserId($response);
        if ($userId instanceof Response) {
            return $userId;
        }

        $data = $request->getParsedBody();
        $mode = is_array($data)
            ? (string) ($data['privacy_mode'] ?? '')
            : '';

        if (!in_array($mode, ['private', 'link', 'public'], true)) {
            return $this->error($response, 'Niepoprawny tryb prywatności.', 422);
        }

        $user = $this->findUserById($userId);
        $shareToken = $user['share_token'] ?: null;
        $publicSlug = $user['public_slug'] ?: null;

        if ($mode === 'link' && $shareToken === null) {
            $shareToken = bin2hex(random_bytes(24));
        }

        if ($mode === 'public') {
            $publicSlug = $this->uniqueSlug((string) $user['nick'], $userId);
        }

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET privacy_mode = :privacy_mode,
                share_token = :share_token,
                public_slug = :public_slug
            WHERE id = :id
            "
        );
        $stmt->execute([
            'privacy_mode' => $mode,
            'share_token' => $shareToken,
            'public_slug' => $publicSlug,
            'id' => $userId,
        ]);

        return $this->json($response, [
            'status' => 'ok',
            'user' => $this->publicUserPayload($this->findUserById($userId)),
        ]);
    }

    public function regenerateShareLink(Request $request, Response $response): Response
    {
        $userId = $this->authenticatedUserId($response);
        if ($userId instanceof Response) {
            return $userId;
        }

        $token = bin2hex(random_bytes(24));

        $stmt = $this->pdo->prepare(
            'UPDATE ml_users SET share_token = :token WHERE id = :id'
        );
        $stmt->execute(['token' => $token, 'id' => $userId]);

        return $this->json($response, [
            'status' => 'ok',
            'user' => $this->publicUserPayload($this->findUserById($userId)),
        ]);
    }

    public function changePassword(Request $request, Response $response): Response
    {
        $userId = $this->authenticatedUserId($response);
        if ($userId instanceof Response) {
            return $userId;
        }

        $data = $request->getParsedBody();

        if (!is_array($data)) {
            return $this->error($response, 'Niepoprawne dane formularza.', 400);
        }

        $current = (string) ($data['current_password'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $repeat = (string) ($data['password_repeat'] ?? '');

        $user = $this->findUserById($userId);

        if (!password_verify($current, (string) ($user['password_hash'] ?? ''))) {
            return $this->error($response, 'Aktualne hasło jest nieprawidłowe.', 422);
        }

        $passwordError = $this->passwordValidationError($password);
        if ($passwordError !== null) {
            return $this->fieldError($response, $passwordError, 'password', 422);
        }

        if ($password !== $repeat) {
            return $this->fieldError($response, 'Nowe hasła nie są identyczne.', 'password_repeat', 422);
        }

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET password_hash = :password_hash,
                session_version = session_version + 1
            WHERE id = :id
            "
        );
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        $this->auth->logout();

        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Hasło zostało zmienione. Zaloguj się ponownie.',
            'authenticated' => false,
        ]);
    }

    public function requestEmailChange(Request $request, Response $response): Response
    {
        $userId = $this->authenticatedUserId($response);
        if ($userId instanceof Response) {
            return $userId;
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            return $this->error($response, 'Niepoprawne dane formularza.', 400);
        }

        $currentPassword = (string) ($data['current_password'] ?? '');
        $newEmail = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->fieldError($response, 'Podaj poprawny nowy adres e-mail.', 'email', 422);
        }

        $user = $this->findUserById($userId);

        if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            return $this->error($response, 'Aktualne hasło jest nieprawidłowe.', 422);
        }

        if (mb_strtolower((string) $user['email'], 'UTF-8') === $newEmail) {
            return $this->fieldError($response, 'To jest już aktualny adres e-mail konta.', 'email', 422);
        }

        $existing = $this->findUserByEmail($newEmail);
        if ($existing && (int) $existing['id'] !== $userId) {
            return $this->fieldError($response, 'Konto z tym adresem e-mail już istnieje.', 'email', 409);
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET pending_email = :pending_email,
                email_change_token_hash = :hash,
                email_change_expires_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE)
            WHERE id = :id
            "
        );
        $stmt->execute([
            'pending_email' => $newEmail,
            'hash' => $hash,
            'id' => $userId,
        ]);

        $confirmUrl = $this->appUrl
            . '/?konto=email&token='
            . rawurlencode($token);

        $this->mailer->send(
            $newEmail,
            'Potwierdź nowy adres e-mail - Mapa Lotów',
            $this->mailTemplate(
                'Potwierdź nowy adres e-mail',
                'Na Twoim koncie w Mapie Lotów rozpoczęto zmianę adresu e-mail. Kliknij poniższy przycisk, aby potwierdzić ten adres.',
                'Potwierdź nowy e-mail',
                $confirmUrl,
                'Link jest jednorazowy i ważny przez 60 minut. Do czasu potwierdzenia konto nadal korzysta ze starego adresu.',
                'Otrzymujesz tę wiadomość, ponieważ ten adres został wskazany jako nowy adres e-mail konta w Mapie Lotów.'
            )
        );

        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Wysłaliśmy link potwierdzający na nowy adres e-mail. Do czasu jego kliknięcia dotychczasowy adres pozostaje bez zmian.',
        ]);
    }

    public function confirmEmailChange(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $token = is_array($data)
            ? trim((string) ($data['token'] ?? ''))
            : '';

        if ($token === '') {
            return $this->error($response, 'Brak tokenu zmiany adresu e-mail.', 422);
        }

        $hash = hash('sha256', $token);

        $stmt = $this->pdo->prepare(
            "
            SELECT id, email, pending_email, session_version
            FROM ml_users
            WHERE email_change_token_hash = :hash
              AND email_change_expires_at >= NOW()
              AND pending_email IS NOT NULL
            LIMIT 1
            "
        );
        $stmt->execute(['hash' => $hash]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->error($response, 'Link do zmiany adresu e-mail jest nieprawidłowy lub wygasł.', 422);
        }

        $oldEmail = (string) $user['email'];
        $newEmail = (string) $user['pending_email'];

        $existing = $this->findUserByEmail($newEmail);
        if ($existing && (int) $existing['id'] !== (int) $user['id']) {
            return $this->error($response, 'Ten adres e-mail jest już używany przez inne konto.', 409);
        }

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET email = :email,
                pending_email = NULL,
                email_change_token_hash = NULL,
                email_change_expires_at = NULL,
                email_verified_at = NOW(),
                session_version = session_version + 1
            WHERE id = :id
            "
        );
        $stmt->execute([
            'email' => $newEmail,
            'id' => (int) $user['id'],
        ]);

        $this->mailer->send(
            $oldEmail,
            'Adres e-mail konta został zmieniony - Mapa Lotów',
            $this->noticeMailTemplate(
                'Adres e-mail został zmieniony',
                'Adres e-mail przypisany do Twojego konta w Mapie Lotów został zmieniony na ' . $newEmail . '.',
                'Jeśli to nie Ty wykonałeś tę operację, jak najszybciej zabezpiecz konto i skontaktuj się z administratorem Mapy Lotów.',
                'Otrzymujesz tę wiadomość na poprzedni adres e-mail jako informację bezpieczeństwa o zmianie danych konta.'
            )
        );

        $this->auth->logout();

        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Nowy adres e-mail został potwierdzony. Ze względów bezpieczeństwa zaloguj się ponownie.',
            'authenticated' => false,
        ]);
    }

    private function findUserByEmail(string $email): array|false
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                ml_users.*,
                CASE
                    WHEN locked_until IS NOT NULL
                     AND locked_until > NOW()
                    THEN 1
                    ELSE 0
                END AS is_locked
            FROM ml_users
            WHERE LOWER(email) = LOWER(:email)
            LIMIT 1
            "
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    private function findUserById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ml_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new \RuntimeException('Nie znaleziono użytkownika.');
        }

        return $user;
    }

    private function publicUserPayload(array $user): array
    {
        $email = (string) ($user['email'] ?? '');
        $nick = (string) ($user['nick'] ?? $user['display_name'] ?? 'Użytkownik');
        $privacy = (string) ($user['privacy_mode'] ?? 'private');
        $shareToken = $user['share_token'] ?? null;
        $publicSlug = $user['public_slug'] ?? null;

        return [
            'id' => (int) $user['id'],
            'email' => $email,
            'nick' => $nick,
            'avatar_url' => $this->gravatarUrl($email),
            'privacy_mode' => $privacy,
            'share_url' => $shareToken
                ? $this->appUrl . '/udostepniona/' . rawurlencode((string) $shareToken)
                : null,
            'public_url' => $publicSlug
                ? $this->appUrl . '/profil/' . rawurlencode((string) $publicSlug)
                : null,
            'public_slug' => $publicSlug,
        ];
    }

    private function gravatarUrl(string $email): string
    {
        $hash = md5(mb_strtolower(trim($email), 'UTF-8'));
        return 'https://www.gravatar.com/avatar/' . $hash . '?s=160&d=404&r=g';
    }

    private function uniqueSlug(string $nick, int $userId): string
    {
        $base = $this->slugify($nick);
        if ($base === '') {
            $base = 'profil-' . $userId;
        }

        $candidate = $base;
        $suffix = 2;

        while (true) {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM ml_users WHERE public_slug = :slug AND id <> :id LIMIT 1'
            );
            $stmt->execute(['slug' => $candidate, 'id' => $userId]);

            if (!$stmt->fetch()) {
                return $candidate;
            }

            $candidate = $base . '-' . $suffix;
            $suffix++;
        }
    }

    private function repairPublicSlug(array $user): array
    {
        if (empty($user['public_slug'])) {
            return $user;
        }

        $correctSlug = $this->uniqueSlug(
            (string) ($user['nick'] ?? ''),
            (int) $user['id']
        );

        if ((string) $user['public_slug'] === $correctSlug) {
            return $user;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE ml_users SET public_slug = :slug WHERE id = :id'
        );
        $stmt->execute([
            'slug' => $correctSlug,
            'id' => (int) $user['id'],
        ]);

        $user['public_slug'] = $correctSlug;

        return $user;
    }

    private function slugify(string $value): string
    {
        $value = trim($value);

        $value = strtr($value, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l',
            'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
            'ż' => 'z',
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L',
            'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z',
            'Ż' => 'Z',
        ]);

        $transliterated = @iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function mailTemplate(
        string $title,
        string $message,
        string $buttonLabel,
        string $buttonUrl,
        string $note,
        string $reason
    ): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeButton = htmlspecialchars($buttonLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($buttonUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeNote = htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLogoUrl = htmlspecialchars(
            $this->appUrl . '/src/assets/branding/mapa-lotow-symbol.png',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $year = date('Y');

        return <<<HTML
<!doctype html>
<html lang="pl">
<body style="margin:0;padding:0;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#263244">
  <div style="padding:28px 14px">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dfe7ee;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(11,45,92,.08)">
      <div style="padding:12px 24px;background:#f8fbfd;border-bottom:1px solid #e5edf3">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;border-collapse:collapse">
          <tr>
            <td style="vertical-align:middle;padding-right:10px">
              <img src="{$safeLogoUrl}" alt="Mapa Lotów" width="38" height="38" style="display:block;width:38px;height:38px;object-fit:contain">
            </td>
            <td style="vertical-align:middle;color:#0b2d5c;font-size:18px;font-weight:800;letter-spacing:.05em;line-height:1">
              MAPA LOTÓW
            </td>
          </tr>
        </table>
      </div>

      <div style="padding:28px 30px">
        <h1 style="margin:0 0 14px;color:#0b2d5c;font-size:25px;line-height:1.2">{$safeTitle}</h1>
        <p style="margin:0;color:#445367;font-size:15px;line-height:1.65">{$safeMessage}</p>

        <div style="margin:26px 0;text-align:center">
          <a href="{$safeUrl}" style="display:inline-block;background:#0b2d5c;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:9px;font-size:14px;font-weight:700">{$safeButton}</a>
        </div>

        <p style="margin:0 0 16px;color:#738091;font-size:12px;line-height:1.5">{$safeNote}</p>

        <div style="padding-top:16px;border-top:1px solid #e7edf2">
          <p style="margin:0 0 4px;color:#7a8796;font-size:12px;line-height:1.4">
            Jeśli przycisk nie działa, skopiuj poniższy adres i wklej go do przeglądarki:
          </p>
          <p style="margin:0;overflow-wrap:anywhere;word-break:break-all">
            <a href="{$safeUrl}" style="color:#315d8e;font-size:12px;line-height:1.4">{$safeUrl}</a>
          </p>
        </div>
      </div>

      <div style="padding:15px 30px;background:#f7f9fb;border-top:1px solid #e5edf3;color:#7d8996;font-size:11px;line-height:1.3">
        <div style="margin:0 0 2px;font-weight:700;color:#667586">Mapa Lotów</div>
        <div style="margin:0">{$safeReason}</div>
        <div style="margin:3px 0 0">Jeżeli nie inicjowałeś tej operacji, możesz zignorować tę wiadomość.</div>
        <div style="margin:3px 0 0">© {$year} Mapa Lotów</div>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function noticeMailTemplate(
        string $title,
        string $message,
        string $warning,
        string $reason
    ): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeWarning = htmlspecialchars($warning, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLogoUrl = htmlspecialchars(
            $this->appUrl . '/src/assets/branding/mapa-lotow-symbol.png',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $year = date('Y');

        return <<<HTML
<!doctype html>
<html lang="pl">
<body style="margin:0;padding:0;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#263244">
  <div style="padding:28px 14px">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dfe7ee;border-radius:16px;overflow:hidden">
      <div style="padding:12px 24px;background:#f8fbfd;border-bottom:1px solid #e5edf3">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;border-collapse:collapse">
          <tr>
            <td style="vertical-align:middle;padding-right:10px">
              <img src="{$safeLogoUrl}" alt="Mapa Lotów" width="38" height="38" style="display:block;width:38px;height:38px;object-fit:contain">
            </td>
            <td style="vertical-align:middle;color:#0b2d5c;font-size:18px;font-weight:800;letter-spacing:.05em;line-height:1">
              MAPA LOTÓW
            </td>
          </tr>
        </table>
      </div>

      <div style="padding:28px 30px">
        <h1 style="margin:0 0 14px;color:#0b2d5c;font-size:25px">{$safeTitle}</h1>
        <p style="margin:0 0 16px;color:#445367;font-size:15px;line-height:1.6">{$safeMessage}</p>
        <div style="padding:14px 16px;border:1px solid #efc9c9;border-radius:9px;background:#fff6f6;color:#8f3434;font-size:13px;line-height:1.5">{$safeWarning}</div>
      </div>

      <div style="padding:15px 30px;background:#f7f9fb;border-top:1px solid #e5edf3;color:#7d8996;font-size:11px;line-height:1.3">
        <div style="margin:0 0 2px;font-weight:700;color:#667586">Mapa Lotów</div>
        <div style="margin:0">{$safeReason}</div>
        <div style="margin:3px 0 0">© {$year} Mapa Lotów</div>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    private function passwordValidationError(string $password): ?string
    {
        if (mb_strlen($password, 'UTF-8') < 10) {
            return 'Hasło musi mieć co najmniej 10 znaków.';
        }

        preg_match_all('/\d/u', $password, $digits);
        if (count($digits[0]) < 2) {
            return 'Hasło musi zawierać co najmniej 2 cyfry.';
        }

        if (!preg_match('/[^\p{L}\p{N}\s]/u', $password)) {
            return 'Hasło musi zawierać co najmniej 1 znak specjalny.';
        }

        return null;
    }

    private function authenticatedUserId(Response $response): int|Response
    {
        try {
            return $this->auth->requireUserId();
        } catch (\RuntimeException $e) {
            return $this->error($response, 'Musisz się zalogować.', 401);
        }
    }

    private function fieldError(
        Response $response,
        string $message,
        string $field,
        int $status
    ): Response {
        return $this->json($response, [
            'status' => 'error',
            'message' => $message,
            'field' => $field,
        ], $status);
    }

    private function error(Response $response, string $message, int $status): Response
    {
        return $this->json($response, [
            'status' => 'error',
            'message' => $message,
        ], $status);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
