<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix global role/permission rows so brand_id may be NULL.
     *
     * MySQL rejects NULL values in a composite PRIMARY KEY, so the permission
     * pivots must use unique indexes instead of PKs when teams/global roles are
     * enabled. This preserves brand-scoped rows and allows a true global role
     * record such as super-admin with brand_id = NULL.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $rolesHasPrimaryKey = DB::select("SHOW KEYS FROM model_has_roles WHERE Key_name = 'PRIMARY'");
        if (!empty($rolesHasPrimaryKey)) {
            DB::statement('ALTER TABLE model_has_roles DROP FOREIGN KEY model_has_roles_brand_id_foreign');
            DB::statement('ALTER TABLE model_has_roles DROP FOREIGN KEY model_has_roles_role_id_foreign');
            DB::statement('ALTER TABLE model_has_roles MODIFY brand_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE model_has_roles DROP PRIMARY KEY');
            DB::statement('ALTER TABLE model_has_roles ADD UNIQUE KEY model_has_roles_role_model_type_unique (role_id, model_id, model_type, brand_id)');
            DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_brand_id_foreign FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        }

        $permissionsHasPrimaryKey = DB::select("SHOW KEYS FROM model_has_permissions WHERE Key_name = 'PRIMARY'");
        if (!empty($permissionsHasPrimaryKey)) {
            DB::statement('ALTER TABLE model_has_permissions DROP FOREIGN KEY model_has_permissions_brand_id_foreign');
            DB::statement('ALTER TABLE model_has_permissions DROP FOREIGN KEY model_has_permissions_permission_id_foreign');
            DB::statement('ALTER TABLE model_has_permissions MODIFY brand_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE model_has_permissions DROP PRIMARY KEY');
            DB::statement('ALTER TABLE model_has_permissions ADD UNIQUE KEY model_has_permissions_permission_model_type_unique (permission_id, model_id, model_type, brand_id)');
            DB::statement('ALTER TABLE model_has_permissions ADD CONSTRAINT model_has_permissions_brand_id_foreign FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE model_has_permissions ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        // The previous schema was invalid for global team-less roles because MySQL
        // rejects NULL values within a composite PRIMARY KEY. We intentionally keep
        // the fixed unique-index configuration to preserve global assignments.
    }
};
