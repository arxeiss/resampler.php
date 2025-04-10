# Resampler.php

Simple PHP library for resampling images with GD extension. More on https://arxeiss.github.io/resampler.php/

### Why another  library? What is so special?

* Resampler is only for resampling and never will contain drawing methods
* Counting memory usage to avoid Fatal error caused by allocating too much memory
* Support *resize*, *crop*, *rectangle* and *rotate* methods. Especially *rectangle* is missing in many other libraries
* Support auto rotation based on EXIF data
* Using cascade pattern / method chaining
* Support JPEG, PNG and GIF images. Decision is automatical based on file suffix

### Installation & requirements
Library can be installed from [Packagist](https://packagist.org/packages/resampler/resampler) and loaded with Composer

Put this line into your `composer.json` file
```
"resampler/resampler": "~2.0"
```

or execute following command

```
composer require resampler/resampler
```

#### Requirements
Library requires at least PHP 8.1 and GD and Fileinfo extensions. For PHP 5.4 use version 1.x of this library.

## Documentation and examples
More info about library, API documentation and code examples can be found on https://arxeiss.github.io/resampler.php/