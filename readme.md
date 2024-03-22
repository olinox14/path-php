[![CI](https://github.com/olinox14/path-php/actions/workflows/php.yml/badge.svg)](https://github.com/olinox14/path-php/actions/workflows/php.yml)
[![Coverage Status](https://coveralls.io/repos/github/olinox14/path-php/badge.svg?branch=master)](https://coveralls.io/github/olinox14/path-php?branch=master)

# Path-php

> This library is still under development, DO NOT USE IN PRODUCTION. Contributions, however, are welcome.

An intuitive and object-oriented file and path operations, inspired by the path.py python library.

    <?php

    use Path\Path;

    $dir = (new Path(__file__))->parent();
    
    var_dump(
        $dir->dirs()
    );
    
    $path = new Path('.');
    
    foreach($path->files() as $file) {
        $file->chmod(755);
    }
    
    $newPath = $path->append('readme.md');
    
    var_dump($newPath->absPath());


### Contribute 

#### Build docker

    docker build -t path .

    # Linux
    docker run -v "$(pwd)":/path --name path path

    # Windows
    docker run -d -v "%cd%:/path" --name path path

    docker exec -it path bash
    composer install

#### To run tests

    docker start path
    docker exec -it path bash
    XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.xml