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
