// test-s3.php
<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $s3Config = config('filesystems.disks.s3');
    echo "S3 Configuration:\n";
    echo "Bucket: " . $s3Config['bucket'] . "\n";
    echo "Region: " . $s3Config['region'] . "\n";
    echo "URL: " . $s3Config['url'] . "\n\n";

    // Try to list files
    $files = Storage::disk('s3')->files();
    echo "Files in bucket:\n";
    print_r($files);

    // Try to upload a test file
    $result = Storage::disk('s3')->put('test.txt', 'Hello World');
    echo "\nUpload result: " . ($result ? "Success" : "Failed") . "\n";

    // Get URL
    $url = Storage::disk('s3')->url('test.txt');
    echo "File URL: " . $url . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
