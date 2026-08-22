<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 100);
            $table->string('target_type', 150)->nullable();
            $table->uuid('target_id')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->uuid('correlation_id')->nullable();
            $table->string('request_id', 255)->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->string('schema_version', 30)->default('1');
            $table->jsonb('metadata')->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['target_type', 'target_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index('correlation_id');
        });
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS prevent_audit_event_mutation() CASCADE;

            CREATE FUNCTION prevent_audit_event_mutation()
            RETURNS trigger
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_events are append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_events_append_only
            BEFORE UPDATE OR DELETE ON audit_events
            FOR EACH ROW
            EXECUTE FUNCTION prevent_audit_event_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_audit_event_mutation() CASCADE');
        Schema::dropIfExists('audit_events');
    }
};
