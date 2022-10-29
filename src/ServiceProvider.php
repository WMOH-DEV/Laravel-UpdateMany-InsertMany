<?php

namespace WaelMoh\LaravelUpdateMany;

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
        | It will insert the timestamps and createdBy, updatedBy as well.
        | Still it's an optional to update both of them by passing boolean value
        | ! By default, CreatedBy and UpdatedBy won't be inserted.
        | If the output parameter is ture The query will return a support collection of the inserted data depending on the primary key
        | which by default is [ID], so if you have a different key name, PASS IT with the parameters
        |
        */
        Builder::macro('insertMany', fn ($rows, $createdUpdatedBy = false, $output = false, $key = 'id', $timestamps = true) => (new InsertMany(
            $this,
            $createdUpdatedBy,
            $output,
            $key,
            $timestamps,
        ))->insert($rows));

        EloquentBuilder::macro('insertMany', function ($rows, $createdUpdatedBy = false, $output = false, $key = 'id', $timestamps = true) {
            return $this->newQuery()->insertMany($rows, $createdUpdatedBy, $output, $key, $timestamps);
        });
    }
}
