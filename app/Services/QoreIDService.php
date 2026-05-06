<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class QoreIDService
{
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected string $baseUrl;
    protected Client $client;

    public function __construct()
    {
        // Read from Admin Settings (database) — falls back to .env if not set
        $this->clientId = Setting::get('qoreid_client_id', config('services.qoreid.client_id', ''));
        $this->clientSecret = Setting::get('qoreid_client_secret', config('services.qoreid.client_secret', ''));
        $rawBaseUrl = Setting::get('qoreid_base_url', config('services.qoreid.base_url', 'https://api.qoreid.com/v1'));

        // Normalize base URL: ensure it ends with /v1 (QoreID API base path)
        $this->baseUrl = rtrim($rawBaseUrl, '/') . '/v1';
        // If the URL already ends with /v1, don't duplicate
        if (str_ends_with(rtrim($rawBaseUrl, '/'), '/v1')) {
            $this->baseUrl = rtrim($rawBaseUrl, '/');
        }

        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false,
        ]);
    }

    /**
     * Verify NIN using QoreID NIN Premium endpoint.
     *
     * @param string $nin       11-digit National Identity Number
     * @param string $firstName First name (required)
     * @param string $lastName  Last name (required)
     * @param array  $optional  Optional fields: middlename, dob, phone, email, gender
     * @return array            ['success' => bool, 'data' => array|null, 'error' => string|null, 'status_code' => int]
     */
    public function verifyNinPremium(
        string $nin,
        string $firstName,
        string $lastName,
        array $optional = []
    ): array {
        $token = $this->getAccessToken();
        // Validate NIN format: must be exactly 11 digits
        if (!preg_match('/^\d{11}$/', $nin)) {
            Log::warning('QoreID NIN validation failed', ['nin' => $nin]);
            return [
                'success' => false,
                'data' => null,
                'error' => 'Invalid NIN format. NIN must be exactly 11 digits.',
                'status_code' => 422,
            ];
        }

        if (empty($token)) {
            Log::error('QoreID token generation failed.');
            return [
                'success' => false,
                'data' => null,
                'error' => 'QoreID service authentication failed. Missing valid API token.',
                'status_code' => 500,
            ];
        }

        $body = [
            'firstname' => trim($firstName),
            'lastname' => trim($lastName),
        ];

        // Add optional fields if provided
        $optionalFields = ['middlename', 'dob', 'phone', 'email', 'gender'];
        foreach ($optionalFields as $field) {
            if (!empty($optional[$field])) {
                $body[$field] = trim($optional[$field]);
            }
        }

        $endpoint = "{$this->baseUrl}/ng/identities/nin-premium/" . urlencode($nin);

        Log::info('QoreID NIN Premium request', [
            'nin' => $nin,
            'endpoint' => $endpoint,
            'body_keys' => array_keys($body),
        ]);

        try {
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseData = json_decode($response->getBody()->getContents(), true);

            Log::info('QoreID NIN Premium response', [
                'status_code' => $statusCode,
                'nin' => $nin,
                'has_data' => !empty($responseData),
            ]);

            if ($statusCode === 200 && !empty($responseData)) {
                return [
                    'success' => true,
                    'data' => $responseData,
                    'error' => null,
                    'status_code' => $statusCode,
                ];
            }

            // Unexpected but valid response
            Log::warning('QoreID returned non-200 status', [
                'status_code' => $statusCode,
                'response' => $responseData ?? null,
            ]);

            return [
                'success' => false,
                'data' => $responseData ?? null,
                'error' => $responseData['message'] ?? 'Unexpected response from QoreID.',
                'status_code' => $statusCode,
            ];

        } catch (GuzzleException $e) {
            // Handle specific HTTP errors
            $statusCode = 0;
            $errorMessage = $e->getMessage();

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                try {
                    $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
                    $errorMessage = $errorBody['message'] ?? $e->getMessage();
                } catch (Exception $parseEx) {
                    // Ignore parse errors
                }
            }

            Log::error('QoreID API request failed', [
                'nin' => $nin,
                'status_code' => $statusCode,
                'error' => $errorMessage,
                'exception' => get_class($e),
            ]);

            $userFriendlyError = match ($statusCode) {
                401 => 'QoreID authentication failed. Please check your API token.',
                402 => 'QoreID account has insufficient credits. Please top up your QoreID balance.',
                403 => 'QoreID access denied. Your account may not have NIN Premium access.',
                404 => 'NIN not found in the NIMC database.',
                422 => 'Invalid request data. Please check the NIN and name fields.',
                429 => 'Too many requests. Please try again later.',
                500, 502, 503 => 'QoreID service is temporarily unavailable. Please try again later.',
                default => 'Failed to verify identity. ' . $errorMessage,
            };

            return [
                'success' => false,
                'data' => null,
                'error' => $userFriendlyError,
                'status_code' => $statusCode,
            ];

        } catch (Exception $e) {
            Log::error('QoreID service unexpected error', [
                'nin' => $nin,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Verification service error. Please try again later.',
                'status_code' => 500,
            ];
        }
    }

    /**
     * Retrieve a valid QoreID Access Token.
     * Caches the token to avoid redundant API calls.
     */
    protected function getAccessToken(): ?string
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            Log::error('QoreID Client ID or Secret is missing.');
            return null;
        }

        return Cache::remember('qoreid_access_token', now()->addMinutes(115), function () {
            try {
                $response = $this->client->post('https://auth.qoreid.com/auth/realms/qoreid/protocol/openid-connect/token', [
                    'form_params' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                    ],
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                if (!empty($data['access_token'])) {
                    return $data['access_token'];
                }

                Log::error('QoreID Token Response invalid', ['response' => $data]);
                return null;
            } catch (\Exception $e) {
                Log::error('Failed to fetch QoreID Access Token: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Compare provided name with QoreID response and calculate confidence.
     *
     * @param string $providedFirst
     * @param string $providedLast
     * @param array  $qoreIdData   The decoded QoreID response
     * @return array               ['match' => bool, 'confidence' => int, 'details' => array]
     */
    public function compareNames(string $providedFirst, string $providedLast, array $qoreIdData): array
    {
        // Extract nested identity data if present
        $identityData = $qoreIdData['nin'] ?? $qoreIdData['nin_premium'] ?? $qoreIdData['data'] ?? $qoreIdData;

        $apiFirst = $identityData['firstname'] ?? $identityData['firstName'] ?? '';
        $apiLast = $identityData['lastname'] ?? $identityData['lastName'] ?? '';
        $apiMiddle = $identityData['middlename'] ?? $identityData['middleName'] ?? '';

        $providedFirst = trim(mb_strtoupper($providedFirst));
        $providedLast = trim(mb_strtoupper($providedLast));
        $apiFirst = trim(mb_strtoupper($apiFirst));
        $apiLast = trim(mb_strtoupper($apiLast));
        $apiMiddle = trim(mb_strtoupper($apiMiddle));

        $firstMatch = ($providedFirst === $apiFirst);
        $lastMatch = ($providedLast === $apiLast);

        // Check if provided first name matches either first or middle name from API
        $firstOrMiddleMatch = false;
        if (!empty($apiMiddle)) {
            $firstOrMiddleMatch = ($providedFirst === $apiFirst) || ($providedFirst === $apiMiddle);
        }

        $confidence = 0;
        $details = [];

        if ($firstMatch && $lastMatch) {
            $confidence = 100;
            $details = ['first_name' => 'exact', 'last_name' => 'exact'];
        } elseif ($firstOrMiddleMatch && $lastMatch) {
            $confidence = 95;
            $details = ['first_name' => 'matched_first_or_middle', 'last_name' => 'exact'];
        } else {
            // Partial match scoring
            $firstSimilarity = 0;
            $lastSimilarity = 0;

            if (!empty($apiFirst) && !empty($providedFirst)) {
                similar_text($providedFirst, $apiFirst, $firstSimilarity);
            }
            if (!empty($apiLast) && !empty($providedLast)) {
                similar_text($providedLast, $apiLast, $lastSimilarity);
            }

            $confidence = (int) round(($firstSimilarity + $lastSimilarity) / 2);
            $details = [
                'first_name_similarity' => round($firstSimilarity, 1),
                'last_name_similarity' => round($lastSimilarity, 1),
            ];
        }

        return [
            'match' => $confidence >= 80,
            'confidence' => $confidence,
            'details' => $details,
            'api_first' => $identityData['firstname'] ?? $identityData['firstName'] ?? '',
            'api_last' => $identityData['lastname'] ?? $identityData['lastName'] ?? '',
        ];
    }
}