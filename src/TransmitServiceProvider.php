<?php

namespace NavJobs\Temporal;

class TemporalServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->bind(Schema::class, function () {

            $this->schema = DB::connection()->getSchemaBuilder();

            $this->schema->blueprintResolver(function ($table, $callback) {
                return new Blueprint($table, $callback);
            });

            return $schema;
        });
    }
}
