<?php

$path = __DIR__;

function makeFile($filePath, $content) {
    global $path;
    $fullPath = $path . '/' . $filePath;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($fullPath, $content);
}

$factories = ['Client', 'Item', 'Purchase', 'PurchaseLine', 'PurchaseComment', 'PurchaseStatusLog', 'AuditLog'];

foreach ($factories as $factory) {
    makeFile("database/factories/{$factory}Factory.php", <<<EOF
<?php

namespace Database\Factories;

use App\Models\\{$factory};
use Illuminate\Database\Eloquent\Factories\Factory;

class {$factory}Factory extends Factory
{
    protected \$model = {$factory}::class;

    public function definition(): array
    {
        return [
            // Definition can be filled as needed
        ];
    }
}
EOF
    );
}

// Ensure Models use HasFactory
$modelsDir = __DIR__ . '/app/Models';
$files = glob($modelsDir . '/*.php');
foreach ($files as $file) {
    if (str_contains($file, 'User.php')) continue;
    $content = file_get_contents($file);
    if (!str_contains($content, 'HasFactory')) {
        $content = str_replace('use Illuminate\Database\Eloquent\Model;', "use Illuminate\Database\Eloquent\Model;\nuse Illuminate\Database\Eloquent\Factories\HasFactory;", $content);
        $content = str_replace('class ', "class ", $content);
        $content = preg_replace('/class\s+([A-Za-z0-9_]+)\s+extends\s+Model\s*\{/', "class $1 extends Model\n{\n    use HasFactory;\n", $content);
        file_put_contents($file, $content);
    }
}
echo "Factories created.\n";
