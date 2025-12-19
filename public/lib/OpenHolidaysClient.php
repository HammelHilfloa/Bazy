<?php

class OpenHolidaysClient
{
    private const BASE_URL = 'https://openholidaysapi.org';

    private string $country;
    private string $subdivision;
    private string $language;

    public function __construct(string $country = 'DE', string $subdivision = 'DE-NW', string $language = 'DE')
    {
        $this->country = $country;
        $this->subdivision = $subdivision;
        $this->language = $language;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchPublicHolidays(string $validFrom, string $validTo): array
    {
        return $this->request('/PublicHolidays', $validFrom, $validTo);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchSchoolHolidays(string $validFrom, string $validTo): array
    {
        return $this->request('/SchoolHolidays', $validFrom, $validTo);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function request(string $path, string $validFrom, string $validTo): array
    {
        $query = http_build_query(
            [
                'countryIsoCode' => $this->country,
                'subdivisionCode' => $this->subdivision,
                'languageIsoCode' => $this->language,
                'validFrom' => $validFrom,
                'validTo' => $validTo,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $url = rtrim(self::BASE_URL, '/') . $path . '?' . $query;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('HTTP-Client konnte nicht initialisiert werden.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: BazyCalendar/1.0 (+https://openholidaysapi.org/)',
            ],
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('OpenHolidays Anfrage fehlgeschlagen: ' . ($curlError ?: 'Unbekannter Fehler'));
        }

        if ($status >= 400 || $status === 0) {
            throw new RuntimeException('OpenHolidays antwortete mit Status ' . $status);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new RuntimeException('Antwort von OpenHolidays konnte nicht gelesen werden.');
        }

        return $decoded;
    }
}
