<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('column_name');
            $table->string('color_type'); // success, warning, danger, gray и т.д.
            $table->decimal('min_value', 10, 2)->nullable();
            $table->decimal('max_value', 10, 2)->nullable();
            $table->string('condition_type')->default('range'); // range, less_than, greater_than
            $table->timestamps();
        });
        
        // Добавляем настройки по умолчанию для существующих колонок с цветами
        DB::table('settings')->insert([
            // Настройки для survival_rate
            [
                'column_name' => 'survival_rate',
                'color_type' => 'danger',
                'min_value' => 0,
                'max_value' => 25,
                'condition_type' => 'less_than',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'column_name' => 'survival_rate',
                'color_type' => 'warning',
                'min_value' => 25,
                'max_value' => 75,
                'condition_type' => 'range',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'column_name' => 'survival_rate',
                'color_type' => 'success',
                'min_value' => 75,
                'max_value' => 100,
                'condition_type' => 'greater_than',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Настройки для spam_percent_accounts
            [
                'column_name' => 'spam_percent_accounts',
                'color_type' => 'danger',
                'min_value' => 75,
                'max_value' => 100,
                'condition_type' => 'greater_than',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'column_name' => 'spam_percent_accounts',
                'color_type' => 'warning',
                'min_value' => 25,
                'max_value' => 75,
                'condition_type' => 'range',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'column_name' => 'spam_percent_accounts',
                'color_type' => 'success',
                'min_value' => 0,
                'max_value' => 25,
                'condition_type' => 'less_than',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Настройки для clean_percent_accounts
            [
                'column_name' => 'clean_percent_accounts',
                'color_type' => 'danger',
                'min_value' => 0,
                'max_value' => 25,
                'condition_type' => 'less_than',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'column_name' => 'clean_percent_accounts',
                'color_type' => 'warning',
                'min_value' => 25,
                'max_value' => 75,
                'condition_type' => 'range',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'column_name' => 'clean_percent_accounts',
                'color_type' => 'success',
                'min_value' => 75,
                'max_value' => 100,
                'condition_type' => 'greater_than',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Настройки для percent_worked
            [
                'column_name' => 'percent_worked',
                'color_type' => 'danger',
                'min_value' => 0,
                'max_value' => 25,
                'condition_type' => 'less_than',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'column_name' => 'percent_worked',
                'color_type' => 'warning',
                'min_value' => 25,
                'max_value' => 75,
                'condition_type' => 'range',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'column_name' => 'percent_worked',
                'color_type' => 'success',
                'min_value' => 75,
                'max_value' => 100,
                'condition_type' => 'greater_than',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
