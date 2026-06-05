<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     */
    protected function success(mixed $data = null, string $message = 'Operation successful', array $meta = [], int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        $response['meta'] = array_merge([
            'timestamp' => now()->toIso8601String(),
            'request_id' => request()->header('X-Request-ID', uniqid('req_', true)),
            'api_version' => 'v1',
        ], $meta);

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message = 'Error occurred', int $code = 400, mixed $errors = null, ?string $errorCode = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'code'    => $errorCode ?? $this->getErrorCode($code),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $response['meta'] = [
            'timestamp' => now()->toIso8601String(),
            'request_id' => request()->header('X-Request-ID', uniqid('req_', true)),
            'api_version' => 'v1',
        ];

        return response()->json($response, $code);
    }

    /**
     * Return a paginated JSON response.
     */
    protected function paginated(mixed $paginator, string $message = 'Items retrieved successfully'): JsonResponse
    {
        $pagination = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];

        $links = [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];

        return $this->success(
            $paginator->items(),
            $message,
            [
                'pagination' => $pagination,
                'links' => $links,
            ]
        );
    }

    /**
     * Map HTTP status codes to application error codes.
     */
    private function getErrorCode(int $code): string
    {
        $codes = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            500 => 'INTERNAL_ERROR',
        ];

        return $codes[$code] ?? 'UNKNOWN_ERROR';
    }
}
