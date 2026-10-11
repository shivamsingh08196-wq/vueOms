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
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = [
            'users.manage',
            'roles.manage',
            'customers.view',
            'customers.manage',
            'products.view',
            'products.manage',
            'orders.view_all',
            'orders.view_assigned',
            'orders.create',
            'orders.update',
            'orders.delete',
            'orders.assign',
            'orders.change_status',
            'orders.add_note',
            'dashboard.view',
            'reports.view',
            'audit.view',
            'view',
            'create',
            'update',
            'delete',
        ];

        foreach ($allPermissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions($allPermissions);

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions([
            'customers.view', 'customers.manage', 'products.view',
            'orders.view_all', 'orders.create', 'orders.update',
            'orders.assign', 'orders.change_status', 'orders.add_note',
            'dashboard.view', 'reports.view', 'view', 'create', 'update',
        ]);

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->syncPermissions([
            'customers.view', 'products.view',
            'orders.view_assigned', 'orders.change_status', 'orders.add_note',
            'view', 'create',
        ]);

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
        $allClients = Client::all();
        $allItems = Item::all();
        $statuses = ['completed', 'confirmed', 'processing', 'ready', 'pending', 'cancelled'];
        
        $purchaseDates = [
            '2026-10-10', '2026-10-08', '2026-10-05', '2026-10-01',
            '2026-09-25', '2026-09-15', '2026-09-02', '2026-08-20',
            '2026-08-10', '2026-07-14', '2026-06-30', '2026-05-18',
            '2026-04-12', '2026-03-22', '2026-02-15', '2026-01-10',
            '2025-12-20', '2025-11-15', '2025-10-05', '2025-08-14',
        ];

        for ($k = 0; $k < count($purchaseDates); $k++) {
            $pNum = sprintf('PUR-%03d', $k + 1);
            if (!Purchase::where('purchase_number', $pNum)->exists()) {
                $client = $allClients[$k % count($allClients)];
                $status = $statuses[$k % count($statuses)];
                $date = $purchaseDates[$k];

                $purchase = Purchase::create([
                    'purchase_number' => $pNum,
                    'client_id' => $client->id,
                    'purchase_date' => $date,
                    'status' => $status,
                    'priority' => ($k % 3 === 0) ? 'high' : 'normal',
                    'total_amount' => 0,
                ]);

                // Create 1 to 3 line items
                $numLines = ($k % 3) + 1;
                $runningTotal = 0;
                for ($lineIdx = 0; $lineIdx < $numLines; $lineIdx++) {
                    $item = $allItems[($k + $lineIdx) % count($allItems)];
                    $qty = ($lineIdx + 1) * 2;
                    $unitPrice = (float) $item->price;
                    $subtotal = $qty * $unitPrice;
                    $runningTotal += $subtotal;

                    PurchaseLine::create([
                        'purchase_id' => $purchase->id,
                        'item_id' => $item->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                    ]);
                }

                $purchase->update(['total_amount' => $runningTotal]);
            }
        }
    }
}