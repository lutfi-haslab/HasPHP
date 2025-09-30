<?php

namespace Hasphp\App\Models\Relations;

use Hasphp\App\Core\DB\QueryBuilder;
use Hasphp\App\Models\Model;

abstract class Relation
{
    /**
     * The query builder instance.
     */
    protected QueryBuilder $query;
    
    /**
     * The parent model instance.
     */
    protected Model $parent;
    
    /**
     * The related model instance.
     */
    protected Model $related;
    
    /**
     * Create a new relation instance.
     */
    public function __construct(QueryBuilder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();
        
        $this->addConstraints();
    }
    
    /**
     * Set the base constraints on the relation query.
     */
    abstract public function addConstraints(): void;
    
    /**
     * Set the constraints for an eager load of the relation.
     */
    abstract public function addEagerConstraints(array $models): void;
    
    /**
     * Initialize the relation on a set of models.
     */
    abstract public function initRelation(array $models, string $relation): array;
    
    /**
     * Match the eagerly loaded results to their parents.
     */
    abstract public function match(array $models, array $results, string $relation): array;
    
    /**
     * Get the results of the relationship.
     */
    abstract public function getResults();
    
    /**
     * Get the relationship for eager loading.
     */
    public function getEager(): array
    {
        return $this->get();
    }
    
    /**
     * Execute the query as a "select" statement.
     */
    public function get(array $columns = ['*']): array
    {
        return $this->query->get($columns);
    }
    
    /**
     * Get the first related model record matching the attributes or instantiate it.
     */
    public function first(array $columns = ['*'])
    {
        $results = $this->take(1)->get($columns);
        return count($results) > 0 ? $results[0] : null;
    }
    
    /**
     * Find a related model by its primary key.
     */
    public function find($id, array $columns = ['*'])
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }
        
        return $this->where($this->related->getKeyName(), '=', $id)->first($columns);
    }
    
    /**
     * Find multiple related models by their primary keys.
     */
    public function findMany(array $ids, array $columns = ['*']): array
    {
        if (empty($ids)) {
            return [];
        }
        
        return $this->whereIn($this->related->getKeyName(), $ids)->get($columns);
    }
    
    /**
     * Get the key for comparing against the parent key in "has" query.
     */
    public function getExistenceCompareKey(): string
    {
        return $this->getQualifiedParentKeyName();
    }
    
    /**
     * Get the fully qualified parent key name.
     */
    public function getQualifiedParentKeyName(): string
    {
        return $this->parent->getTable() . '.' . $this->parent->getKeyName();
    }
    
    /**
     * Get the key value of the parent's local key.
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->parent->getKeyName());
    }
    
    /**
     * Get the plain foreign key.
     */
    abstract public function getForeignKeyName(): string;
    
    /**
     * Get the name of the "where in" method for eager loading.
     */
    protected function whereInMethod(Model $model, string $key): string
    {
        return 'whereIn';
    }
    
    /**
     * Set or get the morph map for polymorphic relations.
     */
    public static function morphMap(array $map = null, bool $merge = true): array
    {
        $map = static::buildMorphMapFromModels($map);
        
        if (is_array($map)) {
            static::$morphMap = $merge && static::$morphMap
                            ? array_merge(static::$morphMap, $map) : $map;
        }
        
        return static::$morphMap;
    }
    
    /**
     * Builds a table-keyed array from model class names.
     */
    protected static function buildMorphMapFromModels(array $models = null): ?array
    {
        if (is_null($models) || array_is_list($models)) {
            return $models;
        }
        
        return array_combine(array_map(function ($model) {
            return (new $model)->getTable();
        }, $models), $models);
    }
    
    /**
     * Handle dynamic method calls to the relationship.
     */
    public function __call(string $method, array $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }
        
        $result = $this->query->$method(...$parameters);
        
        if ($result === $this->query) {
            return $this;
        }
        
        return $result;
    }
    
    /**
     * Force a clone of the underlying query builder when cloning.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }
}
