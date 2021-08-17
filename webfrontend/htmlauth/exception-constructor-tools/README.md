# Exception Constructor Tools

[![Build Status](https://travis-ci.org/tomphp/exception-constructor-tools.svg?branch=master)](https://travis-ci.org/tomphp/exception-constructor-tools)
[![Latest Stable Version](https://poser.pugx.org/tomphp/exception-constructor-tools/v/stable)](https://packagist.org/packages/tomphp/exception-constructor-tools)
[![Total Downloads](https://poser.pugx.org/tomphp/exception-constructor-tools/downloads)](https://packagist.org/packages/tomphp/exception-constructor-tools)
[![Latest Unstable Version](https://poser.pugx.org/tomphp/exception-constructor-tools/v/unstable)](https://packagist.org/packages/tomphp/exception-constructor-tools)
[![License](https://poser.pugx.org/tomphp/exception-constructor-tools/license)](https://packagist.org/packages/tomphp/exception-constructor-tools)

A simple PHP trait which makes creating static constructors for exceptions nicer.

## Installation

```
$ composer require tomphp/exception-constructor-tools
```

## Usage

Define your exception:

```php
<?php

use TomPHP\ExceptionConstructorTools\ExceptionConstructorTools;

class MyExceptionClass extends \RuntimeException
{
    use ExceptionConstructorTools;

    public static function forEntity($entity)
    {
        return self::create(
            'There was an error with an entity of type %s with value of %s.',
            [
                self::typeToString($entity)
                self::valueToString($entity)
            ]
        );
    }
}
```

Throw your exception:

```php
if ($errorOccurred) {
    throw MyExceptionClass::forEntity($entity);
}
```
