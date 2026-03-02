<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tableau_data', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->index();
            $table->date('date_operation')->index();
            $table->text('libelle');
            $table->decimal('montant', 15, 2)->index();
            $table->string('devise', 10)->default('EUR');
            $table->string('compte', 50)->index();
            $table->string('agence', 50)->nullable();
            $table->string('type_operation', 50)->nullable()->index();
            $table->string('statut', 50)->nullable()->index();
            $table->timestamps();

            // Index composites pour les requêtes fréquentes
            $table->index(['date_operation', 'compte']);
            $table->index(['compte', 'date_operation']);
            $table->index(['devise', 'date_operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tableau_data');
    }
};
