<?php

namespace Hasphp\App\Models\Relations;

use Hasphp\App\Core\DB\QueryBuilder;
use Hasphp\App\Models\Model;

class BelongsTo extends Relation
{
    /**
     * The child model instance of the relation.
     */
    protected Model $child;
    
    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;
    
    /**
     * The associated key on the parent model.
     */
    protected string $ownerKey;
    
    /**
     * The name of the relationship.
     */
    protected string $relationName;
    
    /**
     * Create a new belongs to relationship instance.
     */
    public function __construct(QueryBuilder $query, Model $child, string $foreignKey, string $ownerKey, string $relationName)
    {
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;
        $this->foreignKey = $foreignKey;
        $this->child = $child;
        
        parent::__construct($query, $child);
    }
    
    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query->where($this->ownerKey, '=', $this->child->{$this->foreignKey});
        }
    }
    
    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->ownerKey;
        
        $keys = $this->getEagerModelKeys($models);
        
        $this->query->whereIn($key, $keys);
    }
    
    /**
     * Gather the keys from an array of related models.
     */
    protected function getEagerModelKeys(array $models): array
    {
        $keys = [];
        
        // First we need to gather all of the keys from the parent models so we know what
        // to query for via the eager loading query. We will add them to an array then
        // execute a "where in" statement to gather up all of those related records.
        foreach ($models as $model) {
            $value = $model->{$this->foreignKey};
            
            if (!is_null($value)) {
                $keys[] = $value;
            }
        }
        
        sort($keys);
        
        return array_values(array_unique($keys));
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
        $foreign = $this->foreignKey;
        $owner = $this->ownerKey;
        
        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = [];
        
        foreach ($results as $result) {
            $dictionary[$result[$owner]] = $result;
        }
        
        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            if (isset($dictionary[$model->{$foreign}])) {
                $model->setRelation($relation, $dictionary[$model->{$foreign}]);
            }
        }
        
        return $models;
    }
    
    /**
     * Update the parent model on the relationship.
     */
    public function update(array $attributes): int
    {
        return $this->getResults()->fill($attributes)->save();
    }
    
    /**
     * Associate the model instance to the given parent.
     */
    public function associate($model): Model
    {
        $ownerKey = $model instanceof Model ? $model->getAttribute($this->ownerKey) : $model;
        
        $this->child->setAttribute($this->foreignKey, $ownerKey);
        
        if ($model instanceof Model) {
            $this->child->setRelation($this->relationName, $model);
        } elseif ($this->child->isDirty($this->foreignKey)) {
            $this->child->unsetRelation($this->relationName);
        }
        
        return $this->child;
    }
    
    /**
     * Dissociate previously associated model from the given parent.
     */
    public function dissociate(): Model
    {
        $this->child->setAttribute($this->foreignKey, null);
        
        return $this->child->setRelation($this->relationName, null);
    }
    
    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        if (is_null($this->child->{$this->foreignKey})) {
            return null;
        }
        
        return $this->query->first();
    }
    
    /**
     * Get the child of the relationship.
     */
    public function getChild(): Model
    {
        return $this->child;
    }
    
    /**
     * Get the foreign key of the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }
    
    /**
     * Get the fully qualified foreign key of the relationship.
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->child->getTable() . '.' . $this->foreignKey;
    }
    
    /**
     * Get the associated key of the relationship.
     */
    public function getOwnerKeyName(): string
    {
        return $this->ownerKey;
    }
    
    /**
     * Get the fully qualified associated key of the relationship.
     */
    public function getQualifiedOwnerKeyName(): string
    {
        return $this->related->getTable() . '.' . $this->ownerKey;
    }
    
    /**
     * Get the name of the relationship.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }
}
