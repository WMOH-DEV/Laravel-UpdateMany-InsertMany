<?php

namespace App\Queries;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InsertMany
{
  /**
   * @var Builder
   */
  protected Builder $query;

  /**
   * @var bool
   */
  public bool $timestamps = true;

  /**
   * @var string
   */
  public string $key = 'id';

  /**
   * @var bool
   */
  public bool $output = false;

  /**
   * @var string
   */
  protected string $createdAtColumn;

  /**
   * @var string
   */
  protected string $updatedAtColumn;

  public function __construct(
    Builder $query,
    bool $output = false,
    string $key = 'id',
    bool $timestamps = true,
    string $createdAtColumn = 'created_at',
    string $updatedAtColumn = 'updated_at',
  ) {
    $this->query = $query;
    $this->output = $output;
    $this->key = $key;
    $this->timestamps = $timestamps;
    $this->createdAtColumn = $createdAtColumn;
    $this->updatedAtColumn = $updatedAtColumn;
  }

  public function insert($rows)
  {
    $ts = now();
    $rows = collect($rows)->map(function ($row) use ($ts) {
      $timestamps = $this->timestamps;

      // Only if the passed data is collection of models
      if ($row instanceof Model) {
        $additionalFillableColumns = [];

        // Check timestamp status from the model.
        if ($row->usesTimestamps() && $timestamps) {
          $createdAtColumn = $row->getCreatedAtColumn();
          $updatedAtColumn = $row->getUpdatedAtColumn();

          $row->{$createdAtColumn} ??= $ts;
          $row->{$updatedAtColumn} ??= $ts;

          $additionalFillableColumns = [...$additionalFillableColumns, $createdAtColumn, $updatedAtColumn];
        }

        $row = empty($row->getFillable())
          ? Arr::except($row->getAttributes(), $row->getGuarded())
          : Arr::only($row->getAttributes(), [...$row->getFillable(), ...$additionalFillableColumns]);
      } else {
        if ($timestamps) {
          //Due to MySql Strict, Invalid datetime format may occur, so we're parsing the date to avoid any invalid format.
          $row[$this->createdAtColumn] = array_key_exists($this->createdAtColumn, $row) ? Carbon::parse($row[$this->createdAtColumn]) : $ts;
          $row[$this->updatedAtColumn] = array_key_exists($this->updatedAtColumn, $row) ? Carbon::parse($row[$this->updatedAtColumn]) : $ts;
        }
      }

      return $row;
    });

    return $this->output
      ? DB::transaction(function () use ($rows) {
        $lastId = $this->query->max($this->key) ?? 0;

        $rows->chunk(999)->each(fn ($chunk) => $this->query->insert(is_array($chunk) ? $chunk : $chunk->toArray()));

        return $this->query->where($this->key, '>', $lastId)->get();
      })
      : DB::transaction(fn () => $rows->chunk(999)
        ->each(fn ($chunk) => $this->query->insert(is_array($chunk) ? $chunk : $chunk->toArray()))
        ->flatten(1))->count();
  }
}
