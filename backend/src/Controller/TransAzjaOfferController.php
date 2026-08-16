<?php

declare(strict_types=1);

namespace Transazja\MapaLotowApi\Controller;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class TransAzjaOfferController
{
    private const SOURCE_URL = 'https://wyprawy.transazja.pl/';
    private const CACHE_SECONDS = 900;

    /**
     * Statusy, które mogą pojawić się w reklamach Mapy Lotów.
     */
    private const ALLOWED_STATUSES = [
        'zapisy',
        'potwierdzony',
        'promocja',
    ];

    public function index(
        Request $request,
        Response $response
    ): Response {
        try {
            $offers = $this->loadOffers();

            return $this->json(
                $response,
                [
                    'status' => 'ok',
                    'source' => self::SOURCE_URL,
                    'offers' => $offers,
                ]
            );
        } catch (Throwable $e) {
            /*
             * Moduł reklamowy nie może blokować działania Mapy Lotów.
             * Przy problemie ze stroną TransAzji zwracamy pustą listę.
             */
            return $this->json(
                $response,
                [
                    'status' => 'ok',
                    'source' => self::SOURCE_URL,
                    'offers' => [],
                    'warning' => 'Nie udało się pobrać aktualnych ofert TransAzji.',
                ]
            );
        }
    }

    private function loadOffers(): array
    {
        $cacheFile = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'mapa_lotow_transazja_offers_v3.json';

        if (
            is_file($cacheFile)
            && (time() - (int) filemtime($cacheFile)) < self::CACHE_SECONDS
        ) {
            $cached = file_get_contents($cacheFile);

            if ($cached !== false) {
                $decoded = json_decode($cached, true);

                if (is_array($decoded)) {
                    return $this->normalizeOffers(
                        $decoded
                    );
                }
            }
        }

        $html = $this->download(self::SOURCE_URL);
        $offers = $this->normalizeOffers(
            $this->parseOffers($html)
        );

        @file_put_contents(
            $cacheFile,
            json_encode(
                $offers,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
            )
        );

        return $offers;
    }

    private function download(string $url): string
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);

            if ($curl === false) {
                throw new \RuntimeException('Nie udało się uruchomić cURL.');
            }

            curl_setopt_array(
                $curl,
                [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 7,
                    CURLOPT_TIMEOUT => 12,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT =>
                        'Mozilla/5.0 (compatible; MapaLotow/1.0; +https://wyprawy.transazja.pl/)',
                    CURLOPT_HTTPHEADER => [
                        'Accept: text/html,application/xhtml+xml',
                        'Accept-Language: pl-PL,pl;q=0.9,en;q=0.8',
                    ],
                ]
            );

            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            curl_close($curl);

            if (
                !is_string($body)
                || $body === ''
                || $status < 200
                || $status >= 400
            ) {
                throw new \RuntimeException(
                    'Błąd pobierania strony TransAzji: '
                    . ($error !== '' ? $error : 'HTTP ' . $status)
                );
            }

            return $body;
        }

        $context = stream_context_create(
            [
                'http' => [
                    'timeout' => 12,
                    'follow_location' => 1,
                    'header' =>
                        "User-Agent: Mozilla/5.0 (compatible; MapaLotow/1.0)\r\n"
                        . "Accept-Language: pl-PL,pl;q=0.9,en;q=0.8\r\n",
                ],
            ]
        );

        $body = @file_get_contents(
            $url,
            false,
            $context
        );

        if (!is_string($body) || $body === '') {
            throw new \RuntimeException(
                'Nie udało się pobrać strony TransAzji.'
            );
        }

        return $body;
    }

    private function parseOffers(string $html): array
    {
        $previous = libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOWARNING | LIBXML_NOERROR
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException(
                'Nie udało się przeanalizować HTML TransAzji.'
            );
        }

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[@href]');

        if ($anchors === false) {
            return [];
        }

        $offers = [];
        $seen = [];

        foreach ($anchors as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $text = $this->normalizeText(
                $anchor->textContent ?? ''
            );

            if ($text === '') {
                continue;
            }

            $status = $this->detectStatus($text);

            if ($status === null) {
                continue;
            }

            /*
             * Kafelki terminów na stronie zawierają liczbę dni.
             * Dzięki temu nie łapiemy zwykłych linków tekstowych
             * ze słowami "zapisy" / "potwierdzony".
             */
            if (
                !preg_match(
                    '/\b(\d{1,2})\s*dni\b/ui',
                    $text,
                    $daysMatch
                )
            ) {
                continue;
            }

            $href = trim(
                $anchor->getAttribute('href')
            );

            if ($href === '') {
                continue;
            }

            $url = $this->absoluteUrl(
                $href,
                self::SOURCE_URL
            );

            $image = $this->extractImage(
                $anchor,
                $xpath
            );

            $cleanOfferText =
                $this->cleanOfferText(
                    $text,
                    $status,
                    (int) $daysMatch[1]
                );

            $title =
                $this->extractDisplayTitle(
                    $cleanOfferText,
                    $anchor,
                    $url
                );

            if ($title === '') {
                continue;
            }

            $dateText =
                $this->extractCleanDateText(
                    $cleanOfferText,
                    $title
                );

            $key = mb_strtolower(
                $url . '|' . $dateText,
                'UTF-8'
            );

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $offers[] = [
                'id' => sha1($key),
                'title' => $title,
                'days' => (int) $daysMatch[1],
                'date_text' => $dateText,
                'status' => $status,
                'image' => $image !== null
                    ? $this->toMinImageUrl($image)
                    : null,
                'url' => $url,
            ];
        }

        return array_values($offers);
    }

    private function detectStatus(string $text): ?string
    {
        $lower = mb_strtolower(
            $text,
            'UTF-8'
        );

        foreach (self::ALLOWED_STATUSES as $status) {
            if (
                preg_match(
                    '/(^|\s)'
                    . preg_quote($status, '/')
                    . '(\s|$)/ui',
                    $lower
                )
            ) {
                return $status;
            }
        }

        return null;
    }

    private function normalizeOffers(
        array $offers
    ): array {
        $normalized = [];

        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                continue;
            }

            if (
                isset($offer['image'])
                && is_string($offer['image'])
                && $offer['image'] !== ''
            ) {
                $offer['image'] =
                    $this->toMinImageUrl(
                        $offer['image']
                    );
            }

            $normalized[] = $offer;
        }

        return $normalized;
    }


    private function cleanOfferText(
        string $text,
        string $status,
        int $days
    ): string {
        $value = $text;

        /*
         * Usuwamy status i liczbę dni.
         */
        $value = preg_replace(
            '/\b'
            . preg_quote($status, '/')
            . '\b/ui',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\b'
            . preg_quote((string) $days, '/')
            . '\s*dni\b/ui',
            ' ',
            $value
        ) ?? $value;

        /*
         * Usuwamy informacje o wolnych miejscach.
         * W HTML mogą występować różne warianty fleksyjne,
         * a czasem tekst bywa zapisany nieidealnie.
         *
         * Przykłady:
         * "3 wolne miejsca Laos ..."
         * "3 wolnych miejsc Laos ..."
         * "3 wojne miejsca Laos ..." - tolerujemy również taki wariant.
         */
        $value = preg_replace(
            '/(?:^|\s)\d+\s+\S{2,12}\s+miejsc(?:e|a)?\b/ui',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/(?:^|\s)\d+\s+(?:wolne|wolnych|wolna|wolny)\b/ui',
            ' ',
            $value
        ) ?? $value;

        return $this->normalizeText(
            $value
        );
    }


    private function extractDisplayTitle(
        string $cleanText,
        DOMElement $anchor,
        string $url
    ): string {
        /*
         * Najlepsze źródło nazwy to tekst widoczny w kafelku.
         * Szukamy wszystkiego przed początkiem terminu, np.:
         *
         * "Indie 13-27 lutego 2027"        -> "Indie"
         * "Bali & Lombok 21 kwietnia-6 maja" -> "Bali & Lombok"
         * "Laos 14–29 stycznia 2027"       -> "Laos"
         */
        if (
            preg_match(
                '/^(.+?)\s+(?=\d{1,2}(?:\s|[-–—]))/u',
                $cleanText,
                $match
            )
        ) {
            $title = $this->normalizeText(
                $match[1]
            );

            if ($title !== '') {
                return $title;
            }
        }

        /*
         * Jeżeli data ma postać "24 listopada - 9 grudnia",
         * pierwszy dzień nadal pozwala oddzielić nazwę.
         */
        if (
            preg_match(
                '/^(.+?)\s+(?=\d{1,2}\s+[[:alpha:]ąćęłńóśźżĄĆĘŁŃÓŚŹŻ])/u',
                $cleanText,
                $match
            )
        ) {
            $title = $this->normalizeText(
                $match[1]
            );

            if ($title !== '') {
                return $title;
            }
        }

        /*
         * Fallback: próbujemy uzyskać nazwę z adresu oferty.
         * Dla obecnej strony daje to np. "kambodza", "laos", "indie".
         */
        $path = parse_url(
            $url,
            PHP_URL_PATH
        );

        if (is_string($path)) {
            $slug = trim(
                basename($path)
            );

            $slug = preg_replace(
                '/-wycieczka.*$/i',
                '',
                $slug
            ) ?? $slug;

            $slug = str_replace(
                '-',
                ' ',
                $slug
            );

            $slug = $this->normalizeText(
                $slug
            );

            if ($slug !== '') {
                return mb_convert_case(
                    $slug,
                    MB_CASE_TITLE,
                    'UTF-8'
                );
            }
        }

        /*
         * Ostateczny fallback: poprzedni mechanizm oparty o alt/tekst.
         */
        return $this->extractTitle(
            $anchor,
            $cleanText,
            ''
        );
    }


    private function extractCleanDateText(
        string $cleanText,
        string $title
    ): string {
        $value = $cleanText;

        if (
            str_starts_with(
                mb_strtolower($value, 'UTF-8'),
                mb_strtolower($title, 'UTF-8')
            )
        ) {
            $value = mb_substr(
                $value,
                mb_strlen($title, 'UTF-8'),
                null,
                'UTF-8'
            );
        }

        $value = $this->normalizeText(
            $value
        );

        /*
         * Jeśli przed terminem pozostał jakiś techniczny tekst,
         * zaczynamy od pierwszego dnia daty.
         */
        if (
            preg_match(
                '/(\d{1,2}(?:\s|[-–—]).*)$/u',
                $value,
                $match
            )
        ) {
            return $this->normalizeText(
                $match[1]
            );
        }

        return $value;
    }


    private function extractTitle(
        DOMElement $anchor,
        string $text,
        string $status
    ): string {
        /*
         * Najpierw próbujemy użyć tekstu/alt grafiki,
         * jeśli strona go udostępnia.
         */
        $images = $anchor->getElementsByTagName('img');

        foreach ($images as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }

            $alt = $this->normalizeText(
                $image->getAttribute('alt')
            );

            if (
                $alt !== ''
                && !$this->looksLikeFileName($alt)
                && mb_strlen($alt, 'UTF-8') <= 60
            ) {
                $clean = $this->cleanPossibleTitle($alt);

                if ($clean !== '') {
                    return $clean;
                }
            }
        }

        /*
         * Następnie analizujemy fragmenty tekstu kafelka.
         * Szukamy krótkiej etykiety, która nie jest statusem,
         * liczbą dni, datą ani komunikatem o wolnych miejscach.
         */
        $parts = preg_split(
            '/[\r\n\t]+|\s{2,}/u',
            trim($anchor->textContent ?? '')
        );

        if (is_array($parts)) {
            foreach ($parts as $part) {
                $candidate = $this->normalizeText($part);

                if (
                    $candidate === ''
                    || $this->shouldIgnoreTitleCandidate(
                        $candidate,
                        $status
                    )
                ) {
                    continue;
                }

                $clean = $this->cleanPossibleTitle($candidate);

                if ($clean !== '') {
                    return $clean;
                }
            }
        }

        /*
         * Fallback dla silnie skompresowanego HTML.
         * Usuwamy elementy techniczne i wybieramy pierwszy
         * sensowny fragment tekstu.
         */
        $cleaned = preg_replace(
            [
                '/\b(zapisy|potwierdzony|promocja)\b/ui',
                '/\b\d{1,2}\s*dni\b/ui',
                '/\b\d+\s+(wolne|wolnych|wolne miejsce|wolne miejsca|wolnych miejsc)\b/ui',
            ],
            ' ',
            $text
        );

        $cleaned = $this->normalizeText(
            is_string($cleaned) ? $cleaned : ''
        );

        if (
            preg_match(
                '/\b([A-ZĄĆĘŁŃÓŚŹŻ][A-Za-zĄĆĘŁŃÓŚŹŻąćęłńóśźż& -]{2,40})\b/u',
                $cleaned,
                $match
            )
        ) {
            return trim($match[1]);
        }

        return '';
    }

    private function shouldIgnoreTitleCandidate(
        string $candidate,
        string $status
    ): bool {
        $lower = mb_strtolower(
            $candidate,
            'UTF-8'
        );

        if (
            $lower === $status
            || in_array(
                $lower,
                self::ALLOWED_STATUSES,
                true
            )
        ) {
            return true;
        }

        if (
            preg_match('/^\d{1,2}\s*dni$/ui', $candidate)
            || preg_match('/^\d+\s+wol/ui', $candidate)
            || preg_match('/\b(stycz|lut|mar|kwie|maj|czerw|lip|sierp|wrześ|wrzes|paź|paz|listop|grud)\w*/ui', $candidate)
            || preg_match('/^\d{1,2}[\s.–-]/u', $candidate)
        ) {
            return true;
        }

        return false;
    }

    private function cleanPossibleTitle(
        string $value
    ): string {
        $value = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? ''
        );

        /*
         * Alt-y mogą zawierać techniczne nazwy plików/slajdów.
         */
        if (
            $value === ''
            || $this->looksLikeFileName($value)
        ) {
            return '';
        }

        return $value;
    }

    private function extractDateText(
        string $text,
        string $status,
        int $days,
        string $title
    ): string {
        $value = $text;

        $value = preg_replace(
            '/\b'
            . preg_quote($status, '/')
            . '\b/ui',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\b'
            . preg_quote($title, '/')
            . '\b/ui',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\b'
            . preg_quote((string) $days, '/')
            . '\s*dni\b/ui',
            ' ',
            $value
        ) ?? $value;

        /*
         * Usuwamy komunikaty o dostępności miejsc.
         * Sama informacja o statusie jest już prezentowana osobno.
         */
        $value = preg_replace(
            '/\b\d+\s+(wolne miejsce|wolne miejsca|wolnych miejsc|wolne|wolnych)\b/ui',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\bzapisy\b/ui',
            ' ',
            $value
        ) ?? $value;

        return $this->normalizeText($value);
    }

    private function extractImage(
        DOMElement $anchor,
        DOMXPath $xpath
    ): ?string {
        $images = $anchor->getElementsByTagName('img');

        foreach ($images as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }

            foreach (
                [
                    'data-src',
                    'data-lazy-src',
                    'data-original',
                    'src',
                ] as $attribute
            ) {
                $value = trim(
                    $image->getAttribute($attribute)
                );

                if (
                    $value !== ''
                    && !str_starts_with($value, 'data:')
                ) {
                    return $this->absoluteUrl(
                        $value,
                        self::SOURCE_URL
                    );
                }
            }

            $srcset = trim(
                $image->getAttribute('srcset')
            );

            if ($srcset !== '') {
                $first = trim(
                    explode(',', $srcset)[0] ?? ''
                );

                $first = trim(
                    preg_replace(
                        '/\s+\d+[wx]$/',
                        '',
                        $first
                    ) ?? $first
                );

                if ($first !== '') {
                    return $this->absoluteUrl(
                        $first,
                        self::SOURCE_URL
                    );
                }
            }
        }

        /*
         * Część kafelków może korzystać z background-image.
         * Sprawdzamy sam link i jego potomków.
         */
        $nodes = $xpath->query(
            './/*[@style] | self::*[@style]',
            $anchor
        );

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $style = $node->getAttribute('style');

                if (
                    preg_match(
                        '/background(?:-image)?\s*:[^;]*url\([\'"]?([^\'")]+)[\'"]?\)/ui',
                        $style,
                        $match
                    )
                ) {
                    return $this->absoluteUrl(
                        trim($match[1]),
                        self::SOURCE_URL
                    );
                }
            }
        }

        foreach (
            [
                'data-bg',
                'data-background-image',
                'data-src',
            ] as $attribute
        ) {
            $value = trim(
                $anchor->getAttribute($attribute)
            );

            if ($value !== '') {
                return $this->absoluteUrl(
                    $value,
                    self::SOURCE_URL
                );
            }
        }

        return null;
    }

    private function toMinImageUrl(
        string $url
    ): string {
        /*
         * TransAzja przechowuje mniejsze wersje zdjęć
         * z dopiskiem "-min" przed rozszerzeniem, np.:
         *
         * rajastan_03.jpg
         * rajastan_03-min.jpg
         *
         * Jeżeli adres już wskazuje na wersję -min,
         * pozostawiamy go bez zmian.
         */
        if (
            preg_match(
                '/-min\.(jpg|jpeg|png|webp)(\?.*)?$/i',
                $url
            )
        ) {
            return $url;
        }

        $converted = preg_replace(
            '/\.(jpg|jpeg|png|webp)(\?.*)?$/i',
            '-min.$1$2',
            $url
        );

        return is_string($converted)
            ? $converted
            : $url;
    }


    private function absoluteUrl(
        string $value,
        string $base
    ): string {
        if (
            preg_match(
                '#^https?://#i',
                $value
            )
        ) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return 'https:' . $value;
        }

        $parts = parse_url($base);

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'wyprawy.transazja.pl';

        if (str_starts_with($value, '/')) {
            return $scheme
                . '://'
                . $host
                . $value;
        }

        return $scheme
            . '://'
            . $host
            . '/'
            . ltrim($value, '/');
    }

    private function normalizeText(
        string $value
    ): string {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? ''
        );
    }

    private function looksLikeFileName(
        string $value
    ): bool {
        return (bool) preg_match(
            '/\.(jpg|jpeg|png|gif|webp|svg)$/i',
            $value
        );
    }

    private function json(
        Response $response,
        array $payload,
        int $status = 200
    ): Response {
        $response->getBody()->write(
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
        );

        return $response
            ->withStatus($status)
            ->withHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            )
            ->withHeader(
                'Cache-Control',
                'no-store'
            );
    }
}
