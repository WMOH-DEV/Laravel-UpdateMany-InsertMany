<?php

namespace WaelMoh\LaravelUpdateInsertMany;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register application services.
     *
     * @return void
     */
    public function register()
    {
        Builder::macro('updateMany', function ($rows, $key = 'id', $columns = []) {
            return (new UpdateMany(
                $this->from,
                $key,
                $columns
            ))->update($rows);
        });

        EloquentBuilder::macro('updateMany', function ($rows, $key = 'id', $columns = []) {
            return (new UpdateMany(
                $this->getModel()->getTable(),
                $key,
                !empty($columns) ? $columns : $this->getModel()->getFillable()
            ))->update($rows);
        });


        /*
        |--------------------------------------------------------------------------
        | Registering InsertMany Query
        |--------------------------------------------------------------------------
        |
        | Unlike normal insert, InsertMany accepts collection of models or array of arrays.
        | It will insert the timestamps.still it's an optional by passing boolean value
        | If the output parameter is ture The query will return a support collection 
        | of the inserted data depending on the primary key which by default is [ID],
        | so if you have a different key name, PASS IT with the parameters
        |
        */
        Builder::macro('insertMany', fn ($rows, $output = false, $key = 'id', $timestamps = true) => (new InsertMany(
            $this,
            $output,
            $key,
            $timestamps,
        ))->insert($rows));

        EloquentBuilder::macro('insertMany', function ($rows, $output = false, $key = 'id', $timestamps = true) {
            return $this->getQuery()->insertMany($rows, $output, $key, $timestamps);
        });
    }
}
