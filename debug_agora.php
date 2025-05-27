<?php
require_once 'vendor/autoload.php';

// Check if different variations of the class exist
$classes_to_check = [
    'AgoraToken\RtcTokenBuilder',
    'AgoraToken\TokenBuilder',
    'AgoraToken\Builder\RtcTokenBuilder',
    'Agora\RtcTokenBuilder',
    'RtcTokenBuilder',
];

foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "✅ Found: $class\n";
        $reflection = new ReflectionClass($class);
        echo "Methods: " . implode(', ', array_map(function($method) {
            return $method->getName();
        }, $reflection->getMethods())) . "\n\n";
    } else {
        echo "❌ Not found: $class\n";
    }
}

// Also check what's in the vendor directory
if (is_dir('vendor/boogiefromzk/agora-token/src')) {
    echo "\n📁 Package files:\n";
    $files = glob('vendor/boogiefromzk/agora-token/src/*.php');
    foreach ($files as $file) {
        echo "- " . basename($file) . "\n";
    }
}
?>
