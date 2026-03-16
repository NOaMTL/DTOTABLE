<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $dateNaissance = $this->faker->dateTimeBetween('-70 years', '-18 years');
        $dateOuverture = $this->faker->dateTimeBetween('-10 years', 'now');
        
        return [
            'numero_client' => 'CLI' . $this->faker->unique()->numberBetween(100000, 999999),
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'email' => $this->faker->unique()->safeEmail(),
            'telephone' => $this->faker->phoneNumber(),
            'date_naissance' => $dateNaissance,
            
            // Finances
            'solde_compte' => $this->faker->randomFloat(2, -5000, 150000),
            'revenus_mensuels' => $this->faker->randomFloat(2, 1200, 15000),
            'credit_en_cours' => $this->faker->randomFloat(2, 0, 300000),
            'a_credit_immobilier' => $this->faker->boolean(30),
            'a_assurance_vie' => $this->faker->boolean(40),
            
            // Localisation
            'adresse' => $this->faker->streetAddress(),
            'code_postal' => $this->faker->postcode(),
            'ville' => $this->faker->city(),
            'region' => $this->faker->randomElement([
                'Île-de-France', 'Auvergne-Rhône-Alpes', 'Provence-Alpes-Côte d\'Azur',
                'Nouvelle-Aquitaine', 'Occitanie', 'Hauts-de-France', 'Bretagne',
                'Grand Est', 'Pays de la Loire', 'Normandie', 'Bourgogne-Franche-Comté',
            ]),
            'pays' => 'France',
            
            // Compte
            'type_compte' => $this->faker->randomElement(['particulier', 'professionnel', 'premium']),
            'statut' => $this->faker->randomElement(['actif', 'actif', 'actif', 'actif', 'inactif', 'suspendu']), // Plus d'actifs
            'date_ouverture_compte' => $dateOuverture,
            'derniere_transaction' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'nombre_operations_mois' => $this->faker->numberBetween(0, 150),
            
            // Segmentation
            'categorie_client' => $this->faker->randomElement(['bronze', 'silver', 'gold', 'platinum']),
            'score_fidelite' => $this->faker->numberBetween(0, 1000),
        ];
    }

    /**
     * Client Premium avec bon solde
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'type_compte' => 'premium',
            'categorie_client' => $this->faker->randomElement(['gold', 'platinum']),
            'solde_compte' => $this->faker->randomFloat(2, 50000, 500000),
            'revenus_mensuels' => $this->faker->randomFloat(2, 5000, 20000),
            'score_fidelite' => $this->faker->numberBetween(700, 1000),
        ]);
    }

    /**
     * Client avec crédit immobilier
     */
    public function withMortgage(): static
    {
        return $this->state(fn (array $attributes) => [
            'a_credit_immobilier' => true,
            'credit_en_cours' => $this->faker->randomFloat(2, 50000, 400000),
        ]);
    }
}
