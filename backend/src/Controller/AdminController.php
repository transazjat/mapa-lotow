<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;
use Transazja\MapaLotowApi\Security\AuthService;
use Transazja\MapaLotowApi\Service\AdminAuditService;
use Transazja\MapaLotowApi\Service\SmtpMailer;

final class AdminController
{
    public function __construct(
        private PDO $pdo,
        private AuthService $auth,
        private SmtpMailer $mailer,
        private string $appUrl,
        private AdminAuditService $audit
    ) {
    }

    public function dashboard(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) {
            return $adminId;
        }

        $users = $this->pdo->query(
            "SELECT COUNT(*) total,
                    SUM(is_active=1) active,
                    SUM(is_active=0) inactive,
                    SUM(email_verified_at IS NOT NULL) verified,
                    SUM(email_verified_at IS NULL) unverified,
                    SUM(is_admin=1) admins,
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) new_7,
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) new_30
             FROM ml_users"
        )->fetch();

        $flights = $this->pdo->query(
            "SELECT COUNT(*) total,
                    SUM(departure_date <= CURDATE()) completed,
                    SUM(departure_date > CURDATE()) planned,
                    COALESCE(SUM(distance_km),0) distance_km,
                    COALESCE(SUM(duration_seconds),0) duration_seconds,
                    COUNT(DISTINCT user_id) users_with_flights
             FROM ml_flights"
        )->fetch();

        $catalogs = [
            'airports' => (int) $this->pdo->query('SELECT COUNT(*) FROM ml_airports')->fetchColumn(),
            'airlines' => (int) $this->pdo->query('SELECT COUNT(*) FROM ml_airlines')->fetchColumn(),
            'aircraft_types' => (int) $this->pdo->query('SELECT COUNT(*) FROM ml_aircraft_types')->fetchColumn(),
            'countries' => (int) $this->pdo->query('SELECT COUNT(*) FROM ml_countries')->fetchColumn(),
        ];

        $recentUsers = $this->pdo->query(
            "SELECT id,nick,email,is_active,email_verified_at,created_at,last_login_at
             FROM ml_users ORDER BY created_at DESC,id DESC LIMIT 8"
        )->fetchAll();

        $recentFlights = $this->pdo->query(
            "SELECT f.id,f.user_id,f.departure_date,f.departure_time,f.flight_number,
                    u.nick user_nick,dep.iata_code departure_iata,arr.iata_code arrival_iata,
                    al.name airline_name
             FROM ml_flights f
             JOIN ml_users u ON u.id=f.user_id
             JOIN ml_airports dep ON dep.id=f.departure_airport_id
             JOIN ml_airports arr ON arr.id=f.arrival_airport_id
             LEFT JOIN ml_airlines al ON al.id=f.airline_id
             ORDER BY f.id DESC LIMIT 8"
        )->fetchAll();

        return $this->json($response, [
            'status' => 'ok',
            'users' => $this->castNumbers($users ?: []),
            'flights' => $this->castNumbers($flights ?: []),
            'catalogs' => $catalogs,
            'recent_users' => $recentUsers,
            'recent_flights' => $recentFlights,
        ]);
    }

    public function users(Request $request, Response $response): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) {
            return $adminId;
        }

        $query = $request->getQueryParams();
        $q = trim((string) ($query['q'] ?? ''));
        $status = trim((string) ($query['status'] ?? 'all'));
        $sort = (string) ($query['sort'] ?? 'created_desc');
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = $this->perPage($query['per_page'] ?? 25);

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.nick LIKE :q_nick OR u.email LIKE :q_email OR CAST(u.id AS CHAR)=:exact_id)';
            $like = '%' . $q . '%';
            $params['q_nick'] = $like;
            $params['q_email'] = $like;
            $params['exact_id'] = $q;
        }
        $statusMap = [
            'active' => 'u.is_active=1',
            'inactive' => 'u.is_active=0',
            'verified' => 'u.email_verified_at IS NOT NULL',
            'unverified' => 'u.email_verified_at IS NULL',
            'admin' => 'u.is_admin=1',
        ];
        if (isset($statusMap[$status])) {
            $where[] = $statusMap[$status];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderMap = [
            'id_asc' => 'u.id ASC',
            'id_desc' => 'u.id DESC',
            'nick_asc' => 'u.nick ASC,u.id ASC',
            'nick_desc' => 'u.nick DESC,u.id DESC',
            'email_asc' => 'u.email ASC,u.id ASC',
            'email_desc' => 'u.email DESC,u.id DESC',
            'status_asc' => 'u.is_active ASC,u.is_admin ASC,u.id ASC',
            'status_desc' => 'u.is_active DESC,u.is_admin DESC,u.id DESC',
            'flights_asc' => 'flights_count ASC,u.id ASC',
            'flights_desc' => 'flights_count DESC,u.id DESC',
            'distance_asc' => 'distance_km ASC,u.id ASC',
            'distance_desc' => 'distance_km DESC,u.id DESC',
            'created_asc' => 'u.created_at ASC,u.id ASC',
            'created_desc' => 'u.created_at DESC,u.id DESC',
            'login_asc' => 'u.last_login_at ASC,u.id ASC',
            'login_desc' => 'u.last_login_at DESC,u.id DESC',
        ];
        $order = $orderMap[$sort] ?? $orderMap['created_desc'];

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM ml_users u {$whereSql}");
        $this->bindAll($count, $params);
        $count->execute();
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT u.id,u.nick,u.email,u.is_active,u.is_admin,u.email_verified_at,
                    u.privacy_mode,u.created_at,u.last_login_at,
                    COUNT(f.id) flights_count,
                    COALESCE(SUM(f.distance_km),0) distance_km,
                    COALESCE(SUM(f.duration_seconds),0) duration_seconds
             FROM ml_users u LEFT JOIN ml_flights f ON f.user_id=u.id
             {$whereSql}
             GROUP BY u.id
             ORDER BY {$order}
             LIMIT :limit OFFSET :offset"
        );
        $this->bindAll($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->json($response, [
            'status' => 'ok', 'page' => $page, 'per_page' => $perPage,
            'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)),
            'users' => $stmt->fetchAll(),
        ]);
    }

    public function user(Request $request, Response $response, array $args): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) {
            return $adminId;
        }
        $userId = (int) ($args['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            "SELECT u.id,u.nick,u.email,u.is_active,u.is_admin,u.email_verified_at,u.privacy_mode,
                    u.share_token,u.public_slug,u.created_at,u.updated_at,u.last_login_at,u.session_version,
                    COUNT(f.id) flights_count,
                    SUM(f.departure_date>CURDATE()) planned_count,
                    SUM(f.departure_date<=CURDATE()) completed_count,
                    COALESCE(SUM(f.distance_km),0) distance_km,
                    COALESCE(SUM(f.duration_seconds),0) duration_seconds,
                    COUNT(DISTINCT f.departure_airport_id) + COUNT(DISTINCT f.arrival_airport_id) airport_mentions
             FROM ml_users u LEFT JOIN ml_flights f ON f.user_id=u.id
             WHERE u.id=:id GROUP BY u.id LIMIT 1"
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return $this->error($response, 'Nie znaleziono użytkownika.', 404);
        }

        $stats = $this->pdo->prepare(
            "SELECT
               (SELECT COUNT(DISTINCT x.airport_id) FROM (
                  SELECT departure_airport_id airport_id FROM ml_flights WHERE user_id=:id1
                  UNION SELECT arrival_airport_id airport_id FROM ml_flights WHERE user_id=:id2
                ) x) unique_airports,
               (SELECT COUNT(DISTINCT country_id) FROM (
                  SELECT a.country_id FROM ml_flights f JOIN ml_airports a ON a.id=f.departure_airport_id WHERE f.user_id=:id3 AND a.country_id IS NOT NULL
                  UNION SELECT a.country_id FROM ml_flights f JOIN ml_airports a ON a.id=f.arrival_airport_id WHERE f.user_id=:id4 AND a.country_id IS NOT NULL
                ) c) unique_countries,
               COUNT(DISTINCT airline_id) unique_airlines,
               COUNT(DISTINCT aircraft_type_id) unique_aircraft_types,
               MAX(departure_date) last_flight_date
             FROM ml_flights WHERE user_id=:id5"
        );
        $stats->execute(['id1'=>$userId,'id2'=>$userId,'id3'=>$userId,'id4'=>$userId,'id5'=>$userId]);

        $recent = $this->pdo->prepare(
            "SELECT f.id,f.user_id,f.departure_date,f.departure_time,f.flight_number,
                    u.nick user_nick,dep.iata_code departure_iata,arr.iata_code arrival_iata,al.name airline_name
             FROM ml_flights f JOIN ml_users u ON u.id=f.user_id
             JOIN ml_airports dep ON dep.id=f.departure_airport_id
             JOIN ml_airports arr ON arr.id=f.arrival_airport_id
             LEFT JOIN ml_airlines al ON al.id=f.airline_id
             WHERE f.user_id=:id ORDER BY f.departure_date DESC,f.id DESC LIMIT 10"
        );
        $recent->execute(['id'=>$userId]);

        return $this->json($response, [
            'status'=>'ok', 'user'=>$user, 'stats'=>$stats->fetch() ?: [],
            'recent_flights'=>$recent->fetchAll(), 'is_current_admin'=>$userId===$adminId,
        ]);
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $adminId = $this->requireAdmin($response);
        if ($adminId instanceof Response) return $adminId;
        $userId = (int) ($args['id'] ?? 0);
        $before = $this->findUser($userId);
        if (!$before) return $this->error($response, 'Nie znaleziono użytkownika.', 404);
        $data = (array) ($request->getParsedBody() ?: []);

        $nick = array_key_exists('nick', $data) ? trim((string)$data['nick']) : (string)$before['nick'];
        $email = array_key_exists('email', $data) ? mb_strtolower(trim((string)$data['email']), 'UTF-8') : (string)$before['email'];
        $isActive = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : (bool)$before['is_active'];
        $isAdmin = array_key_exists('is_admin', $data) ? (bool)$data['is_admin'] : (bool)$before['is_admin'];
        $verified = array_key_exists('email_verified', $data)
            ? (bool)$data['email_verified']
            : !empty($before['email_verified_at']);

        if (mb_strlen($nick,'UTF-8') < 2 || mb_strlen($nick,'UTF-8') > 60) {
            return $this->error($response, 'Nick musi mieć od 2 do 60 znaków.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error($response, 'Podaj poprawny adres e-mail.', 422);
        }
        if ($userId === $adminId && !$isActive) return $this->error($response, 'Nie możesz dezaktywować własnego konta administratora.', 422);
        if ($userId === $adminId && !$isAdmin) return $this->error($response, 'Nie możesz odebrać sobie uprawnień administratora.', 422);

        $check = $this->pdo->prepare('SELECT id FROM ml_users WHERE (LOWER(nick)=LOWER(:nick) OR LOWER(email)=LOWER(:email)) AND id<>:id LIMIT 1');
        $check->execute(['nick'=>$nick,'email'=>$email,'id'=>$userId]);
        if ($check->fetch()) return $this->error($response, 'Nick lub adres e-mail jest już używany przez inne konto.', 409);

        $slug = $before['public_slug'];
        if ($slug && $nick !== $before['nick']) $slug = $this->uniqueSlug($nick, $userId);
        $securityChanged = $isActive !== (bool)$before['is_active'] || $email !== mb_strtolower((string)$before['email'],'UTF-8');

        $stmt = $this->pdo->prepare(
            "UPDATE ml_users SET nick=:nick,email=:email,is_active=:active,is_admin=:admin,
                email_verified_at=CASE WHEN :verified=1 THEN COALESCE(email_verified_at,NOW()) ELSE NULL END,
                public_slug=:slug,
                session_version=session_version + :session_bump
             WHERE id=:id"
        );
        $stmt->execute([
            'nick'=>$nick,'email'=>$email,'active'=>$isActive?1:0,'admin'=>$isAdmin?1:0,
            'verified'=>$verified?1:0,'slug'=>$slug,'session_bump'=>$securityChanged?1:0,'id'=>$userId,
        ]);
        $after = $this->findUser($userId);
        $this->audit->log($adminId,'user.update','user',$userId,'Zmieniono dane użytkownika',$this->auditUser($before),$this->auditUser($after ?: []),$this->ip($request));
        return $this->json($response,['status'=>'ok','message'=>'Dane użytkownika zostały zapisane.']);
    }

    public function resetUserSessions(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response); if($adminId instanceof Response)return $adminId;
        $userId=(int)($args['id']??0); $before=$this->findUser($userId);
        if(!$before)return $this->error($response,'Nie znaleziono użytkownika.',404);
        $this->pdo->prepare('UPDATE ml_users SET session_version=session_version+1 WHERE id=:id')->execute(['id'=>$userId]);
        $this->audit->log($adminId,'user.sessions.reset','user',$userId,'Unieważniono wszystkie sesje użytkownika',null,null,$this->ip($request));
        return $this->json($response,['status'=>'ok','message'=>'Wszystkie sesje użytkownika zostały unieważnione.']);
    }

    public function resendActivation(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response); if($adminId instanceof Response)return $adminId;
        $userId=(int)($args['id']??0); $user=$this->findUser($userId);
        if(!$user)return $this->error($response,'Nie znaleziono użytkownika.',404);
        if(!empty($user['email_verified_at']))return $this->error($response,'Ten adres e-mail jest już zweryfikowany.',422);
        $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token);
        $this->pdo->prepare("UPDATE ml_users SET email_verification_token_hash=:hash,email_verification_expires_at=DATE_ADD(NOW(),INTERVAL 24 HOUR) WHERE id=:id")
            ->execute(['hash'=>$hash,'id'=>$userId]);
        $url=$this->appUrl.'/?konto=aktywacja&token='.rawurlencode($token);
        $this->mailer->send((string)$user['email'],'Aktywuj konto w Mapie Lotów',$this->simpleMail('Aktywuj konto','Administrator ponownie wysłał link aktywacyjny do Twojego konta.','Aktywuj konto',$url,'Link jest ważny przez 24 godziny.'));
        $this->audit->log($adminId,'user.activation.resend','user',$userId,'Ponownie wysłano link aktywacyjny',null,null,$this->ip($request));
        return $this->json($response,['status'=>'ok','message'=>'Link aktywacyjny został wysłany.']);
    }

    public function createResetLink(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response); if($adminId instanceof Response)return $adminId;
        $userId=(int)($args['id']??0); $user=$this->findUser($userId);
        if(!$user)return $this->error($response,'Nie znaleziono użytkownika.',404);
        $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token);
        $this->pdo->prepare("UPDATE ml_users SET password_reset_token_hash=:hash,password_reset_expires_at=DATE_ADD(NOW(),INTERVAL 60 MINUTE) WHERE id=:id")
            ->execute(['hash'=>$hash,'id'=>$userId]);
        $url=$this->appUrl.'/?konto=reset&token='.rawurlencode($token);
        $this->audit->log($adminId,'user.password.reset_link','user',$userId,'Wygenerowano administracyjny link resetu hasła',null,null,$this->ip($request));
        return $this->json($response,['status'=>'ok','message'=>'Link resetu jest ważny przez 60 minut.','reset_url'=>$url]);
    }

    public function userPreview(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response); if($adminId instanceof Response)return $adminId;
        $userId=(int)($args['id']??0); $user=$this->findUser($userId);
        if(!$user)return $this->error($response,'Nie znaleziono użytkownika.',404);
        $stmt=$this->pdo->prepare($this->flightSelectSql().' WHERE f.user_id=:user_id ORDER BY f.departure_date DESC,f.departure_time DESC,f.id DESC');
        $stmt->execute(['user_id'=>$userId]);
        return $this->json($response,['status'=>'ok','read_only'=>true,'user'=>[
            'id'=>(int)$user['id'],'nick'=>$user['nick'],'privacy_mode'=>$user['privacy_mode'],'public_slug'=>$user['public_slug']
        ],'flights'=>$stmt->fetchAll()]);
    }

    public function flights(Request $request, Response $response): Response
    {
        $adminId=$this->requireAdmin($response); if($adminId instanceof Response)return $adminId;
        $q=$request->getQueryParams();
        $search=trim((string)($q['q']??'')); $scope=(string)($q['scope']??'all');
        $userId=max(0,(int)($q['user_id']??0)); $dateFrom=trim((string)($q['date_from']??'')); $dateTo=trim((string)($q['date_to']??''));
        $sort=(string)($q['sort']??'date_desc'); $page=max(1,(int)($q['page']??1)); $perPage=$this->perPage($q['per_page']??25);
        $where=[];$params=[];
        if($search!==''){
            $like='%'.$search.'%';
            $where[]='(u.nick LIKE :q_user_nick OR u.email LIKE :q_user_email OR f.flight_number LIKE :q_flight_number OR dep.iata_code LIKE :q_dep_iata OR arr.iata_code LIKE :q_arr_iata OR dep.name LIKE :q_dep_name OR arr.name LIKE :q_arr_name OR al.name LIKE :q_airline OR ac.name LIKE :q_aircraft)';
            foreach(['q_user_nick','q_user_email','q_flight_number','q_dep_iata','q_arr_iata','q_dep_name','q_arr_name','q_airline','q_aircraft'] as $key)$params[$key]=$like;
        }
        if($scope==='completed')$where[]='f.departure_date<=CURDATE()'; elseif($scope==='planned')$where[]='f.departure_date>CURDATE()';
        if($userId>0){$where[]='f.user_id=:user_id';$params['user_id']=$userId;}
        if($dateFrom!==''){$where[]='f.departure_date>=:date_from';$params['date_from']=$dateFrom;}
        if($dateTo!==''){$where[]='f.departure_date<=:date_to';$params['date_to']=$dateTo;}
        $whereSql=$where?'WHERE '.implode(' AND ',$where):'';
        $from='FROM ml_flights f JOIN ml_users u ON u.id=f.user_id JOIN ml_airports dep ON dep.id=f.departure_airport_id JOIN ml_airports arr ON arr.id=f.arrival_airport_id LEFT JOIN ml_airlines al ON al.id=f.airline_id LEFT JOIN ml_aircraft_types ac ON ac.id=f.aircraft_type_id';
        $count=$this->pdo->prepare("SELECT COUNT(*) {$from} {$whereSql}");$this->bindAll($count,$params);$count->execute();$total=(int)$count->fetchColumn();
        $orders=[
            'id_asc'=>'f.id ASC','id_desc'=>'f.id DESC',
            'user_asc'=>'u.nick ASC,f.id ASC','user_desc'=>'u.nick DESC,f.id DESC',
            'date_asc'=>'f.departure_date ASC,f.departure_time ASC,f.id ASC','date_desc'=>'f.departure_date DESC,f.departure_time DESC,f.id DESC',
            'route_asc'=>'dep.iata_code ASC,arr.iata_code ASC,f.departure_date DESC','route_desc'=>'dep.iata_code DESC,arr.iata_code DESC,f.departure_date DESC',
            'flight_number_asc'=>'f.flight_number ASC,f.id ASC','flight_number_desc'=>'f.flight_number DESC,f.id DESC',
            'airline_asc'=>'al.name ASC,f.id ASC','airline_desc'=>'al.name DESC,f.id DESC',
            'aircraft_asc'=>'ac.name ASC,f.id ASC','aircraft_desc'=>'ac.name DESC,f.id DESC',
            'distance_asc'=>'f.distance_km ASC,f.id ASC','distance_desc'=>'f.distance_km DESC,f.id DESC'
        ];$order=$orders[$sort]??$orders['date_desc'];
        $offset=($page-1)*$perPage;
        $stmt=$this->pdo->prepare("SELECT f.id,f.user_id,f.departure_date,f.departure_time,f.arrival_date,f.arrival_time,f.flight_number,f.distance_km,f.duration_seconds,f.travel_class,f.seat_type,f.travel_reason,f.notes,u.nick user_nick,u.email user_email,dep.iata_code departure_iata,dep.name departure_airport,dep.city departure_city,arr.iata_code arrival_iata,arr.name arrival_airport,arr.city arrival_city,al.name airline_name,al.iata_code airline_iata,ac.name aircraft_name {$from} {$whereSql} ORDER BY {$order} LIMIT :limit OFFSET :offset");
        $this->bindAll($stmt,$params);$stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();
        return $this->json($response,['status'=>'ok','page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>max(1,(int)ceil($total/$perPage)),'flights'=>$stmt->fetchAll()]);
    }

    public function flight(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return $adminId;
        $flight=$this->findFlight((int)($args['id']??0));
        if(!$flight)return $this->error($response,'Nie znaleziono lotu.',404);
        return $this->json($response,['status'=>'ok','flight'=>$flight]);
    }

    public function updateFlight(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return $adminId;
        $id=(int)($args['id']??0);$before=$this->findFlight($id);if(!$before)return $this->error($response,'Nie znaleziono lotu.',404);
        $d=(array)($request->getParsedBody()?:[]);
        $allowed=['user_id','departure_airport_id','arrival_airport_id','airline_id','aircraft_type_id','departure_date','departure_time','arrival_date','arrival_time','flight_number','travel_class','seat_type','seat_number','travel_reason','aircraft_registration','notes'];
        $v=[];foreach($allowed as $k)$v[$k]=array_key_exists($k,$d)?$d[$k]:$before[$k];
        foreach(['user_id','departure_airport_id','arrival_airport_id'] as $k){$v[$k]=(int)$v[$k];if($v[$k]<=0)return $this->error($response,"Niepoprawna wartość {$k}.",422);}
        foreach(['airline_id','aircraft_type_id'] as $k)$v[$k]=($v[$k]===null||$v[$k]==='')?null:(int)$v[$k];
        $v['departure_date']=trim((string)$v['departure_date']); if(!$this->isDate($v['departure_date']))return $this->error($response,'Niepoprawna data wylotu.',422);
        $v['arrival_date']=$this->nullableString($v['arrival_date']); if($v['arrival_date']!==null&&!$this->isDate($v['arrival_date']))return $this->error($response,'Niepoprawna data przylotu.',422);
        $v['departure_time']=$this->normalizeTime($v['departure_time']);$v['arrival_time']=$this->normalizeTime($v['arrival_time']);
        foreach(['flight_number','travel_class','seat_type','seat_number','travel_reason','aircraft_registration','notes'] as $k)$v[$k]=$this->nullableString($v[$k]);

        $allowedTravelClasses=[null,'economy','premium_economy','business','first'];
        $allowedSeatTypes=[null,'window','middle','aisle'];
        $travelReasonAliases=[
            'p'=>'p','private'=>'p','personal'=>'p','prywatny'=>'p',
            'b'=>'b','business'=>'b','biznesowy'=>'b',
        ];
        if($v['travel_reason']!==null){
            $travelReasonKey=mb_strtolower(trim((string)$v['travel_reason']),'UTF-8');
            if(isset($travelReasonAliases[$travelReasonKey]))$v['travel_reason']=$travelReasonAliases[$travelReasonKey];
        }
        $allowedTravelReasons=['p','b'];
        if(($v['travel_class']??null)!==($before['travel_class']??null) && !in_array($v['travel_class'],$allowedTravelClasses,true))return $this->error($response,'Niepoprawna klasa podróży.',422);
        if(($v['seat_type']??null)!==($before['seat_type']??null) && !in_array($v['seat_type'],$allowedSeatTypes,true))return $this->error($response,'Niepoprawny typ miejsca.',422);
        if(($v['travel_reason']??null)!==($before['travel_reason']??null) && !in_array($v['travel_reason'],$allowedTravelReasons,true))return $this->error($response,'Niepoprawny cel podróży.',422);
        if($v['flight_number']!==null && mb_strlen($v['flight_number'],'UTF-8')>20)return $this->error($response,'Numer lotu jest zbyt długi.',422);
        if($v['seat_number']!==null && mb_strlen($v['seat_number'],'UTF-8')>10)return $this->error($response,'Numer miejsca jest zbyt długi.',422);
        if($v['aircraft_registration']!==null && mb_strlen($v['aircraft_registration'],'UTF-8')>24)return $this->error($response,'Rejestracja samolotu jest zbyt długa.',422);

        if(!$this->exists('ml_users',$v['user_id'])||!$this->exists('ml_airports',$v['departure_airport_id'])||!$this->exists('ml_airports',$v['arrival_airport_id']))return $this->error($response,'Użytkownik lub lotnisko nie istnieje.',422);
        if($v['airline_id']!==null&&!$this->exists('ml_airlines',$v['airline_id']))return $this->error($response,'Linia lotnicza nie istnieje.',422);
        if($v['aircraft_type_id']!==null&&!$this->exists('ml_aircraft_types',$v['aircraft_type_id']))return $this->error($response,'Typ samolotu nie istnieje.',422);

        $distance=(int)$before['distance_km'];$duration=$before['duration_seconds']===null?null:(int)$before['duration_seconds'];
        if((int)$before['departure_airport_id']!==$v['departure_airport_id']||(int)$before['arrival_airport_id']!==$v['arrival_airport_id'])$distance=$this->distanceForAirports($v['departure_airport_id'],$v['arrival_airport_id']);
        $timeKeys=['departure_date','departure_time','arrival_date','arrival_time'];$timeChanged=false;foreach($timeKeys as $k){if(($before[$k]??null)!==($v[$k]??null)){$timeChanged=true;break;}}
        if($timeChanged)$duration=$this->durationForValues($v['departure_date'],$v['departure_time'],$v['departure_airport_id'],$v['arrival_date'],$v['arrival_time'],$v['arrival_airport_id']);

        $stmt=$this->pdo->prepare("UPDATE ml_flights SET user_id=:user_id,departure_airport_id=:departure_airport_id,arrival_airport_id=:arrival_airport_id,airline_id=:airline_id,aircraft_type_id=:aircraft_type_id,departure_date=:departure_date,departure_time=:departure_time,arrival_date=:arrival_date,arrival_time=:arrival_time,flight_number=:flight_number,distance_km=:distance_km,duration_seconds=:duration_seconds,travel_class=:travel_class,seat_type=:seat_type,seat_number=:seat_number,travel_reason=:travel_reason,aircraft_registration=:aircraft_registration,notes=:notes WHERE id=:id");
        $stmt->execute($v+['distance_km'=>$distance,'duration_seconds'=>$duration,'id'=>$id]);
        $after=$this->findFlight($id);$this->audit->log($adminId,'flight.update','flight',$id,'Edytowano lot',$this->auditFlight($before),$this->auditFlight($after?:[]),$this->ip($request));
        return $this->json($response,['status'=>'ok','message'=>'Lot został zapisany.','flight'=>$after]);
    }

    public function deleteFlight(Request $request, Response $response, array $args): Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return $adminId;
        $id=(int)($args['id']??0);$before=$this->findFlight($id);if(!$before)return $this->error($response,'Nie znaleziono lotu.',404);
        $this->pdo->prepare('DELETE FROM ml_flights WHERE id=:id')->execute(['id'=>$id]);
        $this->audit->log($adminId,'flight.delete','flight',$id,'Usunięto lot',$this->auditFlight($before),null,$this->ip($request));
        return $this->json($response,['status'=>'ok','message'=>'Lot został usunięty.']);
    }

    public function lookupUsers(Request $request, Response $response): Response { return $this->lookup($request,$response,'ml_users','CONCAT(nick,\' · \',email)','nick,email',false); }
    public function lookupAirports(Request $request, Response $response): Response { return $this->lookup($request,$response,'ml_airports',"CONCAT(COALESCE(NULLIF(iata_code,''),icao_code,'---'),' · ',name,' · ',COALESCE(city,''))",'iata_code,icao_code,name,city',true); }
    public function lookupAirlines(Request $request, Response $response): Response { return $this->lookup($request,$response,'ml_airlines',"CONCAT(name,' · ',COALESCE(iata_code,''),' ',COALESCE(icao_code,''))",'name,iata_code,icao_code',true); }
    public function lookupAircraft(Request $request, Response $response): Response { return $this->lookup($request,$response,'ml_aircraft_types',"CONCAT(name,' · ',COALESCE(manufacturer,''),' ',COALESCE(model,''),' ',COALESCE(variant,''))",'name,manufacturer,model,variant',true); }

    private function lookup(Request $request,Response $response,string $table,string $labelExpr,string $columns,bool $aliases):Response
    {
        $adminId=$this->requireAdmin($response);if($adminId instanceof Response)return $adminId;
        $q=trim((string)($request->getQueryParams()['q']??''));if(mb_strlen($q,'UTF-8')<1)return $this->json($response,['status'=>'ok','items'=>[]]);
        $ors=[];$params=[];$like='%'.$q.'%';
        foreach(explode(',',$columns) as $i=>$c){$key='lookup_'.$i;$ors[]="{$c} LIKE :{$key}";$params[$key]=$like;}
        $sql="SELECT id,{$labelExpr} label FROM {$table} WHERE (".implode(' OR ',$ors).") ORDER BY label LIMIT 20";
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $this->json($response,['status'=>'ok','items'=>$stmt->fetchAll()]);
    }

    private function flightSelectSql():string
    {
        return "SELECT f.*,u.nick user_nick,u.email user_email,
          dep.iata_code departure_iata,dep.icao_code departure_icao,dep.name departure_airport,dep.city departure_city,dep.latitude departure_latitude,dep.longitude departure_longitude,
          arr.iata_code arrival_iata,arr.icao_code arrival_icao,arr.name arrival_airport,arr.city arrival_city,arr.latitude arrival_latitude,arr.longitude arrival_longitude,
          al.name airline_name,al.iata_code airline_iata,al.icao_code airline_icao,
          ac.name aircraft_name,ac.manufacturer aircraft_manufacturer,ac.model aircraft_model,ac.variant aircraft_variant,ac.family aircraft_family
          FROM ml_flights f JOIN ml_users u ON u.id=f.user_id JOIN ml_airports dep ON dep.id=f.departure_airport_id JOIN ml_airports arr ON arr.id=f.arrival_airport_id LEFT JOIN ml_airlines al ON al.id=f.airline_id LEFT JOIN ml_aircraft_types ac ON ac.id=f.aircraft_type_id";
    }
    private function findFlight(int $id):array|false{$s=$this->pdo->prepare($this->flightSelectSql().' WHERE f.id=:id LIMIT 1');$s->execute(['id'=>$id]);return $s->fetch();}
    private function findUser(int $id):array|false{$s=$this->pdo->prepare('SELECT * FROM ml_users WHERE id=:id LIMIT 1');$s->execute(['id'=>$id]);return $s->fetch();}
    private function exists(string $table,int $id):bool{if(!in_array($table,['ml_users','ml_airports','ml_airlines','ml_aircraft_types'],true))return false;$s=$this->pdo->prepare("SELECT 1 FROM {$table} WHERE id=:id");$s->execute(['id'=>$id]);return(bool)$s->fetchColumn();}
    private function distanceForAirports(int $dep,int $arr):int{$s=$this->pdo->prepare('SELECT id,latitude,longitude FROM ml_airports WHERE id IN (:dep,:arr)');$s->execute(['dep'=>$dep,'arr'=>$arr]);$r=[];foreach($s->fetchAll() as $x)$r[(int)$x['id']]=$x;if(!isset($r[$dep],$r[$arr]))throw new \RuntimeException('Nie znaleziono lotniska.');$lat1=deg2rad((float)$r[$dep]['latitude']);$lat2=deg2rad((float)$r[$arr]['latitude']);$dlat=$lat2-$lat1;$dlon=deg2rad((float)$r[$arr]['longitude']-(float)$r[$dep]['longitude']);$a=sin($dlat/2)**2+cos($lat1)*cos($lat2)*sin($dlon/2)**2;return(int)round(6371.0088*2*atan2(sqrt($a),sqrt(1-$a)));}
    private function durationForValues(string $dd,?string $dt,int $dep,?string $ad,?string $at,int $arr):?int{if(!$dt||!$ad||!$at)return null;$s=$this->pdo->prepare('SELECT id,timezone_name FROM ml_airports WHERE id IN (:dep,:arr)');$s->execute(['dep'=>$dep,'arr'=>$arr]);$tz=[];foreach($s->fetchAll() as $x)$tz[(int)$x['id']]=$x['timezone_name'];if(empty($tz[$dep])||empty($tz[$arr]))return null;try{$a=new DateTimeImmutable($dd.' '.$dt,new DateTimeZone((string)$tz[$dep]));$b=new DateTimeImmutable($ad.' '.$at,new DateTimeZone((string)$tz[$arr]));$seconds=$b->getTimestamp()-$a->getTimestamp();}catch(Throwable){return null;}if($seconds<=0||$seconds>48*3600)return null;return $seconds;}
    private function isDate(string $v):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);return$d!==false&&$d->format('Y-m-d')===$v;}
    private function normalizeTime(mixed $v):?string{$v=$this->nullableString($v);if($v===null)return null;if(preg_match('/^\d{2}:\d{2}$/',$v))$v.=':00';return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',$v)?$v:null;}
    private function nullableString(mixed $v):?string{if($v===null)return null;$v=trim((string)$v);return$v===''?null:$v;}
    private function perPage(mixed $v):int{$n=(int)$v;return in_array($n,[25,50,100],true)?$n:25;}
    private function bindAll(\PDOStatement $stmt,array $params):void{foreach($params as $k=>$v)$stmt->bindValue(':'.$k,$v);}
    private function castNumbers(array $a):array{foreach($a as $k=>$v){if(is_numeric($v))$a[$k]=(str_contains((string)$v,'.')?(float)$v:(int)$v);}return$a;}
    private function auditUser(array $u):array{return array_intersect_key($u,array_flip(['id','nick','email','is_active','is_admin','email_verified_at','privacy_mode','public_slug','session_version']));}
    private function auditFlight(array $f):array{return array_intersect_key($f,array_flip(['id','user_id','departure_airport_id','arrival_airport_id','airline_id','aircraft_type_id','departure_date','departure_time','arrival_date','arrival_time','flight_number','distance_km','duration_seconds','travel_class','seat_type','seat_number','travel_reason','aircraft_registration','notes']));}
    private function ip(Request $r):?string{$server=$r->getServerParams();return isset($server['REMOTE_ADDR'])?(string)$server['REMOTE_ADDR']:null;}

    private function requireAdmin(Response $response): int|Response
    {
        try{$id=$this->auth->requireUserId();}catch(\RuntimeException){return$this->error($response,'Musisz się zalogować.',401);}
        $s=$this->pdo->prepare('SELECT is_admin,is_active FROM ml_users WHERE id=:id LIMIT 1');$s->execute(['id'=>$id]);$u=$s->fetch();
        if(!$u||!(bool)$u['is_active']||!(bool)$u['is_admin'])return$this->error($response,'Brak uprawnień administratora.',403);return$id;
    }
    private function uniqueSlug(string $nick,int $id):string{$base=$this->slugify($nick)?:'profil-'.$id;$candidate=$base;$i=2;while(true){$s=$this->pdo->prepare('SELECT id FROM ml_users WHERE public_slug=:slug AND id<>:id LIMIT 1');$s->execute(['slug'=>$candidate,'id'=>$id]);if(!$s->fetch())return$candidate;$candidate=$base.'-'.$i++;}}
    private function slugify(string $v):string{$v=strtr(trim($v),['ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z','Ą'=>'A','Ć'=>'C','Ę'=>'E','Ł'=>'L','Ń'=>'N','Ó'=>'O','Ś'=>'S','Ź'=>'Z','Ż'=>'Z']);$x=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v);if(is_string($x)&&$x!=='')$v=$x;$v=mb_strtolower($v,'UTF-8');$v=preg_replace('/[^a-z0-9]+/i','-',$v)??'';return trim($v,'-');}
    private function simpleMail(string $title,string $message,string $button,string $url,string $note):string
    {
        $safeTitle=htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $safeMessage=htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $safeButton=htmlspecialchars($button,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $safeUrl=htmlspecialchars($url,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $safeNote=htmlspecialchars($note,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $safeLogoUrl=htmlspecialchars($this->appUrl.'/src/assets/branding/mapa-lotow-symbol.png',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $year=date('Y');
        return <<<HTML
<!doctype html>
<html lang="pl">
<body style="margin:0;padding:0;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#263244">
  <div style="padding:28px 14px">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dfe7ee;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(11,45,92,.08)">
      <div style="padding:12px 24px;background:#f8fbfd;border-bottom:1px solid #e5edf3">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;border-collapse:collapse">
          <tr>
            <td style="vertical-align:middle;padding-right:10px"><img src="{$safeLogoUrl}" alt="Mapa Lotów" width="38" height="38" style="display:block;width:38px;height:38px;object-fit:contain"></td>
            <td style="vertical-align:middle;color:#0b2d5c;font-size:18px;font-weight:800;letter-spacing:.05em;line-height:1">MAPA LOTÓW</td>
          </tr>
        </table>
      </div>
      <div style="padding:28px 30px">
        <h1 style="margin:0 0 14px;color:#0b2d5c;font-size:25px;line-height:1.2">{$safeTitle}</h1>
        <p style="margin:0;color:#445367;font-size:15px;line-height:1.65">{$safeMessage}</p>
        <div style="margin:26px 0;text-align:center"><a href="{$safeUrl}" style="display:inline-block;background:#0b2d5c;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:9px;font-size:14px;font-weight:700">{$safeButton}</a></div>
        <p style="margin:0 0 16px;color:#738091;font-size:12px;line-height:1.5">{$safeNote}</p>
        <div style="padding-top:16px;border-top:1px solid #e7edf2">
          <p style="margin:0 0 4px;color:#7a8796;font-size:12px;line-height:1.4">Jeśli przycisk nie działa, skopiuj poniższy adres i wklej go do przeglądarki:</p>
          <p style="margin:0;overflow-wrap:anywhere;word-break:break-all"><a href="{$safeUrl}" style="color:#315d8e;font-size:12px;line-height:1.4">{$safeUrl}</a></p>
        </div>
      </div>
      <div style="padding:15px 30px;background:#f7f9fb;border-top:1px solid #e5edf3;color:#7d8996;font-size:11px;line-height:1.3">
        <div style="margin:0 0 2px;font-weight:700;color:#667586">Mapa Lotów</div>
        <div style="margin:0">Wiadomość systemowa wysłana przez panel administracyjny RUNWAY.</div>
        <div style="margin:3px 0 0">Jeżeli nie inicjowałeś tej operacji, możesz zignorować tę wiadomość.</div>
        <div style="margin:3px 0 0">© {$year} Mapa Lotów</div>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }
    private function json(Response $response,array $data,int $status=200):Response{$response->getBody()->write(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return$response->withStatus($status)->withHeader('Content-Type','application/json; charset=utf-8');}
    private function error(Response $response,string $message,int $status):Response{return$this->json($response,['status'=>'error','message'=>$message],$status);}
}
