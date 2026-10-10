<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'purchase_number' => 'PUR-' . $this->faker->unique()->numberBetween(1000, 9999),
            'purchase_date' => $this->faker->date(),
            'status' => 'New',
            'total_amount' => $this->faker->randomFloat(2, 50, 5000),
        ];
    }
}