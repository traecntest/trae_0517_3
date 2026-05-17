<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('category', 50)->default('default');
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->default('#1890ff');
            $table->tinyInteger('type')->default(1)->comment('1:审批流程 2:业务流程 3:自动化流程');
            $table->tinyInteger('status')->default(0)->comment('0:草稿 1:已发布 2:已停用');
            $table->integer('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });

        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->integer('version');
            $table->json('definition')->comment('流程定义JSON');
            $table->text('change_log')->nullable();
            $table->tinyInteger('is_active')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['workflow_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflows');
    }
};
