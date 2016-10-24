[![Circle CI](https://circleci.com/gh/navjobs/temporal-models.svg?style=shield)](https://circleci.com/gh/navjobs/temporal-models)
[![Coverage Status](https://coveralls.io/repos/github/navjobs/temporal-models/badge.svg?branch=master)](https://coveralls.io/github/navjobs/temporal-models?branch=master)

###### Temporal Models for Laravel
Adds support for Temporal Models to Laravel 5.1+

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
