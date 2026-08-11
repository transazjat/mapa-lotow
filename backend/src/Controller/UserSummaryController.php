<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserSummaryController
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

        $data = [
            'status' => 'ok',
            'user_id' => $userId,

            'summary' => [
                'airports' => $this->getAirports($userId),
                'countries' => $this->getCountries($userId),
                'airlines' => $this->getAirlines($userId),
                'aircraft' => $this->getAircraft($userId),
                'routes' => $this->getRoutes($userId),
            ],
        ];

        return $this->json(
            $response,
            $data
        );
    }

    private function getAirports(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                a.id,
                a.iata_code,
                a.name,
                a.city,
                c.name_pl AS country,
                COUNT(*) AS flights

            FROM (
                SELECT
                    departure_airport_id AS airport_id
                FROM ml_flights
                WHERE user_id = :user_id_1

                UNION ALL

                SELECT
                    arrival_airport_id AS airport_id
                FROM ml_flights
                WHERE user_id = :user_id_2
            ) x

            JOIN ml_airports a
                ON a.id = x.airport_id

            LEFT JOIN ml_countries c
                ON c.id = a.country_id

            GROUP BY
                a.id,
                a.iata_code,
                a.name,
                a.city,
                c.name_pl

            ORDER BY
                flights DESC,
                a.iata_code

            LIMIT 20
            "
        );

        $stmt->execute([
            'user_id_1' => $userId,
            'user_id_2' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    private function getCountries(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                c.id,
                c.name_pl AS name,
                c.iso2,
                c.continent_code,
                COUNT(*) AS flights

            FROM (
                SELECT
                    dep.country_id

                FROM ml_flights f

                JOIN ml_airports dep
                    ON dep.id = f.departure_airport_id

                WHERE f.user_id = :user_id_1

                UNION ALL

                SELECT
                    arr.country_id

                FROM ml_flights f

                JOIN ml_airports arr
                    ON arr.id = f.arrival_airport_id

                WHERE f.user_id = :user_id_2
            ) x

            JOIN ml_countries c
                ON c.id = x.country_id

            GROUP BY
                c.id,
                c.name_pl,
                c.iso2,
                c.continent_code

            ORDER BY
                flights DESC,
                c.name_pl

            LIMIT 20
            "
        );

        $stmt->execute([
            'user_id_1' => $userId,
            'user_id_2' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    private function getAirlines(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                al.id,
                al.name,
                al.iata_code,
                al.icao_code,
                COUNT(*) AS flights

            FROM ml_flights f

            JOIN ml_airlines al
                ON al.id = f.airline_id

            WHERE f.user_id = :user_id

            GROUP BY
                al.id,
                al.name,
                al.iata_code,
                al.icao_code

            ORDER BY
                flights DESC,
                al.name

            LIMIT 20
            "
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    private function getAircraft(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                ac.id,
                ac.name,
                ac.family,
                COUNT(*) AS flights

            FROM ml_flights f

            JOIN ml_aircraft_types ac
                ON ac.id = f.aircraft_type_id

            WHERE f.user_id = :user_id
            AND ac.name IS NOT NULL
            AND ac.name <> ''

            GROUP BY
                ac.id,
                ac.name,
                ac.family

            ORDER BY
                flights DESC,
                ac.name

            LIMIT 20
            "
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    private function getRoutes(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                dep.id AS departure_airport_id,
                dep.iata_code AS departure_iata,

                arr.id AS arrival_airport_id,
                arr.iata_code AS arrival_iata,

                COUNT(*) AS flights

            FROM ml_flights f

            JOIN ml_airports dep
                ON dep.id = f.departure_airport_id

            JOIN ml_airports arr
                ON arr.id = f.arrival_airport_id

            WHERE f.user_id = :user_id

            GROUP BY
                dep.id,
                dep.iata_code,
                arr.id,
                arr.iata_code

            ORDER BY
                flights DESC,
                dep.iata_code,
                arr.iata_code

            LIMIT 20
            "
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
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