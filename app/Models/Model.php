<?php

namespace Hasphp\App\Models;

use Hasphp\App\Core\DB\QueryBuilder;
use Hasphp\App\Core\DB\Drivers\DatabaseDriver;
use Hasphp\App\Core\Container;
use Hasphp\App\Models\Relations\HasOne;
use Hasphp\App\Models\Relations\HasMany;
use Hasphp\App\Models\Relations\BelongsTo;
use Hasphp\App\Models\Relations\BelongsToMany;

abstract class Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = null;
    
    /**
     * The primary key for the model.
     */
    protected string $primaryKey = 'id';
    
    /**
     * The "type" of the auto-incrementing ID.
     */
    protected string $keyType = 'int';
    
    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public bool $incrementing = true;
    
    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = true;
    
    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'created_at';
    
    /**
     * The name of the "updated at" column.
     */
    const UPDATED_AT = 'updated_at';
    
    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [];
    
    /**
     * The attributes that aren't mass assignable.
     */
    protected array $guarded = ['*'];
    
    /**
     * The attributes that should be hidden for arrays.
     */
    protected array $hidden = [];
    
    /**
     * The attributes that should be visible in arrays.
     */
    protected array $visible = [];
    
    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [];
    
    /**
     * The model's attributes.
     */
    protected array $attributes = [];
    
    /**
     * The model attribute's original state.
     */
    protected array $original = [];
    
    /**
     * The changed model attributes.
     */
    protected array $changes = [];
    
    /**
     * The relationships that should be eager loaded.
     */
    protected array $with = [];
    
    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];
    
    /**
     * Indicates if the model exists.
     */
    public bool $exists = false;
    
    /**
     * Indicates if the model was inserted during the current request lifecycle.
     */
    public bool $wasRecentlyCreated = false;
    
    /**
     * The database connection instance.
     */
    protected static ?DatabaseDriver $connection = null;
    
    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();
    }
    
    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes): self
    {
        $totallyGuarded = $this->totallyGuarded();
        
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which will allow them to have control over every attribute when creating the 
            // model. We'll check if the key is in the array and mark it as fillable.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new \InvalidArgumentException("Add [{$key}] to fillable property to allow mass assignment on [" . static::class . "].");
            }
        }
        
        return $this;
    }
    
    /**
     * Get the fillable attributes of the model.
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }
    
    /**
     * Set the fillable attributes for the model.
     */
    public function fillable(array $fillable): self
    {
        $this->fillable = $fillable;
        
        return $this;
    }
    
    /**
     * Determine if the model is totally guarded.
     */
    public function totallyGuarded(): bool
    {
        return count($this->getFillable()) === 0 && $this->getGuarded() == ['*'];
    }
    
    /**
     * Get the guarded attributes for the model.
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }
    
    /**
     * Determine if the given attribute may be mass assigned.
     */
    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->getFillable())) {
            return true;
        }
        
        if ($this->isGuarded($key)) {
            return false;
        }
        
        return empty($this->getFillable()) && ! str_starts_with($key, '_');
    }
    
    /**
     * Determine if the given key is guarded.
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->getGuarded()) || $this->getGuarded() == ['*'];
    }
    
    /**
     * Get the fillable attributes from the given array.
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->getFillable()) > 0 && ! static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->getFillable()));
        }
        
        return $attributes;
    }
    
    /**
     * Create a new instance of the given model.
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);
        
        $model->exists = $exists;
        
        $model->setConnection($this->getConnection());
        
        return $model;
    }
    
    /**
     * Create a new model instance that is existing.
     */
    public function newFromBuilder(array $attributes = []): static
    {
        $model = $this->newInstance([], true);
        
        $model->setRawAttributes($attributes, true);
        
        return $model;
    }
    
    /**
     * Set the raw attributes array and sync the original.
     */
    public function setRawAttributes(array $attributes, bool $sync = false): self
    {
        $this->attributes = $attributes;
        
        if ($sync) {
            $this->syncOriginal();
        }
        
        return $this;
    }
    
    /**
     * Get the model's original attribute values.
     */
    public function getOriginal(string $key = null, $default = null)
    {
        return $key ? ($this->original[$key] ?? $default) : $this->original;
    }
    
    /**
     * Sync the original attributes with the current.
     */
    public function syncOriginal(): self
    {
        $this->original = $this->attributes;
        
        return $this;
    }
    
    /**
     * Sync the changed attributes.
     */
    public function syncChanges(): self
    {
        $this->changes = $this->getDirty();
        
        return $this;
    }
    
    /**
     * Determine if the model or any given attribute has been modified.
     */
    public function isDirty(string|array $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getDirty(), is_array($attributes) ? $attributes : func_get_args()
        );
    }
    
    /**
     * Determine if the model or any given attributes have been modified.
     */
    public function isClean(string|array $attributes = null): bool
    {
        return ! $this->isDirty(...func_get_args());
    }
    
    /**
     * Get the attributes that have been changed since last sync.
     */
    public function getDirty(): array
    {
        $dirty = [];
        
        foreach ($this->getAttributes() as $key => $value) {
            if (! $this->originalIsEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }
        
        return $dirty;
    }
    
    /**
     * Get all of the current attributes on the model.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
    
    /**
     * Set a given attribute on the model.
     */
    public function setAttribute(string $key, $value): self
    {
        // Handle JSON casts
        if ($this->isJsonCastable($key)) {
            $value = $this->castAttributeAsJson($key, $value);
        }
        
        // Handle dates
        if ($this->isDateAttribute($key) && $value !== null) {
            $value = $this->asDateTime($value);
        }
        
        $this->attributes[$key] = $value;
        
        return $this;
    }
    
    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key)
    {
        if (! $key) {
            return;
        }
        
        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship, which we will determine from the model.
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttributeValue($key);
        }
        
        // Check if it's a relationship
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }
        
        return null;
    }
    
    /**
     * Get a plain attribute (not a relationship).
     */
    public function getAttributeValue(string $key)
    {
        $value = $this->getAttributeFromArray($key);
        
        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }
        
        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependant upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }
        
        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if ($this->isDateAttribute($key) && $value !== null) {
            return $this->asDateTime($value);
        }
        
        return $value;
    }
    
    /**
     * Get an attribute from the $attributes array.
     */
    protected function getAttributeFromArray(string $key)
    {
        return $this->attributes[$key] ?? null;
    }
    
    /**
     * Get a relationship.
     */
    public function getRelationValue(string $key)
    {
        // If the key already exists in the relationships array, it just means the
        // relationship has already been loaded, so we'll just return it out of
        // here because there is no need to query within the relations twice.
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }
        
        // If the "attribute" exists as a method on the model, we will just assume
        // it is a relationship and will load and return results from the query
        // and hydrate the relationship's value on the "relationships" array.
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }
        
        return null;
    }
    
    /**
     * Get a relationship value from a method.
     */
    protected function getRelationshipFromMethod(string $method)
    {
        $relation = $this->$method();
        
        if (! $relation instanceof Relation) {
            if (is_null($relation)) {
                throw new \LogicException(
                    sprintf('%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?', static::class, $method)
                );
            }
            
            throw new \LogicException(
                sprintf('%s::%s must return a relationship instance.', static::class, $method)
            );
        }
        
        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }
    
    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }
    
    /**
     * Set the specific relationship in the model.
     */
    public function setRelation(string $relation, $value): self
    {
        $this->relations[$relation] = $value;
        
        return $this;
    }
    
    /**
     * Unset a loaded relationship.
     */
    public function unsetRelation(string $relation): self
    {
        unset($this->relations[$relation]);
        
        return $this;
    }
    
    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table ?? str_replace(
            '\\', '', snake_case(str_plural(class_basename($this)))
        );
    }
    
    /**
     * Set the table associated with the model.
     */
    public function setTable(string $table): self
    {
        $this->table = $table;
        
        return $this;
    }
    
    /**
     * Get the value of the model's primary key.
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }
    
    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }
    
    /**
     * Set the primary key for the model.
     */
    public function setKeyName(string $key): self
    {
        $this->primaryKey = $key;
        
        return $this;
    }
    
    /**
     * Save the model to the database.
     */
    public function save(array $options = []): bool
    {
        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }
        
        $query = $this->newModelQuery();
        
        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->isDirty() ?
                $this->performUpdate($query) : true;
        }
        
        // If the model is brand new, we'll insert it into our database and set the
        // ID attribute on the model to the value of the newly inserted row's ID
        // which is typically an auto-increment value managed by the database.
        else {
            $saved = $this->performInsert($query);
            
            if (! $this->getConnectionName() &&
                $connection = $query->getConnection()) {
                $this->setConnection($connection);
            }
        }
        
        // If the model is successfully saved, we need to do a few more things once
        // that is done. We will call the "saved" method so the developers may hook
        // into post-save operations. We will also set the "exists" property.
        if ($saved) {
            $this->finishSave($options);
        }
        
        return $saved;
    }
    
    /**
     * Perform a model update operation.
     */
    protected function performUpdate(QueryBuilder $query): bool
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }
        
        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }
        
        $attributes = $this->getDirtyForUpdate();
        
        if (count($attributes) > 0) {
            $this->setKeysForSaveQuery($query)->update($attributes);
            
            $this->syncChanges();
            
            $this->fireModelEvent('updated', false);
        }
        
        return true;
    }
    
    /**
     * Perform a model insert operation.
     */
    protected function performInsert(QueryBuilder $query): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }
        
        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }
        
        $attributes = $this->getAttributesForInsert();
        
        if (empty($attributes)) {
            return true;
        }
        
        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final insert ID for this
        // table from the database. Not all tables have to be incrementing though.
        if ($this->getIncrementing()) {
            $this->insertAndSetId($query, $attributes);
        }
        
        // If the table isn't incrementing we'll simply insert these attributes as they
        // are. These attribute arrays must contain an "id" column previously placed
        // there by the developer as the manually determined key for these models.
        else {
            if (empty($attributes)) {
                return true;
            }
            
            $query->insert($attributes);
        }
        
        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;
        
        $this->wasRecentlyCreated = true;
        
        $this->fireModelEvent('created', false);
        
        return true;
    }
    
    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery(): QueryBuilder
    {
        return $this->newModelQuery();
    }
    
    /**
     * Get a new query builder instance for the connection.
     */
    public function newModelQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->getConnection()))
                    ->table($this->getTable());
    }
    
    /**
     * Create a new instance of the given model.
     */
    public static function create(array $attributes = []): static
    {
        $model = new static($attributes);
        
        $model->save();
        
        return $model;
    }
    
    /**
     * Get the database connection for the model.
     */
    public function getConnection(): DatabaseDriver
    {
        return static::$connection ??= Container::getInstance()->resolve(DatabaseDriver::class);
    }
    
    /**
     * Set the connection associated with the model.
     */
    public function setConnection(DatabaseDriver $connection): self
    {
        static::$connection = $connection;
        
        return $this;
    }
    
    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }
    
    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }
    
    /**
     * Determine if an attribute or relation exists on the model.
     */
    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }
    
    /**
     * Unset an attribute on the model.
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }
    
    /**
     * Handle dynamic static method calls into the method.
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return (new static)->$method(...$parameters);
    }
    
    /**
     * Handle dynamic method calls into the model.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }
    
    // Helper methods that need to be implemented...
    
    protected function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get' . str_studly($key) . 'Attribute');
    }
    
    protected function mutateAttribute(string $key, $value)
    {
        return $this->{'get' . str_studly($key) . 'Attribute'}($value);
    }
    
    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->getCasts());
    }
    
    protected function getCasts(): array
    {
        if ($this->getIncrementing()) {
            return array_merge([$this->getKeyName() => $this->getKeyType()], $this->casts);
        }
        
        return $this->casts;
    }
    
    protected function castAttribute(string $key, $value)
    {
        $castType = $this->getCastType($key);
        
        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return $value;
        }
        
        return match ($castType) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => $this->fromFloat($value),
            'decimal' => $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]),
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'object' => $this->fromJson($value, true),
            'array', 'json' => $this->fromJson($value),
            'collection' => new Collection($this->fromJson($value)),
            'date' => $this->asDate($value),
            'datetime', 'custom_datetime' => $this->asDateTime($value),
            'timestamp' => $this->asTimestamp($value),
            default => $value,
        };
    }
    
    // Additional helper methods would need to be implemented here...
    // This is a foundational structure for the Eloquent-style ORM
}
