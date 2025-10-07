<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            return; // niets te migreren
        }

        // Oude support-structuur herkennen aan kolommen 'subject' of 'priority'.
        $legacy = Schema::hasColumn('tickets','subject') || Schema::hasColumn('tickets','priority');

        if (! $legacy) {
            // Reeds nieuwe structuur – niets doen.
            return;
        }

        // SQLite (en soms MySQL strict) kan problemen geven bij dropColumn met foreign keys.
        // We kiezen voor een veilige herbouw: hernoem oude tabel en maak nieuwe aan.
        $backupName = 'tickets_support_backup';
        if (! Schema::hasTable($backupName)) {
            Schema::rename('tickets', $backupName);
        } else {
            // Als backup al bestaat, voeg timestamp suffix toe.
            $ts = now()->format('Ymd_His');
            $dynamicBackup = $backupName.'_'.$ts;
            Schema::rename('tickets', $dynamicBackup);
            $backupName = $dynamicBackup;
        }

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

        // (Optioneel) Oude data migreren? We slaan dat over omdat semantiek verschilt.
        // Laat backup tabel staan zodat handmatige analyse mogelijk blijft.
    }

    public function down(): void
    {
        // Proberen te herstellen naar backup indien aanwezig.
        $backupName = 'tickets_support_backup';
        if (Schema::hasTable($backupName) && Schema::hasTable('tickets')) {
            Schema::drop('tickets');
            Schema::rename($backupName, 'tickets');
        }
    }
};
