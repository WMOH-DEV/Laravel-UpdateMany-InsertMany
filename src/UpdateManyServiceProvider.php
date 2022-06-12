<?php

namespace WaelMoh\LaravelUpdateMany;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class UpdateManyServiceProvider extends BaseServiceProvider
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
  }
}
