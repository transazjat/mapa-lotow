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

    private const ROUTE_THRESHOLDS = [
        10,
        25,
        50,
        75,
        100,
        150,
        200,
        250,
        300,
        500,
    ];

    private const DURATION_THRESHOLDS = [
        24,
        100,
        250,
        500,
        750,
        1000,
        1250,
        1500,
        2000,
        2500,
    ];

    private const ASTRONOMICAL_THRESHOLDS = [
        40075,
        200375,
        299792,
        384400,
        768800,
        1000000,
        1153200,
        1922000,
        2997925,
        3844000,
    ];

    private const INTENSITY_YEAR_THRESHOLDS = [10, 20, 30, 40, 50, 60, 75, 100, 125, 150];
    private const INTENSITY_MONTH_THRESHOLDS = [4, 6, 8, 10, 12, 15, 18, 22, 26, 30];
    private const INTENSITY_STREAK_THRESHOLDS = [2, 3, 4, 5, 6, 7, 10, 14, 21, 30];
    private const INTENSITY_DAY_THRESHOLDS = [2, 3, 4, 5, 6, 7, 8, 10, 12, 15];


    private const SPECIAL_ACHIEVEMENTS = [
        ['slug' => 'new-year-air', 'label' => 'Nowy Rok w powietrzu', 'category' => 'calendar', 'description' => 'Start 31 grudnia i lądowanie 1 stycznia następnego roku, zgodnie z lokalnymi datami lotnisk wylotu i przylotu.', 'order' => 1],
        ['slug' => 'leap-day', 'label' => '29 lutego', 'category' => 'calendar', 'description' => 'Odbyłeś lot rozpoczynający się 29 lutego - w dniu, który pojawia się w kalendarzu tylko w roku przestępnym.', 'order' => 2],
        ['slug' => 'friday-13', 'label' => 'Piątek trzynastego', 'category' => 'calendar', 'description' => 'Odbyłeś lot rozpoczynający się w piątek, 13. dnia miesiąca.', 'order' => 3],
        ['slug' => 'christmas-air', 'label' => 'Boże Narodzenie w powietrzu', 'category' => 'calendar', 'description' => 'Odbyłeś lot rozpoczynający się 25 grudnia.', 'order' => 4],
        ['slug' => 'history-5', 'label' => 'Pierwsze pięć lat', 'category' => 'history', 'description' => 'Pierwszy lot odbyty po upływie pięciu pełnych lat od najstarszego lotu w Twojej historii.', 'order' => 5],
        ['slug' => 'history-10', 'label' => 'Dekada w powietrzu', 'category' => 'history', 'description' => 'Pierwszy lot odbyty po upływie dziesięciu pełnych lat od najstarszego lotu w Twojej historii.', 'order' => 6],
        ['slug' => 'history-15', 'label' => 'Piętnaście lat podróży', 'category' => 'history', 'description' => 'Pierwszy lot odbyty po upływie piętnastu pełnych lat od najstarszego lotu w Twojej historii.', 'order' => 7],
        ['slug' => 'history-20', 'label' => 'Dwie dekady w powietrzu', 'category' => 'history', 'description' => 'Pierwszy lot odbyty po upływie dwudziestu pełnych lat od najstarszego lotu w Twojej historii.', 'order' => 8],
        ['slug' => 'history-25', 'label' => 'Ćwierć wieku w powietrzu', 'category' => 'history', 'description' => 'Pierwszy lot odbyty po upływie dwudziestu pięciu pełnych lat od najstarszego lotu w Twojej historii.', 'order' => 9],
        ['slug' => 'route-10', 'label' => 'Dziesięć razy tą samą trasą', 'category' => 'repeat', 'description' => 'Dowolna jedna trasa osiągnęła co najmniej dziesięć odbytych lotów. W szczegółach pokazujemy wszystkie trasy spełniające warunek.', 'order' => 10],
        ['slug' => 'airline-25', 'label' => 'Ta sama linia 25 razy', 'category' => 'repeat', 'description' => 'Co najmniej 25 odbytych segmentów zostało obsłużonych przez tego samego przewoźnika.', 'order' => 11],
        ['slug' => 'aircraft-25', 'label' => 'Ten sam typ samolotu 25 razy', 'category' => 'repeat', 'description' => 'Co najmniej 25 odbytych lotów wykonano tym samym typem samolotu.', 'order' => 12],
        ['slug' => 'country-airports-10', 'label' => '10 różnych lotnisk w jednym kraju', 'category' => 'repeat', 'description' => 'W jednym państwie odwiedziłeś co najmniej dziesięć różnych lotnisk.', 'order' => 13],
        ['slug' => 'airport-50', 'label' => 'Lot z tego samego lotniska 50 razy', 'category' => 'repeat', 'description' => 'Jedno lotnisko wystąpiło w co najmniej 50 odbytych lotach jako port startu lub lądowania.', 'order' => 14],
        ['slug' => 'return-after-years', 'label' => 'Powrót po latach', 'category' => 'repeat', 'description' => 'Powróciłeś na lotnisko po co najmniej dziesięciu pełnych latach bez żadnego startu ani lądowania na tym lotnisku.', 'order' => 15],
        ['slug' => 'first-intercontinental', 'label' => 'Pierwszy lot międzykontynentalny', 'category' => 'geography', 'description' => 'Pierwszy zapisany lot, którego lotniska startu i lądowania leżą na różnych kontynentach.', 'order' => 16],
        ['slug' => 'atlantic', 'label' => 'Przekroczenie Atlantyku', 'category' => 'geography', 'description' => 'Co najmniej jedna z Twoich tras prowadziła pomiędzy Ameryką a Europą lub Afryką, czyli przez Atlantyk.', 'order' => 17],
        ['slug' => 'pacific', 'label' => 'Przekroczenie Pacyfiku', 'category' => 'geography', 'description' => 'Co najmniej jedna z Twoich tras prowadziła pomiędzy Ameryką a Azją lub Oceanią, czyli przez Pacyfik.', 'order' => 18],
        ['slug' => 'indian-ocean', 'label' => 'Przekroczenie Oceanu Indyjskiego', 'category' => 'geography', 'description' => 'Najkrótszy łuk trasy przecina obszar Oceanu Indyjskiego, Morza Arabskiego lub Zatoki Bengalskiej.', 'order' => 19],
        ['slug' => 'arctic-ocean', 'label' => 'Przekroczenie Oceanu Arktycznego', 'category' => 'geography', 'description' => 'Co najmniej 100 km najkrótszego łuku trasy przebiega nad konserwatywnie zdefiniowanym obszarem wód Oceanu Arktycznego.', 'order' => 20],
        ['slug' => 'polar-route', 'label' => 'Trasa polarna', 'category' => 'geography', 'description' => 'Międzykontynentalny lot, którego najkrótszy łuk prowadzi co najmniej 300 km na północ od 75°N.', 'order' => 21],
        ['slug' => 'north-pole', 'label' => 'Nad Biegunem Północnym', 'category' => 'geography', 'description' => 'Międzykontynentalny lot, którego najkrótszy łuk prowadzi co najmniej 100 km na północ od 85°N.', 'order' => 22],
        ['slug' => 'equator', 'label' => 'Przekroczenie równika', 'category' => 'geography', 'description' => 'Trasa lotu połączyła lotniska leżące po przeciwnych stronach równika.', 'order' => 23],
        ['slug' => 'date-line', 'label' => 'Przekroczenie linii zmiany daty', 'category' => 'geography', 'description' => 'Najkrótszy łuk trasy przekroczył okolice południka 180° i międzynarodowej linii zmiany daty.', 'order' => 24],
    ];

    /** @var array<int,array<string,array{earned_at:?string,details:array<int,array{label:string,value:string,note:?string}>}>> */
    private array $specialAnalysisCache = [];


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
            ...$state['routes']['achievements'],
            ...$state['duration']['achievements'],
            ...$state['astronomical']['achievements'],
            ...$state['intensity']['year']['achievements'],
            ...$state['intensity']['month']['achievements'],
            ...$state['intensity']['streak']['achievements'],
            ...$state['intensity']['day']['achievements'],
            ...$state['special']['achievements'],
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

        foreach (self::ROUTE_THRESHOLDS as $threshold) {
            $allowedKeys['routes_' . $threshold] = true;
        }

        foreach (self::DURATION_THRESHOLDS as $threshold) {
            $allowedKeys['duration_' . $threshold] = true;
        }

        foreach (self::ASTRONOMICAL_THRESHOLDS as $threshold) {
            $allowedKeys['astronomical_' . $threshold] = true;
        }

        foreach (self::INTENSITY_YEAR_THRESHOLDS as $threshold) {
            $allowedKeys['intensity_year_' . $threshold] = true;
        }
        foreach (self::INTENSITY_MONTH_THRESHOLDS as $threshold) {
            $allowedKeys['intensity_month_' . $threshold] = true;
        }
        foreach (self::INTENSITY_STREAK_THRESHOLDS as $threshold) {
            $allowedKeys['intensity_streak_' . $threshold] = true;
        }
        foreach (self::INTENSITY_DAY_THRESHOLDS as $threshold) {
            $allowedKeys['intensity_day_' . $threshold] = true;
        }

        foreach (self::SPECIAL_ACHIEVEMENTS as $definition) {
            $allowedKeys['special_' . $definition['slug']] = true;
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
            ...$this->syncRouteAchievements($userId),
            ...$this->syncDurationAchievements($userId),
            ...$this->syncAstronomicalAchievements($userId),
            ...$this->syncIntensityYearAchievements($userId),
            ...$this->syncIntensityMonthAchievements($userId),
            ...$this->syncIntensityStreakAchievements($userId),
            ...$this->syncIntensityDayAchievements($userId),
            ...$this->syncSpecialAchievements($userId),
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
    private function syncRouteAchievements(int $userId): array
    {
        $completedRoutes = $this->completedRouteCount($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'routes',
            self::ROUTE_THRESHOLDS,
            $completedRoutes,
            fn(int $threshold): ?string => $this->routeThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }

    /** @return list<string> */
    private function syncDurationAchievements(int $userId): array
    {
        $completedHours = $this->completedDurationHours($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'duration',
            self::DURATION_THRESHOLDS,
            $completedHours,
            fn(int $threshold): ?string => $this->durationThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }

    /** @return list<string> */
    private function syncAstronomicalAchievements(int $userId): array
    {
        $completedDistance = $this->completedDistanceKm($userId);

        return $this->syncFamilyAchievements(
            $userId,
            'astronomical',
            self::ASTRONOMICAL_THRESHOLDS,
            $completedDistance,
            fn(int $threshold): ?string => $this->distanceThresholdEarnedAt(
                $userId,
                $threshold
            )
        );
    }

    /** @return list<string> */
    private function syncIntensityYearAchievements(int $userId): array
    {
        $record = $this->intensityYearRecord($userId);
        return $this->syncFamilyAchievements(
            $userId,
            'intensity_year',
            self::INTENSITY_YEAR_THRESHOLDS,
            $record['value'],
            fn(int $threshold): ?string => $this->intensityThresholdEarnedAt($userId, 'year', $threshold)
        );
    }

    /** @return list<string> */
    private function syncIntensityMonthAchievements(int $userId): array
    {
        $record = $this->intensityMonthRecord($userId);
        return $this->syncFamilyAchievements(
            $userId,
            'intensity_month',
            self::INTENSITY_MONTH_THRESHOLDS,
            $record['value'],
            fn(int $threshold): ?string => $this->intensityThresholdEarnedAt($userId, 'month', $threshold)
        );
    }

    /** @return list<string> */
    private function syncIntensityStreakAchievements(int $userId): array
    {
        $record = $this->intensityStreakRecord($userId);
        return $this->syncFamilyAchievements(
            $userId,
            'intensity_streak',
            self::INTENSITY_STREAK_THRESHOLDS,
            $record['value'],
            fn(int $threshold): ?string => $this->intensityStreakThresholdEarnedAt($userId, $threshold)
        );
    }

    /** @return list<string> */
    private function syncIntensityDayAchievements(int $userId): array
    {
        $record = $this->intensityDayRecord($userId);
        return $this->syncFamilyAchievements(
            $userId,
            'intensity_day',
            self::INTENSITY_DAY_THRESHOLDS,
            $record['value'],
            fn(int $threshold): ?string => $this->intensityThresholdEarnedAt($userId, 'day', $threshold)
        );
    }

    /** @return list<string> */
    private function syncSpecialAchievements(int $userId): array
    {
        $analysis = $this->analyzeSpecialAchievements($userId);

        return $this->syncCollectionAchievements(
            $userId,
            'special',
            self::SPECIAL_ACHIEVEMENTS,
            'special_',
            static fn(array $definition): ?string => $analysis[$definition['slug']]['earned_at'] ?? null
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

    /** @return array{value:int,label:string,start_date:?string,end_date:?string} */
    private function intensityYearRecord(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT YEAR(departure_date) AS period, COUNT(*) AS total FROM ml_flights WHERE user_id = :user_id AND departure_date <= CURDATE() GROUP BY YEAR(departure_date) ORDER BY total DESC, period DESC LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['value' => 0, 'label' => '—', 'start_date' => null, 'end_date' => null];
        $year = (string) $row['period'];
        return ['value' => (int) $row['total'], 'label' => $year, 'start_date' => $year . '-01-01', 'end_date' => $year . '-12-31'];
    }

    /** @return array{value:int,label:string,start_date:?string,end_date:?string} */
    private function intensityMonthRecord(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT DATE_FORMAT(departure_date, '%Y-%m') AS period, COUNT(*) AS total FROM ml_flights WHERE user_id = :user_id AND departure_date <= CURDATE() GROUP BY DATE_FORMAT(departure_date, '%Y-%m') ORDER BY total DESC, period DESC LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['value' => 0, 'label' => '—', 'start_date' => null, 'end_date' => null];
        $period = (string) $row['period'];
        $start = $period . '-01';
        $end = date('Y-m-t', strtotime($start));
        return ['value' => (int) $row['total'], 'label' => $period, 'start_date' => $start, 'end_date' => $end];
    }

    /** @return array{value:int,label:string,start_date:?string,end_date:?string} */
    private function intensityDayRecord(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT departure_date AS period, COUNT(*) AS total FROM ml_flights WHERE user_id = :user_id AND departure_date <= CURDATE() GROUP BY departure_date ORDER BY total DESC, period DESC LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['value' => 0, 'label' => '—', 'start_date' => null, 'end_date' => null];
        $date = (string) $row['period'];
        return ['value' => (int) $row['total'], 'label' => $date, 'start_date' => $date, 'end_date' => $date];
    }

    /** @return array{value:int,label:string,start_date:?string,end_date:?string} */
    private function intensityStreakRecord(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT departure_date FROM ml_flights WHERE user_id = :user_id AND departure_date <= CURDATE() ORDER BY departure_date ASC");
        $stmt->execute(['user_id' => $userId]);
        $dates = array_map(static fn(array $row): string => (string) $row['departure_date'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        if ($dates === []) return ['value' => 0, 'label' => '—', 'start_date' => null, 'end_date' => null];

        $best = 1; $current = 1; $bestStart = $dates[0]; $bestEnd = $dates[0]; $currentStart = $dates[0];
        for ($i = 1, $count = count($dates); $i < $count; $i++) {
            $prev = new \DateTimeImmutable($dates[$i - 1]);
            $curr = new \DateTimeImmutable($dates[$i]);
            if ($prev->modify('+1 day')->format('Y-m-d') === $curr->format('Y-m-d')) {
                $current++;
            } else {
                $current = 1;
                $currentStart = $dates[$i];
            }
            if ($current >= $best) {
                $best = $current;
                $bestStart = $currentStart;
                $bestEnd = $dates[$i];
            }
        }
        return ['value' => $best, 'label' => $bestStart . ' – ' . $bestEnd, 'start_date' => $bestStart, 'end_date' => $bestEnd];
    }

    private function intensityThresholdEarnedAt(int $userId, string $period, int $threshold): ?string
    {
        $format = $period === 'year' ? '%Y' : ($period === 'month' ? '%Y-%m' : '%Y-%m-%d');
        $stmt = $this->pdo->prepare("SELECT departure_date, COALESCE(departure_time, '00:00:00') AS departure_time FROM ml_flights WHERE user_id = :user_id AND departure_date <= CURDATE() ORDER BY departure_date ASC, COALESCE(departure_time, '00:00:00') ASC, id ASC");
        $stmt->execute(['user_id' => $userId]);
        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = date($period === 'year' ? 'Y' : ($period === 'month' ? 'Y-m' : 'Y-m-d'), strtotime((string) $row['departure_date']));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            if ($counts[$key] === $threshold) return (string) $row['departure_date'] . ' ' . (string) $row['departure_time'];
        }
        return null;
    }

    private function intensityStreakThresholdEarnedAt(int $userId, int $threshold): ?string
    {
        $stmt = $this->pdo->prepare("SELECT departure_date, MIN(COALESCE(departure_time, '00:00:00')) AS departure_time FROM ml_flights WHERE user_id = :user_id AND departure_date <= CURDATE() GROUP BY departure_date ORDER BY departure_date ASC");
        $stmt->execute(['user_id' => $userId]);
        $previous = null; $streak = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = (string) $row['departure_date'];
            if ($previous !== null && (new \DateTimeImmutable($previous))->modify('+1 day')->format('Y-m-d') === $date) $streak++; else $streak = 1;
            if ($streak >= $threshold) return $date . ' ' . (string) $row['departure_time'];
            $previous = $date;
        }
        return null;
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

    private function completedDurationHours(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COALESCE(SUM(COALESCE(duration_seconds, 0)), 0)
            FROM ml_flights
            WHERE user_id = :user_id
              AND departure_date <= CURDATE()
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        return (int) floor(((int) $stmt->fetchColumn()) / 3600);
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

    private function completedRouteCount(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "
            SELECT COUNT(*)
            FROM (
                SELECT departure_airport_id, arrival_airport_id
                FROM ml_flights
                WHERE user_id = :user_id
                  AND departure_date <= CURDATE()
                GROUP BY departure_airport_id, arrival_airport_id
            ) AS completed_routes
            "
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function durationThresholdEarnedAt(
        int $userId,
        int $threshold
    ): ?string {
        $stmt = $this->pdo->prepare(
            "
            SELECT
                departure_date,
                COALESCE(departure_time, '00:00:00') AS departure_time,
                COALESCE(duration_seconds, 0) AS duration_seconds
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

        $requiredSeconds = $threshold * 3600;
        $elapsedSeconds = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $elapsedSeconds += (int) $row['duration_seconds'];

            if ($elapsedSeconds >= $requiredSeconds) {
                return sprintf(
                    '%s %s',
                    (string) $row['departure_date'],
                    (string) $row['departure_time']
                );
            }
        }

        return null;
    }

    private function routeThresholdEarnedAt(
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
            // Trasy są kierunkowe: WAW→DOH i DOH→WAW liczą się osobno.
            $routeKey = (string) $row['departure_airport_id']
                . ':'
                . (string) $row['arrival_airport_id'];
            $visited[$routeKey] = true;

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

    private function buildSpecialState(int $userId): array
    {
        $analysis = $this->analyzeSpecialAchievements($userId);

        $stmt = $this->pdo->prepare(
            "
            SELECT achievement_key, threshold_value, earned_at, notified_at
            FROM ml_user_achievements
            WHERE user_id = :user_id
              AND family = 'special'
            "
        );
        $stmt->execute(['user_id' => $userId]);

        $earnedRows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $earnedRows[(string) $row['achievement_key']] = $row;
        }

        $achievements = [];
        $pendingUnlocks = [];
        $lastEarned = null;
        $earnedCount = 0;

        foreach (self::SPECIAL_ACHIEVEMENTS as $definition) {
            $key = 'special_' . $definition['slug'];
            $row = $earnedRows[$key] ?? null;
            $earned = $row !== null;
            $details = $analysis[$definition['slug']]['details'] ?? [];

            $item = [
                'key' => $key,
                'label' => $definition['label'],
                'slug' => $definition['slug'],
                'order' => $definition['order'],
                'category' => $definition['category'],
                'description' => $definition['description'],
                'status' => $earned ? 'active' : 'locked',
                'earned' => $earned,
                'active' => $earned,
                'earned_at' => $earned ? (string) $row['earned_at'] : null,
                'notified_at' => $earned && $row['notified_at'] !== null ? (string) $row['notified_at'] : null,
                'details' => $details,
            ];

            $achievements[] = $item;
            if ($earned) {
                $earnedCount++;
                if ($row['notified_at'] === null) {
                    $pendingUnlocks[] = $item;
                }
                if ($lastEarned === null || strcmp((string) $item['earned_at'], (string) $lastEarned['earned_at']) >= 0) {
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
                'total_count' => count(self::SPECIAL_ACHIEVEMENTS),
                'last_earned' => $lastEarned,
            ],
        ];
    }

    /**
     * Analiza specjalnych osiągnięć jest wykonywana wyłącznie na odbytych lotach.
     * Daty startu i lądowania w ml_flights są datami lokalnymi lotnisk, dlatego
     * osiągnięcie noworoczne zachowuje poprawną logikę stref czasowych.
     *
     * @return array<string,array{earned_at:?string,details:array<int,array{label:string,value:string,note:?string}>}>
     */
    private function analyzeSpecialAchievements(int $userId): array
    {
        if (isset($this->specialAnalysisCache[$userId])) {
            return $this->specialAnalysisCache[$userId];
        }

        $result = [];
        foreach (self::SPECIAL_ACHIEVEMENTS as $definition) {
            $result[$definition['slug']] = ['earned_at' => null, 'details' => []];
        }

        $stmt = $this->pdo->prepare(
            "
            SELECT
                f.id,
                f.departure_date,
                COALESCE(f.departure_time, '00:00:00') AS departure_time,
                f.arrival_date,
                COALESCE(f.arrival_time, '00:00:00') AS arrival_time,
                f.departure_airport_id,
                f.arrival_airport_id,
                f.airline_id,
                f.aircraft_type_id,
                COALESCE(dep.iata_code, dep.icao_code, dep.name) AS departure_code,
                COALESCE(arr.iata_code, arr.icao_code, arr.name) AS arrival_code,
                dep.name AS departure_name,
                arr.name AS arrival_name,
                dep.country_id AS departure_country_id,
                arr.country_id AS arrival_country_id,
                COALESCE(dep_country.name, dep.country_name, '—') AS departure_country,
                COALESCE(arr_country.name, arr.country_name, '—') AS arrival_country,
                dep_country.continent_code AS departure_continent,
                arr_country.continent_code AS arrival_continent,
                dep.latitude AS departure_latitude,
                dep.longitude AS departure_longitude,
                arr.latitude AS arrival_latitude,
                arr.longitude AS arrival_longitude,
                al.name AS airline_name,
                ac.name AS aircraft_name
            FROM ml_flights f
            JOIN ml_airports dep ON dep.id = f.departure_airport_id
            JOIN ml_airports arr ON arr.id = f.arrival_airport_id
            LEFT JOIN ml_countries dep_country ON dep_country.id = dep.country_id
            LEFT JOIN ml_countries arr_country ON arr_country.id = arr.country_id
            LEFT JOIN ml_airlines al ON al.id = f.airline_id
            LEFT JOIN ml_aircraft_types ac ON ac.id = f.aircraft_type_id
            WHERE f.user_id = :user_id
              AND f.departure_date <= CURDATE()
            ORDER BY f.departure_date ASC, COALESCE(f.departure_time, '00:00:00') ASC, f.id ASC
            "
        );
        $stmt->execute(['user_id' => $userId]);
        $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($flights === []) {
            return $this->specialAnalysisCache[$userId] = $result;
        }

        $firstDate = new \DateTimeImmutable((string) $flights[0]['departure_date']);
        foreach ([5 => 'history-5', 10 => 'history-10', 15 => 'history-15', 20 => 'history-20', 25 => 'history-25'] as $years => $slug) {
            $anniversary = $firstDate->modify('+' . $years . ' years')->format('Y-m-d');
            foreach ($flights as $flight) {
                if ((string) $flight['departure_date'] >= $anniversary) {
                    $result[$slug]['earned_at'] = $this->specialDepartureTimestamp($flight);
                    $result[$slug]['details'][] = ['label' => 'Pierwszy zapisany lot', 'value' => $firstDate->format('d.m.Y'), 'note' => null];
                    $result[$slug]['details'][] = ['label' => 'Lot rocznicowy', 'value' => $this->specialRouteLabel($flight), 'note' => (string) $flight['departure_date']];
                    break;
                }
            }
        }

        $routeCounts = [];
        $airlineCounts = [];
        $aircraftCounts = [];
        $countryAirports = [];
        $airportCounts = [];
        $airportLabels = [];
        $airportEvents = [];

        foreach ($flights as $flight) {
            $depStamp = $this->specialDepartureTimestamp($flight);

            if (
                $result['new-year-air']['earned_at'] === null &&
                substr((string) $flight['departure_date'], 5) === '12-31' &&
                $flight['arrival_date'] !== null &&
                substr((string) $flight['arrival_date'], 5) === '01-01' &&
                ((int) substr((string) $flight['arrival_date'], 0, 4)) === ((int) substr((string) $flight['departure_date'], 0, 4)) + 1
            ) {
                $result['new-year-air']['earned_at'] = $depStamp;
                $result['new-year-air']['details'] = [
                    ['label' => 'Lot', 'value' => $this->specialRouteLabel($flight), 'note' => null],
                    ['label' => 'Start', 'value' => (string) $flight['departure_date'] . ' ' . (string) $flight['departure_time'], 'note' => 'czas lokalny lotniska wylotu'],
                    ['label' => 'Lądowanie', 'value' => (string) $flight['arrival_date'] . ' ' . (string) $flight['arrival_time'], 'note' => 'czas lokalny lotniska przylotu'],
                ];
            }

            $departureDate = new \DateTimeImmutable((string) $flight['departure_date']);

            if ($result['leap-day']['earned_at'] === null && $departureDate->format('m-d') === '02-29') {
                $result['leap-day']['earned_at'] = $depStamp;
                $result['leap-day']['details'] = [[
                    'label' => 'Lot',
                    'value' => $this->specialRouteLabel($flight),
                    'note' => $departureDate->format('d.m.Y'),
                ]];
            }

            if ($result['friday-13']['earned_at'] === null && $departureDate->format('d') === '13' && $departureDate->format('N') === '5') {
                $result['friday-13']['earned_at'] = $depStamp;
                $result['friday-13']['details'] = [[
                    'label' => 'Lot',
                    'value' => $this->specialRouteLabel($flight),
                    'note' => $departureDate->format('d.m.Y'),
                ]];
            }

            if ($result['christmas-air']['earned_at'] === null && $departureDate->format('m-d') === '12-25') {
                $result['christmas-air']['earned_at'] = $depStamp;
                $result['christmas-air']['details'] = [[
                    'label' => 'Lot',
                    'value' => $this->specialRouteLabel($flight),
                    'note' => $departureDate->format('d.m.Y'),
                ]];
            }

            $depContinent = (string) ($flight['departure_continent'] ?? '');
            $arrContinent = (string) ($flight['arrival_continent'] ?? '');
            if ($result['first-intercontinental']['earned_at'] === null && $depContinent !== '' && $arrContinent !== '' && $depContinent !== $arrContinent) {
                $result['first-intercontinental']['earned_at'] = $depStamp;
                $result['first-intercontinental']['details'] = [['label' => 'Lot', 'value' => $this->specialRouteLabel($flight), 'note' => (string) $flight['departure_date']]];
            }

            if ($result['atlantic']['earned_at'] === null && $this->specialOceanPair($depContinent, $arrContinent, 'atlantic')) {
                $result['atlantic']['earned_at'] = $depStamp;
                $result['atlantic']['details'] = [['label' => 'Pierwszy kwalifikujący lot', 'value' => $this->specialRouteLabel($flight), 'note' => (string) $flight['departure_date']]];
            }
            if ($result['pacific']['earned_at'] === null && $this->specialOceanPair($depContinent, $arrContinent, 'pacific')) {
                $result['pacific']['earned_at'] = $depStamp;
                $result['pacific']['details'] = [['label' => 'Pierwszy kwalifikujący lot', 'value' => $this->specialRouteLabel($flight), 'note' => (string) $flight['departure_date']]];
            }
            if ($result['indian-ocean']['earned_at'] === null && $this->specialIndianOceanFlight($flight)) {
                $result['indian-ocean']['earned_at'] = $depStamp;
                $result['indian-ocean']['details'] = [['label' => 'Pierwszy kwalifikujący lot', 'value' => $this->specialRouteLabel($flight), 'note' => (string) $flight['departure_date']]];
            }

            $arcticDistance = $this->specialGreatCircleDistanceInAreaKm($flight, fn(float $lat, float $lon): bool => $this->specialPointInArcticOcean($lat, $lon));
            if ($result['arctic-ocean']['earned_at'] === null && $arcticDistance >= 100.0) {
                $result['arctic-ocean']['earned_at'] = $depStamp;
                $result['arctic-ocean']['details'] = [[
                    'label' => 'Pierwszy kwalifikujący lot',
                    'value' => $this->specialRouteLabel($flight),
                    'note' => sprintf('około %.0f km nad wodami Arktyki', $arcticDistance),
                ]];
            }

            $isIntercontinental = $depContinent !== '' && $arrContinent !== '' && $depContinent !== $arrContinent;
            if ($isIntercontinental) {
                $north75Distance = $this->specialGreatCircleDistanceInLatitudeBandKm($flight, 75.0);
                if ($result['polar-route']['earned_at'] === null && $north75Distance >= 300.0) {
                    $result['polar-route']['earned_at'] = $depStamp;
                    $result['polar-route']['details'] = [[
                        'label' => 'Pierwszy kwalifikujący lot',
                        'value' => $this->specialRouteLabel($flight),
                        'note' => sprintf('około %.0f km na północ od 75°N', $north75Distance),
                    ]];
                }

                $north85Distance = $this->specialGreatCircleDistanceInLatitudeBandKm($flight, 85.0);
                if ($result['north-pole']['earned_at'] === null && $north85Distance >= 100.0) {
                    $result['north-pole']['earned_at'] = $depStamp;
                    $result['north-pole']['details'] = [[
                        'label' => 'Pierwszy kwalifikujący lot',
                        'value' => $this->specialRouteLabel($flight),
                        'note' => sprintf('około %.0f km na północ od 85°N', $north85Distance),
                    ]];
                }
            }

            $depLat = (float) $flight['departure_latitude'];
            $arrLat = (float) $flight['arrival_latitude'];
            if ($result['equator']['earned_at'] === null && (($depLat < 0 && $arrLat > 0) || ($depLat > 0 && $arrLat < 0))) {
                $result['equator']['earned_at'] = $depStamp;
                $result['equator']['details'] = [['label' => 'Lot', 'value' => $this->specialRouteLabel($flight), 'note' => (string) $flight['departure_date']]];
            }

            $depLon = (float) $flight['departure_longitude'];
            $arrLon = (float) $flight['arrival_longitude'];
            if ($result['date-line']['earned_at'] === null && abs($depLon - $arrLon) > 180.0) {
                $result['date-line']['earned_at'] = $depStamp;
                $result['date-line']['details'] = [['label' => 'Lot', 'value' => $this->specialRouteLabel($flight), 'note' => (string) $flight['departure_date']]];
            }

            $routeKey = (int) $flight['departure_airport_id'] . ':' . (int) $flight['arrival_airport_id'];
            if (!isset($routeCounts[$routeKey])) {
                $routeCounts[$routeKey] = ['count' => 0, 'label' => $this->specialRouteLabel($flight)];
            }
            $routeCounts[$routeKey]['count']++;
            if ($routeCounts[$routeKey]['count'] === 10 && $result['route-10']['earned_at'] === null) {
                $result['route-10']['earned_at'] = $depStamp;
            }

            if ($flight['airline_id'] !== null) {
                $key = (int) $flight['airline_id'];
                $airlineCounts[$key] ??= ['count' => 0, 'label' => (string) ($flight['airline_name'] ?? ('Linia #' . $key))];
                $airlineCounts[$key]['count']++;
                if ($airlineCounts[$key]['count'] === 25 && $result['airline-25']['earned_at'] === null) {
                    $result['airline-25']['earned_at'] = $depStamp;
                }
            }

            if ($flight['aircraft_type_id'] !== null) {
                $key = (int) $flight['aircraft_type_id'];
                $aircraftCounts[$key] ??= ['count' => 0, 'label' => (string) ($flight['aircraft_name'] ?? ('Typ #' . $key))];
                $aircraftCounts[$key]['count']++;
                if ($aircraftCounts[$key]['count'] === 25 && $result['aircraft-25']['earned_at'] === null) {
                    $result['aircraft-25']['earned_at'] = $depStamp;
                }
            }

            foreach ([
                ['id' => (int) $flight['departure_airport_id'], 'code' => (string) $flight['departure_code'], 'name' => (string) $flight['departure_name'], 'country_id' => $flight['departure_country_id'], 'country' => (string) $flight['departure_country'], 'date' => (string) $flight['departure_date'], 'time' => (string) $flight['departure_time']],
                ['id' => (int) $flight['arrival_airport_id'], 'code' => (string) $flight['arrival_code'], 'name' => (string) $flight['arrival_name'], 'country_id' => $flight['arrival_country_id'], 'country' => (string) $flight['arrival_country'], 'date' => (string) ($flight['arrival_date'] ?? $flight['departure_date']), 'time' => (string) $flight['arrival_time']],
            ] as $airportEvent) {
                $airportLabels[$airportEvent['id']] = trim($airportEvent['code'] . ' · ' . $airportEvent['name']);
                $airportEvents[] = $airportEvent;

                if ($airportEvent['country_id'] !== null) {
                    $countryId = (int) $airportEvent['country_id'];
                    $countryAirports[$countryId] ??= ['airports' => [], 'label' => $airportEvent['country']];
                    $before = count($countryAirports[$countryId]['airports']);
                    $countryAirports[$countryId]['airports'][$airportEvent['id']] = true;
                    if ($before < 10 && count($countryAirports[$countryId]['airports']) === 10 && $result['country-airports-10']['earned_at'] === null) {
                        $result['country-airports-10']['earned_at'] = $airportEvent['date'] . ' ' . $airportEvent['time'];
                    }
                }
            }

            foreach (array_unique([(int) $flight['departure_airport_id'], (int) $flight['arrival_airport_id']]) as $airportId) {
                $airportCounts[$airportId] = ($airportCounts[$airportId] ?? 0) + 1;
                if ($airportCounts[$airportId] === 50 && $result['airport-50']['earned_at'] === null) {
                    $result['airport-50']['earned_at'] = $depStamp;
                }
            }
        }

        $this->specialAttachCountDetails($result['route-10'], $routeCounts, 10, 'Trasa');
        $this->specialAttachCountDetails($result['airline-25'], $airlineCounts, 25, 'Linia');
        $this->specialAttachCountDetails($result['aircraft-25'], $aircraftCounts, 25, 'Typ samolotu');

        foreach ($countryAirports as $country) {
            $count = count($country['airports']);
            if ($count >= 10) {
                $result['country-airports-10']['details'][] = ['label' => 'Państwo', 'value' => (string) $country['label'], 'note' => $count . ' różnych lotnisk'];
            }
        }
        usort($result['country-airports-10']['details'], static fn(array $a, array $b): int => strcmp($a['value'], $b['value']));

        arsort($airportCounts);
        foreach ($airportCounts as $airportId => $count) {
            if ($count < 50) continue;
            $result['airport-50']['details'][] = ['label' => 'Lotnisko', 'value' => $airportLabels[$airportId] ?? ('Lotnisko #' . $airportId), 'note' => $count . ' lotów'];
        }

        usort($airportEvents, static fn(array $a, array $b): int => strcmp($a['date'] . ' ' . $a['time'], $b['date'] . ' ' . $b['time']));
        $lastVisit = [];
        foreach ($airportEvents as $event) {
            $airportId = (int) $event['id'];
            $date = new \DateTimeImmutable((string) $event['date']);
            if (isset($lastVisit[$airportId])) {
                $previous = $lastVisit[$airportId];
                $tenYearsLater = $previous->modify('+10 years');
                if ($date >= $tenYearsLater) {
                    $earnedAt = $event['date'] . ' ' . $event['time'];
                    if ($result['return-after-years']['earned_at'] === null) {
                        $result['return-after-years']['earned_at'] = $earnedAt;
                    }
                    $years = $previous->diff($date)->y;
                    $result['return-after-years']['details'][] = [
                        'label' => $airportLabels[$airportId] ?? ('Lotnisko #' . $airportId),
                        'value' => $previous->format('d.m.Y') . ' → ' . $date->format('d.m.Y'),
                        'note' => $years . ' lat przerwy',
                    ];
                }
            }
            $lastVisit[$airportId] = $date;
        }

        return $this->specialAnalysisCache[$userId] = $result;
    }

    private function specialDepartureTimestamp(array $flight): string
    {
        return (string) $flight['departure_date'] . ' ' . (string) $flight['departure_time'];
    }

    private function specialRouteLabel(array $flight): string
    {
        return trim((string) $flight['departure_code']) . ' – ' . trim((string) $flight['arrival_code']);
    }

    private function specialOceanPair(string $departure, string $arrival, string $ocean): bool
    {
        $americas = ['NA', 'SA'];
        if ($ocean === 'atlantic') {
            $east = ['EU', 'AF'];
            return (in_array($departure, $americas, true) && in_array($arrival, $east, true)) ||
                (in_array($arrival, $americas, true) && in_array($departure, $east, true));
        }

        $west = ['AS', 'OC'];
        return (in_array($departure, $americas, true) && in_array($arrival, $west, true)) ||
            (in_array($arrival, $americas, true) && in_array($departure, $west, true));
    }

    private function specialIndianOceanFlight(array $flight): bool
    {
        $lat1 = (float) $flight['departure_latitude'];
        $lon1 = (float) $flight['departure_longitude'];
        $lat2 = (float) $flight['arrival_latitude'];
        $lon2 = (float) $flight['arrival_longitude'];

        if (!is_finite($lat1) || !is_finite($lon1) || !is_finite($lat2) || !is_finite($lon2)) {
            return false;
        }

        // Próbkujemy najkrótszy łuk wielkiego koła zamiast używać średniego punktu
        // i pary kontynentów. Dzięki temu np. DOH-JNB nie jest fałszywie uznawany
        // za Ocean Indyjski, a DOH-RGN przechodzi przez obszar Morza Arabskiego.
        foreach ($this->specialGreatCirclePoints($lat1, $lon1, $lat2, $lon2, 72) as [$lat, $lon]) {
            if ($this->specialPointInIndianOcean($lat, $lon)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,array{0:float,1:float}> */
    private function specialGreatCirclePoints(float $lat1, float $lon1, float $lat2, float $lon2, int $segments = 72): array
    {
        $toVector = static function (float $lat, float $lon): array {
            $phi = deg2rad($lat);
            $lambda = deg2rad($lon);
            return [cos($phi) * cos($lambda), cos($phi) * sin($lambda), sin($phi)];
        };

        $a = $toVector($lat1, $lon1);
        $b = $toVector($lat2, $lon2);
        $dot = max(-1.0, min(1.0, $a[0] * $b[0] + $a[1] * $b[1] + $a[2] * $b[2]));
        $omega = acos($dot);
        $points = [];

        for ($i = 0; $i <= $segments; $i++) {
            $t = $segments === 0 ? 0.0 : $i / $segments;
            if ($omega < 1.0e-9) {
                $v = $a;
            } else {
                $sinOmega = sin($omega);
                $s1 = sin((1.0 - $t) * $omega) / $sinOmega;
                $s2 = sin($t * $omega) / $sinOmega;
                $v = [
                    $s1 * $a[0] + $s2 * $b[0],
                    $s1 * $a[1] + $s2 * $b[1],
                    $s1 * $a[2] + $s2 * $b[2],
                ];
            }

            $lat = rad2deg(atan2($v[2], sqrt($v[0] * $v[0] + $v[1] * $v[1])));
            $lon = rad2deg(atan2($v[1], $v[0]));
            $points[] = [$lat, $lon];
        }

        return $points;
    }

    private function specialPointInIndianOcean(float $lat, float $lon): bool
    {
        // Przybliżone, rozłączne obszary wodne. Celowo nie używamy jednego
        // ogromnego prostokąta, który obejmowałby ląd Afryki, Arabii i Indii.
        $polygons = [
            // Morze Arabskie / północno-zachodni Ocean Indyjski.
            [[5.0, 48.0], [24.0, 55.0], [26.0, 63.0], [23.0, 68.0], [15.0, 74.0], [5.0, 76.0], [-5.0, 60.0]],
            // Zatoka Bengalska / Morze Andamańskie.
            [[5.0, 78.0], [20.0, 80.0], [23.0, 88.0], [18.0, 98.0], [5.0, 101.0], [-5.0, 90.0]],
            // Centralny i południowy Ocean Indyjski.
            [[-58.0, 20.0], [-5.0, 38.0], [8.0, 50.0], [8.0, 100.0], [-12.0, 120.0], [-58.0, 120.0]],
        ];

        foreach ($polygons as $polygon) {
            if ($this->specialPointInPolygon($lat, $lon, $polygon)) {
                return true;
            }
        }

        return false;
    }

    private function specialPointInArcticOcean(float $lat, float $lon): bool
    {
        // Konserwatywna definicja wód Oceanu Arktycznego: centralny basen oraz
        // główne morza arktyczne. Celowo nie zaliczamy Morza Norweskiego ani
        // północnego Atlantyku, aby zwykłe loty przez Islandię/Skandynawię nie
        // odblokowywały osiągnięcia. Obszary są przybliżeniem do analizy tras,
        // a nie granicą hydrograficzną do celów nawigacyjnych.
        if ($lat < 68.0) {
            return false;
        }

        // Centralny basen arktyczny. Ograniczamy okolice północnej Grenlandii,
        // by nie liczyć oczywistego przelotu nad lądem jako oceanu.
        if ($lat >= 82.0) {
            $overNorthGreenland = $lat <= 84.5 && $lon >= -75.0 && $lon <= -10.0;
            return !$overNorthGreenland;
        }

        $polygons = [
            // Morze Barentsa.
            [[68.0, 15.0], [82.0, 15.0], [82.0, 65.0], [74.0, 65.0], [68.0, 45.0]],
            // Morze Karskie i Łaptiewów.
            [[70.0, 55.0], [82.0, 55.0], [82.0, 145.0], [72.0, 145.0], [70.0, 100.0]],
            // Morze Wschodniosyberyjskie i zachodnie Morze Czukockie.
            [[68.0, 145.0], [82.0, 145.0], [82.0, 180.0], [68.0, 180.0]],
            // Wschodnie Morze Czukockie i Morze Beauforta.
            [[68.0, -180.0], [82.0, -180.0], [82.0, -120.0], [70.0, -120.0], [68.0, -150.0]],
            // Północna część Morza Beauforta / zachodni skraj Archipelagu Arktycznego.
            [[72.0, -140.0], [82.0, -140.0], [82.0, -95.0], [76.0, -95.0], [72.0, -120.0]],
        ];

        foreach ($polygons as $polygon) {
            if ($this->specialPointInPolygon($lat, $lon, $polygon)) {
                return true;
            }
        }

        return false;
    }

    private function specialGreatCircleDistanceInLatitudeBandKm(array $flight, float $minimumLatitude): float
    {
        return $this->specialGreatCircleDistanceInAreaKm(
            $flight,
            static fn(float $lat, float $lon): bool => $lat >= $minimumLatitude
        );
    }

    private function specialGreatCircleDistanceInAreaKm(array $flight, callable $predicate): float
    {
        $lat1 = (float) $flight['departure_latitude'];
        $lon1 = (float) $flight['departure_longitude'];
        $lat2 = (float) $flight['arrival_latitude'];
        $lon2 = (float) $flight['arrival_longitude'];

        if (!is_finite($lat1) || !is_finite($lon1) || !is_finite($lat2) || !is_finite($lon2)) {
            return 0.0;
        }

        $points = $this->specialGreatCirclePoints($lat1, $lon1, $lat2, $lon2, 360);
        $distance = 0.0;
        for ($i = 1, $count = count($points); $i < $count; $i++) {
            [$prevLat, $prevLon] = $points[$i - 1];
            [$lat, $lon] = $points[$i];

            // Segment zaliczamy dopiero, gdy oba jego końce znajdują się w
            // kwalifikującym obszarze. Dzięki temu granice nie dodają przypadkowych km.
            if (!$predicate($prevLat, $prevLon) || !$predicate($lat, $lon)) {
                continue;
            }

            $distance += $this->specialHaversineKm($prevLat, $prevLon, $lat, $lon);
        }

        return $distance;
    }

    private function specialHaversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0088;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lon2 - $lon1);
        $a = sin($deltaPhi / 2.0) ** 2 + cos($phi1) * cos($phi2) * sin($deltaLambda / 2.0) ** 2;
        return 2.0 * $earthRadiusKm * asin(min(1.0, sqrt($a)));
    }

    /** @param array<int,array{0:float,1:float}> $polygon */
    private function specialPointInPolygon(float $lat, float $lon, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lonI] = $polygon[$i];
            [$latJ, $lonJ] = $polygon[$j];
            $crosses = (($latI > $lat) !== ($latJ > $lat));
            if (!$crosses) continue;

            $intersectionLon = ($lonJ - $lonI) * ($lat - $latI) / (($latJ - $latI) ?: 1.0e-12) + $lonI;
            if ($lon < $intersectionLon) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /** @param array{earned_at:?string,details:array<int,array{label:string,value:string,note:?string}>} $target */
    private function specialAttachCountDetails(array &$target, array $counts, int $threshold, string $label): void
    {
        uasort($counts, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
        foreach ($counts as $row) {
            if ((int) $row['count'] < $threshold) continue;
            $target['details'][] = ['label' => $label, 'value' => (string) $row['label'], 'note' => (int) $row['count'] . ' lotów'];
        }
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

        $routeState = $this->buildFamilyState(
            $userId,
            'routes',
            self::ROUTE_THRESHOLDS,
            $this->completedRouteCount($userId)
        );

        $durationState = $this->buildFamilyState(
            $userId,
            'duration',
            self::DURATION_THRESHOLDS,
            $this->completedDurationHours($userId)
        );

        $astronomicalState = $this->buildFamilyState(
            $userId,
            'astronomical',
            self::ASTRONOMICAL_THRESHOLDS,
            $this->completedDistanceKm($userId)
        );

        $intensityYearRecord = $this->intensityYearRecord($userId);
        $intensityMonthRecord = $this->intensityMonthRecord($userId);
        $intensityStreakRecord = $this->intensityStreakRecord($userId);
        $intensityDayRecord = $this->intensityDayRecord($userId);

        $intensityYearState = $this->buildFamilyState($userId, 'intensity_year', self::INTENSITY_YEAR_THRESHOLDS, $intensityYearRecord['value']);
        $intensityMonthState = $this->buildFamilyState($userId, 'intensity_month', self::INTENSITY_MONTH_THRESHOLDS, $intensityMonthRecord['value']);
        $intensityStreakState = $this->buildFamilyState($userId, 'intensity_streak', self::INTENSITY_STREAK_THRESHOLDS, $intensityStreakRecord['value']);
        $intensityDayState = $this->buildFamilyState($userId, 'intensity_day', self::INTENSITY_DAY_THRESHOLDS, $intensityDayRecord['value']);
        $specialState = $this->buildSpecialState($userId);

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
            'routes' => [
                'completed_routes' => $routeState['completed_value'],
                'achievements' => $routeState['achievements'],
                'pending_unlocks' => $routeState['pending_unlocks'],
                'summary' => $routeState['summary'],
            ],
            'duration' => [
                'completed_hours' => $durationState['completed_value'],
                'achievements' => $durationState['achievements'],
                'pending_unlocks' => $durationState['pending_unlocks'],
                'summary' => $durationState['summary'],
            ],
            'astronomical' => [
                'completed_distance_km' => $astronomicalState['completed_value'],
                'achievements' => $astronomicalState['achievements'],
                'pending_unlocks' => $astronomicalState['pending_unlocks'],
                'summary' => $astronomicalState['summary'],
            ],
            'intensity' => [
                'year' => [
                    'completed_value' => $intensityYearState['completed_value'],
                    'achievements' => $intensityYearState['achievements'],
                    'pending_unlocks' => $intensityYearState['pending_unlocks'],
                    'summary' => $intensityYearState['summary'],
                    'record' => $intensityYearRecord,
                ],
                'month' => [
                    'completed_value' => $intensityMonthState['completed_value'],
                    'achievements' => $intensityMonthState['achievements'],
                    'pending_unlocks' => $intensityMonthState['pending_unlocks'],
                    'summary' => $intensityMonthState['summary'],
                    'record' => $intensityMonthRecord,
                ],
                'streak' => [
                    'completed_value' => $intensityStreakState['completed_value'],
                    'achievements' => $intensityStreakState['achievements'],
                    'pending_unlocks' => $intensityStreakState['pending_unlocks'],
                    'summary' => $intensityStreakState['summary'],
                    'record' => $intensityStreakRecord,
                ],
                'day' => [
                    'completed_value' => $intensityDayState['completed_value'],
                    'achievements' => $intensityDayState['achievements'],
                    'pending_unlocks' => $intensityDayState['pending_unlocks'],
                    'summary' => $intensityDayState['summary'],
                    'record' => $intensityDayRecord,
                ],
            ],
            'special' => $specialState,
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
