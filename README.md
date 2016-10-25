[![Circle CI](https://circleci.com/gh/navjobs/temporal-models.svg?style=shield)](https://circleci.com/gh/navjobs/temporal-models)
[![Coverage Status](https://coveralls.io/repos/github/navjobs/temporal-models/badge.svg?branch=master)](https://coveralls.io/github/navjobs/temporal-models?branch=master)

###### Temporal Models for Laravel
Adds support for Temporal Models to Laravel 5.1+

> Usually in a database entities are represented by a row in a table, when this row is updated the old information is
> overwritten. The temporal model allows data to be referenced in time, it makes it possible to query the state of an
> entity at a given time.
>
> For example, say you wanted to keep track of changes to products so when an order is placed you know the state of the
> product without having to duplicate data in the orders table. You can make the products temporal and use the time of
> the order to reference the state of the ordered products at that time rather than how they currently are, as would
> happen without using temporal data.
>
> The temporal model could also be used for auditing changes to things like wiki pages. Any changes would be
> automatically logged without having to use a separate log table.
[From FuelPhp docs](http://fuelphp.com/dev-docs/packages/orm/model/temporal.html)

## Installation

You can install this package via composer using this command:

```bash
composer require navjobs/temporal-model
```

Next, the model you wish to make Temporal must have the following fields in its Schema:

```php
$table->dateTime('valid_start');
$table->dateTime('valid_end')->nullable();
```

The model itself must contain:

```php
class TemporalModel extends Model
{
    use Temporal;

    protected $dates = ['valid_start', 'valid_end'];
    protected $temporalParentColumn = 'parent_id';
}
```

The $temporalParentColumn is the field name of the column that ties the Temporal Models together. For example, if the
model is a commission rate then the $temporalParentColumn might 'representative_id'.

## Usage

###### Creating Temporal Models
When a Temporal Model is created it automatically resolves any scheduling conflicts. If the created model overlaps with
a scheduled model then the scheduled model will be removed. Any already started models will have their
valid_end set to the valid_start of the model that is being created. Temporal Models cannot be created in the
past.

###### Updating Temporal Models
In order to preserve their historic nature, updates to Temporal Models are restricted to just valid_end after
they have started. Attempting to update any other fields will cause the update to not be persisted to the database.
If this behavior is not desired then it can be modified by adding the following public property to the Temporal Model:

```php
public $allowUpdating = true;
```

Additionally the behavior can be changed dynamically by setting $model->allowUpdating to true in-line.

###### Deleting Temporal Models
Temporal Models that have already started cannot be deleted. When the delete method is called on them they will simply
have their valid_end set to now. If delete is called on a scheduled model then it will succeed.

###### Methods and Scopes
The Temporal trait includes an isValid() method that optionally takes a Carbon object. The method returns whether the
model was valid on the provided date or now if no Carbon object is provided. Also included are a valid() and invalid()
scope. These scopes query for either the valid or invalid scopes at the time of the passed Carbon object or now if no
Carbon object is passed.

