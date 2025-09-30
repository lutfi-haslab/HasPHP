<?php

namespace Hasphp\App\Models\Relations;

use Hasphp\App\Core\DB\QueryBuilder;
use Hasphp\App\Models\Model;

class HasMany extends HasOne
{
    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        return ! is_null($this->getParentKey())
                    ? $this->query->get()
                    : [];
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
        return $this->matchMany($models, $results, $relation);
    }
    
    /**
     * Match the eagerly loaded results to their many parents.
     */
    protected function matchMany(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);
        
        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }
        
        return $models;
    }
    
    /**
     * Create a new instance of the related model.
     */
    public function create(array $attributes = []): Model
    {
        return tap($this->make($attributes), function ($instance) {
            $instance->save();
        });
    }
    
    /**
     * Create an array of new instances of the related model.
     */
    public function createMany(array $records): array
    {
        $instances = [];
        
        foreach ($records as $record) {
            $instances[] = $this->create($record);
        }
        
        return $instances;
    }
    
    /**
     * Save an array of models to the database.
     */
    public function saveMany(array $models): array
    {
        foreach ($models as $model) {
            $this->save($model);
        }
        
        return $models;
    }
    
    /**
     * Save a new model and return the instance.
     */
    public function save(Model $model): Model
    {
        $this->setForeignAttributesForCreate($model);
        
        return tap($model, function ($model) {
            $model->save();
        });
    }
    
    /**
     * Find a model by its primary key or return new instance of the related model.
     */
    public function findOrNew($id, array $columns = ['*']): Model
    {
        if (is_null($instance = $this->find($id, $columns))) {
            $instance = $this->related->newInstance();
            
            $this->setForeignAttributesForCreate($instance);
        }
        
        return $instance;
    }
    
    /**
     * Get the first related model record matching the attributes or instantiate it.
     */
    public function firstOrNew(array $attributes = [], array $values = []): Model
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->related->newInstance(array_merge($attributes, $values));
            
            $this->setForeignAttributesForCreate($instance);
        }
        
        return $instance;
    }
    
    /**
     * Get the first record matching the attributes or create it.
     */
    public function firstOrCreate(array $attributes = [], array $values = []): Model
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->create(array_merge($attributes, $values));
        }
        
        return $instance;
    }
    
    /**
     * Create or update a related record matching the attributes, and fill it with values.
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return tap($this->firstOrNew($attributes), function ($instance) use ($values) {
            $instance->fill($values)->save();
        });
    }
    
    /**
     * Update related records in the database.
     */
    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }
    
    /**
     * Delete related records from the database.
     */
    public function delete(): int
    {
        return $this->query->delete();
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
            $key = $result[$this->foreignKey];
            
            if (! isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            
            $dictionary[$key][] = $result;
        }
        
        return $dictionary;
    }
    
    /**
     * Add the constraints for a relationship count query.
     */
    public function getRelationExistenceCountQuery(QueryBuilder $query, QueryBuilder $parentQuery): QueryBuilder
    {
        return $this->getRelationExistenceQuery(
            $query, $parentQuery, 'count(*)'
        );
    }
    
    /**
     * Add the constraints for an internal relationship existence query.
     */
    public function getRelationExistenceQuery(QueryBuilder $query, QueryBuilder $parentQuery, $columns = '*'): QueryBuilder
    {
        if ($query->getQuery()->from == $parentQuery->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }
        
        return $query->select($columns)->whereColumn(
            $this->getQualifiedParentKeyName(), '=', $this->getExistenceCompareKey()
        );
    }
    
    /**
     * Add the constraints for a relationship existence query on the same table.
     */
    public function getRelationExistenceQueryForSelfRelation(QueryBuilder $query, QueryBuilder $parentQuery, $columns = '*'): QueryBuilder
    {
        $query->from($query->getModel()->getTable() . ' as ' . $hash = $this->getRelationCountHash());
        
        $query->select($columns)->whereColumn(
            $this->getQualifiedParentKeyName(), '=', $hash . '.' . $this->getForeignKeyName()
        );
        
        return $query;
    }
    
    /**
     * Get a relationship join table hash.
     */
    public function getRelationCountHash(bool $incrementJoinCount = true): string
    {
        return 'laravel_reserved_' . (static::$selfJoinCount++);
    }
}
