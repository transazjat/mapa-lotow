<?php


declare(strict_types=1);


namespace Transazja\MapaLotowApi\Controller;


use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


class FlightController
{
    public function __construct(
        private PDO $pdo
    ) {
    }


    public function index(
        Request $request,
        Response $response
    ): Response {

        $queryParams = $request->getQueryParams();


        $userId = isset($queryParams['user_id'])
            ? (int) $queryParams['user_id']
            : 0;


        if ($userId <= 0) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Missing or invalid user_id',
                ],
                400
            );
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

            LIMIT 1
            "
        );


        $stmt->execute([
            'flight_id' => $flightId,
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