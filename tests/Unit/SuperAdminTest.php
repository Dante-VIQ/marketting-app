<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class SuperAdminTest extends TestCase
{
    protected function setUp(): void
    {
        $db = new DB();
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setEventDispatcher(new Dispatcher(new Container()));
        $db->setAsGlobal();
        $db->bootEloquent();
        $app = new Container();
        $app->instance('db', $db->getDatabaseManager());
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
        $s = DB::schema();
        $s->create('roles', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id')->nullable(); $t->string('name'); $t->string('guard_name'); });
        $s->create('model_has_roles', function ($t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); $t->unsignedBigInteger('brand_id')->nullable(); });
    }

    private function persisted(int $id): User
    {
        $u = new User();
        $u->id = $id;
        $u->exists = true;

        return $u;
    }

    public function test_user_without_the_role_is_not_super_admin(): void
    {
        $this->assertFalse($this->persisted(1)->isSuperAdmin());
    }

    public function test_a_new_unsaved_user_is_never_super_admin(): void
    {
        $this->assertFalse((new User())->isSuperAdmin());
    }

    public function test_global_super_admin_role_is_found_regardless_of_current_brand(): void
    {
        DB::table('roles')->insert(['id' => 1, 'brand_id' => null, 'name' => 'super-admin', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => User::class, 'model_id' => 7, 'brand_id' => null]);

        $this->assertTrue($this->persisted(7)->isSuperAdmin());
        $this->assertFalse($this->persisted(8)->isSuperAdmin());
    }

    public function test_other_roles_do_not_grant_access(): void
    {
        DB::table('roles')->insert(['id' => 2, 'brand_id' => 3, 'name' => 'owner', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => 2, 'model_type' => User::class, 'model_id' => 9, 'brand_id' => 3]);

        $this->assertFalse($this->persisted(9)->isSuperAdmin());
    }
}
