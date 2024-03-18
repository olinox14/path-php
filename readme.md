[![CI](https://github.com/olinox14/path-php/actions/workflows/php.yml/badge.svg)](https://github.com/olinox14/path-php/actions/workflows/php.yml)
[![Coverage Status](https://coveralls.io/repos/github/olinox14/path-php/badge.svg?branch=master)](https://coveralls.io/github/olinox14/path-php?branch=master)

# Path-php

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

### Contribute 

#### Build docker

    docker build -t path .

    # Sur Linux
    docker run -v "$(pwd)":/path --name path path

    # Sous Windows
    docker run -d -v "%cd%:/path" --name path path

    docker exec -it path bash
    composer install

#### Run tests

    docker start path
    docker exec -it path bash
    XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.xml