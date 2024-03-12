# Path.php

### Lancer le docker 

    docker build -t path .

    # Sur Linux
    docker run -v "$(pwd)":/path --name path path

    # Sous Windows
    docker run -d -v "%cd%:/path" --name path path

    docker exec -it path bash
    composer install

### Executer les tests

Se placer dans le docker, puis : 

    docker start path
    docker exec -it path bash
    XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.xml