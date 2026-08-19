<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Transazja\MapaLotowApi\Security\AuthService;
use ZipArchive;

final class AccountExportController
{
    public function __construct(
        private PDO $pdo,
        private AuthService $auth
    ) {
    }

    public function export(
        Request $request,
        Response $response,
        array $args
    ): Response {
        try {
            $userId = $this->auth->requireUserId();
        } catch (\RuntimeException) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Musisz się zalogować.',
            ], 401);
        }

        $format = mb_strtolower(
            trim((string) ($args['format'] ?? '')),
            'UTF-8'
        );

        if (!in_array($format, ['csv', 'xlsx', 'json'], true)) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Nieobsługiwany format eksportu.',
            ], 422);
        }

        $profile = $this->profile($userId);
        $flights = $this->flights($userId);
        $stamp = date('Ymd-His');

        if ($format === 'json') {
            return $this->exportJson(
                $response,
                $profile,
                $flights,
                $stamp
            );
        }

        if ($format === 'xlsx') {
            return $this->exportXlsx(
                $response,
                $profile,
                $flights,
                $stamp
            );
        }

        return $this->exportCsv(
            $response,
            $flights,
            $stamp
        );
    }

    private function exportJson(
        Response $response,
        array $profile,
        array $flights,
        string $stamp
    ): Response {
        $filename = 'mapa-lotow-dane-' . $stamp . '.json';

        $cleanFlights = array_map(
            static function (array $flight): array {
                unset(
                    $flight['aircraft_registration'],
                    $flight['seat_number']
                );

                return $flight;
            },
            $flights
        );

        $payload = [
            'application' => 'Mapa Lotów',
            'exported_at' => date(DATE_ATOM),
            'profile' => $profile,
            'count' => count($cleanFlights),
            'flights' => $cleanFlights,
        ];

        $response->getBody()->write(
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
            )
        );

        return $response
            ->withHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            )
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . $filename . '"'
            );
    }

    private function exportCsv(
        Response $response,
        array $flights,
        string $stamp
    ): Response {
        $filename = 'mapa-lotow-loty-' . $stamp . '.csv';

        $stream = fopen('php://temp', 'w+');

        if ($stream === false) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Nie udało się przygotować pliku CSV.',
            ], 500);
        }

        // UTF-8 BOM - Excel na Windows prawidłowo rozpoznaje polskie znaki.
        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv(
            $stream,
            $this->csvFlightHeaders(),
            ';',
            '"',
            ''
        );

        foreach ($flights as $flight) {
            fputcsv(
                $stream,
                $this->flightRow($flight),
                ';',
                '"',
                ''
            );
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $response->getBody()->write($csv ?: '');

        return $response
            ->withHeader(
                'Content-Type',
                'text/csv; charset=utf-8'
            )
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . $filename . '"'
            );
    }

    private function exportXlsx(
        Response $response,
        array $profile,
        array $flights,
        string $stamp
    ): Response {
        if (!class_exists(ZipArchive::class)) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Eksport XLSX wymaga włączonego rozszerzenia PHP ZIP.',
            ], 500);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'mapa-lotow-xlsx-');

        if ($tempFile === false) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Nie udało się utworzyć pliku tymczasowego XLSX.',
            ], 500);
        }

        $zip = new ZipArchive();

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);

            return $this->json($response, [
                'status' => 'error',
                'message' => 'Nie udało się przygotować pliku XLSX.',
            ], 500);
        }

        $lotyRows = [
            $this->xlsxFlightHeaders(),
        ];

        foreach ($flights as $flight) {
            $lotyRows[] = $this->flightRow($flight);
        }

        $today = date('Y-m-d');
        $planned = 0;
        $completed = 0;
        $distanceKm = 0.0;
        $durationSeconds = 0;

        foreach ($flights as $flight) {
            $departureDate = (string) ($flight['departure_date'] ?? '');

            if ($departureDate !== '' && $departureDate > $today) {
                $planned++;
            } else {
                $completed++;
            }

            $distanceKm += (float) ($flight['distance_km'] ?? 0);
            $durationSeconds += (int) ($flight['duration_seconds'] ?? 0);
        }

        $summaryRows = [
            ['Mapa Lotów - podsumowanie', ''],
            ['Data eksportu', date('Y-m-d H:i:s')],
            ['Nick', (string) ($profile['nick'] ?? '')],
            ['Liczba wszystkich lotów', count($flights)],
            ['Loty odbyte', $completed],
            ['Loty zaplanowane', $planned],
            ['Łączny dystans [km]', round($distanceKm, 2)],
            ['Łączny czas lotów', $this->formatDuration($durationSeconds)],
        ];

        $profileRows = [
            ['Pole', 'Wartość'],
            ['ID użytkownika', $profile['id'] ?? ''],
            ['Nick', $profile['nick'] ?? ''],
            ['E-mail', $profile['email'] ?? ''],
            ['Prywatność mapy', $profile['privacy_mode'] ?? ''],
            ['Publiczny identyfikator', $profile['public_slug'] ?? ''],
            ['Data utworzenia konta', $profile['created_at'] ?? ''],
            ['Ostatnie logowanie', $profile['last_login_at'] ?? ''],
        ];

        $statisticsRows = $this->statisticsRows(
            $profile,
            $flights
        );

        $zip->addFromString(
            '[Content_Types].xml',
            $this->xlsxContentTypes()
        );
        $zip->addFromString(
            '_rels/.rels',
            $this->xlsxRootRelationships()
        );
        $zip->addFromString(
            'docProps/app.xml',
            $this->xlsxAppProperties()
        );
        $zip->addFromString(
            'docProps/core.xml',
            $this->xlsxCoreProperties()
        );
        $zip->addFromString(
            'xl/workbook.xml',
            $this->xlsxWorkbook()
        );
        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            $this->xlsxWorkbookRelationships()
        );
        $zip->addFromString(
            'xl/styles.xml',
            $this->xlsxStyles()
        );
        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            $this->xlsxWorksheet(
                $lotyRows,
                true,
                [8, 13, 12, 13, 12, 10, 34, 18, 18, 10, 34, 18, 18, 26, 11, 14, 28, 13, 17, 12, 12, 16, 38]
            )
        );
        $zip->addFromString(
            'xl/worksheets/sheet2.xml',
            $this->xlsxWorksheet(
                $summaryRows,
                false,
                [28, 28],
                1
            )
        );
        $zip->addFromString(
            'xl/worksheets/sheet3.xml',
            $this->xlsxWorksheet(
                $profileRows,
                false,
                [28, 42]
            )
        );
        $zip->addFromString(
            'xl/worksheets/sheet4.xml',
            $this->xlsxWorksheet(
                $statisticsRows,
                false,
                [30, 36, 16, 18, 18, 16, 16],
                1
            )
        );

        $zip->close();

        $xlsx = file_get_contents($tempFile);
        @unlink($tempFile);

        if ($xlsx === false) {
            return $this->json($response, [
                'status' => 'error',
                'message' => 'Nie udało się odczytać przygotowanego pliku XLSX.',
            ], 500);
        }

        $filename = 'mapa-lotow-dane-' . $stamp . '.xlsx';

        $response->getBody()->write($xlsx);

        return $response
            ->withHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            )
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . $filename . '"'
            )
            ->withHeader(
                'Content-Length',
                (string) strlen($xlsx)
            );
    }

    private function csvFlightHeaders(): array
    {
        return [
            'ID',
            'Departure date',
            'Departure time',
            'Arrival date',
            'Arrival time',
            'From IATA',
            'Departure airport',
            'Departure city',
            'Departure country',
            'To IATA',
            'Arrival airport',
            'Arrival city',
            'Arrival country',
            'Airline',
            'Airline code',
            'Flight number',
            'Aircraft',
            'Distance km',
            'Duration seconds',
            'Travel class',
            'Seat type',
            'Travel purpose',
            'Notes',
        ];
    }

    private function xlsxFlightHeaders(): array
    {
        return [
            'ID',
            'Data wylotu',
            'Godzina wylotu',
            'Data przylotu',
            'Godzina przylotu',
            'Z IATA',
            'Z lotniska',
            'Z miasta',
            'Z kraju',
            'Do IATA',
            'Do lotniska',
            'Do miasta',
            'Do kraju',
            'Linia lotnicza',
            'Kod linii',
            'Numer lotu',
            'Samolot',
            'Dystans km',
            'Czas lotu sekundy',
            'Klasa',
            'Typ miejsca',
            'Cel podróży',
            'Uwagi',
        ];
    }

    private function flightRow(array $flight): array
    {
        return [
            $flight['id'] ?? '',
            $flight['departure_date'] ?? '',
            $flight['departure_time'] ?? '',
            $flight['arrival_date'] ?? '',
            $flight['arrival_time'] ?? '',
            $flight['departure_iata'] ?? '',
            $flight['departure_airport_name'] ?? '',
            $flight['departure_city'] ?? '',
            $flight['departure_country'] ?? '',
            $flight['arrival_iata'] ?? '',
            $flight['arrival_airport_name'] ?? '',
            $flight['arrival_city'] ?? '',
            $flight['arrival_country'] ?? '',
            $flight['airline_name'] ?? '',
            $flight['airline_iata'] ?? '',
            $flight['flight_number'] ?? '',
            $flight['aircraft_name'] ?? '',
            $flight['distance_km'] ?? '',
            $flight['duration_seconds'] ?? '',
            $flight['travel_class'] ?? '',
            $flight['seat_type'] ?? '',
            $flight['travel_reason'] ?? '',
            $flight['notes'] ?? '',
        ];
    }

    private function statisticsRows(
        array $profile,
        array $flights
    ): array {
        $rows = [];

        $today = date('Y-m-d');

        $totalFlights = count($flights);
        $completed = 0;
        $planned = 0;
        $distanceKm = 0.0;
        $durationSeconds = 0;

        $airports = [];
        $countries = [];
        $airlines = [];
        $aircraftTypes = [];
        $aircraftFamilies = [];
        $routes = [];

        $flightTypes = [
            'domestic' => [
                'label' => 'Krajowe',
                'flights' => 0,
                'distance' => 0.0,
                'duration' => 0,
            ],
            'continental' => [
                'label' => 'Kontynentalne',
                'flights' => 0,
                'distance' => 0.0,
                'duration' => 0,
            ],
            'intercontinental' => [
                'label' => 'Międzykontynentalne',
                'flights' => 0,
                'distance' => 0.0,
                'duration' => 0,
            ],
            'other' => [
                'label' => 'Widokowe / inne',
                'flights' => 0,
                'distance' => 0.0,
                'duration' => 0,
            ],
        ];

        foreach ($flights as $flight) {
            $departureDate = (string) ($flight['departure_date'] ?? '');

            if ($departureDate !== '' && $departureDate > $today) {
                $planned++;
            } else {
                $completed++;
            }

            $distance = (float) ($flight['distance_km'] ?? 0);
            $duration = (int) ($flight['duration_seconds'] ?? 0);

            $distanceKm += $distance;
            $durationSeconds += $duration;

            $type = (string) ($flight['flight_type'] ?? 'other');

            if (!isset($flightTypes[$type])) {
                $type = 'other';
            }

            $flightTypes[$type]['flights']++;
            $flightTypes[$type]['distance'] += $distance;
            $flightTypes[$type]['duration'] += $duration;

            $this->addAirportStatistic(
                $airports,
                (string) ($flight['departure_airport_id'] ?? ''),
                (string) ($flight['departure_iata'] ?? ''),
                (string) ($flight['departure_airport_name'] ?? ''),
                (string) ($flight['departure_city'] ?? ''),
                (string) ($flight['departure_country'] ?? ''),
                true
            );

            $this->addAirportStatistic(
                $airports,
                (string) ($flight['arrival_airport_id'] ?? ''),
                (string) ($flight['arrival_iata'] ?? ''),
                (string) ($flight['arrival_airport_name'] ?? ''),
                (string) ($flight['arrival_city'] ?? ''),
                (string) ($flight['arrival_country'] ?? ''),
                false
            );

            $this->addCountryStatistic(
                $countries,
                (string) ($flight['departure_country_code'] ?? ''),
                (string) ($flight['departure_country'] ?? '')
            );

            $this->addCountryStatistic(
                $countries,
                (string) ($flight['arrival_country_code'] ?? ''),
                (string) ($flight['arrival_country'] ?? '')
            );

            $airlineName = trim((string) ($flight['airline_name'] ?? ''));

            if ($airlineName !== '') {
                if (!isset($airlines[$airlineName])) {
                    $airlines[$airlineName] = 0;
                }

                $airlines[$airlineName]++;
            }

            $aircraftName = trim((string) ($flight['aircraft_name'] ?? ''));

            if ($aircraftName !== '') {
                if (!isset($aircraftTypes[$aircraftName])) {
                    $aircraftTypes[$aircraftName] = 0;
                }

                $aircraftTypes[$aircraftName]++;

                $family = $this->detectAircraftFamily($aircraftName);

                if (!isset($aircraftFamilies[$family])) {
                    $aircraftFamilies[$family] = 0;
                }

                $aircraftFamilies[$family]++;
            }

            $routeKey = (string) ($flight['departure_airport_id'] ?? '')
                . '>'
                . (string) ($flight['arrival_airport_id'] ?? '');

            if (!isset($routes[$routeKey])) {
                $routes[$routeKey] = [
                    'departure_iata' => (string) ($flight['departure_iata'] ?? ''),
                    'departure_city' => (string) ($flight['departure_city'] ?? ''),
                    'departure_airport' => (string) ($flight['departure_airport_name'] ?? ''),
                    'arrival_iata' => (string) ($flight['arrival_iata'] ?? ''),
                    'arrival_city' => (string) ($flight['arrival_city'] ?? ''),
                    'arrival_airport' => (string) ($flight['arrival_airport_name'] ?? ''),
                    'flights' => 0,
                    'distance' => $distance,
                    'total_distance' => 0.0,
                ];
            }

            $routes[$routeKey]['flights']++;
            $routes[$routeKey]['total_distance'] += $distance;
        }

        $rows[] = ['Statystyki Mapy Lotów', '', '', '', '', ''];
        $rows[] = ['Nick', (string) ($profile['nick'] ?? ''), '', '', '', ''];
        $rows[] = ['Data eksportu', date('Y-m-d H:i:s'), '', '', '', ''];
        $rows[] = ['', '', '', '', '', ''];

        $rows[] = ['PODSUMOWANIE', 'Wartość', '', '', '', ''];
        $rows[] = ['Wszystkie loty', $totalFlights, '', '', '', ''];
        $rows[] = ['Loty odbyte', $completed, '', '', '', ''];
        $rows[] = ['Loty zaplanowane', $planned, '', '', '', ''];
        $rows[] = ['Łączny dystans [km]', round($distanceKm, 2), '', '', '', ''];
        $rows[] = ['Łączny czas lotów', $this->formatDuration($durationSeconds), '', '', '', ''];
        $rows[] = ['Lotniska', count($airports), '', '', '', ''];
        $rows[] = ['Państwa', count($countries), '', '', '', ''];
        $rows[] = ['Linie lotnicze', count($airlines), '', '', '', ''];
        $rows[] = ['Typy samolotów', count($aircraftTypes), '', '', '', ''];
        $rows[] = ['Trasy kierunkowe', count($routes), '', '', '', ''];
        $rows[] = ['', '', '', '', '', ''];

        $rows[] = ['TYPY LOTÓW', 'Loty', 'Udział [%]', 'Dystans [km]', 'Czas', ''];
        foreach ($flightTypes as $stats) {
            $share = $totalFlights > 0
                ? ($stats['flights'] / $totalFlights) * 100
                : 0;

            $rows[] = [
                $stats['label'],
                $stats['flights'],
                round($share, 2),
                round($stats['distance'], 2),
                $this->formatDuration((int) $stats['duration']),
                '',
            ];
        }

        $rows[] = ['', '', '', '', '', ''];

        uasort(
            $airports,
            static fn (array $a, array $b): int =>
                ($b['operations'] <=> $a['operations'])
                ?: strcmp($a['name'], $b['name'])
        );

        $rows[] = ['LOTNISKA', 'Kod IATA', 'Miasto', 'Państwo', 'Odloty', 'Przyloty', 'Operacje'];
        foreach ($airports as $airport) {
            $rows[] = [
                $airport['name'],
                $airport['iata'],
                $airport['city'],
                $airport['country'],
                $airport['departures'],
                $airport['arrivals'],
                $airport['operations'],
            ];
        }

        $rows[] = ['', '', '', '', '', '', ''];

        uasort(
            $countries,
            static fn (array $a, array $b): int =>
                ($b['operations'] <=> $a['operations'])
                ?: strcmp($a['name'], $b['name'])
        );

        $totalOperations = $totalFlights * 2;

        $rows[] = ['PAŃSTWA', 'Kod', 'Operacje', 'Udział [%]', '', '', ''];
        foreach ($countries as $country) {
            $share = $totalOperations > 0
                ? ($country['operations'] / $totalOperations) * 100
                : 0;

            $rows[] = [
                $country['name'],
                $country['code'],
                $country['operations'],
                round($share, 2),
                '',
                '',
                '',
            ];
        }

        $rows[] = ['', '', '', '', '', '', ''];

        arsort($airlines, SORT_NUMERIC);

        $rows[] = ['LINIE LOTNICZE', 'Loty', 'Udział [%]', '', '', '', ''];
        foreach ($airlines as $name => $count) {
            $share = $totalFlights > 0
                ? ($count / $totalFlights) * 100
                : 0;

            $rows[] = [
                $name,
                $count,
                round($share, 2),
                '',
                '',
                '',
                '',
            ];
        }

        $rows[] = ['', '', '', '', '', '', ''];

        arsort($aircraftTypes, SORT_NUMERIC);

        $rows[] = ['TYPY SAMOLOTÓW', 'Loty', 'Udział [%]', '', '', '', ''];
        foreach ($aircraftTypes as $name => $count) {
            $share = $totalFlights > 0
                ? ($count / $totalFlights) * 100
                : 0;

            $rows[] = [
                $name,
                $count,
                round($share, 2),
                '',
                '',
                '',
                '',
            ];
        }

        $rows[] = ['', '', '', '', '', '', ''];

        arsort($aircraftFamilies, SORT_NUMERIC);

        $rows[] = ['RODZINY SAMOLOTÓW', 'Loty', 'Udział [%]', '', '', '', ''];
        foreach ($aircraftFamilies as $name => $count) {
            $share = $totalFlights > 0
                ? ($count / $totalFlights) * 100
                : 0;

            $rows[] = [
                $name,
                $count,
                round($share, 2),
                '',
                '',
                '',
                '',
            ];
        }

        $rows[] = ['', '', '', '', '', '', ''];

        uasort(
            $routes,
            static fn (array $a, array $b): int =>
                ($b['flights'] <=> $a['flights'])
                ?: strcmp(
                    $a['departure_iata'] . $a['arrival_iata'],
                    $b['departure_iata'] . $b['arrival_iata']
                )
        );

        $rows[] = [
            'TRASY',
            'Miasta / lotniska',
            'Loty',
            'Dystans trasy [km]',
            'Łączny dystans [km]',
            '',
            '',
        ];

        foreach ($routes as $route) {
            $routeCode = trim(
                $route['departure_iata']
                . ' → '
                . $route['arrival_iata']
            );

            $cities = trim(
                $route['departure_city']
                . ' / '
                . $route['departure_airport']
                . ' → '
                . $route['arrival_city']
                . ' / '
                . $route['arrival_airport']
            );

            $rows[] = [
                $routeCode,
                $cities,
                $route['flights'],
                round((float) $route['distance'], 2),
                round((float) $route['total_distance'], 2),
                '',
                '',
            ];
        }

        return $rows;
    }

    private function addAirportStatistic(
        array &$airports,
        string $id,
        string $iata,
        string $name,
        string $city,
        string $country,
        bool $departure
    ): void {
        $key = $id !== ''
            ? 'id:' . $id
            : 'name:' . $name . ':' . $city;

        if (!isset($airports[$key])) {
            $airports[$key] = [
                'iata' => $iata,
                'name' => $name,
                'city' => $city,
                'country' => $country,
                'departures' => 0,
                'arrivals' => 0,
                'operations' => 0,
            ];
        }

        if ($departure) {
            $airports[$key]['departures']++;
        } else {
            $airports[$key]['arrivals']++;
        }

        $airports[$key]['operations']++;
    }

    private function addCountryStatistic(
        array &$countries,
        string $code,
        string $name
    ): void {
        $normalizedCode = strtoupper(trim($code));
        $normalizedName = trim($name);

        $key = $normalizedCode !== ''
            ? $normalizedCode
            : $normalizedName;

        if ($key === '') {
            return;
        }

        if (!isset($countries[$key])) {
            $countries[$key] = [
                'code' => $normalizedCode,
                'name' => $normalizedName !== ''
                    ? $normalizedName
                    : $normalizedCode,
                'operations' => 0,
            ];
        }

        $countries[$key]['operations']++;
    }

    private function detectAircraftFamily(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');

        $rules = [
            'Airbus' => ['airbus'],
            'Boeing' => ['boeing'],
            'ATR' => ['atr '],
            'Embraer' => ['embraer'],
            'Bombardier' => ['bombardier', 'canadair', 'crj'],
            'De Havilland' => ['de havilland', 'dash 8', 'dhc-'],
            'McDonnell Douglas' => ['mcdonnell douglas', 'md-', 'dc-'],
            'Fokker' => ['fokker'],
            'Saab' => ['saab'],
            'Cessna' => ['cessna'],
            'Beechcraft' => ['beechcraft', 'beech '],
            'Pilatus' => ['pilatus'],
            'Antonov' => ['antonov'],
            'Ilyushin' => ['ilyushin', 'il-'],
            'Tupolev' => ['tupolev', 'tu-'],
            'Sukhoi' => ['sukhoi'],
            'COMAC' => ['comac'],
            'Lockheed' => ['lockheed'],
            'Dornier' => ['dornier'],
        ];

        foreach ($rules as $family => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $family;
                }
            }
        }

        $parts = preg_split('/\s+/u', trim($name));

        return $parts[0] ?? 'Inne';
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv(max(0, $seconds), 3600);
        $minutes = intdiv(max(0, $seconds) % 3600, 60);

        return sprintf('%d:%02d', $hours, $minutes);
    }

    private function xlsxWorksheet(
        array $rows,
        bool $autoFilter,
        array $widths,
        ?int $titleRow = null
    ): string {
        $maxColumns = 1;

        foreach ($rows as $row) {
            $maxColumns = max($maxColumns, count($row));
        }

        $lastColumn = $this->xlsxColumnName($maxColumns);
        $lastRow = max(1, count($rows));

        $colsXml = '';

        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $safeWidth = max(5.0, min(60.0, (float) $width));

            $colsXml .= '<col min="' . $column
                . '" max="' . $column
                . '" width="' . $safeWidth
                . '" customWidth="1"/>';
        }

        $rowsXml = '';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $rowsXml .= '<row r="' . $excelRow . '">';

            foreach (array_values($row) as $columnIndex => $value) {
                $cellRef = $this->xlsxColumnName($columnIndex + 1) . $excelRow;

                $style = 0;

                if ($excelRow === 1) {
                    $style = $titleRow === 1 ? 2 : 1;
                } elseif (
                    isset($row[0])
                    && is_string($row[0])
                    && in_array(
                        $row[0],
                        [
                            'PODSUMOWANIE',
                            'TYPY LOTÓW',
                            'LOTNISKA',
                            'PAŃSTWA',
                            'LINIE LOTNICZE',
                            'TYPY SAMOLOTÓW',
                            'RODZINY SAMOLOTÓW',
                            'TRASY',
                        ],
                        true
                    )
                ) {
                    $style = 1;
                }

                $rowsXml .= $this->xlsxCell(
                    $cellRef,
                    $value,
                    $style
                );
            }

            $rowsXml .= '</row>';
        }

        if ($rowsXml === '') {
            $rowsXml = '<row r="1"/>';
        }

        $filterXml = $autoFilter
            ? '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
            : '';

        $freezeXml = $rows
            ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            : '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $freezeXml
            . '<cols>' . $colsXml . '</cols>'
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . $filterXml
            . '</worksheet>';
    }

    private function xlsxCell(
        string $cellRef,
        mixed $value,
        int $style
    ): string {
        if (
            is_int($value)
            || is_float($value)
        ) {
            return '<c r="' . $cellRef . '" s="' . $style . '" t="n"><v>'
                . $this->xlsxEscape((string) $value)
                . '</v></c>';
        }

        $text = $this->xlsxCleanText((string) $value);

        return '<c r="' . $cellRef . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
            . $this->xlsxEscape($text)
            . '</t></is></c>';
    }

    private function xlsxCleanText(string $value): string
    {
        return preg_replace(
            '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u',
            '',
            $value
        ) ?? '';
    }

    private function xlsxEscape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    private function xlsxColumnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xlsxContentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    private function xlsxRootRelationships(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function xlsxWorkbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Loty" sheetId="1" r:id="rId1"/>
    <sheet name="Podsumowanie" sheetId="2" r:id="rId2"/>
    <sheet name="Profil" sheetId="3" r:id="rId3"/>
    <sheet name="Statystyki" sheetId="4" r:id="rId4"/>
  </sheets>
</workbook>
XML;
    }

    private function xlsxWorkbookRelationships(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function xlsxStyles(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3">
    <font><sz val="11"/><name val="Calibri"/><family val="2"/></font>
    <font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/><family val="2"/></font>
    <font><b/><color rgb="FF0B2D5C"/><sz val="14"/><name val="Calibri"/><family val="2"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0B2D5C"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFDDE3E9"/></left>
      <right style="thin"><color rgb="FFDDE3E9"/></right>
      <top style="thin"><color rgb="FFDDE3E9"/></top>
      <bottom style="thin"><color rgb="FFDDE3E9"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1">
      <alignment vertical="top" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFill="1" applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">
      <alignment vertical="center"/>
    </xf>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
</styleSheet>
XML;
    }

    private function xlsxCoreProperties(): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:dcterms="http://purl.org/dc/terms/"'
            . ' xmlns:dcmitype="http://purl.org/dc/dcmitype/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Mapa Lotów</dc:creator>'
            . '<cp:lastModifiedBy>Mapa Lotów</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
            . '<dc:title>Eksport danych - Mapa Lotów</dc:title>'
            . '</cp:coreProperties>';
    }

    private function xlsxAppProperties(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Mapa Lotów</Application>
  <DocSecurity>0</DocSecurity>
  <ScaleCrop>false</ScaleCrop>
  <HeadingPairs>
    <vt:vector size="2" baseType="variant">
      <vt:variant><vt:lpstr>Arkusze</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>4</vt:i4></vt:variant>
    </vt:vector>
  </HeadingPairs>
  <TitlesOfParts>
    <vt:vector size="4" baseType="lpstr">
      <vt:lpstr>Loty</vt:lpstr>
      <vt:lpstr>Podsumowanie</vt:lpstr>
      <vt:lpstr>Profil</vt:lpstr>
      <vt:lpstr>Statystyki</vt:lpstr>
    </vt:vector>
  </TitlesOfParts>
  <Company></Company>
  <LinksUpToDate>false</LinksUpToDate>
  <SharedDoc>false</SharedDoc>
  <HyperlinksChanged>false</HyperlinksChanged>
  <AppVersion>1.0</AppVersion>
</Properties>
XML;
    }

    private function profile(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                id,
                email,
                nick,
                privacy_mode,
                public_slug,
                created_at,
                last_login_at
            FROM ml_users
            WHERE id = :id
            LIMIT 1
            "
        );

        $stmt->execute([
            'id' => $userId,
        ]);

        return $stmt->fetch() ?: [];
    }

    private function flights(int $userId): array
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

                al.name AS airline_name,
                al.iata_code AS airline_iata,
                al.icao_code AS airline_icao,

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

            WHERE f.user_id = :user_id

            ORDER BY
                f.departure_date ASC,
                f.departure_time ASC,
                f.id ASC
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
