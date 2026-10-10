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

// Ensure auth controllers
makeFile("app/Http/Controllers/Auth/LoginController.php", <<<EOF
<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller {
    public function __invoke(Request \$request) {
        \$request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        \$user = User::where('email', \$request->email)->first();
        if (!\$user || !Hash::check(\$request->password, \$user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }
        return response()->json([
            'token' => \$user->createToken('api-token')->plainTextToken,
            'user' => \$user->load('roles')
        ]);
    }
}
EOF
);

makeFile("app/Http/Controllers/Auth/LogoutController.php", <<<EOF
<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogoutController extends Controller {
    public function __invoke(Request \$request) {
        \$request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
EOF
);

makeFile("app/Http/Controllers/Auth/MeController.php", <<<EOF
<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller {
    public function __invoke(Request \$request) {
        return response()->json(\$request->user()->load('roles'));
    }
}
EOF
);

// Generic single action controllers logic
$entities = ['Client', 'Item', 'Purchase'];
foreach ($entities as $entity) {
    $lower = strtolower($entity);
    $plural = $lower . 's';
    
    // Create
    makeFile("app/Http/Controllers/{$entity}/Create{$entity}Controller.php", <<<EOF
<?php
namespace App\Http\Controllers\\{$entity};
use App\Http\Controllers\Controller;
use App\Models\\{$entity};
use Illuminate\Http\Request;

class Create{$entity}Controller extends Controller {
    public function __invoke(Request \$request) {
        \$this->authorize('create', {$entity}::class);
        \$data = \$request->all();
        \$record = {$entity}::create(\$data);
        return response()->json(\$record, 201);
    }
}
EOF
    );

    // Update
    makeFile("app/Http/Controllers/{$entity}/Update{$entity}Controller.php", <<<EOF
<?php
namespace App\Http\Controllers\\{$entity};
use App\Http\Controllers\Controller;
use App\Models\\{$entity};
use Illuminate\Http\Request;

class Update{$entity}Controller extends Controller {
    public function __invoke(Request \$request, {$entity} \${$lower}) {
        \$this->authorize('update', \${$lower});
        \${$lower}->update(\$request->all());
        return response()->json(\${$lower});
    }
}
EOF
    );

    // Delete
    makeFile("app/Http/Controllers/{$entity}/Delete{$entity}Controller.php", <<<EOF
<?php
namespace App\Http\Controllers\\{$entity};
use App\Http\Controllers\Controller;
use App\Models\\{$entity};

class Delete{$entity}Controller extends Controller {
    public function __invoke({$entity} \${$lower}) {
        \$this->authorize('delete', \${$lower});
        \${$lower}->delete();
        return response()->json(null, 204);
    }
}
EOF
    );

    // Get All
    makeFile("app/Http/Controllers/{$entity}/Get{$entity}sController.php", <<<EOF
<?php
namespace App\Http\Controllers\\{$entity};
use App\Http\Controllers\Controller;
use App\Models\\{$entity};

class Get{$entity}sController extends Controller {
    public function __invoke() {
        \$this->authorize('viewAny', {$entity}::class);
        return response()->json({$entity}::all());
    }
}
EOF
    );

    // Get One
    makeFile("app/Http/Controllers/{$entity}/Get{$entity}Controller.php", <<<EOF
<?php
namespace App\Http\Controllers\\{$entity};
use App\Http\Controllers\Controller;
use App\Models\\{$entity};

class Get{$entity}Controller extends Controller {
    public function __invoke({$entity} \${$lower}) {
        \$this->authorize('view', \${$lower});
        return response()->json(\${$lower});
    }
}
EOF
    );
}

// Custom Purchase creation with lines handling, we override the standard one
makeFile("app/Http/Controllers/Purchase/CreatePurchaseController.php", <<<EOF
<?php
namespace App\Http\Controllers\Purchase;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatePurchaseController extends Controller {
    public function __invoke(Request \$request) {
        \$this->authorize('create', Purchase::class);
        \$data = \$request->validate([
            'client_id' => 'required|exists:clients,id',
            'purchase_date' => 'required|date',
            'purchase_number' => 'required|unique:purchases,purchase_number',
            'lines' => 'required|array',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);
        
        return DB::transaction(function() use (\$data) {
            \$purchase = Purchase::create([
                'client_id' => \$data['client_id'],
                'purchase_date' => \$data['purchase_date'],
                'purchase_number' => \$data['purchase_number'],
                'status' => 'pending',
                'total_amount' => 0
            ]);
            
            \$total = 0;
            foreach (\$data['lines'] as \$line) {
                \$subtotal = \$line['quantity'] * \$line['unit_price'];
                \$total += \$subtotal;
                \$purchase->lines()->create([
                    'item_id' => \$line['item_id'],
                    'quantity' => \$line['quantity'],
                    'unit_price' => \$line['unit_price'],
                    'subtotal' => \$subtotal
                ]);
            }
            \$purchase->update(['total_amount' => \$total]);
            return response()->json(\$purchase->load('lines'), 201);
        });
    }
}
EOF
);

// Create Policies
$policies = ['ClientPolicy', 'ItemPolicy', 'PurchasePolicy'];
foreach ($policies as $policy) {
    makeFile("app/Policies/{$policy}.php", <<<EOF
<?php
namespace App\Policies;
use App\Models\User;

class {$policy} {
    public function viewAny(User \$user) {
        return \$user->hasPermissionTo('view');
    }
    public function view(User \$user) {
        return \$user->hasPermissionTo('view');
    }
    public function create(User \$user) {
        return \$user->hasPermissionTo('create');
    }
    public function update(User \$user) {
        return \$user->hasPermissionTo('update');
    }
    public function delete(User \$user) {
        return \$user->hasPermissionTo('delete');
    }
}
EOF
    );
}

// AuthServiceProvider setup if needed, but in Laravel 11/12 policies are auto-discovered.

// Register Routes
makeFile("routes/api.php", <<<EOF
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;

Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/me', MeController::class);

    // Clients
    Route::get('/clients', App\Http\Controllers\Client\GetClientsController::class);
    Route::get('/clients/{client}', App\Http\Controllers\Client\GetClientController::class);
    Route::post('/clients', App\Http\Controllers\Client\CreateClientController::class);
    Route::put('/clients/{client}', App\Http\Controllers\Client\UpdateClientController::class);
    Route::delete('/clients/{client}', App\Http\Controllers\Client\DeleteClientController::class);

    // Items
    Route::get('/items', App\Http\Controllers\Item\GetItemsController::class);
    Route::get('/items/{item}', App\Http\Controllers\Item\GetItemController::class);
    Route::post('/items', App\Http\Controllers\Item\CreateItemController::class);
    Route::put('/items/{item}', App\Http\Controllers\Item\UpdateItemController::class);
    Route::delete('/items/{item}', App\Http\Controllers\Item\DeleteItemController::class);

    // Purchases
    Route::get('/purchases', App\Http\Controllers\Purchase\GetPurchasesController::class);
    Route::get('/purchases/{purchase}', App\Http\Controllers\Purchase\GetPurchaseController::class);
    Route::post('/purchases', App\Http\Controllers\Purchase\CreatePurchaseController::class);
    Route::put('/purchases/{purchase}', App\Http\Controllers\Purchase\UpdatePurchaseController::class);
    Route::delete('/purchases/{purchase}', App\Http\Controllers\Purchase\DeletePurchaseController::class);
});
EOF
);

echo "Controllers, Policies and Routes created.\n";
