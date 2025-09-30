<?php

namespace Hasphp\App\Models\Relations;

use Hasphp\App\Core\DB\QueryBuilder;
use Hasphp\App\Models\Model;

class BelongsToMany extends Relation
{
    /**
     * The intermediate table for the relation.
     */
    protected string $table;
    
    /**
     * The foreign key of the parent model.
     */
    protected string $foreignPivotKey;
    
    /**
     * The associated key of the relation.
     */
    protected string $relatedPivotKey;
    
    /**
     * The parent key of the relation.
     */
    protected string $parentKey;
    
    /**
     * The related key of the relation.
     */
    protected string $relatedKey;
    
    /**
     * The name of the relationship.
     */
    protected ?string $relationName = null;
    
    /**
     * Create a new belongs to many relationship instance.
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        string $relationName = null
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;
        
        parent::__construct($query, $parent);
    }
    
    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        $this->performJoin();
        
        if (static::$constraints) {
            $this->addWhereConstraints();
        }
    }
    
    /**
     * Set the join clause for the relation query.
     */
    protected function performJoin(QueryBuilder $query = null): void
    {
        $query = $query ?: $this->query;
        
        // We need to join to the intermediate table on the related model's primary
        // key column with the intermediate table's foreign key for the related
        // model instance. Then we can set the "where" for the parent models.
        $baseTable = $this->related->getTable();
        
        $key = $baseTable . '.' . $this->relatedKey;
        
        $query->join($this->table, $key, '=', $this->getQualifiedRelatedPivotKeyName());
    }
    
    /**
     * Set the where clause for the relation query.
     */
    protected function addWhereConstraints(): void
    {
        $this->query->where(
            $this->getQualifiedForeignPivotKeyName(),
            '=',
            $this->parent->{$this->parentKey}
        );
        
        return $this;
    }
    
    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->parentKey);
        
        $this->query->whereIn($this->getQualifiedForeignPivotKeyName(), $keys);
    }
    
    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, []);
        }
        
        return $models;
    }
    
    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);
        
        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->{$this->parentKey};
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }
        
        return $models;
    }
    
    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        
        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly and easily match the models
        // to their parents without having a possibly expensive search operation.
        foreach ($results as $result) {
            $key = $result[$this->foreignPivotKey];
            
            if (! isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            
            $dictionary[$key][] = $result;
        }
        
        return $dictionary;
    }
    
    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        return ! is_null($this->parent->{$this->parentKey})
                    ? $this->get()
                    : [];
    }
    
    /**
     * Execute the query as a "select" statement.
     */
    public function get(array $columns = ['*']): array
    {
        // First we'll add the proper select columns onto the query so it is run with
        // the proper columns. Then, we will get the results and hydrate out pivot
        // models with the result of those columns as a separate model relation.
        $builder = $this->query->applyScopes();
        
        $columns = $builder->getQuery()->columns ? [] : $columns;
        
        $models = $builder->addSelect(
            $this->shouldSelect($columns)
        )->getModels();
        
        $this->hydratePivotRelation($models);
        
        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }
        
        return $models;
    }
    
    /**
     * Get the select columns for the relation query.
     */
    protected function shouldSelect(array $columns = ['*']): array
    {
        if ($columns == ['*']) {
            $columns = [$this->related->getTable() . '.*'];
        }
        
        return array_merge($columns, $this->aliasedPivotColumns());
    }
    
    /**
     * Get the pivot columns for the relation.
     */
    protected function aliasedPivotColumns(): array
    {
        $defaults = [$this->foreignPivotKey, $this->relatedPivotKey];
        
        return collect($defaults)->map(function ($column) {
            return $this->table . '.' . $column . ' as pivot_' . $column;
        })->all();
    }
    
    /**
     * Hydrate the pivot table relationship on the models.
     */
    protected function hydratePivotRelation(array $models): void
    {
        // To hydrate the pivot relationship, we will just gather the pivot attributes
        // and create a new Pivot model, which is basically a dynamic model that we
        // will set the attributes, table, and connections on so it they work right.
        foreach ($models as $model) {
            $model->setRelation('pivot', $this->newExistingPivot(
                $this->migratePivotAttributes($model)
            ));
        }
    }
    
    /**
     * Get the pivot attributes from a model.
     */
    protected function migratePivotAttributes(Model $model): array
    {
        $values = [];
        
        foreach ($model->getAttributes() as $key => $value) {
            // To get the pivots attributes we will just take any of the attributes which
            // begin with "pivot_" and add those to this arrays, as well as unsetting
            // them from the parent's models since they exist in a different table.
            if (str_starts_with($key, 'pivot_')) {
                $values[substr($key, 6)] = $value;
                
                unset($model->$key);
            }
        }
        
        return $values;
    }
    
    /**
     * Create a new existing pivot model instance.
     */
    public function newExistingPivot(array $attributes = []): array
    {
        // For now, we'll just return the attributes as an array
        // In a full implementation, you'd create a Pivot model class
        return $attributes;
    }
    
    /**
     * Attach a model to the parent.
     */
    public function attach($id, array $attributes = [], bool $touch = true): void
    {
        $this->newPivotStatement()->insert($this->formatAttachRecord(
            $this->parseId($id), $attributes
        ));
        
        if ($touch) {
            $this->touchIfTouching();
        }
    }
    
    /**
     * Detach models from the relationship.
     */
    public function detach($ids = null): int
    {
        $query = $this->newPivotQuery();
        
        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the delete.
        if (! is_null($ids)) {
            $ids = $this->parseIds($ids);
            
            if (empty($ids)) {
                return 0;
            }
            
            $query->whereIn($this->relatedPivotKey, $ids);
        }
        
        // Once we have all of the conditions set on the statement, we are ready
        // to run the delete on the pivot table. Then, if the touch parameter
        // is true, we will go ahead and touch all related models to sync.
        $results = $query->delete();
        
        return $results;
    }
    
    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     */
    public function sync($ids, bool $detaching = true): array
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];
        
        // First we need to attach any of the associated models that are not currently
        // in this joining table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we will insert.
        $current = $this->getCurrentlyAttachedPivots()
                        ->pluck($this->relatedPivotKey)->all();
        
        $detach = array_diff($current, array_keys(
            $records = $this->formatRecordsList($this->parseIds($ids))
        ));
        
        // Next, we will take the differences of the currents and given IDs and detach
        // all of the entities that exist in the "current" array but are not in the
        // array of the new IDs given to the method which will complete the sync.
        if ($detaching && count($detach) > 0) {
            $this->detach($detach);
            
            $changes['detached'] = $this->castKeys($detach);
        }
        
        // Now we are finally ready to attach the new records. Note that we'll disable
        // touching until after the entire operation is complete so we don't fire a
        // ton of touch operations until we are totally done syncing the records.
        $changes = array_merge(
            $changes, $this->attachNew($records, $current, false)
        );
        
        return $changes;
    }
    
    /**
     * Get the fully qualified foreign key for the relation.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignPivotKey;
    }
    
    /**
     * Get the fully qualified "related key" for the relation.
     */
    public function getQualifiedRelatedPivotKeyName(): string
    {
        return $this->table . '.' . $this->relatedPivotKey;
    }
    
    /**
     * Get the fully qualified "foreign key" for the relation.
     */
    public function getQualifiedForeignPivotKeyName(): string
    {
        return $this->table . '.' . $this->foreignPivotKey;
    }
    
    /**
     * Get the intermediate table for the relationship.
     */
    public function getTable(): string
    {
        return $this->table;
    }
    
    /**
     * Get the relationship name of the relationship.
     */
    public function getRelationName(): ?string
    {
        return $this->relationName;
    }
    
    // Additional helper methods would be implemented here for a complete BelongsToMany relationship...
    
    /**
     * Get the key values of related models for eager loading.
     */
    protected function getKeys(array $models, string $key = null): array
    {
        return array_unique(array_values(array_map(function ($value) use ($key) {
            return $key ? $value->getAttribute($key) : $value->getKey();
        }, $models)));
    }
}
