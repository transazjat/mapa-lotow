<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserOverviewController
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function show(
        Request $request,
        Response $response,
        array $args
    ): Response {

        $userId = isset($args['id'])
            ? (int) $args['id']
            : 0;

        if ($userId <= 0) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'Invalid user id',
                ],
                400
            );
        }

        $stmt = $this->pdo->prepare(
            "
            SELECT
                COUNT(*) AS flights,

                SUM(distance_km) AS distance_km,

                SUM(duration_seconds) AS duration_seconds,

                COUNT(DISTINCT departure_airport_id)
                    + COUNT(DISTINCT arrival_airport_id)
                    AS airports_raw,

                COUNT(DISTINCT airline_id) AS airlines,

                COUNT(DISTINCT aircraft_type_id) AS aircraft_types

            FROM ml_flights

            WHERE user_id = :user_id
            "
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        $stats = $stmt->fetch();

        if (!$stats || (int) $stats['flights'] === 0) {
            return $this->json(
                $response,
                [
                    'status' => 'error',
                    'message' => 'User has no flights',
                ],
                404
            );
        }

        /*
         * Lotniska musimy policzyć jako sumę zbiorów
         * departure + arrival, a nie dodawać dwa COUNT DISTINCT,
         * bo to samo lotnisko może występować po obu stronach.
         */

        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(DISTINCT airport_id) AS airports
            FROM (
                SELECT departure_airport_id AS airport_id
                FROM ml_flights
                WHERE user_id = :user_id_1

                UNION

                SELECT arrival_airport_id AS airport_id
                FROM ml_flights
                WHERE user_id = :user_id_2
            ) a
            "
        );

        $stmt->execute([
            'user_id_1' => $userId,
            'user_id_2' => $userId,
        ]);

        $airports = (int) $stmt->fetchColumn();

        /*
         * Liczba kierunkowych tras.
         * WAW-DOH i DOH-WAW to dwie różne trasy.
         */

        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(*) AS routes
            FROM (
                SELECT
                    departure_airport_id,
                    arrival_airport_id
                FROM ml_flights
                WHERE user_id = :user_id
                GROUP BY
                    departure_airport_id,
                    arrival_airport_id
            ) r
            "
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        $routes = (int) $stmt->fetchColumn();

        /*
         * Liczba odwiedzonych krajów.
         */

        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(DISTINCT country_id) AS countries
            FROM (
                SELECT dep.country_id
                FROM ml_flights f
                JOIN ml_airports dep
                    ON dep.id = f.departure_airport_id
                WHERE f.user_id = :user_id_1

                UNION

                SELECT arr.country_id
                FROM ml_flights f
                JOIN ml_airports arr
                    ON arr.id = f.arrival_airport_id
                WHERE f.user_id = :user_id_2
            ) c
            WHERE country_id IS NOT NULL
            "
        );

        $stmt->execute([
            'user_id_1' => $userId,
            'user_id_2' => $userId,
        ]);

        $countries = (int) $stmt->fetchColumn();

        /*
         * Klasyfikacja lotów:
         *
         * scenic/other:
         *   to samo lotnisko
         *
         * domestic:
         *   różne lotniska, ten sam kraj
         *
         * continental:
         *   różne kraje, ten sam kontynent
         *
         * intercontinental:
         *   różne kontynenty
         */

        $stmt = $this->pdo->prepare(
            "
            SELECT

                SUM(
                    dep.id = arr.id
                ) AS other_flights,

                SUM(
                    dep.id <> arr.id
                    AND dep.country_id = arr.country_id
                ) AS domestic_flights,

                SUM(
                    dep.id <> arr.id
                    AND dep.country_id <> arr.country_id
                    AND dep_country.continent_code = arr_country.continent_code
                ) AS continental_flights,

                SUM(
                    dep.id <> arr.id
                    AND dep.country_id <> arr.country_id
                    AND dep_country.continent_code <> arr_country.continent_code
                ) AS intercontinental_flights

            FROM ml_flights f

            JOIN ml_airports dep
                ON dep.id = f.departure_airport_id

            JOIN ml_airports arr
                ON arr.id = f.arrival_airport_id

            LEFT JOIN ml_countries dep_country
                ON dep_country.id = dep.country_id

            LEFT JOIN ml_countries arr_country
                ON arr_country.id = arr.country_id

            WHERE f.user_id = :user_id
            "
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        $flightTypes = $stmt->fetch();

        $data = [
            'status' => 'ok',

            'user_id' => $userId,

            'stats' => [
                'flights' => (int) $stats['flights'],

                'distance_km' => (int) ($stats['distance_km'] ?? 0),

                'duration_seconds' => (int) ($stats['duration_seconds'] ?? 0),

                'airports' => $airports,

                'airlines' => (int) $stats['airlines'],

                'aircraft_types' => (int) $stats['aircraft_types'],

                'routes' => $routes,

                'countries' => $countries,

                'flight_types' => [
                    'domestic' => (int) $flightTypes['domestic_flights'],
                    'continental' => (int) $flightTypes['continental_flights'],
                    'intercontinental' => (int) $flightTypes['intercontinental_flights'],
                    'other' => (int) $flightTypes['other_flights'],
                ],
            ],
        ];

        return $this->json(
            $response,
            $data
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
            ->withHeader('Content-Type', 'application/json');
    }
}