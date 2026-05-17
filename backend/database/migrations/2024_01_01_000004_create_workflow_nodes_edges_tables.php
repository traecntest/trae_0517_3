<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->string('node_id', 50);
            $table->string('name', 100);
            $table->string('type', 50)->comment('start,end,approval,condition,parallel,subprocess');
            $table->json('config')->nullable();
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->integer('width')->default(160);
            $table->integer('height')->default(60);
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->unique(['workflow_id', 'node_id']);
        });

        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->string('edge_id', 50);
            $table->string('source_node_id', 50);
            $table->string('target_node_id', 50);
            $table->string('label', 100)->nullable();
            $table->json('condition')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->unique(['workflow_id', 'edge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_edges');
        Schema::dropIfExists('workflow_nodes');
    }
};
