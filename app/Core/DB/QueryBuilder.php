<?php

namespace Hasphp\App\Core\DB;

use PDO;
use PDOStatement;
use Hasphp\App\Core\DB\Drivers\DatabaseDriver;

class QueryBuilder
{
    /**
     * The database connection instance.
     */
    protected DatabaseDriver $connection;
    
    /**
     * The table which the query is targeting.
     */
    protected string $from = '';
    
    /**
     * The columns that should be returned.
     */
    protected array $columns = ['*'];
    
    /**
     * Indicates if the query returns distinct results.
     */
    protected bool $distinct = false;
    
    /**
     * The where constraints for the query.
     */
    protected array $wheres = [];
    
    /**
     * The orderings for the query.
     */
    protected array $orders = [];
    
    /**
     * The maximum number of records to return.
     */
    protected ?int $limit = null;
    
    /**
     * The number of records to skip.
     */
    protected ?int $offset = null;
    
    /**
     * The table joins for the query.
     */
    protected array $joins = [];
    
    /**
     * The relationships that should be eager loaded.
     */
    protected array $eagerLoad = [];
    
    /**
     * All of the available clause operators.
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];
    
    /**
     * Create a new query builder instance.
     */
    public function __construct(DatabaseDriver $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Set the table which the query is targeting.
     */
    public function table(string $table): self
    {
        $this->from = $table;
        return $this;
    }
    
    /**
     * Set the columns to be selected.
     */
    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }
    
    /**
     * Force the query to only return distinct results.
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }
    
    /**
     * Add a basic where clause to the query.
     */
    public function where(string $column, $operator = null, $value = null, string $boolean = 'and'): self
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }
        
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        
        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parentheses.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof \Closure && is_null($operator)) {
            return $this->whereNested($column, $boolean);
        }
        
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        
        $this->wheres[] = array_merge(compact(
            'column', 'operator', 'value', 'boolean'
        ), ['type' => 'Basic']);
        
        return $this;
    }
    
    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere(string $column, $operator = null, $value = null): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        
        return $this->where($column, $operator, $value, 'or');
    }
    
    /**
     * Add a where between statement to the query.
     */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): self
    {
        $type = 'Between';
        
        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');
        
        return $this;
    }
    
    /**
     * Add a where not between statement to the query.
     */
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }
    
    /**
     * Add a where in statement to the query.
     */
    public function whereIn(string $column, $values, string $boolean = 'and', bool $not = false): self
    {
        $type = 'In';
        
        if ($values instanceof \Closure) {
            return $this->whereInSub($column, $values, $boolean, $not);
        }
        
        $values = is_array($values) ? $values : [$values];
        
        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');
        
        return $this;
    }
    
    /**
     * Add a where not in statement to the query.
     */
    public function whereNotIn(string $column, $values, string $boolean = 'and'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }
    
    /**
     * Add a where null statement to the query.
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $type = 'Null';
        
        $this->wheres[] = compact('type', 'column', 'boolean', 'not');
        
        return $this;
    }
    
    /**
     * Add a where not null statement to the query.
     */
    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }
    
    /**
     * Add an "order by" clause to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);
        
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Order direction must be "asc" or "desc".');
        }
        
        $this->orders[] = compact('column', 'direction');
        
        return $this;
    }
    
    /**
     * Add a descending "order by" clause to the query.
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }
    
    /**
     * Set the "limit" value of the query.
     */
    public function limit(int $value): self
    {
        $this->limit = $value > 0 ? $value : 0;
        
        return $this;
    }
    
    /**
     * Alias to set the "limit" value of the query.
     */
    public function take(int $value): self
    {
        return $this->limit($value);
    }
    
    /**
     * Set the "offset" value of the query.
     */
    public function offset(int $value): self
    {
        $this->offset = max(0, $value);
        
        return $this;
    }
    
    /**
     * Alias to set the "offset" value of the query.
     */
    public function skip(int $value): self
    {
        return $this->offset($value);
    }
    
    /**
     * Add a join clause to the query.
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'inner'): self
    {
        // If only three parameters are passed, assume the operator is '='
        if (func_num_args() == 3) {
            $second = $operator;
            $operator = '=';
        }
        
        $this->joins[] = compact('type', 'table', 'first', 'operator', 'second');
        
        return $this;
    }
    
    /**
     * Add a left join to the query.
     */
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }
    
    /**
     * Add a right join to the query.
     */
    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }
    
    /**
     * Execute the query as a "select" statement.
     */
    public function get(array|string $columns = ['*']): array
    {
        if (! empty($columns) && $columns !== ['*']) {
            $this->select($columns);
        }
        
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        
        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($bindings);
        
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute a query for a single record by ID.
     */
    public function find($id, array|string $columns = ['*']): ?array
    {
        return $this->where('id', '=', $id)->first($columns);
    }
    
    /**
     * Execute the query and get the first result.
     */
    public function first(array|string $columns = ['*']): ?array
    {
        $results = $this->take(1)->get($columns);
        
        return count($results) > 0 ? $results[0] : null;
    }
    
    /**
     * Get the count of the total records for the paginator.
     */
    public function count(string $columns = '*'): int
    {
        $original = $this->columns;
        
        if (is_null($this->columns)) {
            $this->columns = [$columns];
        }
        
        $this->columns = ["COUNT({$columns}) as aggregate"];
        
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        
        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($bindings);
        
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        $this->columns = $original;
        
        return isset($results[0]) ? (int) $results[0]['aggregate'] : 0;
    }
    
    /**
     * Insert new records into the database.
     */
    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }
        
        if (! is_array(reset($values))) {
            $values = [$values];
        }
        
        $columns = array_keys(reset($values));
        $sql = $this->compileInsert($columns);
        
        foreach ($values as $record) {
            $statement = $this->connection->pdo()->prepare($sql);
            $statement->execute(array_values($record));
        }
        
        return true;
    }
    
    /**
     * Update records in the database.
     */
    public function update(array $values): int
    {
        $sql = $this->compileUpdate($values);
        $bindings = array_merge(array_values($values), $this->getBindings());
        
        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($bindings);
        
        return $statement->rowCount();
    }
    
    /**
     * Delete records from the database.
     */
    public function delete(): int
    {
        $sql = $this->compileDelete();
        $bindings = $this->getBindings();
        
        $statement = $this->connection->pdo()->prepare($sql);
        $statement->execute($bindings);
        
        return $statement->rowCount();
    }
    
    /**
     * Get the SQL representation of the query.
     */
    public function toSql(): string
    {
        return $this->compileSelect();
    }
    
    /**
     * Compile the query into a select statement.
     */
    protected function compileSelect(): string
    {
        $sql = 'SELECT ';
        
        if ($this->distinct) {
            $sql .= 'DISTINCT ';
        }
        
        $sql .= implode(', ', $this->columns);
        
        if (! empty($this->from)) {
            $sql .= " FROM {$this->from}";
        }
        
        if (! empty($this->joins)) {
            $sql .= ' ' . $this->compileJoins();
        }
        
        if (! empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        
        if (! empty($this->orders)) {
            $sql .= ' ORDER BY ' . $this->compileOrders();
        }
        
        if (! is_null($this->limit)) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        if (! is_null($this->offset)) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        return $sql;
    }
    
    /**
     * Compile the "where" portions of the query.
     */
    protected function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        
        $sql = [];
        
        foreach ($this->wheres as $where) {
            $method = "compileWhere{$where['type']}";
            if (!method_exists($this, $method)) {
                $method = "whereBasic"; // fallback to basic where
            }
            
            $sql[] = $where['boolean'] . ' ' . $this->$method($where);
        }
        
        return preg_replace('/and |or /i', '', implode(' ', $sql), 1);
    }
    
    /**
     * Compile the "join" portions of the query.
     */
    protected function compileJoins(): string
    {
        $sql = [];
        
        foreach ($this->joins as $join) {
            $sql[] = strtoupper($join['type']) . " JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        return implode(' ', $sql);
    }
    
    /**
     * Compile a basic where clause.
     */
    protected function whereBasic(array $where): string
    {
        return "{$where['column']} {$where['operator']} ?";
    }
    
    /**
     * Compile a where in clause.
     */
    protected function compileWhereIn(array $where): string
    {
        if (! empty($where['values'])) {
            return $where['column'] . ' in (' . implode(',', array_fill(0, count($where['values']), '?')) . ')';
        }
        
        return '0 = 1';
    }
    
    /**
     * Compile a where not in clause.
     */
    protected function compileWhereNotIn(array $where): string
    {
        if (! empty($where['values'])) {
            return $where['column'] . ' not in (' . implode(',', array_fill(0, count($where['values']), '?')) . ')';
        }
        
        return '1 = 1';
    }
    
    /**
     * Compile a where null clause.
     */
    protected function compileWhereNull(array $where): string
    {
        return $where['column'] . ' is null';
    }
    
    /**
     * Compile a where not null clause.
     */
    protected function compileWhereNotNull(array $where): string
    {
        return $where['column'] . ' is not null';
    }
    
    /**
     * Compile a where between clause.
     */
    protected function compileWhereBetween(array $where): string
    {
        $between = $where['not'] ? 'not between' : 'between';
        
        return $where['column'] . ' ' . $between . ' ? and ?';
    }
    
    /**
     * Compile the "order by" portions of the query.
     */
    protected function compileOrders(): string
    {
        if (empty($this->orders)) {
            return '';
        }
        
        return implode(', ', array_map(function ($order) {
            return $order['column'] . ' ' . $order['direction'];
        }, $this->orders));
    }
    
    /**
     * Compile an insert statement into SQL.
     */
    protected function compileInsert(array $columns): string
    {
        $columnsString = implode(', ', $columns);
        $valuesString = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        
        return "INSERT INTO {$this->from} ({$columnsString}) VALUES {$valuesString}";
    }
    
    /**
     * Compile an update statement into SQL.
     */
    protected function compileUpdate(array $values): string
    {
        $columns = [];
        
        foreach (array_keys($values) as $column) {
            $columns[] = $column . ' = ?';
        }
        
        $sql = "UPDATE {$this->from} SET " . implode(', ', $columns);
        
        if (! empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        
        return $sql;
    }
    
    /**
     * Compile a delete statement into SQL.
     */
    protected function compileDelete(): string
    {
        $sql = "DELETE FROM {$this->from}";
        
        if (! empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        
        return $sql;
    }
    
    /**
     * Get the current query value bindings in a flattened array.
     */
    public function getBindings(): array
    {
        $bindings = [];
        
        foreach ($this->wheres as $where) {
            if (isset($where['value']) && ! is_null($where['value'])) {
                $bindings[] = $where['value'];
            }
            
            if (isset($where['values']) && is_array($where['values'])) {
                $bindings = array_merge($bindings, $where['values']);
            }
        }
        
        return $bindings;
    }
    
    /**
     * Add an array of where clauses to the query.
     */
    protected function addArrayOfWheres(array $column, string $boolean, string $method = 'where'): self
    {
        return $this->whereNested(function ($query) use ($column, $method, $boolean) {
            foreach ($column as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $query->{$method}(...array_values($value));
                } else {
                    $query->$method($key, '=', $value);
                }
            }
        }, $boolean);
    }
    
    /**
     * Add a nested where statement to the query.
     */
    public function whereNested(\Closure $callback, string $boolean = 'and'): self
    {
        $callback($query = $this->forNestedWhere());
        
        return $this->addNestedWhereQuery($query, $boolean);
    }
    
    /**
     * Create a new query instance for nested where condition.
     */
    public function forNestedWhere(): QueryBuilder
    {
        return new static($this->connection);
    }
    
    /**
     * Add another query builder as a nested where to the query builder.
     */
    public function addNestedWhereQuery(QueryBuilder $query, string $boolean = 'and'): self
    {
        if (count($query->wheres)) {
            $type = 'Nested';
            
            $this->wheres[] = compact('type', 'query', 'boolean');
        }
        
        return $this;
    }
    
    /**
     * Prepare the value and operator for a where clause.
     */
    public function prepareValueAndOperator($value, $operator, bool $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new \InvalidArgumentException('Illegal operator and value combination.');
        }
        
        return [$value, $operator];
    }
    
    /**
     * Determine if the given operator and value combination is legal.
     */
    protected function invalidOperatorAndValue($operator, $value): bool
    {
        return is_null($value) && in_array($operator, $this->operators) && ! in_array($operator, ['=', '<>', '!=']);
    }
    
    /**
     * Determine if the given operator is supported.
     */
    protected function invalidOperator($operator): bool
    {
        return ! in_array(strtolower($operator), $this->operators, true);
    }
}
