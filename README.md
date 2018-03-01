# Resampler.php

Simple PHP library for resampling images with GD extension. More on https://arxeiss.github.io/resampler.php/

### Why another  library? What is so special?

* Resampler is only for resampling and never will contain drawing methods
* Counting memory usage to avoid Fatal error caused by allocating too much memory
* Support *resize*, *crop*, *rectangle* and *rotate* methods. Especially *rectangle* is missing in many other libraries
* Using cascade pattern / method chaining
* Support JPEG, PNG and GIF images. Decision is automatical based on file suffix

### Installation & requirements
Library can be installed from [Packagist](https://packagist.org/packages/resampler/resampler) and loaded with Composer, which is preferred way. Alternatively can be installed manually and loaded with `autoload.php` file.

Put this line into your `composer.json` file
```
"resampler/resampler": "~1.0"
```

or execute following command

```
composer require resampler/resampler
```

Manually can be library loaded with including `autoload.php` file:
```php
// Update to fit your path to resampler folder
include 'resampler/autoload.php'
```

#### Requirements
Library requires at least PHP 5.4 and extension GD

## Documentation and examples
More info about library, API documentation and code examples can be found on https://arxeiss.github.io/resampler.php/