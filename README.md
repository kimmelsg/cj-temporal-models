[![Circle CI](https://circleci.com/gh/navjobs/transmit.svg?style=shield)](https://circleci.com/gh/navjobs/transmit)
[![Coverage Status](https://coveralls.io/repos/NavJobs/Transmit/badge.svg?branch=master&service=github)](https://coveralls.io/github/NavJobs/Transmit?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/NavJobs/Transmit/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/NavJobs/Transmit/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/navjobs/transmit/v/stable)](https://packagist.org/packages/navjobs/transmit) [![Total Downloads](https://poser.pugx.org/navjobs/transmit/downloads)](https://packagist.org/packages/navjobs/transmit) [![License](https://poser.pugx.org/navjobs/transmit/license)](https://packagist.org/packages/navjobs/transmit)


###### Temporal Models for Laravel
Transmit was created to expedite the process of implementing REST APIs.


###### Communication Layer For Laravel
Transmit was created to expedite the process of implementing REST APIs.

#### Install

Via Composer:
``` bash
$ composer require NavJobs/Transmit
```

Register the service provider in your config/app.php:

```php
'providers' => [
    ...
    NavJobs\Transmit\TransmitServiceProvider::class,
];
```

The package has a publishable config file that allows you to chang the default serializer. To publish:

```bash
php artisan vendor:publish --provider="NavJobs\Transmit\TransmitServiceProvider"
```

#### Api
Transmit provides an abstract controller class that you should extend from:

```php
use NavJobs\Transmit\Controller as ApiController;

class BookController extends ApiController
{
...
```

The controller class provides a number of methods that make API responses easy:

```php
//Return the specified item, transformed
$this->respondWithItem($item, $optionalTransformer);

//Sets the status code to 201 and return the specified item, transformed
$this->responsdWithItemCreated($item, $optionalTransformer);

//Return the specified collection, transformed
$this->respondWithCollection($collection, $optionalTransformer);

//Paginate the specified collection
$this->respondWithPaginatedCollection($collection, $optionalTransformer, $perPage = 10);

//Set the status code to 204, and return no content
$this->respondWithNoContent();
```

Also provided are a number of error methods. These return an error response as well as setting the status code:

```php
//Sets the status code to 403
$this->errorForbidden($optionalMessage);

//Sets the status code to 500
$this->errorInternalError($optionalMessage);

//Sets the status code to 404
$this->errorNotFound($optionalMessage);

//Sets the status code to 401
$this->errorUnauthorized($optionalMessage);

//Sets the status code to 400
$this->errorWrongArgs($optionalMessage);
```

The controller also uses a the QueryHelperTrait that aids with applying query string parameters to Eloquent models and query builder instances:

```php
//Eager loads the specified includes on the model
$this->eagerLoadIncludes($eloquentModel, $includes);

//Applies sort, limit, and offset to the provided query Builder
$this->applyParameters($queryBuilder, $parameters);
```

#### Transformers
Transmit provides an abstract transformer class that your transformers should extend from:

```php
use NavJobs\LaravelApi\Transformer as BaseTransformer;

class BookTransformer extends BaseTransformer
...
```

The transformer allows you to easily determine which relationships should be allowed to be eager loaded. This is determined by matching the requested includes against the available and default includes.

```php
//Pass in either an array or csv string
//Returns an array of includes that should be eager loaded
$this->getEagerLoads($requestedIncludes);
```

#### Implementation Example
These methods can be combined to quickly create expressive api controllers. The following is an example of what that implementation might look like:

```php
<?php

namespace App\Book\Http\Books\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Book\Domain\Books\Entities\Book;
use App\Book\Transformers\BookTransformer;
use App\Book\Http\Books\Requests\BookRequest;
use NavJobs\Transmit\Controller as ApiController;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BookController extends ApiController
{
    protected $bookModel;
    protected $transformer;
    protected $fractal;

    /**
     * @param Book $bookModel
     * @param BookTransformer $transformer
     */
    public function __construct(Book $bookModel, BookTransformer $transformer)
    {
        parent::__construct();

        $this->transformer = $transformer;
        $this->bookModel = $bookModel;
    }

    /**
     * Show a list of Books.
     *
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $includes = $this->transformer->getEagerLoads($this->fractal->getRequestedIncludes());
        $books = $this->eagerLoadIncludes($this->bookModel, $includes);
        $books = $this->applyParameters($books, $request->query);

        return $this->respondWithPaginatedCollection($books->get(), $this->transformer);
    }

    /**
     * Show a book by the specified id.
     *
     * @param $bookId
     * @return mixed
     */
    public function show($bookId)
    {
        try {
            $includes = $this->transformer->getEagerLoads($this->fractal->getRequestedIncludes());
            $books = $this->eagerLoadIncludes($this->bookModel, $includes);

            $book = $books->findOrFail($bookId);
        } catch (ModelNotFoundException $e) {
            return $this->errorNotFound();
        }

        return $this->respondWithItem($book, $this->transformer);
    }

    /**
     * Handle the request to persist a Book.
     *
     * @param bookRequest $request
     * @return array
     */
    public function store(BookRequest $request)
    {
        $book = $this->bookModel->create($request->all());

        return $this->respondWithItemCreated($book, $this->transformer);
    }


    /**
     * Handle the request to update a Book.
     *
     * @param BookRequest $request
     * @param $bookId
     * @return mixed
     */
    public function update(BookRequest $request, $bookId)
    {
        try {
            $book = $this->bookModel->findOrFail($bookId);
        } catch (ModelNotFoundException $e) {
            return $this->errorNotFound();
        }

        $book->update($request->all());

        return $this->respondWithNoContent();
    }

    /**
     * Handle the request to delete a Book.
     *
     * @param $bookId
     * @return mixed
     */
    public function destroy($bookId)
    {
        try {
            $book = $this->bookModel->findOrFail($bookId);
        } catch (ModelNotFoundException $e) {
            return $this->errorNotFound();
        }

        try {
            $book->delete();
        } catch (Exception $e) {
            return $this->errorInternalError();
        }

        return $this->respondWithNoContent();
    }
}
```

#### Usage
This implementation allows endpoints to take includes as well as query string parameters. To apply parameters to the current resource:

```
http://www.example.com/books?limit=5&sort=name,-created_at
```

Includes are available as follows:

```
http://www.example.com/books?include=authors,publisher
```

Includes can also be sorted by query parameters, the URL format is:

```
http://www.example.com/books?include=authors:sort(name|-created_at),publisher
```

## Fractal

Transmit is built on the back of two amazing PHP packages.

- [fractal](https://github.com/thephpleague/fractal)
- [laravel-fractal](https://github.com/spatie/laravel-fractal/tree/master/src).

Controllers have an instance of laravel-fractal available through:

```php
$this->fractal;
```
Any methods available from laravel-fractal can be accessed directly through this intance. Any unknown methods called on the instance are passed through to the fractal manager class. Please refer to the documentation of these packages for additional functionality.

## Testing

``` bash
$ composer test
```

## Credits

- [The League of Extraordinary Packages](http://fractal.thephpleague.com/)
- [Spatie](https://spatie.be/)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
