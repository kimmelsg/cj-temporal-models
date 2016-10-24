<?php

use Carbon\Carbon;
use NavJobs\Temporal\Temporal;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use NavJobs\Temporal\Test\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use NavJobs\Temporal\Exceptions\InvalidDateRangeException;

class TemporalTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $db = new DB();

        $db->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
        ]);

        $db->setEventDispatcher(new Dispatcher(new Container()));
        $db->setAsGlobal();
        $db->bootEloquent();

        $this->createSchema();
        $this->resetListeners();
    }

    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function createSchema()
    {
        $this->schema()->create('commissions', function ($table) {
            $table->increments('id');
            $table->integer('agent_id')->unsigned();
            $table->dateTime('valid_start');
            $table->dateTime('valid_end')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->schema()->create('polymorphic_commissions', function ($table) {
            $table->increments('id');
            $table->integer('agent_id')->unsigned();
            $table->string('agent_type');
            $table->dateTime('valid_start');
            $table->dateTime('valid_end')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Address a testing issue where model listeners are not reset.
     */
    public function resetListeners()
    {
        TemporalTestCommission::flushEventListeners();
        TemporalTestCommission::registerEvents();
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    public function tearDown()
    {
        $this->schema()->drop('commissions');
        $this->schema()->drop('polymorphic_commissions');
    }

    /**
     * Test that it can send out a stats based on the provided data
     */
    public function testItCanCheckIfATemporalModelIsValid()
    {
        $temporalModel = new TemporalTestCommission([
            'agent_id' => 2,
            'valid_start' => Carbon::now(),
            'valid_end' => null
        ]);

        $this->assertTrue($temporalModel->isValid());

        $temporalModel = new TemporalTestCommission([
            'agent_id' => 1,
            'valid_start' => Carbon::now()->subYear(),
            'valid_end' => Carbon::now()->subMonth()
        ]);

        $this->assertFalse($temporalModel->isValid());
    }

    /**
     * Test that the dates must be valid.
     */
    public function testItCannotSaveWithAStartDateInThePast()
    {
        $this->setExpectedException(InvalidDateRangeException::class);

        $stub = new TemporalTestCommission();
        $stub->agent_id = 1;
        $stub->valid_start = Carbon::now()->subDays(5);
        $stub->save();
    }

    /**
     * Test that the dates must be valid.
     */
    public function testItCannotSaveWithAStartDateAfterTheEndDate()
    {
        $this->setExpectedException(InvalidDateRangeException::class);

        $stub = new TemporalTestCommission();
        $stub->agent_id = 1;
        $stub->valid_start = Carbon::now()->addDay();
        $stub->valid_end = Carbon::now();
        $stub->save();
    }

    /**
     * Tests...
     */
    public function testItEndsTheCurrentCommissionIfANewOneIsCreatedThatOverlaps()
    {
        $currentCommission = $this->createCommission();

        $newCommission = TemporalTestCommission::create([
            'id' => 2,
            'agent_id' => 1,
            'valid_start' => Carbon::now(),
            'valid_end' => null
        ]);

        $this->assertEquals($newCommission->valid_start, $currentCommission->fresh()->valid_end);
    }

    /**
     * Tests...
     */
    public function testItRemovesAScheduledCommissionWhenANewOneIsCreated()
    {
        $scheduledCommission = TemporalTestCommission::create([
            'id' => 2,
            'agent_id' => 1,
            'valid_start' => Carbon::now()->addDay(),
            'valid_end' => null
        ]);
        TemporalTestCommission::create([
            'id' => 3,
            'agent_id' => 1,
            'valid_start' => Carbon::now(),
            'valid_end' => null
        ]);

        $this->assertNull($scheduledCommission->fresh());
    }

    /**
     * Tests...
     */
    public function testItRemovesAScheduledPolymorphicCommissionWhenANewOneIsCreated()
    {
        $scheduledCommission = PolymorphicTemporalTestCommission::create([
            'id' => 2,
            'agent_id' => 1,
            'agent_type' => 'NavJobs\Temporal\Agent',
            'valid_start' => Carbon::now()->addDay(),
            'valid_end' => null
        ]);
        PolymorphicTemporalTestCommission::create([
            'id' => 3,
            'agent_id' => 1,
            'agent_type' => 'NavJobs\Temporal\Agent',
            'valid_start' => Carbon::now(),
            'valid_end' => null
        ]);

        $this->assertNull($scheduledCommission->fresh());
    }

    /**
     * Tests...
     */
    public function testItOnlyAllowsValidEndToBeUpdated()
    {
        $commission = $this->createCommission();
        $commission->valid_start = Carbon::now()->addYear();
        $commission->save();
        $commission = $commission->fresh();

        $this->assertEquals(Carbon::now()->subDays(10)->toDateString(), $commission->valid_start->toDateString());

        $commission->agent_id = 30;
        $commission->save();
        $commission = $commission->fresh();

        $this->assertEquals(1, $commission->agent_id);

        $expectedEnd = Carbon::now()->addDay(10);
        $commission->valid_end = $expectedEnd;
        $commission->save();
        $commission = $commission->fresh();

        $this->assertEquals($expectedEnd->toDateString(), $commission->valid_end->toDateString());

        //But it will not save if the valid end is in the past.
        $commission->valid_end = Carbon::now()->subDay();
        $commission->save();
        $commission = $commission->fresh();

        $this->assertEquals($expectedEnd->toDateString(), $commission->valid_end->toDateString());
    }

    /**
     * Tests...
     */
    public function testItCanUpdateIfTheUserHasSpecifiedToAllowUpdates()
    {
        $commission = $this->createCommission();
        $commission->allowUpdating = true;
        $commission->valid_start = Carbon::now()->addYear();
        $commission->save();
        $commission = $commission->fresh();

        $this->assertEquals(Carbon::now()->addYear()->toDateString(), $commission->valid_start->toDateString());

        $commission->agent_id = 30;
        $commission->save();
        $commission = $commission->fresh();

        $this->assertEquals(30, $commission->agent_id);
    }

    /**
     * Tests...
     */
    public function testItOnlyEndsACommissionInsteadOfDeletingWhenItHasAlreadyStarted()
    {
        $commission = $this->createCommission();
        $commission->delete();
        $commission = $commission->fresh();

        $this->assertEquals(Carbon::now()->toDateString(), $commission->valid_end->toDateString());
    }

    /**
     * Tests...
     */
    public function testItDeletesACommissionCompletelyIfItHasNotStartedYet()
    {
        $commission = TemporalTestCommission::create([
            'id' => 3,
            'agent_id' => 1,
            'valid_start' => Carbon::now()->addYear(),
            'valid_end' => null
        ]);
        $commission->delete();
        $commission = $commission->fresh();

        $this->assertNull($commission);
    }

    /**
     * Helpers...
     */
    protected function createCommission()
    {
        TemporalTestCommission::flushEventListeners();
        $commission = TemporalTestCommission::create([
            'id' => 1,
            'agent_id' => 1,
            'valid_start' => Carbon::now()->subDays(10),
            'valid_end' => null
        ]);
        TemporalTestCommission::registerEvents();

        return $commission;
    }

    /**
     * Helpers...
     */
    protected function createPolymorphicCommission()
    {
        TemporalTestCommission::flushEventListeners();
        $commission = TemporalTestCommission::create([
            'id' => 1,
            'agent_id' => 1,
            'agent_type' => 'NavJobs\Temporal\Agent',
            'valid_start' => Carbon::now()->subDays(10),
            'valid_end' => null
        ]);
        TemporalTestCommission::registerEvents();

        return $commission;
    }

    /**
     * Get a database connection instance.
     *
     * @return Connection
     */
    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }
}

/**
 * Eloquent Models...
 */
class TemporalTestCommission extends Eloquent
{
    use Temporal;

    protected $dates = ['valid_start', 'valid_end', 'deleted_at'];
    protected $table = 'commissions';
    protected $guarded = [];
    protected $temporalParentColumn = 'agent_id';
}

/**
 * Eloquent Models...
 */
class PolymorphicTemporalTestCommission extends Eloquent
{
    use Temporal;

    protected $dates = ['valid_start', 'valid_end', 'deleted_at'];
    protected $table = 'polymorphic_commissions';
    protected $guarded = [];
    protected $temporalParentColumn = 'agent_id';
    protected $temporalPolymorphicTypeColumn = 'agent_type';
}
