<?php

namespace WaelMoh\LaravelUpdateMany;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use function collect;
use function now;


class UpdateMany
{
    protected string $table;
    protected string $key    = 'id';
    protected array $columns = [];
    protected string $updatedAtColumn;

    /**
     * Construct and pass rows for multiple update.
     *
     * @param string $table
     * @param string $key
     * @param array $columns
     * @param string $updatedAtColumn
     */
    public function __construct(string $table, string $key = 'id', array $columns = [], string $updatedAtColumn = 'updated_at')
    {
        $this->table           = $table;
        $this->key             = $key;
        $this->columns         = $columns;
        $this->updatedAtColumn = $updatedAtColumn;
    }

    /**
     * Set the key.
     *
     * @param  string  $key
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
     * @param  array  $columns
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
     * @param  string $updatedAtColumn
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
     * @param  array|collection $rows
     * @return void
     */
    public function update(array|collection $rows)
    {
        if (collect($rows)->isEmpty()) {
            return;
        }

        if (empty($this->columns)) {
            $this->columns = $this->getColumnsFromRows($rows);
        }

        if ($this->updatedAtColumn) {
            $ts = now();
            foreach ($rows as $row) {
                $row[$this->updatedAtColumn] = $ts;
            }
        }

        DB::statement($this->updateSql($rows));
    }

    /**
     * Get columns from rows.
     *
     * @param  array|collection $rows
     * @return array
     */
    protected function getColumnsFromRows(array|collection $rows): array
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
     * @param  array|collection $rows
     * @return string
     */
    protected function updateSql(array|collection $rows): string
    {
        $updateColumns = implode(', ', $this->updateColumns($rows));
        $whereInKeys   = implode(', ', $this->whereInKeys($rows));

        return "UPDATE `{$this->table}` SET {$updateColumns} where `{$this->key}` in ({$whereInKeys})";
    }

    /**
     * Return the where in keys.
     *
     * @param  array|collection $rows
     * @return array
     */
    protected function whereInKeys(array|collection $rows): array
    {
        return collect($rows)->pluck($this->key)->all();
    }

    /**
     * Return the update columns.
     *
     * @param  array|collection $rows
     * @return array
     */
    protected function updateColumns(array|collection $rows): array
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
     * @param  string $column
     * @param  array|collection $rows
     * @return array
     */
    protected function cases(string $column, array|collection $rows): array
    {
        $cases = [];
        foreach ($rows as $row) {
            // Check if the row has the column
            if (array_key_exists($column, $row)) {
                $value = addslashes($row[$column]);

                // Set null in mysql database
                if (is_null($row[$column])) {
                    $value = 'null';
                } else {
                    $value = "'{$value}'";
                }

                if ($this->includeCase($row, $column)) {
                    $cases[] = "WHEN `{$this->key}` = '{$row[$this->key]}' THEN {$value}";
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
