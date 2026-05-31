<?php

namespace App\Services\Logging;

use App\Models\ApiAuditLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;

class AuditService
{
    /**
     * Log the request/response pair.
     * Sensitive fields defined in config('audit.mask_fields').
     */
    public function logRequest(Request $request, Response $response): void
    {
        $maskFields = Config::get('audit.mask_fields', []);
        $requestBody = $request->all();
        $responseBody = $this->decodeJson($response->getContent());

        $this->maskArray($requestBody, $maskFields);
        $this->maskArray($responseBody, $maskFields);

        ApiAuditLog::create([
            'user_id'        => $request->user() ? $request->user()->id : null,
            'method'         => $request->method(),
            'endpoint'       => $request->path(),
            'request_body'   => $requestBody,
            'response_status'=> $response->getStatusCode(),
            'response_body'  => $responseBody,
        ]);
    }

    /**
     * Recursively mask values for given keys.
     */
    protected function maskArray(array &$array, array $keys): void
    {
        foreach ($array as $k => &$v) {
            if (is_array($v)) {
                $this->maskArray($v, $keys);
            } else {
                foreach ($keys as $mask) {
                    if (stripos($k, $mask) !== false) {
                        $v = '*****MASKED*****';
                        break;
                    }
                }
            }
        }
    }

    protected function decodeJson(string $content)
    {
        $decoded = json_decode($content, true);
        return $decoded ?? [];
    }
}
?>
