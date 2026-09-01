<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_evidences', function (Blueprint $table) {
            $table->string('rule_code', 100)
                ->nullable()
                ->after('classification_reason');

            $table->string('rule_version', 100)
                ->nullable()
                ->after('rule_code');

            $table->string('ruleset_version', 100)
                ->nullable()
                ->after('rule_version');

            $table->string('engine_version', 100)
                ->nullable()
                ->after('ruleset_version');

            $table->string('rule_source', 500)
                ->nullable()
                ->after('engine_version');

            $table->jsonb('rule_provenance')
                ->nullable()
                ->after('rule_source');

            $table->index([
                'rule_code',
                'rule_version',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tax_evidences', function (Blueprint $table) {
            $table->dropIndex([
                'tax_evidences_rule_code_rule_version_index',
            ]);

            $table->dropColumn([
                'rule_code',
                'rule_version',
                'ruleset_version',
                'engine_version',
                'rule_source',
                'rule_provenance',
            ]);
        });
    }
};
