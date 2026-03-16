<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer 500 clients avec des données variées
        Client::factory()->count(300)->create();
        
        // Créer 100 clients premium
        Client::factory()->premium()->count(100)->create();
        
        // Créer 100 clients avec crédit immobilier
        Client::factory()->withMortgage()->count(100)->create();
        
        $this->command->info('✅ 500 clients créés avec succès !');
    }
}
