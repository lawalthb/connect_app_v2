<?php
require_once 'vendor/autoload.php';

// Check the actual content of RtcTokenBuilder.php
$file = 'vendor/boogiefromzk/agora-token/src/RtcTokenBuilder.php';
if (file_exists($file)) {
    echo "📄 Checking RtcTokenBuilder.php:\n";
    $content = file_get_contents($file);

    // Extract namespace
    if (preg_match('/namespace\s+([\w\\\\]+);/', $content, $matches)) {
        echo "Namespace: " . $matches[1] . "\n";
    } else {
        echo "No namespace found\n";
    }

    // Extract class name
    if (preg_match('/class\s+(\w+)/', $content, $matches)) {
        echo "Class: " . $matches[1] . "\n";
    }

    // Extract methods
    preg_match_all('/public\s+static\s+function\s+(\w+)\s*\(/', $content, $matches);
    if (!empty($matches[1])) {
        echo "Methods: " . implode(', ', $matches[1]) . "\n";
    }

    echo "\n📄 First 20 lines of the file:\n";
    $lines = explode("\n", $content);
    for ($i = 0; $i < min(20, count($lines)); $i++) {
        echo ($i + 1) . ": " . $lines[$i] . "\n";
    }
}

// Also check RtcTokenBuilder2.php
echo "\n" . str_repeat("=", 50) . "\n";
$file2 = 'vendor/boogiefromzk/agora-token/src/RtcTokenBuilder2.php';
if (file_exists($file2)) {
    echo "📄 Checking RtcTokenBuilder2.php:\n";
    $content2 = file_get_contents($file2);

    // Extract namespace
    if (preg_match('/namespace\s+([\w\\\\]+);/', $content2, $matches)) {
        echo "Namespace: " . $matches[1] . "\n";
    } else {
        echo "No namespace found\n";
    }

    // Extract class name
    if (preg_match('/class\s+(\w+)/', $content2, $matches)) {
        echo "Class: " . $matches[1] . "\n";
    }

    // Extract methods
    preg_match_all('/public\s+static\s+function\s+(\w+)\s*\(/', $content2, $matches);
    if (!empty($matches[1])) {
        echo "Methods: " . implode(', ', $matches[1]) . "\n";
    }
}
?>
