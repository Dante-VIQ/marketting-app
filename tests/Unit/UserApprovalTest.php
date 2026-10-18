<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Users\UserApprovalService;
use DomainException;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class UserApprovalTest extends TestCase
{
    private UserApprovalService $svc;

    protected function setUp(): void
    {
        $db = new DB();
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setEventDispatcher(new Dispatcher(new Container()));
        $db->setAsGlobal();
        $db->bootEloquent();
        $app = new Container();
        $app->instance('db', $db->getDatabaseManager());
        $app->instance('date', new \Illuminate\Support\DateFactory());
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);

        $s = DB::schema();
        $s->create('users', function ($t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->nullable();
            $t->string('approval_status')->default('pending'); $t->timestamp('approved_at')->nullable(); $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamps();
        });
        $s->create('roles', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id')->nullable(); $t->string('name'); $t->string('guard_name'); });
        $s->create('model_has_roles', function ($t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); $t->unsignedBigInteger('brand_id')->nullable(); });
        $this->svc = new UserApprovalService();
    }

    private function user(string $name, string $role = null, string $status = 'pending'): User
    {
        $u = new User();
        $u->forceFill(['name' => $name, 'email' => "$name@x.test", 'approval_status' => $status])->save();
        if ($role) {
            $rid = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $rid, 'model_type' => User::class, 'model_id' => $u->id]);
        }

        return $u->fresh();
    }

    public function test_new_signup_is_pending_and_not_approved(): void
    {
        $u = $this->user('ann');
        $this->assertSame('pending', $u->approval_status);
        $this->assertFalse($u->isApproved());
    }

    public function test_super_admin_is_never_locked_out(): void
    {
        $this->assertTrue($this->user('boss', 'super-admin')->isApproved());
    }

    public function test_super_admin_approves_a_signup(): void
    {
        $boss = $this->user('boss', 'super-admin', 'approved');
        $ann = $this->user('ann');

        $this->svc->approve($ann, $boss);

        $ann = $ann->fresh();
        $this->assertTrue($ann->isApproved());
        $this->assertSame($boss->id, (int) $ann->approved_by);
    }

    public function test_appointed_approver_can_approve_and_reject(): void
    {
        $helper = $this->user('helper', 'user-approver', 'approved');
        $this->svc->approve($a = $this->user('a'), $helper);
        $this->svc->reject($b = $this->user('b'), $helper);

        $this->assertSame('approved', $a->fresh()->approval_status);
        $this->assertSame('rejected', $b->fresh()->approval_status);
    }

    public function test_ordinary_user_cannot_approve(): void
    {
        $this->expectException(DomainException::class);
        $this->svc->approve($this->user('ann'), $this->user('mallory', null, 'approved'));
    }

    public function test_nobody_changes_their_own_approval(): void
    {
        $helper = $this->user('helper', 'user-approver', 'approved');
        $this->expectExceptionMessage('your own');
        $this->svc->reject($helper, $helper);
    }

    public function test_an_approver_cannot_touch_the_super_admin(): void
    {
        $boss = $this->user('boss', 'super-admin', 'approved');
        $helper = $this->user('helper', 'user-approver', 'approved');
        $this->expectExceptionMessage('platform administrator');
        $this->svc->reject($boss, $helper);
    }

    public function test_only_the_super_admin_appoints_approvers(): void
    {
        $helper = $this->user('helper', 'user-approver', 'approved');
        $this->expectExceptionMessage('Only the platform administrator');
        $this->svc->makeApprover($this->user('x', null, 'approved'), $helper);
    }

    public function test_cannot_appoint_someone_not_yet_approved(): void
    {
        $boss = $this->user('boss', 'super-admin', 'approved');
        $this->expectExceptionMessage('Approve this person first');
        $this->svc->makeApprover($this->user('newbie'), $boss);
    }

    public function test_approver_flag_and_global_role_check(): void
    {
        $this->assertTrue($this->user('helper', 'user-approver', 'approved')->isApprover());
        $this->assertTrue($this->user('boss', 'super-admin', 'approved')->isApprover());
        $this->assertFalse($this->user('plain', null, 'approved')->isApprover());
    }
}
