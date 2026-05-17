<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->integer('workflow_version');
            $table->string('business_type', 100)->nullable();
            $table->string('business_id', 50)->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0:运行中 1:已完成 2:已驳回 3:已取消 4:已撤销');
            $table->unsignedBigInteger('started_by');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('current_node_id')->nullable();
            $table->json('variables')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->foreign('started_by')->references('id')->on('users');
        });

        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->string('node_id', 50);
            $table->string('node_name', 100);
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->string('assignee_type', 50)->default('user')->comment('user, role, dept');
            $table->tinyInteger('status')->default(0)->comment('0:待处理 1:已同意 2:已驳回 3:已转交 4:已撤销');
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('instance_id')->references('id')->on('workflow_instances')->onDelete('cascade');
            $table->foreign('assignee_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('workflow_instance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->string('node_id', 50)->nullable();
            $table->string('action', 50);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('operator_id');
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->foreign('instance_id')->references('id')->on('workflow_instances')->onDelete('cascade');
            $table->foreign('operator_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instance_logs');
        Schema::dropIfExists('workflow_tasks');
        Schema::dropIfExists('workflow_instances');
    }
};
