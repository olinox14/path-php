[![CI](https://github.com/olinox14/path-php/actions/workflows/php.yml/badge.svg)](https://github.com/olinox14/path-php/actions/workflows/php.yml)
[![Coverage Status](https://coveralls.io/repos/github/olinox14/path-php/badge.svg?branch=master)](https://coveralls.io/github/olinox14/path-php?branch=master)

# Path-php

> This library is still under development, DO NOT USE IN PRODUCTION. Contributions, however, are welcome.

An intuitive and object-oriented file and path operations, inspired by the path.py python library.

    <?php

    use Path\Path;
  
    $path = new Path(__file__).parent();

    echo($path->dirs());

    $path = new Path('/home');

    foreach($path->files() as $file) {
        $file->chmod(0555);
    }

    $newPath = $path->append('bar');

    echo($newPath->absPath()); // => '/foo/bar'


> Require php8.0+

[Full documentation here](https://olinox14.github.io/path-php/classes/Path-Path.html)

### Contribute 

#### Build docker

    docker build -t path .

    # On Linux
    docker run -v "$(pwd)":/path --name path path

    # On Windows
    docker run -d -v "%cd%:/path" --name path path

    docker exec -it path bash
    composer install

#### Run tests

    docker start path
    docker exec -it path bash
    XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.xml

#### Generate doc

To install and execute the [phpdoc](https://docs.phpdoc.org/3.0/) container :

    docker pull phpdoc/phpdoc

    # On Linux
    docker run --rm -v "$(pwd):/data" "phpdoc/phpdoc:3"

    # On Windows
    docker run --rm -v "%cd%:/data" "phpdoc/phpdoc:3"

Then, if on linux, you may create an alias :

    alias phpdoc="docker run --rm -v $(pwd):/data phpdoc/phpdoc:3"

And run phpdoc with :

    phpdoc