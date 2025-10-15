<?php

namespace Database\Seeders;

use App\Models\Config;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        Config::firstOrCreate(
            [
                'code' => 'free',
            ],
            [
                'config_value' => 0,
                'comment' => '1 o 0, se è possibile ordinare senza pagare',
            ]
        );
        Config::firstOrCreate(
            [
                'code' => 'change_price',
            ],
            [
                'config_value' => 0,
                'comment' => '1 o 0, se è possibile cambiare il prezzo dell\'ordine',
            ]
        );
        Config::firstOrCreate(
            [
                'code' => 'timezone',
            ],
            [
                'config_value' => 'CEST',
                'comment' => 'NON TOCCARE, fuso orario',
            ]
        );
        Config::firstOrCreate(
            [
                'code' => 'name',
            ],
            [
                'config_value' => 'Sagra',
                'comment' => 'nome scritto sulla stampa',
            ]
        );
        Config::firstOrCreate(
            [
                'code' => 'invoice_print',
            ],
            [
                'config_value' => 'A5',
                'comment' => 'valore A5 o A4',
            ]
        );
        Config::firstOrCreate(
            [
                'code' => 'repeat_products',
            ],
            [
                'config_value' => '0',
                'comment' => 'prodotti ripetuti nella stampa',
            ]
        );
        Config::firstOrCreate(
            [
                'code' => 'max_qty',
            ],
            [
                'config_value' => '15',
                'comment' => 'quantità massima di prodotti ordinabili per ordine',
            ]
        );

    }
}
