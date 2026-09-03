<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use Transazja\MapaLotowApi\Security\AuthService;
use Transazja\MapaLotowApi\Service\AdminAuditService;

final class AdminOperationsController
{
    public function __construct(
        private PDO $pdo,
        private AuthService $auth,
        private AdminAuditService $audit
    ) {
    }

    public function quality(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;

        $categories = [];
        $categories[] = $this->qualityCategory(
            'airports_timezone', 'Lotniska bez strefy czasowej', 'warning', 'airports',
            "SELECT COUNT(*) FROM ml_airports WHERE timezone_name IS NULL OR TRIM(timezone_name)=''",
            "SELECT id,name,iata_code,icao_code,city,timezone_name FROM ml_airports WHERE timezone_name IS NULL OR TRIM(timezone_name)='' ORDER BY id LIMIT 20"
        );
        $categories[] = $this->qualityCategory(
            'airports_country', 'Lotniska bez przypisanego państwa', 'warning', 'airports',
            "SELECT COUNT(*) FROM ml_airports WHERE country_id IS NULL",
            "SELECT id,name,iata_code,icao_code,city,country_name FROM ml_airports WHERE country_id IS NULL ORDER BY id LIMIT 20"
        );
        $categories[] = $this->qualityCategory(
            'airports_coordinates', 'Lotniska bez poprawnych współrzędnych', 'error', 'airports',
            "SELECT COUNT(*) FROM ml_airports WHERE latitude IS NULL OR longitude IS NULL OR latitude NOT BETWEEN -90 AND 90 OR longitude NOT BETWEEN -180 AND 180",
            "SELECT id,name,iata_code,icao_code,latitude,longitude FROM ml_airports WHERE latitude IS NULL OR longitude IS NULL OR latitude NOT BETWEEN -90 AND 90 OR longitude NOT BETWEEN -180 AND 180 ORDER BY id LIMIT 20"
        );
        $categories[] = $this->qualityCategory(
            'flights_distance', 'Loty z niepoprawnym dystansem', 'error', 'flights',
            "SELECT COUNT(*) FROM ml_flights WHERE departure_airport_id<>arrival_airport_id AND (distance_km IS NULL OR distance_km<=0)",
            "SELECT f.id,f.user_id,f.departure_date,f.distance_km,dep.iata_code departure_iata,arr.iata_code arrival_iata FROM ml_flights f JOIN ml_airports dep ON dep.id=f.departure_airport_id JOIN ml_airports arr ON arr.id=f.arrival_airport_id WHERE f.departure_airport_id<>f.arrival_airport_id AND (f.distance_km IS NULL OR f.distance_km<=0) ORDER BY f.departure_date DESC,f.id DESC LIMIT 20"
        );
        $categories[] = $this->qualityCategory(
            'flights_duration', 'Odbyte loty bez czasu lotu', 'warning', 'flights',
            "SELECT COUNT(*) FROM ml_flights WHERE departure_date<=CURDATE() AND (duration_seconds IS NULL OR duration_seconds<=0)",
            "SELECT f.id,f.user_id,f.departure_date,f.duration_seconds,dep.iata_code departure_iata,arr.iata_code arrival_iata FROM ml_flights f JOIN ml_airports dep ON dep.id=f.departure_airport_id JOIN ml_airports arr ON arr.id=f.arrival_airport_id WHERE f.departure_date<=CURDATE() AND (f.duration_seconds IS NULL OR f.duration_seconds<=0) ORDER BY f.departure_date DESC,f.id DESC LIMIT 20"
        );
        $categories[] = $this->qualityCategory(
            'flights_airline', 'Loty bez linii lotniczej', 'info', 'flights',
            "SELECT COUNT(*) FROM ml_flights WHERE airline_id IS NULL",
            "SELECT f.id,f.user_id,f.departure_date,f.flight_number,dep.iata_code departure_iata,arr.iata_code arrival_iata FROM ml_flights f JOIN ml_airports dep ON dep.id=f.departure_airport_id JOIN ml_airports arr ON arr.id=f.arrival_airport_id WHERE f.airline_id IS NULL ORDER BY f.departure_date DESC,f.id DESC LIMIT 20"
        );
        $categories[] = $this->qualityCategory(
            'flights_aircraft', 'Loty bez typu samolotu', 'info', 'flights',
            "SELECT COUNT(*) FROM ml_flights WHERE aircraft_type_id IS NULL",
            "SELECT f.id,f.user_id,f.departure_date,f.flight_number,dep.iata_code departure_iata,arr.iata_code arrival_iata FROM ml_flights f JOIN ml_airports dep ON dep.id=f.departure_airport_id JOIN ml_airports arr ON arr.id=f.arrival_airport_id WHERE f.aircraft_type_id IS NULL ORDER BY f.departure_date DESC,f.id DESC LIMIT 20"
        );

        if ($this->hasColumn('ml_airlines', 'is_active')) {
            $categories[] = $this->qualityCategory(
                'planned_inactive_airline', 'Zaplanowane loty z nieaktywną linią', 'warning', 'flights',
                "SELECT COUNT(*) FROM ml_flights f JOIN ml_airlines a ON a.id=f.airline_id WHERE f.departure_date>CURDATE() AND a.is_active=0",
                "SELECT f.id,f.user_id,f.departure_date,a.id airline_id,a.name airline_name FROM ml_flights f JOIN ml_airlines a ON a.id=f.airline_id WHERE f.departure_date>CURDATE() AND a.is_active=0 ORDER BY f.departure_date LIMIT 20"
            );
        }
        if ($this->hasColumn('ml_aircraft_types', 'is_active')) {
            $categories[] = $this->qualityCategory(
                'planned_inactive_aircraft', 'Zaplanowane loty z nieaktywnym typem samolotu', 'warning', 'flights',
                "SELECT COUNT(*) FROM ml_flights f JOIN ml_aircraft_types a ON a.id=f.aircraft_type_id WHERE f.departure_date>CURDATE() AND a.is_active=0",
                "SELECT f.id,f.user_id,f.departure_date,a.id aircraft_type_id,a.name aircraft_name FROM ml_flights f JOIN ml_aircraft_types a ON a.id=f.aircraft_type_id WHERE f.departure_date>CURDATE() AND a.is_active=0 ORDER BY f.departure_date LIMIT 20"
            );
        }

        $dupCounts = [
            'airports' => $this->duplicateGroupCount('ml_airports', 'iata_code') + $this->duplicateGroupCount('ml_airports', 'icao_code'),
            'airlines' => $this->duplicateGroupCount('ml_airlines', 'iata_code') + $this->duplicateGroupCount('ml_airlines', 'icao_code') + $this->duplicateGroupCount('ml_airlines', 'name'),
            'aircraft' => $this->duplicateGroupCount('ml_aircraft_types', 'name'),
        ];

        $totals = ['error' => 0, 'warning' => 0, 'info' => 0, 'all' => 0];
        foreach ($categories as $category) {
            $count = (int) $category['count'];
            $totals['all'] += $count;
            $totals[$category['severity']] += $count;
        }

        return $this->json($response, [
            'status' => 'ok',
            'totals' => $totals,
            'duplicates' => $dupCounts,
            'categories' => $categories,
            'generated_at' => date('c'),
        ]);
    }

    public function duplicateDetails(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $q = $request->getQueryParams();
        $type = (string) ($q['type'] ?? '');
        $ids = $this->parseIds((string) ($q['ids'] ?? ''));
        if (!$ids || !isset($this->mergeConfig()[$type])) return $this->error($response, 'Niepoprawne dane grupy duplikatów.', 422);

        $config = $this->mergeConfig()[$type];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM {$config['table']} WHERE id IN ({$placeholders}) ORDER BY id");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['usage_count'] = $this->usageCount($type, (int) $row['id']);
            $row['alias_count'] = $this->aliasCount($config['entity'], (int) $row['id']);
        }
        unset($row);
        return $this->json($response, ['status' => 'ok', 'type' => $type, 'items' => $rows]);
    }

    public function mergeDuplicates(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $data = (array) ($request->getParsedBody() ?: []);
        $type = (string) ($data['type'] ?? '');
        $primaryId = (int) ($data['primary_id'] ?? 0);
        $duplicateIds = array_values(array_unique(array_filter(array_map('intval', (array) ($data['duplicate_ids'] ?? [])), fn(int $id) => $id > 0 && $id !== $primaryId)));
        $configs = $this->mergeConfig();
        if (!isset($configs[$type]) || $primaryId <= 0 || !$duplicateIds) return $this->error($response, 'Niepoprawne dane scalania.', 422);
        $config = $configs[$type];

        $allIds = array_merge([$primaryId], $duplicateIds);
        $records = $this->fetchByIds($config['table'], $allIds);
        if (count($records) !== count($allIds)) return $this->error($response, 'Nie wszystkie rekordy istnieją.', 404);
        if (!isset($records[$primaryId])) return $this->error($response, 'Nie znaleziono rekordu docelowego.', 404);

        $blockers = $this->unknownReferenceBlockers($config['table'], $duplicateIds, $config['known_refs']);
        if ($blockers) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Scalanie zatrzymane: wykryto dodatkowe powiązania w bazie, których RUNWAY nie może bezpiecznie przepiąć automatycznie.',
                'blockers' => $blockers,
            ], 409);
        }

        try {
            $this->pdo->beginTransaction();
            $before = ['primary' => $records[$primaryId], 'duplicates' => array_values(array_intersect_key($records, array_flip($duplicateIds)))];

            foreach ($config['flight_refs'] as $column) {
                $ph = implode(',', array_fill(0, count($duplicateIds), '?'));
                $sql = "UPDATE ml_flights SET {$column}=? WHERE {$column} IN ({$ph})";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_merge([$primaryId], $duplicateIds));
            }

            $ph = implode(',', array_fill(0, count($duplicateIds), '?'));
            $aliasMove = $this->pdo->prepare("UPDATE ml_entity_aliases SET entity_id=?,updated_by=?,updated_at=NOW() WHERE entity_type=? AND entity_id IN ({$ph})");
            $aliasMove->execute(array_merge([$primaryId, $adminId, $config['entity']], $duplicateIds));

            foreach ($duplicateIds as $duplicateId) {
                $record = $records[$duplicateId];
                foreach ($this->aliasCandidates($type, $record) as $candidate) {
                    $this->insertAliasIgnore($config['entity'], $primaryId, $candidate, $adminId, 'merge');
                }
            }

            $delete = $this->pdo->prepare("DELETE FROM {$config['table']} WHERE id IN ({$ph})");
            $delete->execute($duplicateIds);

            $after = $this->fetchByIds($config['table'], [$primaryId])[$primaryId] ?? [];
            $this->audit->log(
                $adminId,
                'duplicate.merge',
                $config['entity'],
                $primaryId,
                'Scalono ' . count($duplicateIds) . ' duplikat(y) do rekordu #' . $primaryId,
                $before,
                ['primary' => $after, 'merged_ids' => $duplicateIds],
                $this->ip($request)
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->error($response, 'Nie udało się bezpiecznie scalić rekordów: ' . $e->getMessage(), 500);
        }

        return $this->json($response, ['status' => 'ok', 'message' => 'Rekordy zostały scalone.', 'primary_id' => $primaryId, 'merged_ids' => $duplicateIds]);
    }

    public function history(Request $request, Response $response, array $args): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $entity = (string) ($args['entity'] ?? '');
        $id = (int) ($args['id'] ?? 0);
        $allowed = ['user','flight','airport','airline','aircraft_type','alias'];
        if (!in_array($entity, $allowed, true) || $id <= 0) return $this->error($response, 'Niepoprawny rekord historii.', 422);
        $stmt = $this->pdo->prepare("SELECT l.*,u.nick admin_nick FROM ml_admin_audit_log l LEFT JOIN ml_users u ON u.id=l.admin_user_id WHERE l.entity_type=:entity AND l.entity_id=:id ORDER BY l.created_at DESC,l.id DESC LIMIT 200");
        $stmt->execute(['entity' => $entity, 'id' => $id]);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $before = $this->decodeJson($row['before_json'] ?? null);
            $after = $this->decodeJson($row['after_json'] ?? null);
            $items[] = $row + ['before' => $before, 'after' => $after, 'changes' => $this->diff($before, $after)];
        }
        return $this->json($response, ['status' => 'ok', 'entity' => $entity, 'entity_id' => $id, 'items' => $items]);
    }

    public function userActivity(Request $request, Response $response, array $args): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $id = (int) ($args['id'] ?? 0);
        $columns = $this->columns('ml_users');
        $select = ['id','nick','email','is_active','is_admin','email_verified_at','privacy_mode','created_at','updated_at','last_login_at','session_version'];
        foreach (['failed_login_attempts','last_failed_login_at','locked_until','password_reset_expires_at','email_verification_expires_at','pending_email','email_change_expires_at','public_slug','share_token'] as $column) {
            if (isset($columns[$column])) $select[] = $column;
        }
        $stmt = $this->pdo->prepare('SELECT ' . implode(',', $select) . ' FROM ml_users WHERE id=:id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!$user) return $this->error($response, 'Nie znaleziono użytkownika.', 404);
        $audit = $this->pdo->prepare("SELECT l.id,l.action,l.summary,l.created_at,a.nick admin_nick FROM ml_admin_audit_log l LEFT JOIN ml_users a ON a.id=l.admin_user_id WHERE l.entity_type='user' AND l.entity_id=:id ORDER BY l.created_at DESC LIMIT 25");
        $audit->execute(['id' => $id]);
        return $this->json($response, ['status' => 'ok', 'user' => $user, 'audit' => $audit->fetchAll()]);
    }

    public function security(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $columns = $this->columns('ml_users');
        $hasFailed = isset($columns['failed_login_attempts']);
        $hasLock = isset($columns['locked_until']);
        $failedExpr = $hasFailed ? 'failed_login_attempts' : '0';
        $lockedExpr = $hasLock ? '(locked_until IS NOT NULL AND locked_until>NOW())' : '0';

        $summary = $this->pdo->query(
            "SELECT COUNT(*) total_users,
                    SUM(is_admin=1) admins,
                    SUM(is_active=0) inactive,
                    SUM(email_verified_at IS NULL) unverified,
                    SUM({$failedExpr}>=5) elevated_failed_logins,
                    SUM({$lockedExpr}) locked_users
             FROM ml_users"
        )->fetch() ?: [];

        $select = "id,nick,email,is_active,is_admin,email_verified_at,last_login_at";
        if ($hasFailed) $select .= ',failed_login_attempts';
        if (isset($columns['last_failed_login_at'])) $select .= ',last_failed_login_at';
        if ($hasLock) $select .= ',locked_until';
        $where = [];
        if ($hasFailed) $where[] = 'failed_login_attempts>0';
        if ($hasLock) $where[] = '(locked_until IS NOT NULL AND locked_until>NOW())';
        $whereSql = $where ? 'WHERE ' . implode(' OR ', $where) : 'WHERE 1=0';
        $rows = $this->pdo->query("SELECT {$select} FROM ml_users {$whereSql} ORDER BY " . ($hasLock ? 'locked_until DESC,' : '') . ($hasFailed ? 'failed_login_attempts DESC,' : '') . 'id DESC LIMIT 100')->fetchAll();

        $admins = $this->pdo->query("SELECT id,nick,email,is_active,last_login_at,created_at FROM ml_users WHERE is_admin=1 ORDER BY is_active DESC,last_login_at DESC,id")->fetchAll();
        $unverified = $this->pdo->query("SELECT id,nick,email,is_active,created_at FROM ml_users WHERE email_verified_at IS NULL AND created_at<DATE_SUB(NOW(),INTERVAL 7 DAY) ORDER BY created_at LIMIT 100")->fetchAll();

        return $this->json($response, [
            'status' => 'ok',
            'summary' => $this->castNumbers($summary),
            'login_risks' => $rows,
            'admins' => $admins,
            'unverified' => $unverified,
            'capabilities' => ['failed_login_attempts' => $hasFailed, 'locked_until' => $hasLock],
        ]);
    }

    public function unlockUser(Request $request, Response $response, array $args): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return $this->error($response, 'Niepoprawny użytkownik.', 422);
        $columns = $this->columns('ml_users');
        $sets = [];
        if (isset($columns['failed_login_attempts'])) $sets[] = 'failed_login_attempts=0';
        if (isset($columns['last_failed_login_at'])) $sets[] = 'last_failed_login_at=NULL';
        if (isset($columns['locked_until'])) $sets[] = 'locked_until=NULL';
        if (!$sets) return $this->error($response, 'Aktualna struktura bazy nie posiada pól blokady logowania.', 409);
        $stmt = $this->pdo->prepare('UPDATE ml_users SET ' . implode(',', $sets) . ' WHERE id=:id');
        $stmt->execute(['id' => $id]);
        $this->audit->log($adminId, 'security.unlock', 'user', $id, 'Wyczyszczono blokadę i nieudane próby logowania', null, ['unlocked' => true], $this->ip($request));
        return $this->json($response, ['status' => 'ok', 'message' => 'Blokada logowania została wyczyszczona.']);
    }

    public function serviceStats(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;

        $totals = $this->pdo->query(
            "SELECT
               (SELECT COUNT(*) FROM ml_users) users,
               (SELECT COUNT(*) FROM ml_users WHERE is_active=1) active_users,
               (SELECT COUNT(*) FROM ml_users WHERE last_login_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)) active_users_30,
               (SELECT COUNT(*) FROM ml_flights) flights,
               (SELECT COUNT(*) FROM ml_flights WHERE departure_date>CURDATE()) planned_flights,
               (SELECT COALESCE(SUM(distance_km),0) FROM ml_flights) distance_km,
               (SELECT COALESCE(SUM(duration_seconds),0) FROM ml_flights) duration_seconds,
               (SELECT COUNT(*) FROM ml_airports) airports,
               (SELECT COUNT(*) FROM ml_airlines) airlines,
               (SELECT COUNT(*) FROM ml_aircraft_types) aircraft_types,
               (SELECT COUNT(*) FROM ml_countries) countries"
        )->fetch() ?: [];

        $monthly = $this->pdo->query(
            "WITH RECURSIVE months AS (
                SELECT DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 11 MONTH),'%Y-%m-01') m
                UNION ALL SELECT DATE_FORMAT(DATE_ADD(m,INTERVAL 1 MONTH),'%Y-%m-01') FROM months WHERE m<DATE_FORMAT(CURDATE(),'%Y-%m-01')
             )
             SELECT DATE_FORMAT(m.m,'%Y-%m') month,
                    (SELECT COUNT(*) FROM ml_users u WHERE u.created_at>=m.m AND u.created_at<DATE_ADD(m.m,INTERVAL 1 MONTH)) new_users,
                    (SELECT COUNT(*) FROM ml_flights f WHERE f.departure_date>=m.m AND f.departure_date<DATE_ADD(m.m,INTERVAL 1 MONTH)) flights
             FROM months m ORDER BY m.m"
        )->fetchAll();

        $topAirports = $this->pdo->query(
            "SELECT a.id,a.iata_code,a.name,SUM(x.cnt) operations FROM (
               SELECT departure_airport_id airport_id,COUNT(*) cnt FROM ml_flights GROUP BY departure_airport_id
               UNION ALL SELECT arrival_airport_id airport_id,COUNT(*) cnt FROM ml_flights GROUP BY arrival_airport_id
             ) x JOIN ml_airports a ON a.id=x.airport_id GROUP BY a.id ORDER BY operations DESC LIMIT 10"
        )->fetchAll();
        $topAirlines = $this->pdo->query("SELECT a.id,a.name,a.iata_code,COUNT(f.id) flights FROM ml_airlines a JOIN ml_flights f ON f.airline_id=a.id GROUP BY a.id ORDER BY flights DESC LIMIT 10")->fetchAll();
        $topAircraft = $this->pdo->query("SELECT a.id,a.name,COUNT(f.id) flights FROM ml_aircraft_types a JOIN ml_flights f ON f.aircraft_type_id=a.id GROUP BY a.id ORDER BY flights DESC LIMIT 10")->fetchAll();
        $topRoutes = $this->pdo->query("SELECT dep.iata_code departure_iata,arr.iata_code arrival_iata,COUNT(*) flights FROM ml_flights f JOIN ml_airports dep ON dep.id=f.departure_airport_id JOIN ml_airports arr ON arr.id=f.arrival_airport_id GROUP BY f.departure_airport_id,f.arrival_airport_id ORDER BY flights DESC LIMIT 10")->fetchAll();

        return $this->json($response, [
            'status' => 'ok', 'totals' => $this->castNumbers($totals), 'monthly' => $monthly,
            'top_airports' => $topAirports, 'top_airlines' => $topAirlines, 'top_aircraft' => $topAircraft, 'top_routes' => $topRoutes,
        ]);
    }

    public function notifications(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $alerts = $this->buildAlerts();
        $stmt = $this->pdo->prepare('SELECT alert_key,is_read,is_dismissed,updated_at FROM ml_admin_notification_state WHERE admin_user_id=:id');
        $stmt->execute(['id' => $adminId]);
        $states = [];
        foreach ($stmt->fetchAll() as $row) $states[$row['alert_key']] = $row;
        foreach ($alerts as &$alert) {
            $state = $states[$alert['key']] ?? null;
            $alert['is_read'] = (bool) ($state['is_read'] ?? false);
            $alert['is_dismissed'] = (bool) ($state['is_dismissed'] ?? false);
        }
        unset($alert);
        $visible = array_values(array_filter($alerts, fn(array $a) => !$a['is_dismissed']));
        $unread = count(array_filter($visible, fn(array $a) => !$a['is_read']));
        return $this->json($response, ['status' => 'ok', 'unread' => $unread, 'items' => $visible]);
    }

    public function notificationState(Request $request, Response $response, array $args): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $key = trim((string) ($args['key'] ?? ''));
        $data = (array) ($request->getParsedBody() ?: []);
        if ($key === '' || mb_strlen($key, 'UTF-8') > 120) return $this->error($response, 'Niepoprawny alert.', 422);
        $read = !empty($data['is_read']) ? 1 : 0;
        $dismissed = !empty($data['is_dismissed']) ? 1 : 0;
        $stmt = $this->pdo->prepare(
            "INSERT INTO ml_admin_notification_state(admin_user_id,alert_key,is_read,is_dismissed,updated_at)
             VALUES(:admin,:key,:read,:dismissed,NOW())
             ON DUPLICATE KEY UPDATE is_read=VALUES(is_read),is_dismissed=VALUES(is_dismissed),updated_at=NOW()"
        );
        $stmt->execute(['admin' => $adminId, 'key' => $key, 'read' => $read, 'dismissed' => $dismissed]);
        return $this->json($response, ['status' => 'ok']);
    }

    public function globalSearch(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $q = trim((string) ($request->getQueryParams()['q'] ?? ''));
        if (mb_strlen($q, 'UTF-8') < 2) return $this->json($response, ['status' => 'ok', 'groups' => []]);
        $like = '%' . $q . '%';
        $groups = [];

        $groups['users'] = $this->searchRows(
            "SELECT id,nick title,CONCAT(email,' · ID ',id) subtitle FROM ml_users WHERE nick LIKE :a OR email LIKE :b OR CAST(id AS CHAR)=:exact ORDER BY nick LIMIT 8",
            ['a'=>$like,'b'=>$like,'exact'=>$q], 'users'
        );
        $groups['flights'] = $this->searchRows(
            "SELECT f.id,CONCAT(COALESCE(dep.iata_code,'---'),' → ',COALESCE(arr.iata_code,'---')) title,CONCAT(u.nick,' · ',f.departure_date,' · ',COALESCE(f.flight_number,'')) subtitle FROM ml_flights f JOIN ml_users u ON u.id=f.user_id JOIN ml_airports dep ON dep.id=f.departure_airport_id JOIN ml_airports arr ON arr.id=f.arrival_airport_id LEFT JOIN ml_airlines al ON al.id=f.airline_id WHERE f.flight_number LIKE :a OR dep.iata_code LIKE :b OR arr.iata_code LIKE :c OR dep.name LIKE :d OR arr.name LIKE :e OR al.name LIKE :f OR u.nick LIKE :g ORDER BY f.departure_date DESC LIMIT 8",
            ['a'=>$like,'b'=>$like,'c'=>$like,'d'=>$like,'e'=>$like,'f'=>$like,'g'=>$like], 'flights'
        );
        $groups['airports'] = $this->searchRows(
            "SELECT id,CONCAT(COALESCE(iata_code,icao_code,'---'),' · ',name) title,CONCAT(COALESCE(city,''),' · ',COALESCE(country_name,'')) subtitle FROM ml_airports WHERE name LIKE :a OR iata_code LIKE :b OR icao_code LIKE :c OR city LIKE :d ORDER BY name LIMIT 8",
            ['a'=>$like,'b'=>$like,'c'=>$like,'d'=>$like], 'airports'
        );
        $groups['airlines'] = $this->searchRows(
            "SELECT id,name title,CONCAT(COALESCE(iata_code,''),' ',COALESCE(icao_code,''),' · ',COALESCE(country_name,'')) subtitle FROM ml_airlines WHERE name LIKE :a OR iata_code LIKE :b OR icao_code LIKE :c OR callsign LIKE :d ORDER BY name LIMIT 8",
            ['a'=>$like,'b'=>$like,'c'=>$like,'d'=>$like], 'airlines'
        );
        $groups['aircraft'] = $this->searchRows(
            "SELECT id,name title,CONCAT(COALESCE(manufacturer,''),' · ',COALESCE(model,''),' ',COALESCE(variant,'')) subtitle FROM ml_aircraft_types WHERE name LIKE :a OR manufacturer LIKE :b OR model LIKE :c OR variant LIKE :d OR family LIKE :e ORDER BY name LIMIT 8",
            ['a'=>$like,'b'=>$like,'c'=>$like,'d'=>$like,'e'=>$like], 'aircraft'
        );
        $groups['aliases'] = $this->searchRows(
            "SELECT id,alias title,CONCAT(entity_type,' → #',entity_id) subtitle FROM ml_entity_aliases WHERE alias LIKE :a ORDER BY alias LIMIT 8",
            ['a'=>$like], 'aliases'
        );

        $groups = array_filter($groups, fn(array $rows) => count($rows) > 0);
        return $this->json($response, ['status' => 'ok', 'query' => $q, 'groups' => $groups]);
    }

    private function buildAlerts(): array
    {
        $alerts = [];
        $missingTz = (int) $this->pdo->query("SELECT COUNT(*) FROM ml_airports WHERE timezone_name IS NULL OR TRIM(timezone_name)=''")->fetchColumn();
        if ($missingTz > 0) $alerts[] = $this->alert('quality-airport-timezone', 'warning', 'Brak stref czasowych', "{$missingTz} lotnisk nie ma przypisanej strefy czasowej.", 'quality', $missingTz);
        $badDistance = (int) $this->pdo->query("SELECT COUNT(*) FROM ml_flights WHERE departure_airport_id<>arrival_airport_id AND (distance_km IS NULL OR distance_km<=0)")->fetchColumn();
        if ($badDistance > 0) $alerts[] = $this->alert('quality-flight-distance', 'error', 'Niepoprawne dystanse lotów', "{$badDistance} lotów ma brakujący lub zerowy dystans.", 'quality', $badDistance);
        $duplicates = $this->duplicateGroupCount('ml_airports','iata_code') + $this->duplicateGroupCount('ml_airlines','name') + $this->duplicateGroupCount('ml_aircraft_types','name');
        if ($duplicates > 0) $alerts[] = $this->alert('quality-duplicates', 'info', 'Potencjalne duplikaty', "Wykryto co najmniej {$duplicates} grup duplikatów.", 'duplicates', $duplicates);
        if ($this->hasColumn('ml_users', 'locked_until')) {
            $locked = (int) $this->pdo->query("SELECT COUNT(*) FROM ml_users WHERE locked_until IS NOT NULL AND locked_until>NOW()")->fetchColumn();
            if ($locked > 0) $alerts[] = $this->alert('security-locked-users', 'warning', 'Zablokowane konta', "{$locked} kont jest obecnie zablokowanych po nieudanych logowaniach.", 'security', $locked);
        }
        if ($this->hasColumn('ml_users', 'failed_login_attempts')) {
            $failed = (int) $this->pdo->query("SELECT COUNT(*) FROM ml_users WHERE failed_login_attempts>=5")->fetchColumn();
            if ($failed > 0) $alerts[] = $this->alert('security-failed-logins', 'warning', 'Wiele nieudanych logowań', "{$failed} kont ma co najmniej 5 nieudanych prób logowania.", 'security', $failed);
        }
        return $alerts;
    }

    private function qualityCategory(string $key, string $label, string $severity, string $section, string $countSql, string $sampleSql): array
    {
        return [
            'key'=>$key,'label'=>$label,'severity'=>$severity,'section'=>$section,
            'count'=>(int)$this->pdo->query($countSql)->fetchColumn(),
            'samples'=>$this->pdo->query($sampleSql)->fetchAll(),
        ];
    }

    private function mergeConfig(): array
    {
        return [
            'airports' => ['table'=>'ml_airports','entity'=>'airport','flight_refs'=>['departure_airport_id','arrival_airport_id'],'known_refs'=>[['ml_flights','departure_airport_id'],['ml_flights','arrival_airport_id']]],
            'airlines' => ['table'=>'ml_airlines','entity'=>'airline','flight_refs'=>['airline_id'],'known_refs'=>[['ml_flights','airline_id']]],
            'aircraft' => ['table'=>'ml_aircraft_types','entity'=>'aircraft_type','flight_refs'=>['aircraft_type_id'],'known_refs'=>[['ml_flights','aircraft_type_id']]],
        ];
    }

    private function usageCount(string $type, int $id): int
    {
        if ($type === 'airports') {
            $s=$this->pdo->prepare('SELECT COUNT(*) FROM ml_flights WHERE departure_airport_id=:id1 OR arrival_airport_id=:id2');$s->execute(['id1'=>$id,'id2'=>$id]);return(int)$s->fetchColumn();
        }
        $column = $type === 'airlines' ? 'airline_id' : 'aircraft_type_id';
        $s=$this->pdo->prepare("SELECT COUNT(*) FROM ml_flights WHERE {$column}=:id");$s->execute(['id'=>$id]);return(int)$s->fetchColumn();
    }

    private function aliasCount(string $entity, int $id): int
    {
        $s=$this->pdo->prepare('SELECT COUNT(*) FROM ml_entity_aliases WHERE entity_type=:type AND entity_id=:id');$s->execute(['type'=>$entity,'id'=>$id]);return(int)$s->fetchColumn();
    }

    private function fetchByIds(string $table, array $ids): array
    {
        if (!$ids) return [];
        $ph=implode(',',array_fill(0,count($ids),'?'));$s=$this->pdo->prepare("SELECT * FROM {$table} WHERE id IN ({$ph})");$s->execute($ids);$out=[];foreach($s->fetchAll() as$r)$out[(int)$r['id']]=$r;return$out;
    }

    private function aliasCandidates(string $type, array $row): array
    {
        $fields = $type === 'airports' ? ['name','iata_code','icao_code'] : ($type === 'airlines' ? ['name','iata_code','icao_code','callsign'] : ['name','manufacturer','model','variant','family']);
        $out=[];foreach($fields as$f){$v=trim((string)($row[$f]??''));if($v!==''&&mb_strlen($v,'UTF-8')>=2)$out[]=$v;}return array_values(array_unique($out));
    }

    private function insertAliasIgnore(string $entity, int $targetId, string $alias, int $adminId, string $source): void
    {
        $normalized=$this->normalizeAlias($alias);
        $sql="INSERT IGNORE INTO ml_entity_aliases(entity_type,entity_id,alias,alias_normalized,created_by,updated_by,source,usage_count) VALUES(:type,:id,:alias,:normalized,:admin,:updated_by,:source,0)";
        $s=$this->pdo->prepare($sql);$s->execute(['type'=>$entity,'id'=>$targetId,'alias'=>$alias,'normalized'=>$normalized,'admin'=>$adminId,'updated_by'=>$adminId,'source'=>$source]);
    }

    private function unknownReferenceBlockers(string $table, array $ids, array $knownRefs): array
    {
        $known=[];foreach($knownRefs as[$t,$c])$known[$t.'.'.$c]=true;
        $s=$this->pdo->prepare("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=:table");$s->execute(['table'=>$table]);
        $blockers=[];$ph=implode(',',array_fill(0,count($ids),'?'));
        foreach($s->fetchAll() as$r){$key=$r['TABLE_NAME'].'.'.$r['COLUMN_NAME'];if(isset($known[$key]))continue;$count=$this->pdo->prepare("SELECT COUNT(*) FROM `{$r['TABLE_NAME']}` WHERE `{$r['COLUMN_NAME']}` IN ({$ph})");$count->execute($ids);$n=(int)$count->fetchColumn();if($n>0)$blockers[]=['table'=>$r['TABLE_NAME'],'column'=>$r['COLUMN_NAME'],'count'=>$n];}
        return$blockers;
    }

    private function duplicateGroupCount(string $table, string $column): int
    {
        if (!$this->hasColumn($table,$column)) return 0;
        $sql="SELECT COUNT(*) FROM (SELECT 1 FROM {$table} WHERE {$column} IS NOT NULL AND TRIM({$column})<>'' GROUP BY LOWER(TRIM({$column})) HAVING COUNT(*)>1) x";
        return(int)$this->pdo->query($sql)->fetchColumn();
    }

    private function searchRows(string $sql, array $params, string $section): array
    {
        $s=$this->pdo->prepare($sql);$s->execute($params);$rows=[];foreach($s->fetchAll()as$r)$rows[]=['id'=>(int)$r['id'],'title'=>(string)$r['title'],'subtitle'=>(string)($r['subtitle']??''),'section'=>$section];return$rows;
    }

    private function alert(string $key,string $severity,string $title,string $message,string $section,int $count):array{return compact('key','severity','title','message','section','count');}
    private function normalizeAlias(string $v):string{$v=mb_strtolower(trim($v),'UTF-8');return preg_replace('/\s+/u',' ',$v)??$v;}
    private function parseIds(string $value):array{return array_values(array_unique(array_filter(array_map('intval',explode(',',$value)),fn(int$id)=>$id>0)));}
    private function decodeJson(mixed $v):array{if(!is_string($v)||$v==='')return[];$d=json_decode($v,true);return is_array($d)?$d:[];}
    private function diff(array $before,array $after):array{$keys=array_unique(array_merge(array_keys($before),array_keys($after)));$out=[];foreach($keys as$k){$a=$before[$k]??null;$b=$after[$k]??null;if($a!=$b)$out[]=['field'=>$k,'before'=>$a,'after'=>$b];}return$out;}
    private function castNumbers(array $r):array{foreach($r as$k=>$v)if(is_numeric($v))$r[$k]=(int)$v;return$r;}
    private function columns(string $table):array{$s=$this->pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table");$s->execute(['table'=>$table]);$out=[];foreach($s->fetchAll()as$r)$out[$r['COLUMN_NAME']]=true;return$out;}
    private function hasColumn(string $table,string $column):bool{$c=$this->columns($table);return isset($c[$column]);}
    private function ip(Request $r):?string{$s=$r->getServerParams();return isset($s['REMOTE_ADDR'])?(string)$s['REMOTE_ADDR']:null;}
    private function requireAdmin(Response $r):int|Response{try{$id=$this->auth->requireUserId();}catch(\RuntimeException){return$this->error($r,'Musisz się zalogować.',401);}$s=$this->pdo->prepare('SELECT is_admin,is_active FROM ml_users WHERE id=:id');$s->execute(['id'=>$id]);$u=$s->fetch();if(!$u||!(bool)$u['is_admin']||!(bool)$u['is_active'])return$this->error($r,'Brak uprawnień administratora.',403);return$id;}
    private function json(Response $r,array$d,int$s=200):Response{$r->getBody()->write(json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return$r->withStatus($s)->withHeader('Content-Type','application/json; charset=utf-8');}
    private function error(Response $r,string$m,int$s):Response{return$this->json($r,['status'=>'error','message'=>$m],$s);}
}
