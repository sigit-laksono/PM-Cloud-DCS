<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'name' => 'PT Maju Jaya',
                'code' => 'MAJU',
                'pic_name' => 'Budi Santoso',
                'pic_contact' => 'budi@majujaya.co.id',
                'is_active' => true,
            ],
            [
                'name' => 'CV Teknologi Nusantara',
                'code' => 'TEKNUS',
                'pic_name' => 'Siti Rahayu',
                'pic_contact' => 'siti@teknologinusantara.com',
                'is_active' => true,
            ],
            [
                'name' => 'PT Lama Sejahtera',
                'code' => 'LAMA',
                'pic_name' => 'Agus Wijaya',
                'pic_contact' => 'agus@lamasejahtera.co.id',
                'is_active' => false,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::firstOrCreate(
                ['code' => $customer['code']],
                $customer
            );
        }
    }
}
