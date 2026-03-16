<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('numero_client')->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('telephone')->nullable();
            $table->date('date_naissance');
            
            // Informations financières
            $table->decimal('solde_compte', 15, 2)->default(0);
            $table->decimal('revenus_mensuels', 10, 2)->nullable();
            $table->decimal('credit_en_cours', 15, 2)->default(0);
            $table->boolean('a_credit_immobilier')->default(false);
            $table->boolean('a_assurance_vie')->default(false);
            
            // Géolocalisation
            $table->string('adresse');
            $table->string('code_postal');
            $table->string('ville');
            $table->string('region');
            $table->string('pays')->default('France');
            
            // Informations compte
            $table->enum('type_compte', ['particulier', 'professionnel', 'premium'])->default('particulier');
            $table->enum('statut', ['actif', 'inactif', 'suspendu'])->default('actif');
            $table->date('date_ouverture_compte');
            $table->timestamp('derniere_transaction')->nullable();
            $table->integer('nombre_operations_mois')->default(0);
            
            // Segmentation
            $table->string('categorie_client')->nullable(); // bronze, silver, gold, platinum
            $table->integer('score_fidelite')->default(0);
            
            $table->timestamps();
            
            // Index pour optimiser les recherches
            $table->index(['nom', 'prenom']);
            $table->index('solde_compte');
            $table->index('ville');
            $table->index('type_compte');
            $table->index('statut');
            $table->index('categorie_client');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
