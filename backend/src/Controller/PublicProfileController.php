<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PublicProfileController
{
    public function __construct(
        private PDO $pdo,
        private string $appUrl
    ) {
    }

    public function publicProfile(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $slug = trim((string) ($args['slug'] ?? ''));

        if ($slug === '') {
            return $this->error($response, 'Nie znaleziono profilu.', 404);
        }

        $stmt = $this->pdo->prepare(
            "
            SELECT id, email, nick, privacy_mode, public_slug
            FROM ml_users
            WHERE public_slug = :slug
              AND privacy_mode = 'public'
              AND email_verified_at IS NOT NULL
              AND is_active = 1
            LIMIT 1
            "
        );
        $stmt->execute(['slug' => $slug]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->error($response, 'Nie znaleziono publicznego profilu.', 404);
        }

        return $this->mapResponse($response, $user, 'public');
    }

    public function sharedMap(
        Request $request,
        Response $response,
        array $args
    ): Response {
        $token = trim((string) ($args['token'] ?? ''));

        if ($token === '') {
            return $this->error($response, 'Nie znaleziono udostępnionej mapy.', 404);
        }

        $stmt = $this->pdo->prepare(
            "
            SELECT id, email, nick, privacy_mode, public_slug
            FROM ml_users
            WHERE share_token = :token
              AND privacy_mode = 'link'
              AND email_verified_at IS NOT NULL
              AND is_active = 1
            LIMIT 1
            "
        );
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->error($response, 'Link jest nieprawidłowy albo został unieważniony.', 404);
        }

        return $this->mapResponse($response, $user, 'link');
    }

    private function mapResponse(
        Response $response,
        array $user,
        string $accessMode
    ): Response {
        $flights = $this->loadFlights((int) $user['id']);
        $email = (string) ($user['email'] ?? '');
        $nick = (string) ($user['nick'] ?? 'Użytkownik');

        return $this->json($response, [
            'status' => 'ok',
            'access_mode' => $accessMode,
            'profile' => [
                'nick' => $nick,
                'avatar_url' => $this->gravatarUrl($email),
                'public_url' => !empty($user['public_slug'])
                    ? $this->appUrl . '/profil/' . rawurlencode((string) $user['public_slug'])
                    : null,
            ],
            'count' => count($flights),
            'flights' => $flights,
        ]);
    }

    private function loadFlights(int $userId): array
    {
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
                COALESCE(dep_country.name, dep.country_name) AS departure_country,
                dep_country.iso2 AS departure_country_code,
                dep_country.continent_code AS departure_continent_code,
                dep.latitude AS departure_latitude,
                dep.longitude AS departure_longitude,

                arr.id AS arrival_airport_id,
                arr.iata_code AS arrival_iata,
                arr.name AS arrival_airport_name,
                arr.city AS arrival_city,
                COALESCE(arr_country.name, arr.country_name) AS arrival_country,
                arr_country.iso2 AS arrival_country_code,
                arr_country.continent_code AS arrival_continent_code,
                arr.latitude AS arrival_latitude,
                arr.longitude AS arrival_longitude,

                al.id AS airline_id,
                al.name AS airline_name,
                ac.id AS aircraft_type_id,
                ac.name AS aircraft_name,

                CASE
                    WHEN dep.id = arr.id THEN 'other'
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
            JOIN ml_airports dep ON dep.id = f.departure_airport_id
            JOIN ml_airports arr ON arr.id = f.arrival_airport_id
            LEFT JOIN ml_countries dep_country ON dep_country.id = dep.country_id
            LEFT JOIN ml_countries arr_country ON arr_country.id = arr.country_id
            LEFT JOIN ml_airlines al ON al.id = f.airline_id
            LEFT JOIN ml_aircraft_types ac ON ac.id = f.aircraft_type_id
            WHERE f.user_id = :user_id
            ORDER BY f.departure_date DESC, f.departure_time DESC, f.id DESC
            "
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private function gravatarUrl(string $email): string
    {
        return 'https://www.gravatar.com/avatar/'
            . md5(mb_strtolower(trim($email), 'UTF-8'))
            . '?s=160&d=404&r=g';
    }

    private function error(Response $response, string $message, int $status): Response
    {
        return $this->json($response, [
            'status' => 'error',
            'message' => $message,
        ], $status);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
