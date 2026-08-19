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


class FlightController
{
    public function __construct(
        private PDO $pdo,
        private AuthService $auth
    ) {
    }


    public function index(
        Request $request,
        Response $response
    ): Response {

        $userId = $this->authenticatedUserId($response);

        if ($userId instanceof Response) {
            return $userId;
        }


        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.id,

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

                dep.id AS departure_airport_id,
                dep.iata_code AS departure_iata,
                dep.name AS departure_airport_name,
                dep.city AS departure_city,

                COALESCE(
                    dep_country.name,
                    dep.country_name
                ) AS departure_country,

                dep_country.iso2 AS departure_country_code,
                dep_country.continent_code AS departure_continent_code,

                dep.latitude AS departure_latitude,
                dep.longitude AS departure_longitude,

                arr.id AS arrival_airport_id,
                arr.iata_code AS arrival_iata,
                arr.name AS arrival_airport_name,
                arr.city AS arrival_city,

                COALESCE(
                    arr_country.name,
                    arr.country_name
                ) AS arrival_country,

                arr_country.iso2 AS arrival_country_code,
                arr_country.continent_code AS arrival_continent_code,

                arr.latitude AS arrival_latitude,
                arr.longitude AS arrival_longitude,

                al.id AS airline_id,
                al.name AS airline_name,

                ac.id AS aircraft_type_id,
                ac.name AS aircraft_name,

                CASE
                    WHEN dep.id = arr.id
                        THEN 'other'

                    WHEN dep.country_id IS NOT NULL
                        AND arr.country_id IS NOT NULL
                        AND dep.country_id = arr.country_id
                        THEN 'domestic'

                    WHEN dep_country.continent_code IS NOT NULL
                        AND arr_country.continent_code IS NOT NULL
                        AND dep_country.continent_code = arr_country.continent_code
                        THEN 'continental'

                    WHEN dep_country.continent_code IS NOT NULL
                        AND arr_country.continent_code IS NOT NULL
                        AND dep_country.continent_code <> arr_country.continent_code
                        THEN 'intercontinental'

                    ELSE 'other'
                END AS flight_type

            FROM ml_flights f

            JOIN ml_airports dep
                ON dep.id = f.departure_airport_id

            JOIN ml_airports arr
                ON arr.id = f.arrival_airport_id

            LEFT JOIN ml_countries dep_country
                ON dep_country.id = dep.country_id

            LEFT JOIN ml_countries arr_country
                ON arr_country.id = arr.country_id

            LEFT JOIN ml_airlines al
                ON al.id = f.airline_id

            LEFT JOIN ml_aircraft_types ac
                ON ac.id = f.aircraft_type_id

            WHERE f.user_id = :user_id

            ORDER BY
                f.departure_date DESC,
                f.departure_time DESC,
                f.id DESC
            "
        );


        $stmt->execute([
            'user_id' => $userId,
        ]);


        $flights = $stmt->fetchAll();


        return $this->json(
            $response,
            [
                'status' => 'ok',
                'user_id' => $userId,
                'count' => count($flights),
                'flights' => $flights,
            ]
        );
    }


    public function show(
        Request $request,
        Response $response,
        array $args
    ): Response {

        $flightId = isset($args['id'])
            ? (int) $args['id']
            : 0;


        if ($flightId <= 0) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Invalid flight id',
                ],
                400
            );
        }


        $userId = $this->authenticatedUserId($response);

        if ($userId instanceof Response) {
            return $userId;
        }


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
                f.seat_number,
                f.travel_reason,

                f.aircraft_registration,
                f.notes,

                dep.id AS departure_airport_id,
                dep.iata_code AS departure_iata,
                dep.icao_code AS departure_icao,
                dep.name AS departure_airport_name,
                dep.city AS departure_city,

                COALESCE(
                    dep_country.name,
                    dep.country_name
                ) AS departure_country,

                dep_country.iso2 AS departure_country_code,
                dep_country.continent_code AS departure_continent_code,

                dep.latitude AS departure_latitude,
                dep.longitude AS departure_longitude,
                dep.timezone_name AS departure_timezone,

                arr.id AS arrival_airport_id,
                arr.iata_code AS arrival_iata,
                arr.icao_code AS arrival_icao,
                arr.name AS arrival_airport_name,
                arr.city AS arrival_city,

                COALESCE(
                    arr_country.name,
                    arr.country_name
                ) AS arrival_country,

                arr_country.iso2 AS arrival_country_code,
                arr_country.continent_code AS arrival_continent_code,

                arr.latitude AS arrival_latitude,
                arr.longitude AS arrival_longitude,
                arr.timezone_name AS arrival_timezone,

                al.id AS airline_id,
                al.name AS airline_name,
                al.iata_code AS airline_iata,
                al.icao_code AS airline_icao,

                ac.id AS aircraft_type_id,
                ac.name AS aircraft_name,
                ac.family AS aircraft_family,
                ac.manufacturer AS aircraft_manufacturer,
                ac.model AS aircraft_model,
                ac.variant AS aircraft_variant,

                CASE
                    WHEN dep.id = arr.id
                        THEN 'other'

                    WHEN dep.country_id IS NOT NULL
                        AND arr.country_id IS NOT NULL
                        AND dep.country_id = arr.country_id
                        THEN 'domestic'

                    WHEN dep_country.continent_code IS NOT NULL
                        AND arr_country.continent_code IS NOT NULL
                        AND dep_country.continent_code = arr_country.continent_code
                        THEN 'continental'

                    WHEN dep_country.continent_code IS NOT NULL
                        AND arr_country.continent_code IS NOT NULL
                        AND dep_country.continent_code <> arr_country.continent_code
                        THEN 'intercontinental'

                    ELSE 'other'
                END AS flight_type

            FROM ml_flights f

            JOIN ml_airports dep
                ON dep.id = f.departure_airport_id

            JOIN ml_airports arr
                ON arr.id = f.arrival_airport_id

            LEFT JOIN ml_countries dep_country
                ON dep_country.id = dep.country_id

            LEFT JOIN ml_countries arr_country
                ON arr_country.id = arr.country_id

            LEFT JOIN ml_airlines al
                ON al.id = f.airline_id

            LEFT JOIN ml_aircraft_types ac
                ON ac.id = f.aircraft_type_id

            WHERE f.id = :flight_id
              AND f.user_id = :user_id

            LIMIT 1
            "
        );


        $stmt->execute([
            'flight_id' => $flightId,
            'user_id' => $userId,
        ]);


        $flight = $stmt->fetch();


        if (!$flight) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Flight not found',
                ],
                404
            );
        }


        return $this->json(
            $response,
            [
                'status' => 'ok',
                'flight' => $flight,
            ]
        );
    }


    public function searchAirports(
        Request $request,
        Response $response
    ): Response {

        $query = trim(
            (string) (
                $request->getQueryParams()['q']
                ?? ''
            )
        );


        if (mb_strlen($query) < 2) {
            return $this->json(
                $response,
                [
                    'status' => 'ok',
                    'items' => [],
                ]
            );
        }


        $like = '%' . $query . '%';


        $stmt = $this->pdo->prepare(
            "
            SELECT
                a.id,
                a.iata_code,
                a.icao_code,
                a.name,
                a.city,

                COALESCE(
                    c.name,
                    a.country_name
                ) AS country,

                c.iso2 AS country_code,

                a.latitude,
                a.longitude,
                a.timezone_name

            FROM ml_airports a

            LEFT JOIN ml_countries c
                ON c.id = a.country_id

            WHERE
                a.iata_code LIKE :iata_prefix
                OR a.icao_code LIKE :icao_prefix
                OR a.city LIKE :city_like
                OR a.name LIKE :name_like

            ORDER BY
                CASE
                    WHEN a.iata_code = :iata_exact
                        THEN 0
                    WHEN a.icao_code = :icao_exact
                        THEN 1
                    WHEN a.iata_code LIKE :iata_order_prefix
                        THEN 2
                    WHEN a.city LIKE :city_order_prefix
                        THEN 3
                    ELSE 4
                END,
                a.city,
                a.name

            LIMIT 12
            "
        );


        $stmt->execute([
            'iata_prefix' => $query . '%',
            'icao_prefix' => $query . '%',
            'city_like' => $like,
            'name_like' => $like,
            'iata_exact' => $query,
            'icao_exact' => $query,
            'iata_order_prefix' => $query . '%',
            'city_order_prefix' => $query . '%',
        ]);


        return $this->json(
            $response,
            [
                'status' => 'ok',
                'items' => $stmt->fetchAll(),
            ]
        );
    }


    public function searchAirlines(
        Request $request,
        Response $response
    ): Response {

        $query = trim(
            (string) (
                $request->getQueryParams()['q']
                ?? ''
            )
        );


        if (mb_strlen($query) < 2) {
            return $this->json(
                $response,
                [
                    'status' => 'ok',
                    'items' => [],
                ]
            );
        }


        $stmt = $this->pdo->prepare(
            "
            SELECT
                id,
                name,
                iata_code,
                icao_code

            FROM ml_airlines

            WHERE
                name LIKE :name_like
                OR iata_code LIKE :iata_prefix
                OR icao_code LIKE :icao_prefix

            ORDER BY
                CASE
                    WHEN iata_code = :iata_exact
                        THEN 0
                    WHEN icao_code = :icao_exact
                        THEN 1
                    WHEN name LIKE :name_order_prefix
                        THEN 2
                    ELSE 3
                END,
                name

            LIMIT 12
            "
        );


        $stmt->execute([
            'name_like' => '%' . $query . '%',
            'iata_prefix' => $query . '%',
            'icao_prefix' => $query . '%',
            'iata_exact' => $query,
            'icao_exact' => $query,
            'name_order_prefix' => $query . '%',
        ]);


        return $this->json(
            $response,
            [
                'status' => 'ok',
                'items' => $stmt->fetchAll(),
            ]
        );
    }


    public function searchAircraftTypes(
        Request $request,
        Response $response
    ): Response {

        $query = trim(
            (string) (
                $request->getQueryParams()['q']
                ?? ''
            )
        );


        if (mb_strlen($query) < 1) {
            return $this->json(
                $response,
                [
                    'status' => 'ok',
                    'items' => [],
                ]
            );
        }


        $aliasManufacturer = null;
        $aliasModel = null;


        if (preg_match('/^A\s*(\d{3})(?:[-\s].*)?$/i', $query, $match)) {
            $aliasManufacturer = 'Airbus';
            $aliasModel = $match[1];
        } elseif (preg_match('/^B\s*(\d{3})(?:[-\s].*)?$/i', $query, $match)) {
            $aliasManufacturer = 'Boeing';
            $aliasModel = $match[1];
        }


        $stmt = $this->pdo->prepare(
            "
            SELECT
                id,
                name,
                family,
                manufacturer,
                model,
                variant

            FROM ml_aircraft_types

            WHERE
                name LIKE :name_like
                OR family LIKE :family_like
                OR manufacturer LIKE :manufacturer_like
                OR model LIKE :model_like
                OR variant LIKE :variant_like
                OR REPLACE(LOWER(name), ' ', '') LIKE :compact_name_like
                OR (
                    :alias_manufacturer_check IS NOT NULL
                    AND (
                        manufacturer LIKE :alias_manufacturer_like
                        OR name LIKE :alias_name_manufacturer_like
                    )
                    AND (
                        model LIKE :alias_model_like
                        OR name LIKE :alias_name_model_like
                    )
                )

            ORDER BY
                CASE
                    WHEN name LIKE :name_order_prefix
                        THEN 0
                    WHEN model LIKE :model_order_prefix
                        THEN 1
                    WHEN (
                        :alias_order_check IS NOT NULL
                        AND (
                            manufacturer LIKE :alias_order_manufacturer
                            OR name LIKE :alias_order_name_manufacturer
                        )
                        AND (
                            model LIKE :alias_order_model
                            OR name LIKE :alias_order_name_model
                        )
                    )
                        THEN 2
                    ELSE 3
                END,
                name

            LIMIT 15
            "
        );


        $compactQuery = preg_replace(
            '/\s+/',
            '',
            mb_strtolower($query)
        );


        $stmt->execute([
            'name_like' => '%' . $query . '%',
            'family_like' => '%' . $query . '%',
            'manufacturer_like' => '%' . $query . '%',
            'model_like' => '%' . $query . '%',
            'variant_like' => '%' . $query . '%',
            'compact_name_like' => '%' . $compactQuery . '%',

            'alias_manufacturer_check' => $aliasManufacturer,
            'alias_manufacturer_like' => $aliasManufacturer !== null
                ? '%' . $aliasManufacturer . '%'
                : null,
            'alias_name_manufacturer_like' => $aliasManufacturer !== null
                ? '%' . $aliasManufacturer . '%'
                : null,
            'alias_model_like' => $aliasModel !== null
                ? '%' . $aliasModel . '%'
                : null,
            'alias_name_model_like' => $aliasModel !== null
                ? '%' . $aliasModel . '%'
                : null,

            'name_order_prefix' => $query . '%',
            'model_order_prefix' => $query . '%',

            'alias_order_check' => $aliasManufacturer,
            'alias_order_manufacturer' => $aliasManufacturer !== null
                ? $aliasManufacturer . '%'
                : null,
            'alias_order_name_manufacturer' => $aliasManufacturer !== null
                ? '%' . $aliasManufacturer . '%'
                : null,
            'alias_order_model' => $aliasModel !== null
                ? $aliasModel . '%'
                : null,
            'alias_order_name_model' => $aliasModel !== null
                ? '%' . $aliasModel . '%'
                : null,
        ]);


        return $this->json(
            $response,
            [
                'status' => 'ok',
                'items' => $stmt->fetchAll(),
            ]
        );
    }


    public function create(
        Request $request,
        Response $response
    ): Response {

        $data = $request->getParsedBody();


        if (!is_array($data)) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Invalid request body',
                ],
                400
            );
        }


        $userId = $this->authenticatedUserId($response);

        if ($userId instanceof Response) {
            return $userId;
        }

        $departureAirportId = (int) ($data['departure_airport_id'] ?? 0);
        $arrivalAirportId = (int) ($data['arrival_airport_id'] ?? 0);

        $departureDate = $this->nullableString($data['departure_date'] ?? null);
        $departureTime = $this->nullableTime($data['departure_time'] ?? null);
        $arrivalDate = $this->nullableString($data['arrival_date'] ?? null);
        $arrivalTime = $this->nullableTime($data['arrival_time'] ?? null);

        $airlineId = $this->nullablePositiveInt($data['airline_id'] ?? null);
        $aircraftTypeId = $this->nullablePositiveInt($data['aircraft_type_id'] ?? null);

        $flightNumber = $this->nullableString($data['flight_number'] ?? null);
        $notes = $this->nullableString($data['notes'] ?? null);

        $travelClass = (string) ($data['travel_class'] ?? 'e');
        $seatType = $this->nullableString($data['seat_type'] ?? null);
        $travelReason = (string) ($data['travel_reason'] ?? 'p');

        $force = (bool) ($data['force'] ?? false);


        if (
            $departureAirportId <= 0
            || $arrivalAirportId <= 0
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Brak wymaganych danych użytkownika lub lotnisk.',
                ],
                422
            );
        }


        if (
            !$departureDate
            || !$this->isIsoDate($departureDate)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Data odlotu jest wymagana i musi być poprawną datą.',
                ],
                422
            );
        }


        if (
            $arrivalDate !== null
            && !$this->isIsoDate($arrivalDate)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawna data przylotu.',
                ],
                422
            );
        }


        if (
            $arrivalTime !== null
            && $arrivalDate === null
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Godzina przylotu wymaga daty przylotu.',
                ],
                422
            );
        }


        if (!in_array($travelClass, ['e', 'p', 'b', 'f'], true)) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawna klasa podróży.',
                ],
                422
            );
        }


        if (
            $seatType !== null
            && !in_array($seatType, ['w', 'm', 'a'], true)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawny typ miejsca.',
                ],
                422
            );
        }


        if (!in_array($travelReason, ['p', 'b'], true)) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawny cel podróży.',
                ],
                422
            );
        }


        $travelClassForDatabase = $this->mapTravelClassToDatabase(
            $travelClass
        );


        if ($travelClassForDatabase === null) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Nie udało się dopasować klasy podróży do typu pola travel_class w bazie danych.',
                ],
                422
            );
        }


        $seatTypeForDatabase = $this->mapSeatTypeToDatabase(
            $seatType
        );


        if (
            $seatType !== null
            && $seatTypeForDatabase === null
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Nie udało się dopasować rodzaju miejsca do typu pola seat_type w bazie danych.',
                ],
                422
            );
        }


        $travelReasonForDatabase = $this->mapTravelReasonToDatabase(
            $travelReason
        );


        if ($travelReasonForDatabase === null) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Nie udało się dopasować celu podróży do typu pola travel_reason w bazie danych.',
                ],
                422
            );
        }


        $departureAirport = $this->getAirport($departureAirportId);
        $arrivalAirport = $this->getAirport($arrivalAirportId);


        if (!$departureAirport || !$arrivalAirport) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Wybrane lotnisko nie istnieje.',
                ],
                422
            );
        }


        if (
            $airlineId !== null
            && !$this->existsById('ml_airlines', $airlineId)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Wybrana linia lotnicza nie istnieje.',
                ],
                422
            );
        }


        if (
            $aircraftTypeId !== null
            && !$this->existsById('ml_aircraft_types', $aircraftTypeId)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Wybrany typ samolotu nie istnieje.',
                ],
                422
            );
        }


        $duplicate = $this->findDuplicate(
            $userId,
            $departureAirportId,
            $arrivalAirportId,
            $departureDate,
            $flightNumber
        );


        if ($duplicate && !$force) {
            return $this->json(
                $response,
                [
                    'status' => 'duplicate',
                    'message' => 'Podobny lot już istnieje.',
                    'duplicate' => $duplicate,
                ],
                409
            );
        }


        $distanceKm = $this->greatCircleDistanceKm(
            (float) $departureAirport['latitude'],
            (float) $departureAirport['longitude'],
            (float) $arrivalAirport['latitude'],
            (float) $arrivalAirport['longitude']
        );


        try {
            $durationSeconds = $this->calculateDurationSeconds(
                $departureDate,
                $departureTime,
                $departureAirport['timezone_name'] ?? null,
                $arrivalDate,
                $arrivalTime,
                $arrivalAirport['timezone_name'] ?? null
            );
        } catch (Throwable $e) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
                422
            );
        }


        try {
            $stmt = $this->pdo->prepare(
                "
                INSERT INTO ml_flights (
                    user_id,

                    departure_airport_id,
                    arrival_airport_id,

                    departure_date,
                    departure_time,

                    arrival_date,
                    arrival_time,

                    airline_id,
                    aircraft_type_id,

                    flight_number,

                    distance_km,
                    duration_seconds,

                    travel_class,
                    seat_type,
                    travel_reason,

                    notes
                ) VALUES (
                    :user_id,

                    :departure_airport_id,
                    :arrival_airport_id,

                    :departure_date,
                    :departure_time,

                    :arrival_date,
                    :arrival_time,

                    :airline_id,
                    :aircraft_type_id,

                    :flight_number,

                    :distance_km,
                    :duration_seconds,

                    :travel_class,
                    :seat_type,
                    :travel_reason,

                    :notes
                )
                "
            );


            $stmt->execute([
                'user_id' => $userId,

                'departure_airport_id' => $departureAirportId,
                'arrival_airport_id' => $arrivalAirportId,

                'departure_date' => $departureDate,
                'departure_time' => $departureTime,

                'arrival_date' => $arrivalDate,
                'arrival_time' => $arrivalTime,

                'airline_id' => $airlineId,
                'aircraft_type_id' => $aircraftTypeId,

                'flight_number' => $flightNumber,

                'distance_km' => $distanceKm,
                'duration_seconds' => $durationSeconds,

                'travel_class' => $travelClassForDatabase,
                'seat_type' => $seatTypeForDatabase,
                'travel_reason' => $travelReasonForDatabase,

                'notes' => $notes,
            ]);
        } catch (Throwable $e) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Błąd zapisu lotu do bazy danych: ' . $e->getMessage(),
                ],
                422
            );
        }


        $flightId = (int) $this->pdo->lastInsertId();


        return $this->json(
            $response,
            [
                'status' => 'ok',
                'flight_id' => $flightId,
                'distance_km' => $distanceKm,
                'duration_seconds' => $durationSeconds,
                'planned' => $departureDate > date('Y-m-d'),
            ],
            201
        );
    }


    public function update(
        Request $request,
        Response $response,
        array $args
    ): Response {

        $flightId = isset($args['id'])
            ? (int) $args['id']
            : 0;


        $data = $request->getParsedBody();


        if (
            $flightId <= 0
            || !is_array($data)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawne dane edycji lotu.',
                ],
                400
            );
        }


        $userId = $this->authenticatedUserId($response);

        if ($userId instanceof Response) {
            return $userId;
        }


        if (
            !$this->flightBelongsToUser(
                $flightId,
                $userId
            )
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Lot nie istnieje albo nie należy do użytkownika.',
                ],
                404
            );
        }


        $departureAirportId = (int) ($data['departure_airport_id'] ?? 0);
        $arrivalAirportId = (int) ($data['arrival_airport_id'] ?? 0);

        $departureDate = $this->nullableString($data['departure_date'] ?? null);
        $departureTime = $this->nullableTime($data['departure_time'] ?? null);
        $arrivalDate = $this->nullableString($data['arrival_date'] ?? null);
        $arrivalTime = $this->nullableTime($data['arrival_time'] ?? null);

        $airlineId = $this->nullablePositiveInt($data['airline_id'] ?? null);
        $aircraftTypeId = $this->nullablePositiveInt($data['aircraft_type_id'] ?? null);

        $flightNumber = $this->nullableString($data['flight_number'] ?? null);
        $notes = $this->nullableString($data['notes'] ?? null);

        $travelClass = (string) ($data['travel_class'] ?? 'e');
        $seatType = $this->nullableString($data['seat_type'] ?? null);
        $travelReason = (string) ($data['travel_reason'] ?? 'p');

        $force = (bool) ($data['force'] ?? false);


        if (
            $departureAirportId <= 0
            || $arrivalAirportId <= 0
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Wybierz oba lotniska.',
                ],
                422
            );
        }


        if (
            !$departureDate
            || !$this->isIsoDate($departureDate)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Data odlotu jest wymagana i musi być poprawną datą.',
                ],
                422
            );
        }


        if (
            $arrivalDate !== null
            && !$this->isIsoDate($arrivalDate)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawna data przylotu.',
                ],
                422
            );
        }


        if (
            $arrivalTime !== null
            && $arrivalDate === null
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Godzina przylotu wymaga daty przylotu.',
                ],
                422
            );
        }


        if (!in_array($travelClass, ['e', 'p', 'b', 'f'], true)) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawna klasa podróży.',
                ],
                422
            );
        }


        if (
            $seatType !== null
            && !in_array($seatType, ['w', 'm', 'a'], true)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawny typ miejsca.',
                ],
                422
            );
        }


        if (!in_array($travelReason, ['p', 'b'], true)) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawny cel podróży.',
                ],
                422
            );
        }


        $travelClassForDatabase = $this->mapTravelClassToDatabase(
            $travelClass
        );

        $seatTypeForDatabase = $this->mapSeatTypeToDatabase(
            $seatType
        );

        $travelReasonForDatabase = $this->mapTravelReasonToDatabase(
            $travelReason
        );


        if (
            $travelClassForDatabase === null
            || (
                $seatType !== null
                && $seatTypeForDatabase === null
            )
            || $travelReasonForDatabase === null
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Nie udało się dopasować danych klasy, miejsca lub celu podróży do bazy.',
                ],
                422
            );
        }


        $departureAirport = $this->getAirport($departureAirportId);
        $arrivalAirport = $this->getAirport($arrivalAirportId);


        if (!$departureAirport || !$arrivalAirport) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Wybrane lotnisko nie istnieje.',
                ],
                422
            );
        }


        if (
            $airlineId !== null
            && !$this->existsById('ml_airlines', $airlineId)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Wybrana linia lotnicza nie istnieje.',
                ],
                422
            );
        }


        if (
            $aircraftTypeId !== null
            && !$this->existsById('ml_aircraft_types', $aircraftTypeId)
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Wybrany typ samolotu nie istnieje.',
                ],
                422
            );
        }


        $duplicate = $this->findDuplicate(
            $userId,
            $departureAirportId,
            $arrivalAirportId,
            $departureDate,
            $flightNumber,
            $flightId
        );


        if ($duplicate && !$force) {
            return $this->json(
                $response,
                [
                    'status' => 'duplicate',
                    'message' => 'Podobny lot już istnieje.',
                    'duplicate' => $duplicate,
                ],
                409
            );
        }


        $distanceKm = $this->greatCircleDistanceKm(
            (float) $departureAirport['latitude'],
            (float) $departureAirport['longitude'],
            (float) $arrivalAirport['latitude'],
            (float) $arrivalAirport['longitude']
        );


        try {
            $durationSeconds = $this->calculateDurationSeconds(
                $departureDate,
                $departureTime,
                $departureAirport['timezone_name'] ?? null,
                $arrivalDate,
                $arrivalTime,
                $arrivalAirport['timezone_name'] ?? null
            );
        } catch (Throwable $e) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
                422
            );
        }


        try {
            $stmt = $this->pdo->prepare(
                "
                UPDATE ml_flights

                SET
                    departure_airport_id = :departure_airport_id,
                    arrival_airport_id = :arrival_airport_id,

                    departure_date = :departure_date,
                    departure_time = :departure_time,

                    arrival_date = :arrival_date,
                    arrival_time = :arrival_time,

                    airline_id = :airline_id,
                    aircraft_type_id = :aircraft_type_id,

                    flight_number = :flight_number,

                    distance_km = :distance_km,
                    duration_seconds = :duration_seconds,

                    travel_class = :travel_class,
                    seat_type = :seat_type,
                    travel_reason = :travel_reason,

                    notes = :notes

                WHERE
                    id = :flight_id
                    AND user_id = :user_id
                "
            );


            $stmt->execute([
                'departure_airport_id' => $departureAirportId,
                'arrival_airport_id' => $arrivalAirportId,

                'departure_date' => $departureDate,
                'departure_time' => $departureTime,

                'arrival_date' => $arrivalDate,
                'arrival_time' => $arrivalTime,

                'airline_id' => $airlineId,
                'aircraft_type_id' => $aircraftTypeId,

                'flight_number' => $flightNumber,

                'distance_km' => $distanceKm,
                'duration_seconds' => $durationSeconds,

                'travel_class' => $travelClassForDatabase,
                'seat_type' => $seatTypeForDatabase,
                'travel_reason' => $travelReasonForDatabase,

                'notes' => $notes,

                'flight_id' => $flightId,
                'user_id' => $userId,
            ]);
        } catch (Throwable $e) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Błąd zapisu zmian lotu: ' . $e->getMessage(),
                ],
                422
            );
        }


        return $this->json(
            $response,
            [
                'status' => 'ok',
                'flight_id' => $flightId,
                'distance_km' => $distanceKm,
                'duration_seconds' => $durationSeconds,
                'planned' => $departureDate > date('Y-m-d'),
            ]
        );
    }


    public function delete(
        Request $request,
        Response $response,
        array $args
    ): Response {

        $flightId = isset($args['id'])
            ? (int) $args['id']
            : 0;

        $userId = $this->authenticatedUserId($response);

        if ($userId instanceof Response) {
            return $userId;
        }


        if (
            $flightId <= 0
        ) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Niepoprawne dane usuwania lotu.',
                ],
                400
            );
        }


        $stmt = $this->pdo->prepare(
            "
            DELETE FROM ml_flights

            WHERE
                id = :flight_id
                AND user_id = :user_id
            "
        );


        $stmt->execute([
            'flight_id' => $flightId,
            'user_id' => $userId,
        ]);


        if ($stmt->rowCount() !== 1) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Lot nie istnieje albo nie należy do użytkownika.',
                ],
                404
            );
        }


        return $this->json(
            $response,
            [
                'status' => 'ok',
                'deleted_id' => $flightId,
                'message' => 'Lot został usunięty.',
            ]
        );
    }


    private function flightBelongsToUser(
        int $flightId,
        int $userId
    ): bool {

        $stmt = $this->pdo->prepare(
            "
            SELECT 1

            FROM ml_flights

            WHERE
                id = :flight_id
                AND user_id = :user_id

            LIMIT 1
            "
        );


        $stmt->execute([
            'flight_id' => $flightId,
            'user_id' => $userId,
        ]);


        return (bool) $stmt->fetchColumn();
    }


    private function getAirport(
        int $airportId
    ): array|false {

        $stmt = $this->pdo->prepare(
            "
            SELECT
                id,
                iata_code,
                latitude,
                longitude,
                timezone_name

            FROM ml_airports

            WHERE id = :id

            LIMIT 1
            "
        );


        $stmt->execute([
            'id' => $airportId,
        ]);


        return $stmt->fetch();
    }


    private function findDuplicate(
        int $userId,
        int $departureAirportId,
        int $arrivalAirportId,
        string $departureDate,
        ?string $flightNumber,
        ?int $excludeFlightId = null
    ): array|false {

        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.id,
                f.departure_date,
                f.departure_time,
                f.arrival_date,
                f.arrival_time,
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

            WHERE
                f.user_id = :user_id
                AND f.departure_airport_id = :departure_airport_id
                AND f.arrival_airport_id = :arrival_airport_id
                AND f.departure_date = :departure_date
                AND (
                    :flight_number_null IS NULL
                    OR f.flight_number = :flight_number_match
                    OR f.flight_number IS NULL
                )
                AND (
                    :exclude_flight_id_null IS NULL
                    OR f.id <> :exclude_flight_id_match
                )

            ORDER BY
                f.id DESC

            LIMIT 1
            "
        );


        $stmt->execute([
            'user_id' => $userId,
            'departure_airport_id' => $departureAirportId,
            'arrival_airport_id' => $arrivalAirportId,
            'departure_date' => $departureDate,
            'flight_number_null' => $flightNumber,
            'flight_number_match' => $flightNumber,
            'exclude_flight_id_null' => $excludeFlightId,
            'exclude_flight_id_match' => $excludeFlightId,
        ]);


        return $stmt->fetch();
    }


    private function calculateDurationSeconds(
        string $departureDate,
        ?string $departureTime,
        ?string $departureTimezone,
        ?string $arrivalDate,
        ?string $arrivalTime,
        ?string $arrivalTimezone
    ): ?int {

        if (
            $departureTime === null
            || $arrivalDate === null
            || $arrivalTime === null
        ) {
            return null;
        }


        if (
            !$departureTimezone
            || !$arrivalTimezone
        ) {
            return null;
        }


        try {
            $departure = new DateTimeImmutable(
                $departureDate . ' ' . $departureTime,
                new DateTimeZone($departureTimezone)
            );

            $arrival = new DateTimeImmutable(
                $arrivalDate . ' ' . $arrivalTime,
                new DateTimeZone($arrivalTimezone)
            );
        } catch (Throwable) {
            throw new \RuntimeException(
                'Nie udało się zinterpretować daty, godziny lub strefy czasowej.'
            );
        }


        $seconds = $arrival->getTimestamp() - $departure->getTimestamp();


        if ($seconds <= 0) {
            throw new \RuntimeException(
                'Z podanych dat i godzin wynika zerowy lub ujemny czas lotu. Sprawdź datę przylotu.'
            );
        }


        if ($seconds > 48 * 3600) {
            throw new \RuntimeException(
                'Z podanych dat i godzin wynika czas lotu dłuższy niż 48 godzin. Sprawdź dane.'
            );
        }


        return $seconds;
    }


    private function greatCircleDistanceKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): int {

        $earthRadiusKm = 6371.0088;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad)
            * cos($lat2Rad)
            * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );


        return (int) round(
            $earthRadiusKm * $c
        );
    }


    private function existsById(
        string $table,
        int $id
    ): bool {

        if (!in_array(
            $table,
            [
                'ml_airlines',
                'ml_aircraft_types',
            ],
            true
        )) {
            return false;
        }


        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM {$table} WHERE id = :id LIMIT 1"
        );


        $stmt->execute([
            'id' => $id,
        ]);


        return (bool) $stmt->fetchColumn();
    }


    private function mapSeatTypeToDatabase(
        ?string $code
    ): ?string {

        if ($code === null) {
            return null;
        }


        $allowedValues = $this->getEnumValues(
            'ml_flights',
            'seat_type'
        );


        if ($allowedValues === []) {
            return $code;
        }


        $aliases = [
            'w' => [
                'w',
                'window',
                'window_seat',
                'window seat',
                'okno',
            ],

            'm' => [
                'm',
                'middle',
                'middle_seat',
                'middle seat',
                'center',
                'centre',
                'środek',
                'srodek',
            ],

            'a' => [
                'a',
                'aisle',
                'aisle_seat',
                'aisle seat',
                'przejście',
                'przejscie',
            ],
        ];


        return $this->matchEnumAlias(
            $allowedValues,
            $aliases[$code] ?? []
        );
    }


    private function mapTravelReasonToDatabase(
        string $code
    ): ?string {

        $allowedValues = $this->getEnumValues(
            'ml_flights',
            'travel_reason'
        );


        if ($allowedValues === []) {
            return $code;
        }


        $aliases = [
            'p' => [
                'p',
                'private',
                'personal',
                'leisure',
                'prywatny',
            ],

            'b' => [
                'b',
                'business',
                'biznesowy',
            ],
        ];


        return $this->matchEnumAlias(
            $allowedValues,
            $aliases[$code] ?? []
        );
    }


    private function matchEnumAlias(
        array $allowedValues,
        array $candidates
    ): ?string {

        foreach ($candidates as $candidate) {
            foreach ($allowedValues as $allowedValue) {
                if (strcasecmp(
                    (string) $candidate,
                    (string) $allowedValue
                ) === 0) {
                    return (string) $allowedValue;
                }
            }
        }


        return null;
    }


    private function mapTravelClassToDatabase(
        string $code
    ): ?string {

        $allowedValues = $this->getEnumValues(
            'ml_flights',
            'travel_class'
        );


        if ($allowedValues === []) {
            return $code;
        }


        $aliases = [
            'e' => [
                'e',
                'economy',
                'economic',
                'economy_class',
                'economy class',
            ],

            'p' => [
                'p',
                'premium',
                'premium_economy',
                'premium economy',
                'economy_plus',
                'economy plus',
            ],

            'b' => [
                'b',
                'business',
                'business_class',
                'business class',
            ],

            'f' => [
                'f',
                'first',
                'first_class',
                'first class',
            ],
        ];


        foreach ($aliases[$code] ?? [] as $candidate) {
            foreach ($allowedValues as $allowedValue) {
                if (strcasecmp(
                    $candidate,
                    $allowedValue
                ) === 0) {
                    return $allowedValue;
                }
            }
        }


        return null;
    }


    private function getEnumValues(
        string $table,
        string $column
    ): array {

        if (
            $table !== 'ml_flights'
            || !in_array(
                $column,
                [
                    'travel_class',
                    'seat_type',
                    'travel_reason',
                ],
                true
            )
        ) {
            return [];
        }


        $stmt = $this->pdo->query(
            "
            SHOW COLUMNS
            FROM ml_flights
            LIKE " . $this->pdo->quote($column) . "
            "
        );


        $definition = $stmt->fetch();


        if (
            !$definition
            || !isset($definition['Type'])
            || !is_string($definition['Type'])
        ) {
            return [];
        }


        if (!preg_match(
            '/^enum\((.*)\)$/i',
            $definition['Type'],
            $match
        )) {
            return [];
        }


        preg_match_all(
            "/'((?:[^'\\\\]|\\\\.)*)'/",
            $match[1],
            $values
        );


        return array_map(
            static fn (string $value): string =>
                stripcslashes($value),
            $values[1] ?? []
        );
    }


    private function nullablePositiveInt(
        mixed $value
    ): ?int {

        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }


        $number = (int) $value;


        return $number > 0
            ? $number
            : null;
    }


    private function nullableString(
        mixed $value
    ): ?string {

        if ($value === null) {
            return null;
        }


        $value = trim(
            (string) $value
        );


        return $value !== ''
            ? $value
            : null;
    }


    private function nullableTime(
        mixed $value
    ): ?string {

        $value = $this->nullableString(
            $value
        );


        if ($value === null) {
            return null;
        }


        if (preg_match(
            '/^\d{2}:\d{2}$/',
            $value
        )) {
            $value .= ':00';
        }


        if (!preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',
            $value
        )) {
            return null;
        }


        return $value;
    }


    private function isIsoDate(
        string $value
    ): bool {

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );


        return $date !== false
            && $date->format('Y-m-d') === $value;
    }


    private function authenticatedUserId(
        Response $response
    ): int|Response {
        try {
            return $this->auth->requireUserId();
        } catch (\RuntimeException $e) {
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
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
            )
        );


        return $response
            ->withStatus($status)
            ->withHeader(
                'Content-Type',
                'application/json'
            );
    }
}
