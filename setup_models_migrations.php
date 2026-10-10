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

// 1. Models
$models = [
    'Client' => '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class Client extends Model { protected $guarded = []; public function purchases() { return $this->hasMany(Purchase::class); } }',
    'Item' => '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class Item extends Model { protected $guarded = []; public function purchaseLines() { return $this->hasMany(PurchaseLine::class); } }',
    'Purchase' => '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class Purchase extends Model { protected $guarded = []; public function client() { return $this->belongsTo(Client::class); } public function lines() { return $this->hasMany(PurchaseLine::class); } public function comments() { return $this->hasMany(PurchaseComment::class); } public function statusLogs() { return $this->hasMany(PurchaseStatusLog::class); } }',
    'PurchaseLine' => '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class PurchaseLine extends Model { protected $guarded = []; public function purchase() { return $this->belongsTo(Purchase::class); } public function item() { return $this->belongsTo(Item::class); } }',
    'PurchaseComment' => '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class PurchaseComment extends Model { protected $guarded = []; public function purchase() { return $this->belongsTo(Purchase::class); } public function user() { return $this->belongsTo(User::class); } }',
    'PurchaseStatusLog' => '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class PurchaseStatusLog extends Model { protected $guarded = []; public function purchase() { return $this->belongsTo(Purchase::class); } public function user() { return $this->belongsTo(User::class, "changed_by_user_id"); } }',
    'AuditLog' => '<?php namespace App\Models; use Illuminate\Database\Eloquent\Model; class AuditLog extends Model { protected $guarded = []; protected $casts = ["changes" => "array"]; public function user() { return $this->belongsTo(User::class); } }',
];

foreach ($models as $name => $content) {
    makeFile("app/Models/{$name}.php", $content);
}

// Update User Model for Spatie
$userModel = <<<EOF
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected \$guarded = [];

    protected \$hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
EOF;
makeFile("app/Models/User.php", $userModel);

// 2. Database Migration for everything
$migration = <<<EOF
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->string('email')->unique();
            \$table->string('phone')->nullable();
            \$table->text('address')->nullable();
            \$table->timestamps();
        });

        Schema::create('items', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->text('description')->nullable();
            \$table->decimal('price', 10, 2);
            \$table->integer('stock')->default(0);
            \$table->timestamps();
        });

        Schema::create('purchases', function (Blueprint \$table) {
            \$table->id();
            \$table->string('purchase_number')->unique();
            \$table->foreignId('client_id')->constrained()->cascadeOnDelete();
            \$table->date('purchase_date');
            \$table->decimal('total_amount', 12, 2)->default(0);
            \$table->string('status')->default('pending');
            \$table->timestamps();
        });

        Schema::create('purchase_lines', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            \$table->foreignId('item_id')->constrained()->cascadeOnDelete();
            \$table->integer('quantity');
            \$table->decimal('unit_price', 10, 2);
            \$table->decimal('subtotal', 12, 2);
            \$table->timestamps();
        });

        Schema::create('purchase_comments', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            \$table->foreignId('user_id')->constrained()->cascadeOnDelete();
            \$table->text('comment');
            \$table->timestamps();
        });

        Schema::create('purchase_status_logs', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            \$table->string('old_status')->nullable();
            \$table->string('new_status');
            \$table->foreignId('changed_by_user_id')->constrained('users')->cascadeOnDelete();
            \$table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            \$table->string('action');
            \$table->string('model_type');
            \$table->unsignedBigInteger('model_id');
            \$table->json('changes')->nullable();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('purchase_status_logs');
        Schema::dropIfExists('purchase_comments');
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('items');
        Schema::dropIfExists('clients');
    }
};
EOF;

// delete all existing migrations except 0001_01_01, jobs, etc.
$files = glob(__DIR__ . '/database/migrations/*.php');
foreach ($files as $file) {
    if (str_contains($file, 'vue_vms_tables')) {
        unlink($file);
    }
}
makeFile('database/migrations/' . date('Y_m_d_His') . '_create_vue_vms_tables.php', $migration);

echo "Models and Migrations created.\n";
