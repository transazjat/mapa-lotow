<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Transazja\MapaLotowApi\Security\AuthService;

final class AdminController
{
    public function __construct(
        private PDO $pdo,
        private AuthService $auth
    ) {
    }

    public function dashboard(
        Request $request,
        Response $response
    ): Response {
        $adminId = $this->requireAdmin($response);

        if ($adminId instanceof Response) {
            return $adminId;
        }

        $users = $this->pdo->query(
            "
            SELECT
                COUNT(*) AS total,
                SUM(is_active = 1) AS active,
                SUM(is_active = 0) AS inactive,
                SUM(email_verified_at IS NOT NULL) AS verified,
                SUM(email_verified_at IS NULL) AS unverified,
                SUM(is_admin = 1) AS admins,
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_7,
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS new_30
            FROM ml_users
            "
        )->fetch();

        $flights = $this->pdo->query(
            "
            SELECT
                COUNT(*) AS total,
                SUM(departure_date <= CURDATE()) AS completed,
                SUM(departure_date > CURDATE()) AS planned,
                COALESCE(SUM(distance_km), 0) AS distance_km,
                COALESCE(SUM(duration_seconds), 0) AS duration_seconds,
                COUNT(DISTINCT user_id) AS users_with_flights
            FROM ml_flights
            "
        )->fetch();

        $recentUsers = $this->pdo->query(
            "
            SELECT
                id,
                nick,
                email,
                is_active,
                email_verified_at,
                created_at,
                last_login_at
            FROM ml_users
            ORDER BY created_at DESC, id DESC
            LIMIT 8
            "
        )->fetchAll();

        $recentFlights = $this->pdo->query(
            "
            SELECT
                f.id,
                f.user_id,
                f.departure_date,
                f.departure_time,
                f.flight_number,
                u.nick AS user_nick,
                dep.iata_code AS departure_iata,
                arr.iata_code AS arrival_iata,
                al.name AS airline_name
            FROM ml_flights f
            JOIN ml_users u
                ON u.id = f.user_id
            JOIN ml_airports dep
                ON dep.id = f.departure_airport_id
            JOIN ml_airports arr
                ON arr.id = f.arrival_airport_id
            LEFT JOIN ml_airlines al
                ON al.id = f.airline_id
            ORDER BY
                f.departure_date DESC,
                f.departure_time DESC,
                f.id DESC
            LIMIT 8
            "
        )->fetchAll();

        return $this->json($response, [
            'status' => 'ok',
            'users' => [
                'total' => (int) ($users['total'] ?? 0),
                'active' => (int) ($users['active'] ?? 0),
                'inactive' => (int) ($users['inactive'] ?? 0),
                'verified' => (int) ($users['verified'] ?? 0),
                'unverified' => (int) ($users['unverified'] ?? 0),
                'admins' => (int) ($users['admins'] ?? 0),
                'new_7' => (int) ($users['new_7'] ?? 0),
                'new_30' => (int) ($users['new_30'] ?? 0),
            ],
            'flights' => [
                'total' => (int) ($flights['total'] ?? 0),
                'completed' => (int) ($flights['completed'] ?? 0),
                'planned' => (int) ($flights['planned'] ?? 0),
                'distance_km' => (float) ($flights['distance_km'] ?? 0),
                'duration_seconds' => (int) ($flights['duration_seconds'] ?? 0),
                'users_with_flights' => (int) ($flights['users_with_flights'] ?? 0),
            ],
            'recent_users' => $recentUsers,
            'recent_flights' => $recentFlights,
        ]);
    }

    public function users(
        Request $request,
        Response $response
    ): Response {
        $adminId = $this->requireAdmin($response);

        if ($adminId instanceof Response) {
            return $adminId;
        }

        $query = $request->getQueryParams();

        $q = trim((string) ($query['q'] ?? ''));
        $status = trim((string) ($query['status'] ?? 'all'));
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(
            100,
            max(10, (int) ($query['per_page'] ?? 25))
        );

        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = "
                (
                    u.nick LIKE :q
                    OR u.email LIKE :q
                    OR CAST(u.id AS CHAR) = :exact_id
                )
            ";

            $params['q'] = '%' . $q . '%';
            $params['exact_id'] = $q;
        }

        if ($status === 'active') {
            $where[] = 'u.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'u.is_active = 0';
        } elseif ($status === 'verified') {
            $where[] = 'u.email_verified_at IS NOT NULL';
        } elseif ($status === 'unverified') {
            $where[] = 'u.email_verified_at IS NULL';
        } elseif ($status === 'admin') {
            $where[] = 'u.is_admin = 1';
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $countSql = "
            SELECT COUNT(*)
            FROM ml_users u
            {$whereSql}
        ";

        $countStmt = $this->pdo->prepare($countSql);

        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }

        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                u.id,
                u.nick,
                u.email,
                u.is_active,
                u.is_admin,
                u.email_verified_at,
                u.privacy_mode,
                u.created_at,
                u.last_login_at,
                COUNT(f.id) AS flights_count,
                COALESCE(SUM(f.distance_km), 0) AS distance_km,
                COALESCE(SUM(f.duration_seconds), 0) AS duration_seconds
            FROM ml_users u
            LEFT JOIN ml_flights f
                ON f.user_id = u.id
            {$whereSql}
            GROUP BY u.id
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->json($response, [
            'status' => 'ok',
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'users' => $stmt->fetchAll(),
        ]);
    }

    public function user(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $adminId = $this->requireAdmin($response);

        if ($adminId instanceof Response) {
            return $adminId;
        }

        $userId = (int) ($args['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            "
            SELECT
                u.id,
                u.nick,
                u.email,
                u.is_active,
                u.is_admin,
                u.email_verified_at,
                u.privacy_mode,
                u.public_slug,
                u.created_at,
                u.updated_at,
                u.last_login_at,
                COUNT(f.id) AS flights_count,
                COALESCE(SUM(f.distance_km), 0) AS distance_km,
                COALESCE(SUM(f.duration_seconds), 0) AS duration_seconds,
                SUM(f.departure_date > CURDATE()) AS planned_count
            FROM ml_users u
            LEFT JOIN ml_flights f
                ON f.user_id = u.id
            WHERE u.id = :id
            GROUP BY u.id
            LIMIT 1
            "
        );

        $stmt->execute([
            'id' => $userId,
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            return $this->error(
                $response,
                'Nie znaleziono użytkownika.',
                404
            );
        }

        $recent = $this->pdo->prepare(
            "
            SELECT
                f.id,
                f.departure_date,
                f.departure_time,
                f.flight_number,
                dep.iata_code AS departure_iata,
                arr.iata_code AS arrival_iata,
                al.name AS airline_name
            FROM ml_flights f
            JOIN ml_airports dep
                ON dep.id = f.departure_airport_id
            JOIN ml_airports arr
                ON arr.id = f.arrival_airport_id
            LEFT JOIN ml_airlines al
                ON al.id = f.airline_id
            WHERE f.user_id = :id
            ORDER BY
                f.departure_date DESC,
                f.departure_time DESC,
                f.id DESC
            LIMIT 10
            "
        );

        $recent->execute([
            'id' => $userId,
        ]);

        return $this->json($response, [
            'status' => 'ok',
            'user' => $user,
            'recent_flights' => $recent->fetchAll(),
            'is_current_admin' => $userId === $adminId,
        ]);
    }

    public function updateUser(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $adminId = $this->requireAdmin($response);

        if ($adminId instanceof Response) {
            return $adminId;
        }

        $userId = (int) ($args['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            "
            SELECT id, is_active, is_admin
            FROM ml_users
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            'id' => $userId,
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            return $this->error(
                $response,
                'Nie znaleziono użytkownika.',
                404
            );
        }

        $data = $request->getParsedBody();

        if (!is_array($data)) {
            return $this->error(
                $response,
                'Niepoprawne dane.',
                422
            );
        }

        $newActive = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : (bool) $user['is_active'];

        $newAdmin = array_key_exists('is_admin', $data)
            ? (bool) $data['is_admin']
            : (bool) $user['is_admin'];

        if ($userId === $adminId && !$newActive) {
            return $this->error(
                $response,
                'Nie możesz dezaktywować własnego konta administratora.',
                422
            );
        }

        if ($userId === $adminId && !$newAdmin) {
            return $this->error(
                $response,
                'Nie możesz odebrać sobie uprawnień administratora.',
                422
            );
        }

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_users
            SET
                is_active = :is_active,
                is_admin = :is_admin,
                session_version = CASE
                    WHEN is_active <> :is_active_check
                    THEN session_version + 1
                    ELSE session_version
                END
            WHERE id = :id
            "
        );

        $stmt->execute([
            'is_active' => $newActive ? 1 : 0,
            'is_active_check' => $newActive ? 1 : 0,
            'is_admin' => $newAdmin ? 1 : 0,
            'id' => $userId,
        ]);

        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Ustawienia użytkownika zostały zapisane.',
        ]);
    }

    public function flights(
        Request $request,
        Response $response
    ): Response {
        $adminId = $this->requireAdmin($response);

        if ($adminId instanceof Response) {
            return $adminId;
        }

        $query = $request->getQueryParams();

        $q = trim((string) ($query['q'] ?? ''));
        $scope = trim((string) ($query['scope'] ?? 'all'));
        $userId = max(0, (int) ($query['user_id'] ?? 0));
        $dateFrom = trim((string) ($query['date_from'] ?? ''));
        $dateTo = trim((string) ($query['date_to'] ?? ''));
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(
            100,
            max(10, (int) ($query['per_page'] ?? 25))
        );

        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = "
                (
                    u.nick LIKE :q
                    OR u.email LIKE :q
                    OR f.flight_number LIKE :q
                    OR dep.iata_code LIKE :q
                    OR arr.iata_code LIKE :q
                    OR dep.name LIKE :q
                    OR arr.name LIKE :q
                    OR al.name LIKE :q
                )
            ";

            $params['q'] = '%' . $q . '%';
        }

        if ($scope === 'completed') {
            $where[] = 'f.departure_date <= CURDATE()';
        } elseif ($scope === 'planned') {
            $where[] = 'f.departure_date > CURDATE()';
        }

        if ($userId > 0) {
            $where[] = 'f.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if ($dateFrom !== '') {
            $where[] = 'f.departure_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'f.departure_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $whereSql = $where
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $fromSql = "
            FROM ml_flights f
            JOIN ml_users u
                ON u.id = f.user_id
            JOIN ml_airports dep
                ON dep.id = f.departure_airport_id
            JOIN ml_airports arr
                ON arr.id = f.arrival_airport_id
            LEFT JOIN ml_airlines al
                ON al.id = f.airline_id
            LEFT JOIN ml_aircraft_types ac
                ON ac.id = f.aircraft_type_id
        ";

        $countStmt = $this->pdo->prepare(
            "
            SELECT COUNT(*)
            {$fromSql}
            {$whereSql}
            "
        );

        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }

        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.id,
                f.user_id,
                f.departure_date,
                f.departure_time,
                f.arrival_date,
                f.arrival_time,
                f.flight_number,
                f.distance_km,
                f.duration_seconds,
                f.travel_class,
                f.seat_type,
                f.travel_reason,
                f.notes,

                u.nick AS user_nick,
                u.email AS user_email,

                dep.iata_code AS departure_iata,
                dep.name AS departure_airport,
                dep.city AS departure_city,

                arr.iata_code AS arrival_iata,
                arr.name AS arrival_airport,
                arr.city AS arrival_city,

                al.name AS airline_name,
                al.iata_code AS airline_iata,

                ac.name AS aircraft_name

            {$fromSql}
            {$whereSql}

            ORDER BY
                f.departure_date DESC,
                f.departure_time DESC,
                f.id DESC

            LIMIT :limit OFFSET :offset
            "
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->json($response, [
            'status' => 'ok',
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'flights' => $stmt->fetchAll(),
        ]);
    }

    public function flight(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $adminId = $this->requireAdmin($response);

        if ($adminId instanceof Response) {
            return $adminId;
        }

        $flightId = (int) ($args['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.*,
                u.nick AS user_nick,
                u.email AS user_email,

                dep.iata_code AS departure_iata,
                dep.icao_code AS departure_icao,
                dep.name AS departure_airport,
                dep.city AS departure_city,

                arr.iata_code AS arrival_iata,
                arr.icao_code AS arrival_icao,
                arr.name AS arrival_airport,
                arr.city AS arrival_city,

                al.name AS airline_name,
                al.iata_code AS airline_iata,

                ac.name AS aircraft_name

            FROM ml_flights f

            JOIN ml_users u
                ON u.id = f.user_id

            JOIN ml_airports dep
                ON dep.id = f.departure_airport_id

            JOIN ml_airports arr
                ON arr.id = f.arrival_airport_id

            LEFT JOIN ml_airlines al
                ON al.id = f.airline_id

            LEFT JOIN ml_aircraft_types ac
                ON ac.id = f.aircraft_type_id

            WHERE f.id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            'id' => $flightId,
        ]);

        $flight = $stmt->fetch();

        if (!$flight) {
            return $this->error(
                $response,
                'Nie znaleziono lotu.',
                404
            );
        }

        return $this->json($response, [
            'status' => 'ok',
            'flight' => $flight,
        ]);
    }

    public function deleteFlight(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $adminId = $this->requireAdmin($response);

        if ($adminId instanceof Response) {
            return $adminId;
        }

        $flightId = (int) ($args['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            "
            DELETE FROM ml_flights
            WHERE id = :id
            "
        );

        $stmt->execute([
            'id' => $flightId,
        ]);

        if ($stmt->rowCount() === 0) {
            return $this->error(
                $response,
                'Nie znaleziono lotu.',
                404
            );
        }

        return $this->json($response, [
            'status' => 'ok',
            'message' => 'Lot został usunięty.',
        ]);
    }

    private function requireAdmin(
        Response $response
    ): int|Response {
        try {
            $userId = $this->auth->requireUserId();
        } catch (\RuntimeException) {
            return $this->error(
                $response,
                'Musisz się zalogować.',
                401
            );
        }

        $stmt = $this->pdo->prepare(
            "
            SELECT is_admin, is_active
            FROM ml_users
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            'id' => $userId,
        ]);

        $user = $stmt->fetch();

        if (
            !$user
            || !(bool) $user['is_active']
            || !(bool) $user['is_admin']
        ) {
            return $this->error(
                $response,
                'Brak uprawnień administratora.',
                403
            );
        }

        return $userId;
    }

    private function json(
        Response $response,
        array $data,
        int $status = 200
    ): Response {
        $response->getBody()->write(
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );

        return $response
            ->withStatus($status)
            ->withHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            );
    }

    private function error(
        Response $response,
        string $message,
        int $status
    ): Response {
        return $this->json(
            $response,
            [
                'status' => 'error',
                'message' => $message,
            ],
            $status
        );
    }
}
