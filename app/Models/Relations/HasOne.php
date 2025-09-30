<?php

namespace Hasphp\App\Models\Relations;

use Hasphp\App\Core\DB\QueryBuilder;
use Hasphp\App\Models\Model;

class HasOne extends Relation
{
    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;
    
    /**
     * The local key of the parent model.
     */
    protected string $localKey;
    
    /**
     * Create a new has one relationship instance.
     */
    public function __construct(QueryBuilder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        
        parent::__construct($query, $parent);
    }
    
    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());
            
            $this->query->whereNotNull($this->foreignKey);
        }
    }
    
    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->localKey);
        
        $this->query->whereIn($this->foreignKey, $keys);
    }
    
    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }
        
        return $models;
    }
    
    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, array $results, string $relation): array
    {
        return $this->matchOne($models, $results, $relation);
    }
    
    /**
     * Match the eagerly loaded results to their single parents.
     */
    protected function matchOne(array $models, array $results, string $relation): array
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
     * Get the results of the relationship.
     */
    public function getResults()
    {
        if (is_null($this->getParentKey())) {
            return null;
        }
        
        return $this->query->first();
    }
    
    /**
     * Make a new related instance for the given model.
     */
    public function make(array $attributes = []): Model
    {
        return tap($this->related->newInstance($attributes), function ($instance) {
            $this->setForeignAttributesForCreate($instance);
        });
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
     * Create a new instance of the related model or update existing.
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $instance = $this->where($attributes)->first();
        
        if ($instance) {
            $instance->fill($values)->save();
        } else {
            $instance = $this->create(array_merge($attributes, $values));
        }
        
        return $instance;
    }
    
    /**
     * Get the key for comparing against the parent key in "has" query.
     */
    public function getExistenceCompareKey(): string
    {
        return $this->getQualifiedForeignKeyName();
    }
    
    /**
     * Get the fully qualified foreign key name.
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->related->getTable() . '.' . $this->foreignKey;
    }
    
    /**
     * Get the plain foreign key.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }
    
    /**
     * Get the local key for the relationship.
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }
    
    /**
     * Get the key value of the parent's local key.
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }
    
    /**
     * Set the foreign ID for creating a related model.
     */
    protected function setForeignAttributesForCreate(Model $model): void
    {
        $model->setAttribute($this->getForeignKeyName(), $this->getParentKey());
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
            $dictionary[$result[$this->foreignKey]] = $result;
        }
        
        return $dictionary;
    }
    
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
