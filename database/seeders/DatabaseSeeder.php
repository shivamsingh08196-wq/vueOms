<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Client;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseLine;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles and Permissions
        $permissions = ['view', 'create', 'update', 'delete'];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo($permissions);

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->givePermissionTo(['view', 'create', 'update']);

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->givePermissionTo(['view', 'create']);

        // 2. Users
        $admin = User::firstOrCreate(['email' => 'admin@vueoms.com'], [
            'name' => 'Admin User',
            'password' => bcrypt('password')
        ]);
        $admin->assignRole('admin');

        $manager = User::firstOrCreate(['email' => 'manager@vueoms.com'], [
            'name' => 'Manager User',
            'password' => bcrypt('password')
        ]);
        $manager->assignRole('manager');

        $staff = User::firstOrCreate(['email' => 'staff@vueoms.com'], [
            'name' => 'Staff User',
            'password' => bcrypt('password')
        ]);
        $staff->assignRole('staff');

        // 3. Clients
        for ($i = 1; $i <= 5; $i++) {
            Client::firstOrCreate(['email' => "client{$i}@example.com"], [
                'name' => "Client {$i}",
                'phone' => "123456789{$i}",
                'address' => "Address {$i}"
            ]);
        }

        // 4. Items
        for ($i = 1; $i <= 10; $i++) {
            Item::firstOrCreate(['name' => "Item {$i}"], [
                'description' => "Description for Item {$i}",
                'price' => rand(10, 100),
                'stock' => rand(10, 50)
            ]);
        }

        // 5. Purchases
        $client = Client::first();
        $item = Item::first();
        
        if (!Purchase::where('purchase_number', 'PUR-001')->exists()) {
            $purchase = Purchase::create([
                'purchase_number' => 'PUR-001',
                'client_id' => $client->id,
                'purchase_date' => now(),
                'status' => 'pending',
                'total_amount' => $item->price * 2
            ]);

            PurchaseLine::create([
                'purchase_id' => $purchase->id,
                'item_id' => $item->id,
                'quantity' => 2,
                'unit_price' => $item->price,
                'subtotal' => $item->price * 2
            ]);
        }
    }
}