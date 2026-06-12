<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class McpService
{
    /**
     * Perform a request to a given MCP server.
     *
     * @param  int    $serverId
     * @param  string $method   GET|POST|PUT|PATCH|DELETE
     * @param  string $endpoint Relative endpoint (e.g. "/v1/bookings")
     * @param  array  $params   Query parameters for GET
     * @param  array  $data     JSON body for POST/PUT/PATCH
     * @return array            Decoded JSON response or error structure
     */
    public function request(int $serverId, string $method, string $endpoint, array $params = [], array $data = []): array
    {
        /** @var McpServer $server */
        $server = McpServer::findOrFail($serverId);

        $url = rtrim($server->base_url, '/').$endpoint;
        $headers = [];
        if ($server->auth_token) {
            $headers['Authorization'] = "Bearer {$server->auth_token}";
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(config('mcp.timeout', 15))
                ->{$method}($url, $method === 'GET' ? $params : $data);

            $status = $response->status();
            $payload = $response->json();

            // Successful call – update last ping timestamp
            if ($status >= 200 && $status < 300) {
                $server->last_ping_at = now();
                $server->status = 'online';
                $server->save();
            } else {
                $server->status = 'error';
                $server->save();
            }

            return [
                'status' => $status,
                'data'   => $payload,
            ];
        } catch (\Exception $e) {
            Log::error('MCP request failed', [
                'server_id' => $serverId,
                'method'    => $method,
                'endpoint'  => $endpoint,
                'error'     => $e->getMessage(),
            ]);
            $server->status = 'offline';
            $server->save();
            return [
                'error' => $e->getMessage(),
                'status' => 0,
            ];
        }
    }

    /**
     * Ping the MCP server.
     *
     * @param  McpServer $server
     * @return array
     */
    public function ping(McpServer $server): array
    {
        $url = rtrim($server->base_url, '/') . '/ping';
        $headers = [];
        if ($server->auth_token) {
            $headers['Authorization'] = "Bearer {$server->auth_token}";
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(config('mcp.timeout', 15))
                ->get($url);
            $status = $response->status();
            $payload = $response->json();

            if ($status >= 200 && $status < 300) {
                $server->last_ping_at = now();
                $server->status = 'online';
                $server->save();
                return ['status' => $status, 'data' => $payload];
            }

            $server->status = 'error';
            $server->save();
            return ['status' => $status, 'error' => 'Ping failed'];
        } catch (\Exception $e) {
            Log::error('MCP ping failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
            $server->status = 'offline';
            $server->save();
            return ['error' => $e->getMessage(), 'status' => 0];
        }
    }

    /**
     * Generate a usage snippet (PHP) for a given server.
     * The snippet includes the base URL and token placeholder.
     */
    public function generateUsageSnippet(McpServer $server): string
    {
        $base = $server->base_url;
        $token = $server->auth_token ?? 'YOUR_TOKEN_HERE';
        $snippet = <<<PHP
<?php
// MCP client example for server "{$server->name}"

require 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$baseUrl = '{$base}';
$token   = '{$token}';

$response = Http::withHeaders([
    'Authorization' => "Bearer {$token}",
    'Accept' => 'application/json',
])->get("{$base}/v1/endpoint");

if ($response->ok()) {
    $data = $response->json();
    // TODO: handle data
} else {
    // handle error
    echo $response->body();
}
?>
PHP;
        return $snippet;
    }
}
?>
