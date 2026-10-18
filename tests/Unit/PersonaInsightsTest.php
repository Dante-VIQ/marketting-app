<?php

namespace Tests\Unit;

use App\Models\Persona;
use App\Services\Personas\PersonaInsights;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class PersonaInsightsTest extends TestCase
{
    protected function setUp(): void
    {
        $db = new DB();
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setEventDispatcher(new Dispatcher(new Container()));
        $db->setAsGlobal();
        $db->bootEloquent();
        $s = DB::schema();
        $s->create('personas', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('name'); $t->string('role_title')->nullable();
            $t->text('summary')->nullable();
            foreach (Persona::LIST_FIELDS as $f) { $t->json($f)->nullable(); }
            $t->string('buying_role')->nullable(); $t->string('quote')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        $s->create('persona_evidence', function ($t) {
            $t->id(); $t->unsignedBigInteger('persona_id'); $t->string('kind'); $t->string('note'); $t->date('observed_on')->nullable(); $t->timestamps();
        });
    }

    public function test_no_personas_tells_the_agent_not_to_assume_an_audience(): void
    {
        $s = (new PersonaInsights())->summary(1);
        $this->assertSame(0, $s['count']);
        $this->assertStringContainsString('No personas defined', $s['note']);
    }

    public function test_summary_is_scoped_to_brand_active_only_and_trimmed(): void
    {
        Persona::create(['brand_id' => 1, 'name' => 'A', 'pains' => ['p1', 'p2', 'p3', 'p4']]);
        Persona::create(['brand_id' => 1, 'name' => 'Off', 'is_active' => false]);
        Persona::create(['brand_id' => 2, 'name' => 'Other brand']);

        $s = (new PersonaInsights())->summary(1);

        $this->assertSame(1, $s['count']);
        $this->assertSame('A', $s['personas'][0]['name']);
        $this->assertSame('hypothesis', $s['personas'][0]['confidence']);
        $this->assertCount(3, $s['personas'][0]['pains']);
    }

    public function test_evidence_raises_confidence_in_the_summary(): void
    {
        $p = Persona::create(['brand_id' => 1, 'name' => 'A']);
        foreach (range(1, 3) as $i) {
            $p->evidence()->create(['kind' => 'interview', 'note' => "n$i", 'observed_on' => date('Y-m-d')]);
        }

        $this->assertSame('emerging', (new PersonaInsights())->summary(1)['personas'][0]['confidence']);
    }
}
