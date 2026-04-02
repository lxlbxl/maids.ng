<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

class ResponseFormatMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Only format JSON API responses (skip assets, HTML pages)
        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/api/') || str_starts_with($path, '/admin/api/')) {
            $contentType = $response->getHeaderLine('Content-Type');
            if (str_contains($contentType, 'application/json')) {
                $body = $response->getBody()->__toString();
                $data = json_decode($body, true);

                // Skip if already in envelope format
                if ($data === null || (isset($data['success']) && isset($data['meta']))) {
                    return $response;
                }

                // Determine if successful based on HTTP status code
                $success = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;

                // Wrap in standard envelope
                $envelope = [
                    'success' => $success,
                    'data' => $data,
                    'meta' => $this->extractPagination($request, $data),
                    'error' => $success ? null : $this->formatError($data)
                ];

                $newResponse = new Response(
                    $response->getStatusCode(),
                    $response->getHeaders()
                );
                $newResponse->getBody()->write(json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return $newResponse->withHeader('Content-Type', 'application/json');
            }
        }

        return $response;
    }

    private function extractPagination(ServerRequestInterface $request, array $data): array
    {
        // Extract pagination from query params
        $query = $request->getQueryParams();
        $page = (int)($query['page'] ?? 1);
        $perPage = (int)($query['per_page'] ?? 20);

        $meta = [
            'page' => $page,
            'per_page' => $perPage
        ];

        // Infer pagination totals if data contains items with total count
        if (isset($data['total'])) {
            $meta['total'] = (int)$data['total'];
            $meta['pages'] = (int)ceil($meta['total'] / $perPage);
        } elseif (isset($data[0]) && count($data) < $perPage && $page > 1) {
            $meta['has_more'] = false;
        } elseif (isset($data[0]) && count($data) === $perPage) {
            $meta['has_more'] = true; // May have more
        }

        return $meta;
    }

    private function formatError(array $data): array
    {
        // Normalize error format
        if (isset($data['error'])) {
            if (is_string($data['error'])) {
                return [
                    'code' => 'ERROR',
                    'message' => $data['error'],
                    'details' => null
                ];
            }
            return $data['error'];
        }

        if (isset($data['message'])) {
            return [
                'code' => 'ERROR',
                'message' => $data['message'],
                'details' => null
            ];
        }

        return [
            'code' => 'UNKNOWN_ERROR',
            'message' => 'An unexpected error occurred',
            'details' => null
        ];
    }
}
