<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Security;

use PDO;

final class AuthService
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function userId(): ?int
    {
        $userId = isset($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : 0;

        $sessionVersion = isset($_SESSION['session_version'])
            ? (int) $_SESSION['session_version']
            : 0;

        if ($userId <= 0 || $sessionVersion <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT session_version, email_verified_at, is_active FROM ml_users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->logout();
            return null;
        }

        if (
            empty($user['email_verified_at'])
            || (int) ($user['is_active'] ?? 1) !== 1
        ) {
            $this->logout();
            return null;
        }

        if ((int) $user['session_version'] !== $sessionVersion) {
            $this->logout();
            return null;
        }

        return $userId;
    }

    public function requireUserId(): int
    {
        $userId = $this->userId();

        if ($userId === null) {
            throw new \RuntimeException('AUTH_REQUIRED');
        }

        return $userId;
    }

    public function login(
        int $userId,
        int $sessionVersion,
        bool $remember
    ): void {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
        $_SESSION['session_version'] = $sessionVersion;
        $_SESSION['remember'] = $remember;

        $params = session_get_cookie_params();
        $expires = $remember
            ? time() + (60 * 60 * 24 * 30)
            : 0;

        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => $expires,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?: '',
                'secure' => (bool) $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?: '/',
                    'domain' => $params['domain'] ?: '',
                    'secure' => (bool) $params['secure'],
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );

            session_destroy();
        }
    }
}
