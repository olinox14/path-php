<?php

namespace Path;

use Generator;
use Path\Exception\FileExistsException;
use Path\Exception\FileNotFoundException;
use Path\Exception\IOException;
use RuntimeException;
use Throwable;

/**
 * Represents a file or directory path.
 *
 * @package olinox14/path
 */
class Path
{
    /**
     * File exists
     */
    const F_OK = 0;
    /**
     * Has read permission on the file
     */
    const R_OK = 4;
    /**
     * Has write permission on the file
     */
    const W_OK = 2;
    /**
     * Has execute permission on the file
     */
    const X_OK = 1;

    protected string $path;

    protected mixed $handle;

    /**
     * Joins two or more parts of a path together, inserting '/' as needed.
     * If any component is an absolute path, all previous path components
     * will be discarded. An empty last part will result in a path that
     * ends with a separator.
     *
     * TODO: see if necessary : https://github.com/python/cpython/blob/d22c066b802592932f9eb18434782299e80ca42e/Lib/posixpath.py#L81
     *
     * @param string|Path $path The base path
     * @param string ...$parts The parts of the path to be joined.
     * @return string The resulting path after joining the parts using the directory separator.
     */
    public static function join(string|self $path, string|self ...$parts): string
    {
        $path = (string)$path;
        $parts = array_map(fn($x) => (string)$x, $parts);

        foreach ($parts as $part) {
            if (str_starts_with($part, DIRECTORY_SEPARATOR)) {
                $path = $part;
            } elseif (!$path || str_ends_with($path, DIRECTORY_SEPARATOR)) {
                $path .= $part;
            } else {
                $path .= DIRECTORY_SEPARATOR . $part;
            }
        }
        return $path;
    }

    public function withFile(string|self $path, string $mode = 'r') {
        //TODO: do a 'with open' like method
    }

    /**
     * Copies a directory and its contents recursively from the source directory to the destination directory.
     *
     * @param string|self $src The source directory to be copied. It can be a string representing the directory path
     *                         or an instance of the same class.
     * @param string|self $dst The destination directory where the source directory and its contents will be copied.
     *                         It can be a string representing the directory path or an instance of the same class.
     *
     * @return void
     * TODO: see https://stackoverflow.com/a/12763962/4279120
     *
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public static function copy_dir(string|self $src, string|self $dst): void
    {
        $src = (string)$src;
        $dst = (string)$dst;

        if (!is_dir($src)) {
            throw new FileNotFoundException("Directory does not exist : " . $src);
        }
        if (!is_dir($dst)) {
            throw new FileNotFoundException("Directory does not exist : " . $dst);
        }
        $newDir = self::join($dst, pathinfo($src, PATHINFO_FILENAME));
        if (file_exists($newDir)) {
            throw new FileExistsException("Directory already exists : " . $newDir);
        }

        self::_copy_dir($src, $dst);
    }

    /**
     * [internal] Recursively copies a directory from source to destination.
     *
     * @param string $src The path to the source directory.
     * @param string $dst The path to the destination directory.
     * @return void
     * @throws FileNotFoundException If a file within the source directory does not exist.
     */
    private static function _copy_dir(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            mkdir($dst);
        }
        try {
            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $path = self::join($src, $file);
                $newPath = self::join($dst, $file);

                if (is_dir($path)) {
                    self::_copy_dir($path, $newPath);
                } else if(is_file($path)) {
                    copy($path, $newPath);
                } else {
                    throw new FileNotFoundException("File does not exist : " . $path);
                }
            }
        } finally {
            closedir($dir);
        }
    }

    /** @noinspection SpellCheckingInspection */
    private static function _rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $object) {
            if ($object == "." || $object == "..") {
                continue;
            }

            if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object)) {
                self::_rrmdir($dir . DIRECTORY_SEPARATOR . $object);
            }
            else {
                unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
        rmdir($dir);
    }

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->handle = null;
        return $this;
    }

    public function __toString(): string {
        return $this->path;
    }

    /**
     * Retrieves the current path of the file or directory
     *
     * @return string The path of the file or directory
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Checks if the given path is equal to the current path.
     *
     * @param string|Path $path The path to compare against.
     *
     * @return bool Returns true if the given path is equal to the current path, false otherwise.
     */
    public function eq(string|self $path): bool {
        return (string)$path === $this->path;
    }

    /**
     * Appends parts to the current path.
     *
     * @see Path::join()
     *
     * @param string ...$parts The parts to be appended to the current path.
     * @return self Returns an instance of the class with the appended path.
     */
    public function append(string ...$parts): self
    {
        $this->path = self::join($this->path, ...$parts);
        return $this;
    }

    /**
     * Returns an absolute version of the current path.
     *
     * @return string
     * TODO: make an alias `realpath`
     */
    public function abspath(): string
    {
        return realpath($this->path);
    }

    /**
     * Checks the access rights for a given file or directory.
     * From the python `os.access` method
     *
     * @param int $mode The access mode to check. Permitted values:
     *        - F_OK: checks for the existence of the file or directory.
     *        - R_OK: checks for read permission.
     *        - W_OK: checks for write permission.
     *        - X_OK: checks for execute permission.
     * @return bool Returns true if the permission check is successful; otherwise, returns false.
     * TODO: complete unit tests
     */
    function access(int $mode): bool
    {
        return match ($mode) {
            self::F_OK => file_exists($this->path),
            self::R_OK => is_readable($this->path),
            self::W_OK => is_writable($this->path),
            self::X_OK => is_executable($this->path),
            default => throw new RuntimeException('Invalid mode'),
        };
    }

    /**
     * Retrieves the last access time of a file or directory.
     *
     * @return string|null The last access time of the file or directory in 'Y-m-d H:i:s' format. Returns null if the file or directory does not exist or on error.
     */
    function atime(): ?string
    {
        $time = fileatime($this->path);
        if ($time === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $time);
    }

    /**
     * Check if the path refers to a regular file.
     *
     * @return bool Returns true if the path refers to a regular file, otherwise returns false.
     */
    public function isFile(): bool
    {
        return is_file($this->path);
    }

    /**
     * Check if the given path is a directory.
     *
     * @return bool Returns true if the path is a directory, false otherwise.
     */
    public function isDir(): bool
    {
        return is_dir($this->path);
    }

    /**
     * Get the extension of the given path.
     *
     * @return string Returns the extension of the path as a string if it exists, or an empty string otherwise.
     */
    public function ext(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /**
     * Get the base name of the path.
     *
     * @return string The base name of the path.
     */
    public function basename(): string
    {
        return pathinfo($this->path, PATHINFO_BASENAME);
    }

    /**
     * Get the name of the file or path.
     *
     * @return string Returns the name of the file without its extension
     */
    public function name(): string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * Creates a new directory.
     *
     * @param int $mode (optional) The permissions for the new directory. Default is 0777.
     * @param bool $recursive (optional) Indicates whether to create parent directories if they do not exist. Default is false.
     *
     * @return void
     * @throws FileExistsException
     */
    public function mkdir(int $mode = 0777, bool $recursive = false): void
    {
        // TODO: may we make $mode the second arg, and mimic the mode of the parent if not provided?
        if ($this->isDir()) {
            if (!$recursive) {
                throw new FileExistsException("Directory already exists : " . $this);
            } else {
                return;
            }
        }
        if ($this->isFile()) {
            throw new FileExistsException("A file with this name already exists : " . $this);
        }

        mkdir($this->path, $mode, $recursive);
    }

    /**
     * Deletes a file or a directory.
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function delete(): void
    {
        if ($this->isFile()) {
            unlink($this->path);
        } else if ($this->isDir()) {
            rmdir($this->path);
        } else {
            throw new FileNotFoundException("File does not exist : " . $this);
        }
    }

    /**
     * Copies a file or directory to the specified destination.
     *
     * @param string|self $destination The destination path or object to copy the file or directory to.
     * @throws FileNotFoundException If the source file or directory does not exist.
     * @throws FileExistsException
     */
    public function copy(string|self $destination): void
    {
        if ($this->isFile()) {
            $destination = (string)$destination;
            if (is_dir($destination)) {
                $destination = self::join($destination, $this->basename());
            }
            if (file_exists($destination)) {
                throw new FileExistsException("File or dir already exists : " . $destination);
            }
            copy($this->path, $destination);
        } else if ($this->isDir()) {
            self::copy_dir($this, $destination);
        } else {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }
    }

    /**
     * Moves a file or directory to a new location.
     *
     * @param string|Path $destination The new location where the file or directory should be moved to.
     *
     * @return void
     * @throws FileExistsException
     */
    public function move(string|self $destination): void
    {
        $destination = (string)$destination;
        if (is_dir($destination)) {
            $destination = self::join($destination, $this->basename());
        }
        if (file_exists($destination)) {
            throw new FileExistsException("File or dir already exists : " . $destination);
        }
        rename($this->path, $destination);
    }

    /**
     * Updates the access and modification time of a file or creates a new empty file if it doesn't exist.
     *
     * @param int|\DateTime|null $time (optional) The access and modification time to set. Default is the current time.
     * @param int|\DateTime|null $atime (optional) The access time to set. Default is the value of $time.
     *
     * @return void
     */
    public function touch(int|\DateTime $time = null, int|\DateTime $atime = null): void
    {
        if ($time instanceof \DateTime) {
            $time = $time->getTimestamp();
        }
        if ($atime instanceof \DateTime) {
            $atime = $atime->getTimestamp();
        }
        touch($this->path, $time, $atime);
    }

    /**
     * Returns the last modified timestamp of a file or directory.
     *
     * @return int|bool The last modified timestamp, or false if an error occurred.
     */
    public function lastModified(): bool|int
    {
        return filemtime($this->path);
    }

    /**
     * Calculates the size of a file.
     *
     * @return int The size of the file in bytes.
     * @throws FileNotFoundException
     */
    public function size(): int
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File does not exist : " . $this->path);
        }
        return filesize($this->path);
    }

    /**
     * Retrieves the parent directory of a file or directory path.
     *
     * @return string The parent directory of the specified path.
     */
    public function parent(): string
    {
        // TODO: check on special cases
        return dirname($this->path);
    }

    /**
     * Retrieves the content of a file.
     *
     * @return bool|string The content of the file as a string.
     * @throws FileNotFoundException|IOException
     */
    public function getContent(): bool|string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File does not exist : " . $this->path);
        }
        $text = file_get_contents($this->path);
        if ($text === false) {
            throw new IOException("Error reading file {$this->path}");
        }
        return $text;
    }

    /**
     * Writes contents to a file.
     *
     * @param string $content The contents to be written to the file.
     * @return void
     */
    public function putContent(string $content): void
    {
        // TODO: review use-cases
        // TODO: complete the input types
        // TODO: add a condition on the creation of the file if not existing
        file_put_contents($this->path, $content);
    }

    /**
     * Appends contents to a file.
     *
     * @param string $content The contents to append to the file.
     *
     * @return void
     */
    public function appendContent(string $content): void
    {
        // TODO: review use-cases
        // TODO: complete the input types
        // TODO: add a condition on the creation of the file if not existing
        file_put_contents($this->path, $content, FILE_APPEND);
    }

    /**
     * Retrieves the permissions of a file or directory.
     *
     * @return int The permissions of the file or directory in octal notation.
     * @throws FileNotFoundException
     */
    public function getPermissions(): int
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }
        return (int)substr(sprintf('%o', fileperms($this->path)), -4);
    }

    // TODO; add some more user-friendly methods to get permissions (read, write, exec...)

    /**
     * Changes the permissions of a file or directory.
     *
     * @param int $permissions The new permissions to set. The value should be an octal number.
     * @return bool Returns true on success, false on failure.
     * @throws FileNotFoundException
     */
    public function setPermissions(int $permissions): bool
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }
        clearstatcache(); // TODO: check for a better way of dealing with PHP cache
        return chmod($this->path, $permissions);
    }

    /**
     * Changes ownership of the file.
     *
     * @param string $user The new owner username.
     * @param string $group The new owner group name.
     * @return bool
     * @throws FileNotFoundException
     */
    public function setOwner(string $user, string $group): bool
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }
        clearstatcache(); // TODO: check for a better way of dealing with PHP cache
        return
            chown($this->path, $user) &&
            chgrp($this->path, $group);
    }

    /**
     * Checks if a file exists.
     *
     * @return bool Returns true if the file exists, false otherwise.
     */
    public function exists(): bool
    {
        return file_exists($this->path);
    }

    /**
     * Retrieves a list of files and directories that match a specified pattern.
     *
     * @param string $pattern The pattern to search for.
     * @return Generator An iterable list of objects representing files and directories that match the pattern.
     */
    public static function glob(string $pattern): Generator
    {
        foreach (glob($pattern) as $filename) {
            yield new static($filename);
        }
    }

    /**
     * Removes a directory and its contents recursively.
     *
     * @throws FileNotFoundException
     */
    public function rmdir(bool $recursive = false): void
    {
        if (!is_dir($this->path)) {
            throw new FileNotFoundException("{$this->path} is not a directory");
        }

        if ($recursive) {
            self::_rrmdir($this->path);
        } else {
            rmdir($this->path);
        }
    }

    /**
     * Opens a file in the specified mode.
     *
     * @param string $mode The mode in which to open the file. Defaults to 'r'.
     * @return resource|false Returns a file pointer resource on success, or false on failure.
     * @throws FileNotFoundException If the path does not refer to a file.
     * @throws IOException If the file fails to open.
     */
    public function open(string $mode = 'r'): mixed
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("{$this->path} is not a file");
        }

        $handle = fopen($this->path, $mode);
        if ($handle === false) {
            throw new IOException("Failed opening file {$this->path}");
        }

        return $handle;
    }

    /**
     * Calls a callback with a file handle opened with the specified mode and closes the handle afterward.
     *
     * @param callable $callback The callback function to be called with the file handle.
     * @param string $mode The mode in which to open the file. Defaults to 'r'.
     * @throws Throwable If an exception is thrown within the callback function.
     */
    public function with(callable $callback, string $mode = 'r'): void
    {
        $handle = $this->open($mode);
        try {
            $callback($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Check whether this path is absolute.
     *
     * @return bool
     */
    public function isAbs(): bool
    {
        return str_starts_with($this->path, '/');
    }

    /**
     * > Alias for Path->setPermissions() method
     * Changes permissions of the file.
     *
     * @param int $mode The new permissions (octal).
     * @return bool
     * @throws FileNotFoundException
     */
    public function chmod(int $mode): bool
    {
        return $this->setPermissions($mode);
    }

    /**
     * > Alias for Path->setOwner() method
     * Changes ownership of the file.
     *
     * @param string $user The new owner username.
     * @param string $group The new owner group name.
     * @return bool
     */
    public function chown(string $user, string $group): bool
    {
        return $this->setOwner($user, $group);
    }

    /**
     * Checks if the file is a symbolic link.
     *
     * @return bool
     */
    public function isLink(): bool
    {
        return is_link($this->path);
    }

    /**
     * Iterate over the files in this directory.
     *
     * @return Generator
     * @throws FileNotFoundException if the path is not a directory.
     */
    public function iterDir(): Generator
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("{$this->path} is not a directory");
        }

        foreach (new \DirectoryIterator($this->path) as $fileInfo) {
            // TODO: use the DirectoryIterator everywhere else?
            if ($fileInfo->isDot()) {
                continue;
            }
            yield $fileInfo->getFilename();
        }
    }

    /**
     * Create a hard link pointing to a path.
     *
     * @param string|Path $target
     * @return bool
     */
    public function link(string|self $target): bool
    {
        $target = (string)$target;
        if (!function_exists('link')) {
            return false;
        }
        return link($this->path, $target);
    }

    /**
     * Like stat(), but do not follow symbolic links.
     *
     * @return array|false
     */
    public function lstat(): bool|array
    {
        return lstat($this->path);
    }

    /**
     * Returns the individual parts of this path.
     * The eventual leading directory separator is kept.
     *
     * Ex:
     *
     *     Path('/foo/bar/baz').parts()
     *     >>> '/', 'foo', 'bar', 'baz'
     *
     * @return array
     */
    public function parts(): array
    {
        $parts = [];
        if (str_starts_with($this->path, DIRECTORY_SEPARATOR)) {
            $parts[] = DIRECTORY_SEPARATOR;
        }
        $parts += explode(DIRECTORY_SEPARATOR, $this->path);
        return $parts;
    }

    /**
     * Compute a version of this path that is relative to another path.
     *
     * @param string|Path $basePath
     * @return string
     * @throws FileNotFoundException
     */
    public function getRelativePath(string|self $basePath): string
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("{$this->path} is not a file or directory");
        }

        $path = $this->abspath();
        $basePath = (string)$basePath;

        $realBasePath = realpath($basePath);
        if ($realBasePath === false) {
            throw new FileNotFoundException("$basePath does not exist or unable to get a real path");
        }

        $pathParts = explode(DIRECTORY_SEPARATOR, $path);
        $baseParts = explode(DIRECTORY_SEPARATOR, $realBasePath);

        while (count($pathParts) && count($baseParts) && ($pathParts[0] == $baseParts[0])) {
            array_shift($pathParts);
            array_shift($baseParts);
        }

        return str_repeat('..' . DIRECTORY_SEPARATOR, count($baseParts)) . implode(DIRECTORY_SEPARATOR, $pathParts);
    }
}