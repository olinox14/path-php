[![CI](https://github.com/olinox14/path-php/actions/workflows/php.yml/badge.svg)](https://github.com/olinox14/path-php/actions/workflows/php.yml)
[![Coverage Status](https://coveralls.io/repos/github/olinox14/path-php/badge.svg?branch=master)](https://coveralls.io/github/olinox14/path-php?branch=master)

# Path-php

> This library is still under development, DO NOT USE IN PRODUCTION. Contributions, however, are welcome.

An intuitive and object-oriented library for file and path operations, inspired by the path.py python library.

    <?php

    use Path\Path;

    // Get the parent directory of the current script file
    $dir = (new Path(__file__))->parent();
    
    // Display the liste of the subdirectories of this directory
    var_dump(
        $dir->dirs()
    );
    
    // Get the path of the working directory
    $path = new Path('.');
    
    // Iterate over the files in this directory and change the permissions of these files to 755
    foreach($path->files() as $file) {
        $file->chmod(755);
    }
    
    // Create a new path by adding a file's name to the previous path
    $newPath = $path->append('readme.md');
    
    // Display the absolute path of this file
    var_dump($newPath->absPath());

## Requirement

path-php requires **php8.0 or ulterior versions**.

## Installation

Install with composer :

    composer require olinox14/path-php

## Usage

Import the Path class : 

    use Path\Path;

Instantiate with some path : 

    $path = new Path('./foo');
    $path = new Path('/foo/bar/file.ext');
    $path = new Path(__file__);

And use it as needed. For example, if you want to rename all the html files in the directory where
your current script lies into .md files : 

    $path = new Path(__file__);

    $dir = $path->parent();
    
    foreach ($dir->files() as $file) {
        if ($file->ext() === 'html') {
            $file->rename($file->name() . '.md');
        }
    }

## Documentation

> [API Documentation](https://olinox14.github.io/path-php/classes/Path-Path.html)

## Contribute

### Git branching

Contributions shall follow the [gitflow](https://www.gitkraken.com/learn/git/git-flow) pattern.

### Tests

#### First build

> Default php version in the dockerfile is set to be the oldest actively supported 
> version of php, but you can change it locally before building your container.

Build your docker container :

    docker build -t path .

Run it (you can change the name): 

    # On Linux
    docker run -v "$(pwd)":/path --name path path

    # On Windows
    docker run -d -v "%cd%:/path" --name path path

Execute it and install : 

    docker exec -it path bash
    composer install

Run the unit tests :

    XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.xml

#### Next runs

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

And then run phpdoc with :

    phpdoc

## Licence 

Path-php is under the [MIT](http://opensource.org/licenses/MIT) licence.

## Roadmap

0.1.5 : 

* [x] Review the name of a copied symlink with $followSymlink = true
* [x] Review the expected behavior when copyTree on a file into a non existing directory
* [ ] Prevent infinite recursion when using copyTree
* [ ] Review `files` and `exists` method: what about symlinks 
* [ ] Move should erase existing file or dir, not throw an error
* [ ] Study if a $followSymlinks is pertinent in the move method

0.2 :

* [ ] multi os compat (windows)
* [ ] handle protocols (ftp, sftp, file, smb, http, ...etc)
* [ ] handle unc paths (windows)
* [ ] improve error management and tracebacks
* [ ] add 'ignore' and 'errorOnExistingDestination' to the copyTree method
* [ ] review copyTree performances
* [ ] study the interest of implementing a 'mergeTree' method
* [ ] study the interest of mimic the perms of the source when using the copy, copyTree and move methods

0.3 : 

* [Feature] Add a $followSymlink property to the absPath method (currently always true according to php realpath behavior)