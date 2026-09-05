<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Transazja\MapaLotowApi\Security\AuthService;

final class AchievementController
{
    private const FLIGHT_THRESHOLDS = [
        25,
        50,
        100,
        200,
        300,
        400,
        500,
        600,
        750,
        1000,
    ];

    public function __construct(
        private PDO $pdo,
        private AuthService $auth
    ) {
    }

    public function index(
        Request $request,
        Response $response
    ): Response {
        $userId = $this->requireUserId($response);

        if ($userId instanceof Response) {
            return $userId;
        }

        // Bezpieczny fallback: jeśli użytkownik osiągnął próg po migracji,
        // ale synchronizacja nie została jeszcze wywołana, uzupełniamy wpis.
        $this->syncMissingAchievements($userId);

        return $this->json(
            $response,
            $this->buildState($userId)
        );
    }

    public function sync(
        Request $request,
        Response $response
    ): Response {
        $userId = $this->requireUserId($response);

        if ($userId instanceof Response) {
            return $userId;
        }

        $newKeys = $this->syncMissingAchievements($userId);
        $state = $this->buildState($userId);

        $state['newly_earned'] = array_values(
            array_filter(
                $state['achievements'],
                static fn(array $item): bool => in_array(
                    $item['key'],
                    $newKeys,
                    true
                )
            )
        );

        return $this->json(
            $response,
            $state
        );
    }

    public function markNotified(
        Request $request,
        Response $response
    ): Response {
        $userId = $this->requireUserId($response);

        if ($userId instanceof Response) {
            return $userId;
        }

        $body = $request->getParsedBody();
        $keys = is_array($body)
            ? ($body['keys'] ?? [])
            : [];

        if (!is_array($keys)) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawna lista osiągnięć.',
                ],
                422
            );
        }

        $keys = array_values(
            array_filter(
                array_map(
                    static fn($key): string => trim((string) $key),
                    $keys
                ),
                static fn(string $key): bool => preg_match(
                    '/^flights_(25|50|100|200|300|400|500|600|750|1000)$/',
                    $key
                ) === 1
            )
        );

        if ($keys === []) {
            return $this->json(
                $response,
                ['status' => 'ok']
            );
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($keys), '?')
        );

        $stmt = $this->pdo->prepare(
            "
            UPDATE ml_user_achievements
            SET notified_at = COALESCE(notified_at, NOW())
            WHERE user_id = ?
              AND achievement_key IN ($placeholders)
            "
        );

        $stmt->execute([
            $userId,
            ...$keys,
        ]);

        return $this->json(
            $response,
            ['status' => 'ok']
        );
    }

    /**
     * @return list<string> klucze osiągnięć utworzonych w tej synchronizacji
     */
    private function syncMissingAchievements(int $userId): array
    {
        $completedFlights = $this->completedFlightCount($userId);

        if ($completedFlights < self::FLIGHT_THRESHOLDS[0]) {
            return [];
        }

        $existingStmt = $this->pdo->prepare(
            "
            SELECT achievement_key
            FROM ml_user_achievements
            WHERE user_id = :user_id
              AND family = 'flights'
            "
        );
        $existingStmt->execute([
            'user_id' => $userId,
        ]);

        $existing = array_fill_keys(
            array_map(
                static fn(array $row): string => (string) $row['achievement_key'],
                $existingStmt->fetchAll(PDO::FETCH_ASSOC)
            ),
            true
        );

        $insert = $this->pdo->prepare(
            "
            INSERT IGNORE INTO ml_user_achievements (
                user_id,
                achievement_key,
                family,
                threshold_value,
                earned_at,
                notified_at,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                :achievement_key,
                'flights',
                :threshold_value,
                :earned_at,
                NULL,
                NOW(),
                NOW()
            )
            "
        );

        $newKeys = [];

        foreach (self::FLIGHT_THRESHOLDS as $threshold) {
            if ($completedFlights < $threshold) {
                break;
            }

            $key = 'flights_' . $threshold;

            if (isset($existing[$key])) {
                continue;
            }

            $earnedAt = $this->thresholdEarnedAt(
                $userId,
                $threshold
            );

            if ($earnedAt === null) {
                continue;
            }

            $insert->execute([
                'user_id' => $userId,
                'achievement_key' => $key,
                'threshold_value' => $threshold,
                'earned_at' => $earnedAt,
            ]);

            if ($insert->rowCount() > 0) {
                $newKeys[] = $key;
            }
        }

        return $newKeys;
    }

    private function completedFlightCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(*)
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function thresholdEarnedAt(
        int $userId,
        int $threshold
    ): ?string {
        $offset = $threshold - 1;

        $stmt = $this->pdo->prepare(
            "
            SELECT
                departure_date,
                COALESCE(departure_time, '00:00:00') AS departure_time
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
            ORDER BY
                departure_date ASC,
                COALESCE(departure_time, '00:00:00') ASC,
                id ASC
            LIMIT 1 OFFSET $offset
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return sprintf(
            '%s %s',
            (string) $row['departure_date'],
            (string) $row['departure_time']
        );
    }

    private function buildState(int $userId): array
    {
        $completedFlights = $this->completedFlightCount($userId);

        $stmt = $this->pdo->prepare(
            "
            SELECT
                achievement_key,
                threshold_value,
                earned_at,
                notified_at
            FROM ml_user_achievements
            WHERE user_id = :user_id
              AND family = 'flights'
            ORDER BY threshold_value ASC
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $earnedRows = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $earnedRows[(int) $row['threshold_value']] = $row;
        }

        $achievements = [];
        $earnedCount = 0;
        $activeCount = 0;
        $lastEarned = null;
        $pendingUnlocks = [];

        foreach (self::FLIGHT_THRESHOLDS as $threshold) {
            $row = $earnedRows[$threshold] ?? null;
            $earned = $row !== null;
            $active = $earned && $completedFlights >= $threshold;

            if ($earned) {
                $earnedCount++;
            }

            if ($active) {
                $activeCount++;
            }

            $item = [
                'key' => 'flights_' . $threshold,
                'family' => 'flights',
                'threshold' => $threshold,
                'status' => !$earned
                    ? 'locked'
                    : ($active ? 'active' : 'inactive'),
                'earned' => $earned,
                'active' => $active,
                'earned_at' => $earned
                    ? (string) $row['earned_at']
                    : null,
                'notified_at' => $earned && $row['notified_at'] !== null
                    ? (string) $row['notified_at']
                    : null,
            ];

            $achievements[] = $item;

            if (
                $earned &&
                $row['notified_at'] === null
            ) {
                $pendingUnlocks[] = $item;
            }

            if (
                $earned &&
                (
                    $lastEarned === null ||
                    strcmp(
                        (string) $item['earned_at'],
                        (string) $lastEarned['earned_at']
                    ) > 0
                )
            ) {
                $lastEarned = $item;
            }
        }

        $nextThreshold = null;

        foreach (self::FLIGHT_THRESHOLDS as $threshold) {
            if ($threshold > $completedFlights) {
                $nextThreshold = $threshold;
                break;
            }
        }

        return [
            'status' => 'ok',
            'completed_flights' => $completedFlights,
            'achievements' => $achievements,
            'pending_unlocks' => $pendingUnlocks,
            'summary' => [
                'earned_count' => $earnedCount,
                'active_count' => $activeCount,
                'last_earned' => $lastEarned,
                'next_threshold' => $nextThreshold,
                'remaining_to_next' => $nextThreshold !== null
                    ? max(0, $nextThreshold - $completedFlights)
                    : null,
            ],
        ];
    }

    private function requireUserId(Response $response): int|Response
    {
        try {
            return $this->auth->requireUserId();
        } catch (\RuntimeException) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Musisz się zalogować.',
                ],
                401
            );
        }
    }

    private function json(
        Response $response,
        array $data,
        int $status = 200
    ): Response {
        $response->getBody()->write(
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )
        );

        return $response
            ->withStatus($status)
            ->withHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            );
    }
}
