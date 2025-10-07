<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Deze herstel-migratie kijkt of de 'tickets' tabel onvolledig is (geen event_id enz.).
     * Als dat zo is wordt de huidige tabel hernoemd naar een backup en wordt de juiste
     * purchase-structuur opnieuw opgebouwd.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            // Niets te herstellen.
            return;
        }

        $required = [
            'user_id', 'event_id', 'type', 'quantity', 'unit_price', 'total_price', 'status'
        ];

        $missing = collect($required)->filter(fn ($col) => ! Schema::hasColumn('tickets', $col));

        if ($missing->isEmpty()) {
            // Structuur al in orde.
            return;
        }

        // Hernoem huidige (kapotte / legacy) tabel naar backup met timestamp.
        $timestamp = now()->format('Ymd_His');
        $backupName = 'tickets_legacy_backup_' . $timestamp;
        Schema::rename('tickets', $backupName);

        // Bouw nieuwe correcte structuur.
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('regular'); // regular | vip
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('status')->default('pending'); // pending | paid | cancelled
            $table->timestamps();
            $table->index(['event_id','status']);
        });
    }

    public function down(): void
    {
        // We proberen NIET automatisch terug te gaan naar een willekeurige backup, dat zou data kunnen verliezen.
        // Handmatige rollback kan door tickets te droppen en backup te hernoemen.
        if (Schema::hasTable('tickets')) {
            Schema::drop('tickets');
        }
        // Zoek laatste backup? (Niet automatisch gedaan.)
    }
};
