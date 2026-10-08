<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que un usuario pertenezca a varias áreas.
 *
 * - Crea la tabla pivote area_user.
 * - Copia el área actual de cada usuario (users.area_id) a la pivote.
 * - NO borra users.area_id: se mantiene como respaldo y compatibilidad
 *   (guarda el "área principal") hasta que se retire en una migración futura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['area_id', 'user_id']);
            $table->index('user_id');
        });

        $now = now();

        DB::table('area_user')->insertUsing(
            ['area_id', 'user_id', 'created_at', 'updated_at'],
            DB::table('users')
                ->whereNotNull('area_id')
                ->select(['area_id', 'id'])
                ->selectRaw('? as created_at, ? as updated_at', [$now, $now])
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('area_user');
    }
};
