[![Circle CI](https://circleci.com/gh/navjobs/temporal-models.svg?style=shield)](https://circleci.com/gh/navjobs/temporal-models)
[![Coverage Status](https://coveralls.io/repos/github/navjobs/temporal-models/badge.svg?branch=master)](https://coveralls.io/github/navjobs/temporal-models?branch=master)
[![Code Climate](https://codeclimate.com/github/navjobs/temporal-models/badges/gpa.svg)](https://codeclimate.com/github/navjobs/temporal-models)

###### Temporal Models for Laravel
Adds support for Temporal Models to Laravel 5.1+

> Usually in a database, entities are represented by a row in a table, when this row is updated the old information is
> overwritten. The temporal model allows data to be referenced in time, it makes it possible to query the state of an
> entity at a given time.
>
> For example, say you wanted to keep track of changes to products so when an order is placed you know the state of the
> product without having to duplicate data in the orders table. You can make the products temporal and use the time of
> the order to reference the state of the ordered products at that time, rather than how they currently are, as would
> happen without using temporal data.
>
> The temporal model could also be used for auditing changes to things like wiki pages. Any changes would be
> automatically logged without having to use a separate log table.

[From FuelPHP docs](http://fuelphp.com/dev-docs/packages/orm/model/temporal.html)

## Installation

You can install this package via Composer using this command:

```bash
composer require navjobs/temporal-models
```

Next, the model you wish to make temporal must have the following fields in its Schema:

```php
$table->dateTime('valid_start');
$table->dateTime('valid_end')->nullable();
```

The model itself must use the `Temporal` trait and define two protected properties as in this example:

```php
class Commission extends Model
{
    use Temporal;

    protected $dates = ['valid_start', 'valid_end'];
    protected $temporalParentColumn = 'representative_id';
}
```

The $temporalParentColumn property contains the name of the column tying the temporal records together. In the example above the model would represent a commission rate. Its $temporalParentColumn might be 'representative_id'. A representative/salesperson would have only one active commission rate at any given time. Representing the commission in a temporal fashion enables us to record history of the commission rate and schedule any future commission rates.

## Usage

###### Creating Temporal Records
When a temporal record is created it automatically resolves any scheduling conflicts. If a newly created record overlaps with a previously scheduled record then the previously scheduled record will be deleted. Any records already started will have their valid_end set to the valid_start of the newly created record. Temporal records cannot be created in the past.

###### Updating Temporal Records
In order to preserve their historic nature, updates to temporal records are restricted to just valid_end after
they have started. Attempts to update any other fields will fail. If this behavior is undesirable, it can be modified by adding the following property to the temporal model:

```php
protected $enableUpdates = true;
```

Additionally, the behavior can be changed dynamically by calling ```$model->enableUpdates()->save();```

###### Deleting Temporal Records
Temporal records that have already started cannot be deleted. When the delete method is called on them they will simply
have their valid_end set to the current time. If delete is called on a scheduled record then it will succeed.

###### Methods and Scopes
The `Temporal` trait includes an isValid() method that optionally takes a Carbon object. The method returns whether the
model was valid on the provided date or now if no Carbon object is provided. Also included are `valid()` and `invalid()`
scopes. These scopes query for either the valid or invalid scopes at the time of the passed Carbon object or now if no Carbon object is passed.

