<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_tree', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent');
            $table->foreign('parent')
                ->references('id')
                ->on('roles')
                ->noActionOnDelete();
			$table->unsignedBigInteger('child');
            $table->foreign('child')
                ->references('id')
                ->on('roles')
                ->noActionOnDelete();
            $table->unique(['parent', 'child']);
			$table->unsignedInteger('depth');
        });
    }

    public function down(): void
    {
        Schema::drop('role_tree');
    }
};