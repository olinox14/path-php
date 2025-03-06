[![CI](https://github.com/olinox14/path-php/actions/workflows/php.yml/badge.svg)](https://github.com/olinox14/path-php/actions/workflows/php.yml)
[![Coverage Status](https://coveralls.io/repos/github/olinox14/path-php/badge.svg?branch=master)](https://coveralls.io/github/olinox14/path-php?branch=master)
[![Version](https://poser.pugx.org/olinox14/path-php/version)](https://packagist.org/packages/olinox14/path-php)
[![License](https://poser.pugx.org/olinox14/path-php/license)](https://packagist.org/packages/olinox14/path-php)
[![PHP Version Require](https://poser.pugx.org/olinox14/path-php/require/php)](https://packagist.org/packages/olinox14/path-php)

# Path-php

An **intuitive**, **standalone**, and **object-oriented** library for file and path operations.

**See the full documentation here : [Documentation](https://path-php.net/)**

```php
<?php
use Path\Path;

// Get the parent directory of the current script file and list its subdirs
$script = new Path(__file__);
$dir = $script->parent();
var_dump($dir->dirs());


// Get the path of the working directory, iterate over its files and change their permissions
$path = new Path('.');

foreach($path->files() as $file) {
    $file->chmod(755);
}


// Put content into a file 
$path = (new Path('.'))->append('readme.md');

$path->putContent('new readme content');

// And many more...
```


## Requirement

path-php requires **php8.0 or ulterior versions**.

## Installation

Install with composer :

    composer require olinox14/path-php

## Usage

Import the Path class : 

```php
use Path\Path;
```

Instantiate with some path : 

```php
$path = new Path('./foo');
$path = new Path('/foo/bar/file.ext');
$path = new Path(__file__);
```

And use it as needed. For example, if you want to rename all the html files in the directory where
your current script lies into .md files : 

```php
$path = new Path(__file__);

$dir = $path->parent();

foreach ($dir->files() as $file) {
    if ($file->ext() === 'html') {
        $file->rename($file->name() . '.md');
    }
}
```

## Contribute

### Git branching

Contributions shall follow the [gitflow](https://www.gitkraken.com/learn/git/git-flow) pattern.

### Tests

#### First build

> Default php version in the dockerfile is set to be the oldest actively supported 
> version of php, but you can change it locally before building your container.

Build your docker container :

    docker build -t path .

Run it (name can be changed):

    # On Linux
    docker run -v "$(pwd)":/path --name path path

    # On Windows
    docker run -d -v "%cd%:/path" --name path path

Execute it and install dependencies :

    docker exec -it path bash
    composer install

Run the unit tests :

    XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.xml

#### Run on a built container

If you've already built your container, start it and run unit tests with :

    docker start path
    docker exec -it path bash
    XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.xml

### Run code quality tools 

#### Phpstan

Build and start the docker as explained in the unit tests section, then run :

    vendor/bin/phpstan analyse --memory-limit=-1

> see: https://phpstan.org/

#### CS Fixer

Build and start the docker as explained in the unit tests section, then run :

    vendor/bin/php-cs-fixer fix src

> see https://github.com/PHP-CS-Fixer/PHP-CS-Fixer

### Generate documentation

To install and run [phpdoc](https://docs.phpdoc.org/3.0/) :

    docker pull phpdoc/phpdoc

    # On Linux
    docker run --rm -v "$(pwd):/data" "phpdoc/phpdoc:3"

    # On Windows
    docker run --rm -v "%cd%:/data" "phpdoc/phpdoc:3"

If you're on Linux, you could create an alias with :

    alias phpdoc="docker run --rm -v $(pwd):/data phpdoc/phpdoc:3"

## Licence 

Path-php is under the [MIT](http://opensource.org/licenses/MIT) licence.
