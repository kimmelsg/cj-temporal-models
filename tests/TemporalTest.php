<?php

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Events\Dispatcher;
use NavJobs\Temporal\Exceptions\InvalidDateRangeException;
use NavJobs\Temporal\Temporal;

class TemporalTest extends \NavJobs\Temporal\Test\TestCase
{
    public function setUp()
    {
        $db = new DB;

        $db->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
        ]);

        $db->setEventDispatcher(new Dispatcher(new Container()));
        $db->bootEloquent();
        $db->setAsGlobal();

        $this->createSchema();
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
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    public function tearDown()
    {
        $this->schema()->drop('commissions');
        TemporalTestCommission::clearBootedModels();
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
    public function testSoftDeletesAreNotRetrieved()
    {
        $this->createCommissions();

        $commissions = TemporalTestCommission::all();

//        $users = SoftDeletesTestUser::all();
//
//        $this->assertCount(1, $users);
//        $this->assertEquals(2, $users->first()->id);
//        $this->assertNull(SoftDeletesTestUser::find(1));
    }

    /**
     * Helpers...
     */
    protected function createCommissions()
    {
        TemporalTestCommission::flushEventListeners();
        TemporalTestCommission::create([
            'id' => 1,
            'agent_id' => 1,
            'valid_start' => Carbon::now()->subDays(10),
            'valid_end' => null
        ]);
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

    protected $dates = ['deleted_at'];
    protected $table = 'commissions';
    protected $guarded = [];
    protected $temporalParentColumn = 'agent_id';
}
