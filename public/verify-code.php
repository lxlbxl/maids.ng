<?php
/**
 * Debug: Verify which AmbassadorAgent.php file is being executed.
 * Upload to public/, visit: /verify-code.php
 */

$file = __DIR__ . '/../app/Services/Agents/AmbassadorAgent.php';

echo "<pre>";
echo "Expected file: {$file}\n";
echo "Exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";

if (file_exists($file)) {
    $content = file_get_contents($file);
    echo "File size: " . strlen($content) . " bytes\n\n";

    // Check for old code signature
    if (strpos($content, "'temperature' => 0.7,") !== false) {
        echo "[OLD CODE DETECTED] File still contains hardcoded 'temperature' => 0.7\n";
    }

    // Check for new code signature
    if (strpos($content, '$isReasoning') !== false || strpos($content, 'reasoning:') !== false) {
        echo "[NEW CODE DETECTED] File contains reasoning model check\n";
    }

    // Show the exact temperature handling code
    if (preg_match('/temperature.*?=.*?0\.7/s', $content)) {
        echo "\n--- Temperature-related code found ---\n";
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (stripos($line, 'temperature') !== false || stripos($line, 'reasoning') !== false || stripos($line, 'o1') !== false) {
                echo "Line " . ($i + 1) . ": {$line}\n";
            }
        }
    }
}

echo "\n\n--- Reflection check ---\n";
$reflector = new ReflectionClass('App\Services\Agents\AmbassadorAgent');
echo "Actual file path: " . $reflector->getFileName() . "\n";
echo "Last modified: " . date('Y-m-d H:i:s', filemtime($reflector->getFileName())) . "\n";

// Check the actual loaded content
$actualContent = file_get_contents($reflector->getFileName());
if (strpos($actualContent, '$isReasoning') !== false) {
    echo "[CONFIRMED] Loaded file HAS the new reasoning check\n";
} else {
    echo "[CONFIRMED] Loaded file does NOT have the new reasoning check\n";
}

echo "</pre>";
