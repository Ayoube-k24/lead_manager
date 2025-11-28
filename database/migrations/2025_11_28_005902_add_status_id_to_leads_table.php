<?php

use App\LeadStatus as LeadStatusEnum;
use App\Models\LeadStatus;
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
        // Check if column already exists
        if (! Schema::hasColumn('leads', 'status_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('status_id')->nullable()->after('status')->constrained('lead_statuses')->nullOnDelete();
            });
        } else {
            // Column exists but might not have foreign key
            if (! $this->hasForeignKey('leads', 'leads_status_id_foreign')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->foreign('status_id')->references('id')->on('lead_statuses')->nullOnDelete();
                });
            }
        }

        // Migrate existing status values to status_id
        $this->migrateStatuses();

        // Note: We keep status_id nullable to allow flexibility, but we ensure all existing leads have a status_id
        // If you want to make it required, you would need to ensure all leads have a valid status_id first
    }

    /**
     * Check if a foreign key exists on a table.
     */
    private function hasForeignKey(string $table, string $keyName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        $result = $connection->selectOne(
            "SELECT COUNT(*) as count 
             FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = ? 
             AND TABLE_NAME = ? 
             AND CONSTRAINT_NAME = ?",
            [$database, $table, $keyName]
        );

        return $result && $result->count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });
    }

    /**
     * Migrate existing status string values to status_id.
     */
    private function migrateStatuses(): void
    {
        // Get all status slugs mapped to their IDs
        $statusMap = LeadStatus::pluck('id', 'slug')->toArray();

        // Update each lead
        \DB::table('leads')->chunkById(100, function ($leads) use ($statusMap) {
            foreach ($leads as $lead) {
                $statusSlug = $lead->status ?? 'pending_email';
                
                // Try to find the status by slug
                $statusId = $statusMap[$statusSlug] ?? null;
                
                // If not found, try to get from enum and create if needed
                if (! $statusId) {
                    $enum = LeadStatusEnum::tryFrom($statusSlug);
                    if ($enum) {
                        $status = LeadStatus::where('slug', $enum->value)->first();
                        if ($status) {
                            $statusId = $status->id;
                        } else {
                            // Fallback to pending_email
                            $statusId = $statusMap['pending_email'] ?? null;
                        }
                    } else {
                        // Fallback to pending_email
                        $statusId = $statusMap['pending_email'] ?? null;
                    }
                }

                if ($statusId) {
                    \DB::table('leads')
                        ->where('id', $lead->id)
                        ->update(['status_id' => $statusId]);
                }
            }
        });
    }
};
