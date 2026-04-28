<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Request Validation Middleware
 * Validates request body against defined rules
 */
class ValidationMiddleware implements MiddlewareInterface
{
    private array $rules;
    private string $routePattern;

    public function __construct(array $rules = [], string $routePattern = '')
    {
        $this->rules = $rules;
        $this->routePattern = $routePattern;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Only validate POST, PUT, PATCH requests with body
        $method = $request->getMethod();
        if (!in_array($method, ['POST', 'PUT', 'PATCH'])) {
            return $handler->handle($request);
        }

        $data = $request->getParsedBody() ?? [];
        $errors = $this->validate($data, $this->rules);

        if (!empty($errors)) {
            return $this->validationErrorResponse($errors);
        }

        // Sanitize data and pass to handler
        $sanitized = $this->sanitize($data, $this->rules);
        $request = $request->withParsedBody($sanitized);

        return $handler->handle($request);
    }

    /**
     * Validate data against rules
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($ruleList as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $error = $this->applyRule($field, $value, $rule, $params, $data);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Apply a single validation rule
     */
    private function applyRule(string $field, $value, string $rule, array $params, array $data): ?string
    {
        $fieldLabel = ucfirst(str_replace('_', ' ', $field));

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    return "{$fieldLabel} is required";
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "{$fieldLabel} must be a valid email address";
                }
                break;

            case 'phone':
                if ($value && !$this->isValidPhone($value)) {
                    return "{$fieldLabel} must be a valid Nigerian phone number";
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    return "{$fieldLabel} must be a number";
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
                    return "{$fieldLabel} must be an integer";
                }
                break;

            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (is_numeric($value) && $value < $min) {
                    return "{$fieldLabel} must be at least {$min}";
                }
                if (is_string($value) && strlen($value) < $min) {
                    return "{$fieldLabel} must be at least {$min} characters";
                }
                break;

            case 'max':
                $max = (int) ($params[0] ?? PHP_INT_MAX);
                if (is_numeric($value) && $value > $max) {
                    return "{$fieldLabel} must not exceed {$max}";
                }
                if (is_string($value) && strlen($value) > $max) {
                    return "{$fieldLabel} must not exceed {$max} characters";
                }
                break;

            case 'between':
                $min = (int) ($params[0] ?? 0);
                $max = (int) ($params[1] ?? PHP_INT_MAX);
                if (is_numeric($value) && ($value < $min || $value > $max)) {
                    return "{$fieldLabel} must be between {$min} and {$max}";
                }
                break;

            case 'in':
                $allowed = $params;
                if ($value && !in_array($value, $allowed, true)) {
                    return "{$fieldLabel} must be one of: " . implode(', ', $allowed);
                }
                break;

            case 'date':
                if ($value && !strtotime($value)) {
                    return "{$fieldLabel} must be a valid date";
                }
                break;

            case 'date_format':
                $format = $params[0] ?? 'Y-m-d';
                if ($value) {
                    $d = \DateTime::createFromFormat($format, $value);
                    if (!$d || $d->format($format) !== $value) {
                        return "{$fieldLabel} must match format {$format}";
                    }
                }
                break;

            case 'after':
                $afterDate = $params[0] ?? 'now';
                if ($value && strtotime($value) <= strtotime($afterDate)) {
                    return "{$fieldLabel} must be after {$afterDate}";
                }
                break;

            case 'before':
                $beforeDate = $params[0] ?? 'now';
                if ($value && strtotime($value) >= strtotime($beforeDate)) {
                    return "{$fieldLabel} must be before {$beforeDate}";
                }
                break;

            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return "{$fieldLabel} must be a valid URL";
                }
                break;

            case 'alpha':
                if ($value && !ctype_alpha($value)) {
                    return "{$fieldLabel} must contain only letters";
                }
                break;

            case 'alpha_num':
                if ($value && !ctype_alnum($value)) {
                    return "{$fieldLabel} must contain only letters and numbers";
                }
                break;

            case 'alpha_dash':
                if ($value && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                    return "{$fieldLabel} must contain only letters, numbers, dashes, and underscores";
                }
                break;

            case 'regex':
                $pattern = $params[0] ?? '';
                if ($value && $pattern && !preg_match($pattern, $value)) {
                    return "{$fieldLabel} format is invalid";
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if ($value !== ($data[$confirmField] ?? null)) {
                    return "{$fieldLabel} confirmation does not match";
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    return "{$fieldLabel} must be an array";
                }
                break;

            case 'json':
                if ($value && is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return "{$fieldLabel} must be valid JSON";
                    }
                }
                break;

            case 'pin':
                if ($value && !preg_match('/^\d{4,6}$/', $value)) {
                    return "{$fieldLabel} must be 4-6 digits";
                }
                break;

            case 'nin':
                if ($value && !preg_match('/^\d{11}$/', $value)) {
                    return "{$fieldLabel} must be 11 digits";
                }
                break;

            case 'salary':
                if ($value !== null && $value !== '') {
                    $amount = (int) $value;
                    if ($amount < 20000 || $amount > 500000) {
                        return "{$fieldLabel} must be between ₦20,000 and ₦500,000";
                    }
                }
                break;

            case 'location':
                $validLocations = $this->getValidLocations();
                if ($value && !in_array($value, $validLocations, true)) {
                    return "{$fieldLabel} is not a supported location";
                }
                break;

            case 'work_type':
                $validTypes = ['live-in', 'live-out', 'part-time', 'full-time'];
                if ($value && !in_array($value, $validTypes, true)) {
                    return "{$fieldLabel} must be one of: " . implode(', ', $validTypes);
                }
                break;
        }

        return null;
    }

    /**
     * Sanitize input data
     */
    private function sanitize(array $data, array $rules): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Skip fields not in rules unless rules are empty (allow all)
            if (!empty($rules) && !isset($rules[$key])) {
                continue;
            }

            if (is_string($value)) {
                // Trim whitespace
                $value = trim($value);

                // Remove null bytes
                $value = str_replace(chr(0), '', $value);

                // Convert special HTML entities
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Validate Nigerian phone number
     */
    private function isValidPhone(string $phone): bool
    {
        // Remove all non-digits
        $cleaned = preg_replace('/\D/', '', $phone);

        // Check for valid Nigerian format
        // 11 digits starting with 0, or 13 digits starting with 234
        if (strlen($cleaned) === 11 && strpos($cleaned, '0') === 0) {
            return true;
        }
        if (strlen($cleaned) === 13 && strpos($cleaned, '234') === 0) {
            return true;
        }
        if (strlen($cleaned) === 10 && preg_match('/^[789]/', $cleaned)) {
            // Without leading 0
            return true;
        }

        return false;
    }

    /**
     * Get valid locations from config
     */
    private function getValidLocations(): array
    {
        // Common Nigerian cities/states
        return [
            'Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano', 'Kaduna',
            'Benin City', 'Warri', 'Enugu', 'Onitsha', 'Calabar', 'Uyo',
            'Owerri', 'Jos', 'Ilorin', 'Abeokuta', 'Osogbo', 'Akure',
            // States
            'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa',
            'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo',
            'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna',
            'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa',
            'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers',
            'Sokoto', 'Taraba', 'Yobe', 'Zamfara'
        ];
    }

    /**
     * Return validation error response
     */
    private function validationErrorResponse(array $errors): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Validation failed',
            'errors' => $errors
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(422);
    }
}
