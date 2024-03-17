[![CI](https://github.com/olinox14/path-php/actions/workflows/php.yml/badge.svg)](https://github.com/olinox14/path-php/actions/workflows/php.yml)

# Path-php

An intuitive and object-oriented file and path operations, inspired by the path.py python library.

### Examples



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