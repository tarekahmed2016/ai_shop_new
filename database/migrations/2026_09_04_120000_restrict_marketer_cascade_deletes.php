<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Financial / audit integrity fix.
 *
 * Previously:
 *   - marketers.user_id                    -> ON DELETE CASCADE
 *   - marketer_referrals.marketer_id       -> ON DELETE CASCADE
 *   - marketer_referrals.referred_user_id  -> ON DELETE CASCADE
 *   - marketer_commissions.marketer_id     -> ON DELETE CASCADE
 *
 * Deleting a User who was also a Marketer therefore silently destroyed
 * their entire referral and commission audit trail, including commissions
 * tied to already-paid payments, with no recovery path.
 *
 * This migration tightens those four relationships to ON DELETE RESTRICT
 * so the database itself refuses to delete a user/marketer/referral row
 * while dependent referral or commission history still exists, regardless
 * of which code path attempts the delete. This is a schema-only, forward
 * safe change: no rows are moved, changed, or deleted, and no column is
 * added, renamed, or dropped.
 *
 * A matching application-level guard is added in
 * App\Services\UserService::delete() so admins get a clear validation
 * error instead of a raw database constraint failure.
 */
return new class extends Migration
{
    /**
     * Run outside an implicit transaction. SQLite's `PRAGMA foreign_keys`
     * toggle (used by the local/test rebuild path below) is a no-op while
     * a transaction is open, and MySQL's DDL statements auto-commit
     * regardless, so there is no downside to disabling the wrapper here.
     */
    public $withinTransaction = false;

    /**
     * Tables rebuilt on SQLite, in parent-before-child order.
     *
     * @var array<int, string>
     */
    private array $sqliteRebuildOrder = [
        'marketers',
        'marketer_referrals',
        'marketer_commissions',
    ];

    public function up(): void
    {
        if ($this->isSqlite()) {
            $this->rebuildSqliteTables(from: 'on delete cascade', to: 'on delete restrict');

            return;
        }

        $this->alterRelationalForeignKeys(action: 'restrict');
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            $this->rebuildSqliteTables(from: 'on delete restrict', to: 'on delete cascade');

            return;
        }

        $this->alterRelationalForeignKeys(action: 'cascade');
    }

    private function isSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    /**
     * MySQL / MariaDB / PostgreSQL path: standard drop + re-add of the
     * foreign key constraint with the desired ON DELETE action. The
     * constraint names match Laravel's default naming convention used by
     * the original `constrained()` calls, so no explicit names are needed.
     */
    private function alterRelationalForeignKeys(string $action): void
    {
        Schema::table('marketers', function (Blueprint $table) use ($action) {
            $table->dropForeign(['user_id']);
            $this->applyOnDelete(
                $table->foreign('user_id')->references('id')->on('users'),
                $action
            );
        });

        Schema::table('marketer_referrals', function (Blueprint $table) use ($action) {
            $table->dropForeign(['marketer_id']);
            $table->dropForeign(['referred_user_id']);

            $this->applyOnDelete(
                $table->foreign('marketer_id')->references('id')->on('marketers'),
                $action
            );
            $this->applyOnDelete(
                $table->foreign('referred_user_id')->references('id')->on('users'),
                $action
            );
        });

        Schema::table('marketer_commissions', function (Blueprint $table) use ($action) {
            $table->dropForeign(['marketer_id']);
            $this->applyOnDelete(
                $table->foreign('marketer_id')->references('id')->on('marketers'),
                $action
            );
        });
    }

    /**
     * @param  ForeignKeyDefinition  $foreign
     */
    private function applyOnDelete($foreign, string $action): void
    {
        if ($action === 'restrict') {
            $foreign->restrictOnDelete();
        } else {
            $foreign->cascadeOnDelete();
        }
    }

    /**
     * SQLite cannot alter a foreign key's ON DELETE action with ALTER TABLE
     * (the constraint is baked into the table's CREATE TABLE statement and
     * SQLite has no "ALTER TABLE ... ALTER CONSTRAINT" support). Each table
     * is rebuilt instead: renamed aside, recreated from its own live
     * CREATE TABLE definition with only the ON DELETE keyword swapped,
     * refilled from the aside copy with a verbatim `INSERT ... SELECT *`
     * (column list/order/types are untouched), every index that belonged
     * to it is rebuilt from its own stored definition, then the aside copy
     * is dropped. No column, row, or index definition is altered.
     */
    private function rebuildSqliteTables(string $from, string $to): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        // Without this, SQLite's `ALTER TABLE ... RENAME TO` below would
        // "helpfully" rewrite the OTHER tables' foreign key clauses to
        // point at the temporary aside name, permanently orphaning them
        // once the temporary table is dropped. We rename the table
        // ourselves and want every other table's FK text left exactly as
        // it was (still referencing the original table name).
        DB::statement('PRAGMA legacy_alter_table = ON');

        foreach ($this->sqliteRebuildOrder as $table) {
            $definition = DB::selectOne(
                "select sql from sqlite_master where type = 'table' and name = ?",
                [$table]
            );

            if ($definition === null || $definition->sql === null || ! str_contains(strtolower($definition->sql), $from)) {
                // Table missing or already in the desired state — nothing to do.
                continue;
            }

            $indexes = DB::select(
                "select sql from sqlite_master where type = 'index' and tbl_name = ? and sql is not null",
                [$table]
            );

            $tempTable = $table.'__fk_rebuild_tmp';
            $newCreateSql = str_ireplace($from, $to, $definition->sql);

            DB::statement('ALTER TABLE "'.$table.'" RENAME TO "'.$tempTable.'"');
            DB::statement($newCreateSql);
            DB::statement('INSERT INTO "'.$table.'" SELECT * FROM "'.$tempTable.'"');
            DB::statement('DROP TABLE "'.$tempTable.'"');

            foreach ($indexes as $index) {
                DB::statement($index->sql);
            }
        }

        DB::statement('PRAGMA legacy_alter_table = OFF');
        DB::statement('PRAGMA foreign_keys = ON');
    }
};
