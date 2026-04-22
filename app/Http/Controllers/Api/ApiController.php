<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Base API Controller
 * 
 * Provides standardized JSON response formats for all API endpoints.
 * Designed for Agentic AI consumption with consistent structure.
 * 
 * @author Maids.ng API Team
 * @version 1.0.0
 */
abstract class ApiController extends Controller
{
    /**
     * Success Response Format
     * 
     * Structure optimized for AI agents:
     * - success: boolean (always true)
     * - message: human-readable status
     * - data: the actual payload
     * - meta: pagination, timestamps, request_id for tracing
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param array $meta Additional metadata
     * @param int $code HTTP status code
     * @return JsonResponse
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        array $meta = [],
        int $code = Response::HTTP_OK
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        // Add metadata for AI context
        $defaultMeta = [
            'timestamp' => now()->toIso8601String(),
            'request_id' => request()->header('X-Request-ID', uniqid('req_', true)),
            'api_version' => 'v1',
        ];

        if (!empty($meta)) {
            $response['meta'] = array_merge($defaultMeta, $meta);
        } else {
            $response['meta'] = $defaultMeta;
        }

        return response()->json($response, $code);
    }

    /**
     * Error Response Format
     * 
     * Structure for error handling:
     * - success: boolean (always false)
     * - message: human-readable error
     * - errors: detailed error object for debugging
     * - code: error code for programmatic handling
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array|null $errors Detailed errors
     * @param string|null $errorCode Application error code
     * @return JsonResponse
     */
    protected function error(
        string $message = 'Error',
        int $code = Response::HTTP_BAD_REQUEST,
        ?array $errors = null,
        ?string $errorCode = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $errorCode ?? $this->getErrorCode($code),
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
     * Paginated Response Format
     * 
     * Standard pagination structure for list endpoints.
     * Compatible with Laravel's LengthAwarePaginator.
     * 
     * @param mixed $data Paginated data
     * @param string $message Success message
     * @param int $code HTTP status code
     * @return JsonResponse
     */
    protected function paginated(
        mixed $data,
        string $message = 'Success',
        int $code = Response::HTTP_OK
    ): JsonResponse {
        $pagination = [
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'has_more' => $data->hasMorePages(),
        ];

        $links = [
            'first' => $data->url(1),
            'last' => $data->url($data->lastPage()),
            'prev' => $data->previousPageUrl(),
            'next' => $data->nextPageUrl(),
        ];

        return $this->success(
            $data->items(),
            $message,
            [
                'pagination' => $pagination,
                'links' => $links,
            ],
            $code
        );
    }

    /**
     * Created Response (201)
     * 
     * Standard response for resource creation.
     * 
     * @param mixed $data Created resource
     * @param string $message Success message
     * @return JsonResponse
     */
    protected function created(mixed $data, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->success($data, $message, [], Response::HTTP_CREATED);
    }

    /**
     * No Content Response (204)
     * 
     * For delete operations or empty responses.
     * 
     * @return JsonResponse
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Validation Error Response (422)
     * 
     * Standardized validation error format.
     * 
     * @param array $errors Validation errors
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors, 'VALIDATION_ERROR');
    }

    /**
     * Not Found Response (404)
     * 
     * @param string $resource Resource name
     * @return JsonResponse
     */
    protected function notFound(string $resource = 'Resource'): JsonResponse
    {
        return $this->error(
            "{$resource} not found",
            Response::HTTP_NOT_FOUND,
            null,
            'NOT_FOUND'
        );
    }

    /**
     * Unauthorized Response (401)
     * 
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED, null, 'UNAUTHORIZED');
    }

    /**
     * Forbidden Response (403)
     * 
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN, null, 'FORBIDDEN');
    }

    /**
     * Get Error Code from HTTP Status
     * 
     * Maps HTTP status codes to application error codes.
     * 
     * @param int $code HTTP status code
     * @return string
     */
    private function getErrorCode(int $code): string
    {
        $codes = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            500 => 'INTERNAL_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
        ];

        return $codes[$code] ?? 'UNKNOWN_ERROR';
    }

    /**
     * Get Authenticated User
     * 
     * Helper to get current authenticated user with type safety.
     * 
     * @return \App\Models\User|null
     */
    protected function user(): ?\App\Models\User
    {
        return auth()->user();
    }

    /**
     * Check User Role
     * 
     * @param string $role Role to check
     * @return bool
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->user();
        return $user && $user->hasRole($role);
    }

    /**
     * Require Role or Fail
     * 
     * @param string $role Required role
     * @return JsonResponse|null
     */
    protected function requireRole(string $role): ?JsonResponse
    {
        if (!$this->hasRole($role)) {
            return $this->forbidden("This action requires '{$role}' role");
        }
        return null;
    }
}
