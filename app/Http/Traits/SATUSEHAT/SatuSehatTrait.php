<?php

namespace App\Http\Traits\SATUSEHAT;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

use Exception;

trait SatuSehatTrait
{

    // OAuth2 Configuration
    protected $authUrl;
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $organizationId;


    public function initializeSatuSehat()
    {
        $this->authUrl         = env('SATUSEHAT_AUTH_URL');
        $this->clientId        = env('SATUSEHAT_CLIENT_ID');
        $this->clientSecret    = env('SATUSEHAT_SECRET_ID');
        $this->baseUrl         = env('SATUSEHAT_BASE_URL');
        $this->organizationId  = env('SATUSEHAT_ORGANIZATION_ID');
        $this->organizationName = env('SATUSEHAT_ORGANIZATION_NAME');
    }

    /**
     * Get OAuth2 Token
     */
    protected function getAccessToken()
    {
        return Cache::remember('satusehat_access_token', 3500, function () {
            $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
            $url = env('SATUSEHAT_AUTH_URL') . "accesstoken?grant_type=client_credentials";

            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->asForm()
                ->post($url, [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            throw new \Exception('Failed to get access token: ' . $response->body());
        });
    }

    /**
     * Make API Request to SatuSehat
     */
    protected function makeRequest($method, $endpoint, $data = [])
    {

        $token = $this->getAccessToken();
        $url = $this->baseUrl . $endpoint;

        // Base client: timeout, bearer token, common headers
        $client = Http::timeout(10)
            ->withToken($token)
            ->withHeaders([
                'Organization-Id' => $this->organizationId,
            ]);

        // Untuk GET: kirim $data sebagai query string
        if (strtolower($method) === 'get') {
            $response = $client->get($url);
        } else {
            // Untuk POST/PUT/PATCH/DELETE: kirim $data sebagai JSON‐body
            $response = $client
                ->withHeaders(['Content-Type' => 'application/json'])
                ->{$method}($url, $data);
        }

        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception('API request failed: ' . $response->body());
    }
}
