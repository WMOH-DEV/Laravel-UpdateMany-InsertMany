<?php

namespace WaelMoh\LaravelUpdateInsertMany;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use function collect;
use function now;


class UpdateMany
{
    protected string $table;
    protected string|array $key = 'id';
    protected array $columns = [];
    protected string $updatedAtColumn;

    /**
     * Construct and pass rows for multiple update.
     *
     * @param string $table
     * @param string|array $key
     * @param array $columns
     * @param string $updatedAtColumn
     */
    public function __construct(string $table, string|array $key = 'id', array $columns = [], string $updatedAtColumn = 'updated_at')
    {
        $this->table = $table;
        $this->key = $key;
        $this->columns = $columns;
        $this->updatedAtColumn = $updatedAtColumn;
    }

    /**
     * Set the key.
     *
     * @param string $key
     * @return $this
     */
    public function key(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Set the columns to update.
     *
     * @param array $columns
     * @return $this
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Set updated_at column.
     *
     * @param string $updatedAtColumn
     * @return $this
     */
    public function updatedAtColumn(string $updatedAtColumn): static
    {
        $this->updatedAtColumn = $updatedAtColumn;
        return $this;
    }

    /**
     * Execute update statement on the given rows.
     *
     * @param array|Collection|SupportCollection $rows
     * @return bool
     */
    public function update(array|collection|supportCollection $rows): bool
    {
        if (collect($rows)->isEmpty()) {

            return false;
        }

        if (empty($this->columns)) {
            $this->columns = $this->getColumnsFromRows($rows);
        }

        if ($this->updatedAtColumn) {

            $rows = $this->setUpdatedAtColumn(is_array($rows) ? $rows : $rows->toArray());
        }

        $rows = is_array($rows) ? collect($rows) : $rows;

        return $rows->chunk(999)->every(fn ($chunk) => DB::statement($this->updateSql($chunk)));
    }

    /**
     * @param array $rows
     * @return array|SupportCollection
     */
    protected function setUpdatedAtColumn(array $rows): array|supportCollection
    {
        if ($this->updatedAtColumn) {
            $rows = collect($rows)->map(function ($row) {
                is_array($row)
                    //Due to MySql Strict, Invalid datetime format may occur, so we're parsing the date to avoid any invalid format.
                    ? $row[$this->updatedAtColumn] = (array_key_exists($this->updatedAtColumn, $row) ? Carbon::parse($row[$this->updatedAtColumn]) : now())
                    : $row->{$this->updatedAtColumn} ??= now();

                return $row;
            });
        }

        return $rows;
    }


    /**
     * Get columns from rows.
     *
     * @param array|Collection|SupportCollection $rows
     * @return array
     */
    protected function getColumnsFromRows(array|collection|supportCollection $rows): array
    {
        $row = [];

        foreach ($rows as $r) {
            if ($r instanceof Model) {
                $r = $r->getAttributes();
            }

            $row = array_merge($row, $r);
        }

        return array_keys($row);
    }

    /**
     * Return the columns to be updated.
     *
     * @return array
     */
    public function getColumns(): array
    {
        if ($this->updatedAtColumn) {
            $this->columns[] = $this->updatedAtColumn;
        }

        return array_unique($this->columns);
    }

    /**
     * Return the update sql.
     *
     * @param array|SupportCollection $rows
     * @return string
     */
    protected function updateSql(array|supportCollection $rows): string
    {
        $updateColumns = implode(', ', $this->updateColumns($rows));
        $whereInKeys = implode("','", $this->whereInKeys($rows));

        return "UPDATE `{$this->table}` SET {$updateColumns}" . (is_array($this->key) ? " WHERE `{$this->key[0]}` IN ('{$whereInKeys}')" : " WHERE `{$this->key}` IN ('{$whereInKeys}')");
    }

    /**
     * Return the where in keys.
     *
     * @param array|SupportCollection $rows
     * @return array
     */
    protected function whereInKeys(array|supportCollection $rows): array
    {
        return array_unique(collect($rows)->pluck(is_array($this->key) ? $this->key[0] : $this->key)->all());
    }

    /**
     * Return the update columns.
     *
     * @param array|SupportCollection $rows
     * @return array
     */
    protected function updateColumns(array|supportCollection $rows): array
    {
        $updates = [];

        foreach ($this->getColumns() as $column) {
            $cases = $this->cases($column, $rows);

            if (empty($cases)) {
                continue;
            }

            $updates[] = " `{$column}` = " .
                ' CASE ' .
                implode(' ', $cases) .
                " ELSE `{$column}` END";
        }
        return $updates;
    }

    /**
     * Return an array of column cases.
     *
     * @param string $column
     * @param array|SupportCollection $rows
     * @return array
     */
    protected function cases(string $column, array|supportCollection $rows): array
    {
        $cases = [];
        foreach ($rows as $row) {
            $row = is_array($row) ? $row : ($row instanceof Model ? $row : get_object_vars($row));

            // Check if the row has the column
            if (is_array($row) ? array_key_exists($column, $row) : isset($row->{$column})) {

                $row[$column] = is_array($row[$column]) ? json_encode($row[$column]) : $row[$column];

                $value = addslashes($row[$column] ?? '');

                // Set null in mysql database
                if (is_null($row[$column])) {
                    $value = 'null';
                } else {
                    $value = "'{$value}'";
                }

                if ($this->includeCase($row, $column)) {
                    if (is_array($this->key)) {
                        foreach ($this->key as $index => $key) {
                            if (array_key_first($this->key) === $index) {
                                $cases[] = "WHEN `{$key}` = '{$row[$key]}'";
                            } elseif (array_key_last($this->key) === $index) {
                                $cases[] = " AND `{$key}` = '{$row[$key]}' THEN {$value}";
                            } else {
                                $cases[] = " AND `{$key}` = '{$row[$key]}'";
                            }
                        }
                    } else {
                        $cases[] = "WHEN `{$this->key}` = '{$row[$this->key]}' THEN {$value}";
                    }
                }
            }
        }

        return $cases;
    }

    /**
     * Check if the case will be included.
     *
     * @param array|model $row
     * @param string $column
     * @return bool
     */
    protected function includeCase(Model|array $row, string $column): bool
    {
        if ($row instanceof Model) {
            return $row->isDirty($column);
        }

        return true;
    }
}
