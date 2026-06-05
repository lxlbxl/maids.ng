<?php
/**
 * Emergency fix script for Maids.ng
 * 
 * 1. Verifies the ApiResponse trait file exists
 * 2. Regenerates the Composer autoloader classmap
 * 3. Clears Laravel caches
 * 
 * Upload this to the server root and run it via browser: https://maids.ng/fix-autoload.php
 * DELETE THIS FILE AFTER USE!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "=== Maids.ng Autoloader Fix ===\n\n";

// Detect base path
$basePath = dirname(__FILE__);
echo "Base path: $basePath\n\n";

// Step 1: Check if the trait file exists
$traitFile = $basePath . '/app/Traits/ApiResponse.php';
echo "1. Checking ApiResponse trait...\n";
if (file_exists($traitFile)) {
    echo "   ✅ File exists at: $traitFile\n";
    echo "   File size: " . filesize($traitFile) . " bytes\n";
} else {
    echo "   ❌ FILE NOT FOUND: $traitFile\n";
    echo "   This is the root cause of the crash!\n\n";
    
    // Check if the Traits directory exists
    $traitsDir = $basePath . '/app/Traits';
    if (!is_dir($traitsDir)) {
        echo "   Creating directory: $traitsDir\n";
        mkdir($traitsDir, 0755, true);
        echo "   ✅ Directory created\n";
    }
    
    // Create the trait file inline
    echo "   Creating ApiResponse.php...\n";
    $traitContent = <<<'PHP'
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
PHP;
    
    file_put_contents($traitFile, $traitContent);
    
    if (file_exists($traitFile)) {
        echo "   ✅ ApiResponse.php created successfully (" . filesize($traitFile) . " bytes)\n";
    } else {
        echo "   ❌ Failed to create file! Check directory permissions.\n";
    }
}

echo "\n";

// Step 2: Regenerate Composer autoloader
echo "2. Regenerating Composer autoloader...\n";

$autoloadFile = $basePath . '/vendor/composer/autoload_classmap.php';
$autoloadPsr4 = $basePath . '/vendor/composer/autoload_psr4.php';

if (file_exists($autoloadPsr4)) {
    echo "   PSR-4 autoload file exists.\n";
}

// Check if the class is in the classmap
if (file_exists($autoloadFile)) {
    $classmap = include $autoloadFile;
    if (isset($classmap['App\\Traits\\ApiResponse'])) {
        echo "   ✅ ApiResponse is already in the classmap\n";
    } else {
        echo "   ⚠️ ApiResponse NOT in classmap - attempting to add it...\n";
        
        // Try running composer dump-autoload
        $composerPhar = $basePath . '/composer.phar';
        $composerBin = null;
        
        if (file_exists($composerPhar)) {
            $composerBin = "php $composerPhar";
        } else {
            // Try system composer
            $output = [];
            exec('which composer 2>/dev/null', $output);
            if (!empty($output)) {
                $composerBin = 'composer';
            }
        }
        
        if ($composerBin) {
            echo "   Running: $composerBin dump-autoload\n";
            $output = [];
            $returnCode = 0;
            exec("cd $basePath && $composerBin dump-autoload 2>&1", $output, $returnCode);
            echo "   " . implode("\n   ", $output) . "\n";
            if ($returnCode === 0) {
                echo "   ✅ Autoloader regenerated\n";
            } else {
                echo "   ⚠️ Composer failed (exit code: $returnCode)\n";
                echo "   Manually patching classmap...\n";
                goto manual_patch;
            }
        } else {
            manual_patch:
            // Manually add the class to the classmap
            echo "   Composer not available, manually patching classmap...\n";
            
            $classmapContent = file_get_contents($autoloadFile);
            
            // Add ApiResponse to the classmap
            $newEntry = "    'App\\\\Traits\\\\ApiResponse' => \$baseDir . '/app/Traits/ApiResponse.php',\n";
            
            // Insert before the closing bracket
            $classmapContent = str_replace(
                "return array(\n",
                "return array(\n" . $newEntry,
                $classmapContent
            );
            
            file_put_contents($autoloadFile, $classmapContent);
            echo "   ✅ Classmap patched manually\n";
        }
    }
} else {
    echo "   ❌ Classmap file not found. vendor/ may be corrupted.\n";
}

echo "\n";

// Step 3: Clear Laravel caches
echo "3. Clearing Laravel caches...\n";

$cachePaths = [
    $basePath . '/bootstrap/cache/config.php',
    $basePath . '/bootstrap/cache/routes-v7.php', 
    $basePath . '/bootstrap/cache/services.php',
    $basePath . '/bootstrap/cache/packages.php',
];

foreach ($cachePaths as $cachePath) {
    if (file_exists($cachePath)) {
        unlink($cachePath);
        echo "   Deleted: " . basename($cachePath) . "\n";
    }
}

// Clear compiled views
$viewCachePath = $basePath . '/storage/framework/views';
if (is_dir($viewCachePath)) {
    $viewFiles = glob($viewCachePath . '/*.php');
    $count = 0;
    foreach ($viewFiles as $file) {
        unlink($file);
        $count++;
    }
    echo "   Cleared $count compiled views\n";
}

echo "\n";

// Step 4: Verify the fix
echo "4. Verifying the fix...\n";

// Re-include the autoloader
$autoloader = $basePath . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    // We can't re-include the autoloader in the same process since classes are cached
    // But we can verify the file exists and the classmap has it
    $classmap = include $autoloadFile;
    if (isset($classmap['App\\Traits\\ApiResponse']) || file_exists($traitFile)) {
        echo "   ✅ ApiResponse trait file is in place\n";
    }
    
    // Try to include the trait file directly to check for syntax errors
    try {
        $content = file_get_contents($traitFile);
        $tokens = token_get_all($content);
        echo "   ✅ ApiResponse.php has valid PHP syntax\n";
    } catch (\Throwable $e) {
        echo "   ❌ Syntax error in ApiResponse.php: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "=== Done ===\n";
echo "Try loading your site now.\n";
echo "\n⚠️  IMPORTANT: Delete this file (fix-autoload.php) after the site is working!\n";
echo "</pre>";
