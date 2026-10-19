<?php

namespace Tests\Unit\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use PHPUnit\Framework\TestCase;

/** Framework-free harness: a container with config + db + date, an in-memory SQLite, and a fakeable HTTP client. */
abstract class MetaTestCase extends TestCase
{
    protected Factory $http;

    protected function setUp(): void
    {
        $db = new DB();
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setEventDispatcher(new Dispatcher(new Container()));
        $db->setAsGlobal();
        $db->bootEloquent();

        $app = new Container();
        $app->instance('config', new Repository(['meta' => [
            'access_token' => 'user-token', 'api_version' => 'v25.0', 'app_id' => null, 'app_secret' => null, 'page_id' => null,
            'publishing' => ['enabled' => true, 'max_per_day' => 5],
            'insights' => [
                'facebook_post' => ['post_media_view', 'post_total_media_view_unique', 'post_clicks'],
                'instagram_media' => ['views', 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions'],
            ],
        ]]));
        $app->instance('db', $db->getDatabaseManager());
        $app->instance('date', new \Illuminate\Support\DateFactory());
        Container::setInstance($app);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);

        DB::schema()->create('content_ideas', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->unsignedBigInteger('persona_id')->nullable();
            $t->string('title'); $t->string('topic')->nullable(); $t->string('platform')->nullable(); $t->string('format')->nullable();
            $t->string('source')->default('manual'); $t->string('source_detail')->nullable(); $t->text('copy')->nullable();
            $t->string('status')->default('idea'); $t->string('publish_mode')->default('manual'); $t->date('planned_for')->nullable();
            $t->timestamp('scheduled_at')->nullable(); $t->timestamp('posted_at')->nullable(); $t->string('post_url')->nullable();
            $t->unsignedInteger('impressions')->nullable(); $t->unsignedInteger('reach')->nullable(); $t->unsignedInteger('engagements')->nullable();
            $t->unsignedInteger('clicks')->nullable(); $t->unsignedInteger('shares')->nullable(); $t->timestamp('measured_at')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->unsignedBigInteger('approved_by')->nullable(); $t->string('remote_post_id')->nullable();
            $t->string('media_url')->nullable(); $t->string('link_url')->nullable(); $t->text('publish_error')->nullable(); $t->timestamps();
        });

        $this->http = new Factory();
    }

    protected function graph(): \App\Services\Meta\GraphClient
    {
        return new \App\Services\Meta\GraphClient($this->http, 'user-token', 'v25.0');
    }

    protected function setConfig(string $key, $value): void
    {
        Container::getInstance()->make('config')->set($key, $value);
    }
}
