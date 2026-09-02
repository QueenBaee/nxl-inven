<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Owner & Staff Accounts
        $owner = User::firstOrCreate(
            ['email' => 'admin@inventory.test'],
            [
                'name' => 'Owner Admin',
                'password' => Hash::make('password'),
                'role' => 'owner',
            ]
        );

        $staff = User::firstOrCreate(
            ['email' => 'kasir@inventory.test'],
            [
                'name' => 'Kasir Utama',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );

        // 2. Suppliers
        $supplier1 = Supplier::firstOrCreate(
            ['name' => 'PT Billiard Jaya Abadi'],
            [
                'contact_person' => 'Budi Santoso',
                'phone' => '081234567890',
                'address' => 'Jl. Mangga Dua Raya No. 45, Jakarta',
            ]
        );

        $supplier2 = Supplier::firstOrCreate(
            ['name' => 'Ko Hendra Cue Specialist'],
            [
                'contact_person' => 'Hendra Wijaya',
                'phone' => '081899887766',
                'address' => 'Surabaya, Jawa Timur',
            ]
        );

        // 3. Regular (Owned) Products
        Product::firstOrCreate(
            ['sku' => 'REG-CHALK-01'],
            [
                'name' => 'Taom Pyro Chalk - Blue Edition',
                'type' => ProductType::Regular,
                'cost_price' => 250000.00,
                'selling_price' => 350000.00,
                'stock' => 25,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'REG-GLOVE-01'],
            [
                'name' => 'Kamui Billiard Glove Quick-Dry (Size L)',
                'type' => ProductType::Regular,
                'cost_price' => 280000.00,
                'selling_price' => 390000.00,
                'stock' => 15,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'REG-TIP-01'],
            [
                'name' => 'Kamui Clear Black Tip (Medium)',
                'type' => ProductType::Regular,
                'cost_price' => 220000.00,
                'selling_price' => 320000.00,
                'stock' => 30,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'REG-BALL-01'],
            [
                'name' => 'Aramith Tournament Pro TV Pool Ball Set',
                'type' => ProductType::Regular,
                'cost_price' => 4500000.00,
                'selling_price' => 5800000.00,
                'stock' => 4,
            ]
        );

        // 4. Consignment Products
        Product::firstOrCreate(
            ['sku' => 'CNS-FURY-01'],
            [
                'name' => 'Fury Carbon Shaft KL-01 (Titipan Ko Hendra)',
                'type' => ProductType::Consignment,
                'supplier_id' => $supplier2->id,
                'cost_price' => 1800000.00,
                'selling_price' => 2400000.00,
                'stock' => 6,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'CNS-PRED-01'],
            [
                'name' => 'Predator REVO 12.4 WVP Radial (Titipan PT Billiard Jaya)',
                'type' => ProductType::Consignment,
                'supplier_id' => $supplier1->id,
                'cost_price' => 6500000.00,
                'selling_price' => 8200000.00,
                'stock' => 3,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'CNS-CASE-01'],
            [
                'name' => 'Mezz Hard Cue Case 3B5S Black',
                'type' => ProductType::Consignment,
                'supplier_id' => $supplier1->id,
                'cost_price' => 1600000.00,
                'selling_price' => 2100000.00,
                'stock' => 5,
            ]
        );
    }
}
