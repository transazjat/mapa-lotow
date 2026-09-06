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

    private const DISTANCE_THRESHOLDS = [
        10000,
        25000,
        50000,
        100000,
        250000,
        500000,
        750000,
        1000000,
        1500000,
        2000000,
    ];

    private const AIRPORT_THRESHOLDS = [
        5,
        10,
        25,
        50,
        75,
        100,
        125,
        150,
        200,
        250,
    ];

    private const COUNTRY_THRESHOLDS = [
        5,
        10,
        15,
        20,
        25,
        30,
        40,
        50,
        75,
        100,
    ];

    private const CONTINENT_THRESHOLDS = [
        1,
        2,
        3,
        4,
        5,
        6,
        7,
    ];

    private const AIRLINE_THRESHOLDS = [
        5,
        10,
        15,
        20,
        30,
        40,
        50,
        60,
        75,
        100,
    ];

    private const AIRCRAFT_THRESHOLDS = [
        5,
        10,
        15,
        20,
        25,
        30,
        40,
        50,
        75,
        100,
    ];


    private const AIRCRAFT_MANUFACTURERS = [
        ['slug' => 'airbus', 'label' => 'Airbus', 'manufacturers' => ['Airbus'], 'order' => 1],
        ['slug' => 'boeing', 'label' => 'Boeing', 'manufacturers' => ['Boeing'], 'order' => 2],
        ['slug' => 'embraer', 'label' => 'Embraer', 'manufacturers' => ['Embraer'], 'order' => 3],
        ['slug' => 'atr', 'label' => 'ATR', 'manufacturers' => ['ATR'], 'order' => 4],
        ['slug' => 'bombardier', 'label' => 'Bombardier', 'manufacturers' => ['Bombardier'], 'order' => 5],
        ['slug' => 'de-havilland-canada', 'label' => 'De Havilland Canada', 'manufacturers' => ['De Havilland Canada'], 'order' => 6],
        ['slug' => 'fokker', 'label' => 'Fokker', 'manufacturers' => ['Fokker'], 'order' => 7],
        ['slug' => 'mcdonnell-douglas', 'label' => 'McDonnell Douglas', 'manufacturers' => ['McDonnell Douglas'], 'order' => 8],
        ['slug' => 'british-aerospace', 'label' => 'British Aerospace', 'manufacturers' => ['British Aerospace'], 'order' => 9],
        ['slug' => 'tupolev', 'label' => 'Tupolev', 'manufacturers' => ['Tupolev'], 'order' => 10],
        ['slug' => 'saab', 'label' => 'Saab', 'manufacturers' => ['Saab'], 'order' => 11],
        ['slug' => 'comac', 'label' => 'COMAC', 'manufacturers' => ['COMAC'], 'order' => 12],
    ];

    private const AIRCRAFT_ORIGINS = [
        ['slug' => 'american', 'label' => 'Amerykańska', 'manufacturers' => ['Boeing', 'McDonnell Douglas', 'Cessna', 'Beechcraft'], 'order' => 1],
        ['slug' => 'european', 'label' => 'Europejska', 'manufacturers' => ['Airbus'], 'order' => 2],
        ['slug' => 'french', 'label' => 'Francuska', 'manufacturers' => ['ATR'], 'order' => 3],
        ['slug' => 'brazilian', 'label' => 'Brazylijska', 'manufacturers' => ['Embraer'], 'order' => 4],
        ['slug' => 'canadian', 'label' => 'Kanadyjska', 'manufacturers' => ['Bombardier', 'De Havilland Canada'], 'order' => 5],
        ['slug' => 'british', 'label' => 'Brytyjska', 'manufacturers' => ['British Aerospace'], 'order' => 6],
        ['slug' => 'dutch', 'label' => 'Holenderska', 'manufacturers' => ['Fokker'], 'order' => 7],
        ['slug' => 'russian', 'label' => 'Rosyjska', 'manufacturers' => ['Tupolev', 'Sukhoi'], 'order' => 8],
        ['slug' => 'soviet', 'label' => 'Radziecka', 'manufacturers' => ['Ilyushin', 'Yakovlev', 'Antonov'], 'order' => 9],
        ['slug' => 'chinese', 'label' => 'Chińska', 'manufacturers' => ['COMAC'], 'order' => 10],
        ['slug' => 'swedish', 'label' => 'Szwedzka', 'manufacturers' => ['Saab'], 'order' => 11],
        ['slug' => 'czechoslovak', 'label' => 'Czechosłowacka', 'manufacturers' => ['LET'], 'order' => 12],
    ];



    private const AIRCRAFT_UNIQUE = [
        ['slug' => 'airbus-a380', 'label' => 'Airbus A380', 'families' => ['Airbus A380'], 'order' => 1],
        ['slug' => 'boeing-747', 'label' => 'Boeing 747 - Jumbo Jet', 'families' => ['Boeing 747'], 'order' => 2],
        ['slug' => 'md-11', 'label' => 'McDonnell Douglas MD-11', 'families' => ['McDonnell Douglas MD-11'], 'order' => 3],
        ['slug' => 'dc-10', 'label' => 'McDonnell Douglas DC-10', 'families' => ['McDonnell Douglas DC-10'], 'order' => 4],
        ['slug' => 'airbus-a340', 'label' => 'Airbus A340', 'families' => ['Airbus A340'], 'order' => 5],
        ['slug' => 'boeing-727', 'label' => 'Boeing 727', 'families' => ['Boeing 727'], 'order' => 6],
        ['slug' => 'boeing-757', 'label' => 'Boeing 757', 'families' => ['Boeing 757'], 'order' => 7],
        ['slug' => 'boeing-767', 'label' => 'Boeing 767', 'families' => ['Boeing 767'], 'order' => 8],
        ['slug' => 'bae-146-avro-rj', 'label' => 'BAe 146 / Avro RJ', 'families' => ['BAe 146', 'Avro RJ'], 'order' => 9],
        ['slug' => 'tupolev-tu-154', 'label' => 'Tupolev Tu-154', 'families' => ['Tupolev Tu-154'], 'order' => 10],
        ['slug' => 'ilyushin-il-18', 'label' => 'Ilyushin Il-18', 'families' => ['Ilyushin Il-18'], 'order' => 11],
        ['slug' => 'yakovlev-yak-40', 'label' => 'Yakovlev Yak-40', 'families' => ['Yakovlev Yak-40'], 'order' => 12],
        ['slug' => 'comac-c919', 'label' => 'COMAC C919', 'families' => ['COMAC C919'], 'order' => 13],
        ['slug' => 'comac-c909', 'label' => 'COMAC C909 / ARJ21', 'families' => ['COMAC C909'], 'order' => 14],
        ['slug' => 'dhc-6-twin-otter', 'label' => 'De Havilland Canada DHC-6 Twin Otter', 'families' => ['DHC-6 Twin Otter'], 'order' => 15],
        ['slug' => 'dhc-7-dash-7', 'label' => 'De Havilland Canada DHC-7 Dash 7', 'families' => ['DHC-7 Dash 7'], 'order' => 16],
        ['slug' => 'let-l-410', 'label' => 'LET L-410 Turbolet', 'families' => ['LET L-410'], 'order' => 17],
        ['slug' => 'saab-2000', 'label' => 'Saab 2000', 'families' => ['Saab 2000'], 'order' => 18],
        ['slug' => 'cessna-172', 'label' => 'Cessna 172 Skyhawk', 'families' => ['Cessna 172'], 'order' => 19],
        ['slug' => 'cessna-208', 'label' => 'Cessna 208 Caravan', 'families' => ['Cessna 208'], 'order' => 20],
        ['slug' => 'cessna-210', 'label' => 'Cessna 210 Centurion', 'families' => ['Cessna 210'], 'order' => 21],
        ['slug' => 'pilatus-pc-6', 'label' => 'Pilatus PC-6 Porter', 'families' => ['Pilatus PC-6'], 'order' => 22],
        ['slug' => 'beechcraft-king-air', 'label' => 'Beechcraft King Air', 'families' => ['Beechcraft King Air'], 'order' => 23],
        ['slug' => 'helicopter', 'label' => 'Helikopter', 'families' => ['Helicopter'], 'order' => 24],
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

        $allAchievements = [
            ...$state['achievements'],
            ...$state['distance']['achievements'],
            ...$state['airports']['achievements'],
            ...$state['countries']['achievements'],
            ...$state['continents']['achievements'],
            ...$state['airlines']['achievements'],
            ...$state['aircraft']['achievements'],
            ...$state['aircraft_manufacturers']['achievements'],
            ...$state['aircraft_origins']['achievements'],
            ...$state['aircraft_unique']['achievements'],
        ];

        $state['newly_earned'] = array_values(
            array_filter(
                $allAchievements,
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

        $allowedKeys = [];

        foreach (self::FLIGHT_THRESHOLDS as $threshold) {
            $allowedKeys['flights_' . $threshold] = true;
        }

        foreach (self::DISTANCE_THRESHOLDS as $threshold) {
            $allowedKeys['distance_' . $threshold] = true;
        }

        foreach (self::AIRPORT_THRESHOLDS as $threshold) {
            $allowedKeys['airports_' . $threshold] = true;
        }

        foreach (self::COUNTRY_THRESHOLDS as $threshold) {
            $allowedKeys['countries_' . $threshold] = true;
        }

        foreach (self::CONTINENT_THRESHOLDS as $threshold) {
            $allowedKeys['continents_' . $threshold] = true;
        }

        foreach (self::AIRLINE_THRESHOLDS as $threshold) {
            $allowedKeys['airlines_' . $threshold] = true;
        }

        foreach (self::AIRCRAFT_THRESHOLDS as $threshold) {
            $allowedKeys['aircraft_' . $threshold] = true;
        }

        foreach (self::AIRCRAFT_MANUFACTURERS as $definition) {
            $allowedKeys['aircraft_manufacturer_' . $definition['slug']] = true;
        }

        foreach (self::AIRCRAFT_ORIGINS as $definition) {
            $allowedKeys['aircraft_origin_' . $definition['slug']] = true;
        }

        foreach (self::AIRCRAFT_UNIQUE as $definition) {
            $allowedKeys['aircraft_unique_' . $definition['slug']] = true;
        }

        $keys = array_values(
            array_filter(
                array_map(
                    static fn($key): string => trim((string) $key),
                    $keys
                ),
                static fn(string $key): bool => isset($allowedKeys[$key])
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
        return [
            ...$this->syncFlightAchievements($userId),
            ...$this->syncDistanceAchievements($userId),
            ...$this->syncAirportAchievements($userId),
            ...$this->syncCountryAchievements($userId),
            ...$this->syncContinentAchievements($userId),
            ...$this->syncAirlineAchievements($userId),
            ...$this->syncAircraftAchievements($userId),
            ...$this->syncAircraftManufacturerAchievements($userId),
            ...$this->syncAircraftOriginAchievements($userId),
            ...$this->syncAircraftUniqueAchievements($userId),
        ];
    }

    /** @return list<string> */
    private function syncFlightAchievements(int $userId): array
    {
        $completedFlights = $this->completedFlightCount($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'flights',
            self::FLIGHT_THRESHOLDS,
            $completedFlights,
            fn(int $threshold): ?string => $this->flightThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }

    /** @return list<string> */
    private function syncDistanceAchievements(int $userId): array
    {
        $completedDistance = $this->completedDistanceKm($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'distance',
            self::DISTANCE_THRESHOLDS,
            $completedDistance,
            fn(int $threshold): ?string => $this->distanceThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }

    /** @return list<string> */
    private function syncAirportAchievements(int $userId): array
    {
        $completedAirports = $this->completedAirportCount($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'airports',
            self::AIRPORT_THRESHOLDS,
            $completedAirports,
            fn(int $threshold): ?string => $this->airportThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }

    /** @return list<string> */
    private function syncCountryAchievements(int $userId): array
    {
        $completedCountries = $this->completedCountryCount($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'countries',
            self::COUNTRY_THRESHOLDS,
            $completedCountries,
            fn(int $threshold): ?string => $this->countryThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }

    /** @return list<string> */
    private function syncContinentAchievements(int $userId): array
    {
        $completedContinents = $this->completedContinentCount($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'continents',
            self::CONTINENT_THRESHOLDS,
            $completedContinents,
            fn(int $threshold): ?string => $this->continentThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }


    /** @return list<string> */
    private function syncAirlineAchievements(int $userId): array
    {
        $completedAirlines = $this->completedAirlineCount($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'airlines',
            self::AIRLINE_THRESHOLDS,
            $completedAirlines,
            fn(int $threshold): ?string => $this->airlineThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }


    /** @return list<string> */
    private function syncAircraftAchievements(int $userId): array
    {
        $completedAircraftTypes = $this->completedAircraftTypeCount($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'aircraft',
            self::AIRCRAFT_THRESHOLDS,
            $completedAircraftTypes,
            fn(int $threshold): ?string => $this->aircraftThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }

    /** @return list<string> */
    private function syncAircraftManufacturerAchievements(int $userId): array
    {
        return $this->syncCollectionAchievements(
            $userId,
            'aircraft_manufacturers',
            self::AIRCRAFT_MANUFACTURERS,
            'aircraft_manufacturer_',
            fn(array $definition): ?string => $this->aircraftCollectionEarnedAt(
                $userId,
                $definition['manufacturers']
            )
        );
    }

    /** @return list<string> */
    private function syncAircraftOriginAchievements(int $userId): array
    {
        return $this->syncCollectionAchievements(
            $userId,
            'aircraft_origins',
            self::AIRCRAFT_ORIGINS,
            'aircraft_origin_',
            fn(array $definition): ?string => $this->aircraftCollectionEarnedAt(
                $userId,
                $definition['manufacturers']
            )
        );
    }


    /** @return list<string> */
    private function syncAircraftUniqueAchievements(int $userId): array
    {
        return $this->syncCollectionAchievements(
            $userId,
            'aircraft_unique',
            self::AIRCRAFT_UNIQUE,
            'aircraft_unique_',
            fn(array $definition): ?string => $this->aircraftFamilyEarnedAt(
                $userId,
                $definition['families']
            )
        );
    }

    /**
     * @param list<array{slug:string,label:string,manufacturers:array<int,string>,order:int}> $definitions
     * @param callable(array{slug:string,label:string,manufacturers:array<int,string>,order:int}): ?string $earnedAtResolver
     * @return list<string>
     */
    private function syncCollectionAchievements(
        int $userId,
        string $family,
        array $definitions,
        string $keyPrefix,
        callable $earnedAtResolver
    ): array {
        $existingStmt = $this->pdo->prepare(
            "
            SELECT achievement_key, threshold_value, earned_at
            FROM ml_user_achievements
            WHERE user_id = :user_id
              AND family = :family
            "
        );
        $existingStmt->execute([
            'user_id' => $userId,
            'family' => $family,
        ]);

        $existingRows = [];
        foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingRows[(string) $row['achievement_key']] = $row;
        }

        $insert = $this->pdo->prepare(
            "
            INSERT IGNORE INTO ml_user_achievements (
                user_id, achievement_key, family, threshold_value,
                earned_at, notified_at, created_at, updated_at
            ) VALUES (
                :user_id, :achievement_key, :family, :order_value,
                :earned_at, NULL, NOW(), NOW()
            )
            "
        );

        $update = $this->pdo->prepare(
            "
            UPDATE ml_user_achievements
            SET threshold_value = :order_value,
                earned_at = :earned_at,
                updated_at = NOW()
            WHERE user_id = :user_id
              AND achievement_key = :achievement_key
            "
        );

        $delete = $this->pdo->prepare(
            "
            DELETE FROM ml_user_achievements
            WHERE user_id = :user_id
              AND achievement_key = :achievement_key
            "
        );

        $newKeys = [];
        $knownKeys = [];

        foreach ($definitions as $definition) {
            $key = $keyPrefix . $definition['slug'];
            $knownKeys[$key] = true;
            $earnedAt = $earnedAtResolver($definition);
            $existing = $existingRows[$key] ?? null;

            if ($earnedAt === null) {
                if ($existing !== null) {
                    $delete->execute([
                        'user_id' => $userId,
                        'achievement_key' => $key,
                    ]);
                }
                continue;
            }

            if ($existing === null) {
                $insert->execute([
                    'user_id' => $userId,
                    'achievement_key' => $key,
                    'family' => $family,
                    'order_value' => $definition['order'],
                    'earned_at' => $earnedAt,
                ]);

                if ($insert->rowCount() > 0) {
                    $newKeys[] = $key;
                }

                continue;
            }

            if (
                (int) $existing['threshold_value'] !== (int) $definition['order'] ||
                (string) $existing['earned_at'] !== $earnedAt
            ) {
                $update->execute([
                    'user_id' => $userId,
                    'achievement_key' => $key,
                    'order_value' => $definition['order'],
                    'earned_at' => $earnedAt,
                ]);
            }
        }

        foreach (array_keys($existingRows) as $existingKey) {
            if (!isset($knownKeys[$existingKey])) {
                $delete->execute([
                    'user_id' => $userId,
                    'achievement_key' => $existingKey,
                ]);
            }
        }

        return $newKeys;
    }

    /**
     * @param list<int> $thresholds
     * @param callable(int): ?string $earnedAtResolver
     * @return list<string>
     */
    private function syncFamilyAchievements(
        int $userId,
        string $family,
        array $thresholds,
        int $currentValue,
        callable $earnedAtResolver
    ): array {
        if ($currentValue < $thresholds[0]) {
            return [];
        }

        $existingStmt = $this->pdo->prepare(
            "
            SELECT achievement_key
            FROM ml_user_achievements
            WHERE user_id = :user_id
              AND family = :family
            "
        );
        $existingStmt->execute([
            'user_id' => $userId,
            'family' => $family,
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
                :family,
                :threshold_value,
                :earned_at,
                NULL,
                NOW(),
                NOW()
            )
            "
        );

        $newKeys = [];

        foreach ($thresholds as $threshold) {
            if ($currentValue < $threshold) {
                break;
            }

            $key = $family . '_' . $threshold;

            if (isset($existing[$key])) {
                continue;
            }

            $earnedAt = $earnedAtResolver($threshold);

            if ($earnedAt === null) {
                continue;
            }

            $insert->execute([
                'user_id' => $userId,
                'achievement_key' => $key,
                'family' => $family,
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

    private function completedDistanceKm(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COALESCE(SUM(COALESCE(distance_km, 0)), 0)
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        return (int) floor((float) $stmt->fetchColumn());
    }

    private function completedAirportCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(*)
            FROM (
                SELECT departure_airport_id AS airport_id
                FROM ml_flights
                WHERE user_id = :user_id_departure
                  AND departure_date <= CURDATE()
                  AND departure_airport_id IS NOT NULL
                UNION
                SELECT arrival_airport_id AS airport_id
                FROM ml_flights
                WHERE user_id = :user_id_arrival
                  AND departure_date <= CURDATE()
                  AND arrival_airport_id IS NOT NULL
            ) AS visited_airports
            "
        );
        $stmt->execute([
            'user_id_departure' => $userId,
            'user_id_arrival' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function completedCountryCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(*)
            FROM (
                SELECT dep.country_id AS country_id
                FROM ml_flights f
                JOIN ml_airports dep ON dep.id = f.departure_airport_id
                WHERE f.user_id = :user_id_departure
                  AND f.departure_date <= CURDATE()
                  AND dep.country_id IS NOT NULL
                UNION
                SELECT arr.country_id AS country_id
                FROM ml_flights f
                JOIN ml_airports arr ON arr.id = f.arrival_airport_id
                WHERE f.user_id = :user_id_arrival
                  AND f.departure_date <= CURDATE()
                  AND arr.country_id IS NOT NULL
            ) AS visited_countries
            "
        );
        $stmt->execute([
            'user_id_departure' => $userId,
            'user_id_arrival' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function completedContinentCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(*)
            FROM (
                SELECT dep_country.continent_code AS continent_code
                FROM ml_flights f
                JOIN ml_airports dep_airport ON dep_airport.id = f.departure_airport_id
                JOIN ml_countries dep_country ON dep_country.id = dep_airport.country_id
                WHERE f.user_id = :user_id_departure
                  AND f.departure_date <= CURDATE()
                  AND dep_country.continent_code IS NOT NULL
                UNION
                SELECT arr_country.continent_code AS continent_code
                FROM ml_flights f
                JOIN ml_airports arr_airport ON arr_airport.id = f.arrival_airport_id
                JOIN ml_countries arr_country ON arr_country.id = arr_airport.country_id
                WHERE f.user_id = :user_id_arrival
                  AND f.departure_date <= CURDATE()
                  AND arr_country.continent_code IS NOT NULL
            ) AS visited_continents
            "
        );
        $stmt->execute([
            'user_id_departure' => $userId,
            'user_id_arrival' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function completedAirlineCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(DISTINCT airline_id)
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
              AND airline_id IS NOT NULL
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }


    private function completedAircraftTypeCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(DISTINCT aircraft_type_id)
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
              AND aircraft_type_id IS NOT NULL
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function flightThresholdEarnedAt(
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

    private function distanceThresholdEarnedAt(
        int $userId,
        int $threshold
    ): ?string {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                departure_date,
                COALESCE(departure_time, '00:00:00') AS departure_time,
                COALESCE(distance_km, 0) AS distance_km
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
            ORDER BY
                departure_date ASC,
                COALESCE(departure_time, '00:00:00') ASC,
                id ASC
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $distance = 0.0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $distance += (float) $row['distance_km'];

            if ($distance >= $threshold) {
                return sprintf(
                    '%s %s',
                    (string) $row['departure_date'],
                    (string) $row['departure_time']
                );
            }
        }

        return null;
    }

    private function airportThresholdEarnedAt(
        int $userId,
        int $threshold
    ): ?string {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                departure_date,
                COALESCE(departure_time, '00:00:00') AS departure_time,
                departure_airport_id,
                arrival_airport_id
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
            ORDER BY
                departure_date ASC,
                COALESCE(departure_time, '00:00:00') ASC,
                id ASC
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $visited = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['departure_airport_id'] !== null) {
                $visited[(int) $row['departure_airport_id']] = true;
            }

            if ($row['arrival_airport_id'] !== null) {
                $visited[(int) $row['arrival_airport_id']] = true;
            }

            if (count($visited) >= $threshold) {
                return sprintf(
                    '%s %s',
                    (string) $row['departure_date'],
                    (string) $row['departure_time']
                );
            }
        }

        return null;
    }

    private function countryThresholdEarnedAt(
        int $userId,
        int $threshold
    ): ?string {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.departure_date,
                COALESCE(f.departure_time, '00:00:00') AS departure_time,
                dep.country_id AS departure_country_id,
                arr.country_id AS arrival_country_id
            FROM ml_flights f
            JOIN ml_airports dep ON dep.id = f.departure_airport_id
            JOIN ml_airports arr ON arr.id = f.arrival_airport_id
            WHERE f.user_id = :user_id
              AND f.departure_date <= CURDATE()
            ORDER BY
                f.departure_date ASC,
                COALESCE(f.departure_time, '00:00:00') ASC,
                f.id ASC
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $visited = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['departure_country_id'] !== null) {
                $visited[(int) $row['departure_country_id']] = true;
            }

            if ($row['arrival_country_id'] !== null) {
                $visited[(int) $row['arrival_country_id']] = true;
            }

            if (count($visited) >= $threshold) {
                return sprintf(
                    '%s %s',
                    (string) $row['departure_date'],
                    (string) $row['departure_time']
                );
            }
        }

        return null;
    }

    private function continentThresholdEarnedAt(
        int $userId,
        int $threshold
    ): ?string {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.departure_date,
                COALESCE(f.departure_time, '00:00:00') AS departure_time,
                dep_country.continent_code AS departure_continent_code,
                arr_country.continent_code AS arrival_continent_code
            FROM ml_flights f
            LEFT JOIN ml_airports dep_airport ON dep_airport.id = f.departure_airport_id
            LEFT JOIN ml_countries dep_country ON dep_country.id = dep_airport.country_id
            LEFT JOIN ml_airports arr_airport ON arr_airport.id = f.arrival_airport_id
            LEFT JOIN ml_countries arr_country ON arr_country.id = arr_airport.country_id
            WHERE f.user_id = :user_id
              AND f.departure_date <= CURDATE()
            ORDER BY
                f.departure_date ASC,
                COALESCE(f.departure_time, '00:00:00') ASC,
                f.id ASC
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $visited = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['departure_continent_code'] !== null) {
                $visited[(string) $row['departure_continent_code']] = true;
            }

            if ($row['arrival_continent_code'] !== null) {
                $visited[(string) $row['arrival_continent_code']] = true;
            }

            if (count($visited) >= $threshold) {
                return sprintf(
                    '%s %s',
                    (string) $row['departure_date'],
                    (string) $row['departure_time']
                );
            }
        }

        return null;
    }

    private function airlineThresholdEarnedAt(
        int $userId,
        int $threshold
    ): ?string {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                departure_date,
                COALESCE(departure_time, '00:00:00') AS departure_time,
                airline_id
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
              AND airline_id IS NOT NULL
            ORDER BY
                departure_date ASC,
                COALESCE(departure_time, '00:00:00') ASC,
                id ASC
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $visited = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $visited[(int) $row['airline_id']] = true;

            if (count($visited) >= $threshold) {
                return sprintf(
                    '%s %s',
                    (string) $row['departure_date'],
                    (string) $row['departure_time']
                );
            }
        }

        return null;
    }


    private function aircraftThresholdEarnedAt(
        int $userId,
        int $threshold
    ): ?string {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                departure_date,
                COALESCE(departure_time, '00:00:00') AS departure_time,
                aircraft_type_id
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
              AND aircraft_type_id IS NOT NULL
            ORDER BY
                departure_date ASC,
                COALESCE(departure_time, '00:00:00') ASC,
                id ASC
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $visited = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $visited[(int) $row['aircraft_type_id']] = true;

            if (count($visited) >= $threshold) {
                return sprintf(
                    '%s %s',
                    (string) $row['departure_date'],
                    (string) $row['departure_time']
                );
            }
        }

        return null;
    }

    private function aircraftManufacturerEarnedAt(
        int $userId,
        string $manufacturer
    ): ?string {
        return $this->aircraftCollectionEarnedAt(
            $userId,
            [$manufacturer]
        );
    }

    /** @param list<string> $manufacturers */
    private function aircraftCollectionEarnedAt(
        int $userId,
        array $manufacturers
    ): ?string {
        if ($manufacturers === []) {
            return null;
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($manufacturers), '?')
        );

        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.departure_date,
                COALESCE(f.departure_time, '00:00:00') AS departure_time
            FROM ml_flights f
            JOIN ml_aircraft_types at ON at.id = f.aircraft_type_id
            WHERE f.user_id = ?
              AND f.departure_date <= CURDATE()
              AND at.manufacturer IN ($placeholders)
            ORDER BY
                f.departure_date ASC,
                COALESCE(f.departure_time, '00:00:00') ASC,
                f.id ASC
            LIMIT 1
            "
        );
        $stmt->execute([
            $userId,
            ...$manufacturers,
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

    /** @param list<string> $families */
    private function aircraftFamilyEarnedAt(
        int $userId,
        array $families
    ): ?string {
        if ($families === []) {
            return null;
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($families), '?')
        );

        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.departure_date,
                COALESCE(f.departure_time, '00:00:00') AS departure_time
            FROM ml_flights f
            JOIN ml_aircraft_types at ON at.id = f.aircraft_type_id
            WHERE f.user_id = ?
              AND f.departure_date <= CURDATE()
              AND at.family IN ($placeholders)
            ORDER BY
                f.departure_date ASC,
                COALESCE(f.departure_time, '00:00:00') ASC,
                f.id ASC
            LIMIT 1
            "
        );
        $stmt->execute([
            $userId,
            ...$families,
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

    private function buildAircraftManufacturerState(int $userId): array
    {
        return $this->buildAircraftCollectionState(
            $userId,
            'aircraft_manufacturers',
            self::AIRCRAFT_MANUFACTURERS,
            'aircraft_manufacturer_'
        );
    }

    private function buildAircraftOriginState(int $userId): array
    {
        return $this->buildAircraftCollectionState(
            $userId,
            'aircraft_origins',
            self::AIRCRAFT_ORIGINS,
            'aircraft_origin_'
        );
    }


    private function buildAircraftUniqueState(int $userId): array
    {
        return $this->buildAircraftCollectionState(
            $userId,
            'aircraft_unique',
            self::AIRCRAFT_UNIQUE,
            'aircraft_unique_'
        );
    }

    /**
     * @param list<array{slug:string,label:string,manufacturers:array<int,string>,order:int}> $definitions
     */
    private function buildAircraftCollectionState(
        int $userId,
        string $family,
        array $definitions,
        string $keyPrefix
    ): array {
        $stmt = $this->pdo->prepare(
            "
            SELECT achievement_key, threshold_value, earned_at, notified_at
            FROM ml_user_achievements
            WHERE user_id = :user_id
              AND family = :family
            "
        );
        $stmt->execute([
            'user_id' => $userId,
            'family' => $family,
        ]);

        $earnedRows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $earnedRows[(string) $row['achievement_key']] = $row;
        }

        $achievements = [];
        $pendingUnlocks = [];
        $lastEarned = null;
        $earnedCount = 0;

        foreach ($definitions as $definition) {
            $key = $keyPrefix . $definition['slug'];
            $row = $earnedRows[$key] ?? null;
            $earned = $row !== null;

            $item = [
                'key' => $key,
                'label' => $definition['label'],
                'slug' => $definition['slug'],
                'order' => $definition['order'],
                'status' => $earned ? 'active' : 'locked',
                'earned' => $earned,
                'active' => $earned,
                'earned_at' => $earned ? (string) $row['earned_at'] : null,
                'notified_at' => $earned && $row['notified_at'] !== null
                    ? (string) $row['notified_at']
                    : null,
            ];

            $achievements[] = $item;

            if ($earned) {
                $earnedCount++;
                if ($row['notified_at'] === null) {
                    $pendingUnlocks[] = $item;
                }
                if (
                    $lastEarned === null ||
                    strcmp((string) $item['earned_at'], (string) $lastEarned['earned_at']) >= 0
                ) {
                    $lastEarned = $item;
                }
            }
        }

        return [
            'achievements' => $achievements,
            'pending_unlocks' => $pendingUnlocks,
            'summary' => [
                'earned_count' => $earnedCount,
                'active_count' => $earnedCount,
                'total_count' => count($definitions),
                'last_earned' => $lastEarned,
            ],
        ];
    }

    private function buildState(int $userId): array
    {
        $flightState = $this->buildFamilyState(
            $userId,
            'flights',
            self::FLIGHT_THRESHOLDS,
            $this->completedFlightCount($userId)
        );

        $distanceState = $this->buildFamilyState(
            $userId,
            'distance',
            self::DISTANCE_THRESHOLDS,
            $this->completedDistanceKm($userId)
        );

        $airportState = $this->buildFamilyState(
            $userId,
            'airports',
            self::AIRPORT_THRESHOLDS,
            $this->completedAirportCount($userId)
        );

        $countryState = $this->buildFamilyState(
            $userId,
            'countries',
            self::COUNTRY_THRESHOLDS,
            $this->completedCountryCount($userId)
        );

        $continentState = $this->buildFamilyState(
            $userId,
            'continents',
            self::CONTINENT_THRESHOLDS,
            $this->completedContinentCount($userId)
        );

        $airlineState = $this->buildFamilyState(
            $userId,
            'airlines',
            self::AIRLINE_THRESHOLDS,
            $this->completedAirlineCount($userId)
        );


        $aircraftState = $this->buildFamilyState(
            $userId,
            'aircraft',
            self::AIRCRAFT_THRESHOLDS,
            $this->completedAircraftTypeCount($userId)
        );

        $aircraftManufacturerState = $this->buildAircraftManufacturerState($userId);
        $aircraftOriginState = $this->buildAircraftOriginState($userId);
        $aircraftUniqueState = $this->buildAircraftUniqueState($userId);

        return [
            'status' => 'ok',
            'completed_flights' => $flightState['completed_value'],
            'achievements' => $flightState['achievements'],
            'pending_unlocks' => $flightState['pending_unlocks'],
            'summary' => $flightState['summary'],
            'distance' => [
                'completed_distance_km' => $distanceState['completed_value'],
                'achievements' => $distanceState['achievements'],
                'pending_unlocks' => $distanceState['pending_unlocks'],
                'summary' => $distanceState['summary'],
            ],
            'airports' => [
                'completed_airports' => $airportState['completed_value'],
                'achievements' => $airportState['achievements'],
                'pending_unlocks' => $airportState['pending_unlocks'],
                'summary' => $airportState['summary'],
            ],
            'countries' => [
                'completed_countries' => $countryState['completed_value'],
                'achievements' => $countryState['achievements'],
                'pending_unlocks' => $countryState['pending_unlocks'],
                'summary' => $countryState['summary'],
            ],
            'continents' => [
                'completed_continents' => $continentState['completed_value'],
                'achievements' => $continentState['achievements'],
                'pending_unlocks' => $continentState['pending_unlocks'],
                'summary' => $continentState['summary'],
            ],
            'airlines' => [
                'completed_airlines' => $airlineState['completed_value'],
                'achievements' => $airlineState['achievements'],
                'pending_unlocks' => $airlineState['pending_unlocks'],
                'summary' => $airlineState['summary'],
            ],
            'aircraft' => [
                'completed_aircraft_types' => $aircraftState['completed_value'],
                'achievements' => $aircraftState['achievements'],
                'pending_unlocks' => $aircraftState['pending_unlocks'],
                'summary' => $aircraftState['summary'],
            ],
            'aircraft_manufacturers' => $aircraftManufacturerState,
            'aircraft_origins' => $aircraftOriginState,
            'aircraft_unique' => $aircraftUniqueState,
        ];
    }

    /** @param list<int> $thresholds */
    private function buildFamilyState(
        int $userId,
        string $family,
        array $thresholds,
        int $currentValue
    ): array {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                achievement_key,
                threshold_value,
                earned_at,
                notified_at
            FROM ml_user_achievements
            WHERE user_id = :user_id
              AND family = :family
            ORDER BY threshold_value ASC
            "
        );
        $stmt->execute([
            'user_id' => $userId,
            'family' => $family,
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

        foreach ($thresholds as $threshold) {
            $row = $earnedRows[$threshold] ?? null;
            $earned = $row !== null;
            $active = $earned && $currentValue >= $threshold;

            if ($earned) {
                $earnedCount++;
            }

            if ($active) {
                $activeCount++;
            }

            $item = [
                'key' => $family . '_' . $threshold,
                'family' => $family,
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

            if ($earned && $row['notified_at'] === null) {
                $pendingUnlocks[] = $item;
            }

            if (
                $earned &&
                (
                    $lastEarned === null ||
                    strcmp(
                        (string) $item['earned_at'],
                        (string) $lastEarned['earned_at']
                    ) >= 0
                )
            ) {
                $lastEarned = $item;
            }
        }

        $nextThreshold = null;

        foreach ($thresholds as $threshold) {
            if ($threshold > $currentValue) {
                $nextThreshold = $threshold;
                break;
            }
        }

        return [
            'completed_value' => $currentValue,
            'achievements' => $achievements,
            'pending_unlocks' => $pendingUnlocks,
            'summary' => [
                'earned_count' => $earnedCount,
                'active_count' => $activeCount,
                'last_earned' => $lastEarned,
                'next_threshold' => $nextThreshold,
                'remaining_to_next' => $nextThreshold !== null
                    ? max(0, $nextThreshold - $currentValue)
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
