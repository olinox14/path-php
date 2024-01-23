<?php

namespace Path;

use InvalidArgumentException;
use Path\Path\RecursiveDirectoryIterator;
use Path\Path\RecursiveIteratorIterator;
use function Path\Path\lchmod;

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

    /**
     * Joins two or more parts of a path together, inserting '/' as needed.
     * If any component is an absolute path, all previous path components
     * will be discarded. An empty last part will result in a path that
     * ends with a separator.
     *
     * TODO: see if necessary : https://github.com/python/cpython/blob/d22c066b802592932f9eb18434782299e80ca42e/Lib/posixpath.py#L81
     *
     * @param string ...$parts The parts of the path to be joined.
     * @return string The resulting path after joining the parts using the directory separator.
     */
    public static function join(string $path, string ...$parts): string
    {
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

    public function __construct(string $path)
    {
        $this->path = $path;
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
     * @param string $path The path to compare against.
     *
     * @return bool Returns true if the given path is equal to the current path, false otherwise.
     */
    public function eq(string $path): bool {
        return $path === $this->path;
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
            default => throw new \RuntimeException('Invalid mode'),
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
     * Get the name of the file or path.
     *
     * @return array|string Returns the name of the file or path.
     * If the path has an extension, it returns the name without the extension as a string.
     * If the path doesn't have an extension, it returns the name as an array containing the directory name and the file name.
     */
    public function name(): array|string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
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
     * Creates a new directory.
     *
     * @param int $mode (optional) The permissions for the new directory. Default is 0777.
     * @param bool $recursive (optional) Indicates whether to create parent directories if they do not exist. Default is false.
     *
     * @return void
     */
    public function mkdir($mode = 0777, $recursive = false): void
    {
        if (!file_exists($this->path)) {
            mkdir($this->path, $mode, $recursive);
        }
    }

    /**
     * Deletes a file or a directory.
     *
     * @return void
     */
    public function delete(): void
    {
        if (is_file($this->path)) {
            unlink($this->path); //for file
        } else if (is_dir($this->path)) {
            rmdir($this->path); //for directory
        }
    }

    /**
     * Copies a file to a specified destination.
     *
     * @param string $destination The path to the destination file or directory to copy to.
     *
     * @return void
     */
    public function copy($destination): void
    {
        if (is_file($this->path)) {
            copy($this->path, $destination);
        } else if (is_dir($this->path)) {
            // Copy dir needs special handling, not covered in this example.
        }
    }

    /**
     * Moves a file or directory to a new location.
     *
     * @param string $destination The new location where the file or directory should be moved to.
     *
     * @return void
     */
    public function move($destination): void
    {
        rename($this->path, $destination);
    }

    /**
     * Updates the access and modification time of a file or creates a new empty file if it doesn't exist.
     *
     * @param int|null $time (optional) The access and modification time to set. Default is the current time.
     * @param int|null $atime (optional) The access time to set. Default is the value of $time.
     *
     * @return void
     */
    public function touch($time = null, $atime = null): void
    {
        if (!file_exists($this->path)) {
            touch($this->path, $time, $atime);
        }
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
     * @return bool|int The size of the file in bytes. Returns false if the file does not exist or on failure.
     */
    public function size(): bool|int
    {
        return filesize($this->path);
    }

    /**
     * Retrieves the parent directory of a file or directory path.
     *
     * @return string The parent directory of the specified path.
     */
    public function parent(): string
    {
        return dirname($this->path);
    }

    /**
     * Retrieves the contents of a file.
     *
     * @return bool|string The contents of the file as a string. Returns false if the file does not exist or on failure.
     */
    public function getContents(): bool|string
    {
        return file_get_contents($this->path);
    }

    /**
     * Writes contents to a file.
     *
     * @param mixed $contents The contents to be written to the file.
     * @return void
     */
    public function putContents($contents): void
    {
        file_put_contents($this->path, $contents);
    }

    /**
     * Appends contents to a file.
     *
     * @param string $contents The contents to append to the file.
     *
     * @return void
     */
    public function appendContents($contents): void
    {
        file_put_contents($this->path, $contents, FILE_APPEND);
    }

    /**
     * Retrieves the permissions of a file or directory.
     *
     * @return string The permissions of the file or directory in octal notation. Returns an empty string if the file or directory does not exist.
     */
    public function getPermissions(): string
    {
        return substr(sprintf('%o', fileperms($this->path)), -4);
    }

    /**
     * Changes the permissions of a file or directory.
     *
     * @param int $permissions The new permissions to set. The value should be an octal number.
     * @return bool Returns true on success, false on failure.
     */
    public function changePermissions($permissions): bool
    {
        return chmod($this->path, $permissions);
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

    public static function glob(string $pattern)
    {
        foreach (glob($pattern) as $filename) {
            yield new static($filename);
        }
    }

    public function rmdir()
    {
        if (!is_dir($this->path)) {
            throw new \RuntimeException("{$this->path} is not a directory");
        }

        $it = new RecursiveDirectoryIterator($this->path, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it,
            RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($this->path);
    }

    public function open(string $mode = 'r')
    {
        if (!$this->isFile()) {
            throw new \RuntimeException("{$this->path} is not a file");
        }

        $handle = fopen($this->path, $mode);
        if ($handle === false) {
            throw new \RuntimeException("Failed opening file {$this->path}");
        }

        return $handle;
    }

    /**
     * Returns this path as a URI.
     *
     * @return string
     */
    public function as_uri(): string
    {
        throw new \Exception("Method not implemented");
    }

    /**
     * Returns the group that owns the file.
     *
     * @return string
     */
    public function group(): string
    {
        throw new \Exception("Method not implemented");
    }

    /**
     * Check whether this path is absolute.
     *
     * @return bool
     */
    public function is_absolute(): bool
    {
        return substr($this->path, 0, 1) === '/';
    }

    /**
     * (Not supported in PHP). In Python, this would convert the path to POSIX style, but in PHP there's no equivalent.
     * Therefore throwing an exception.
     *
     * @throws \RuntimeException
     */
    public function as_posix(): void
    {
        throw new \RuntimeException("Method 'as_posix' not supported in PHP");
    }

    /**
     * Changes permissions of the file.
     *
     * @param int $mode The new permissions (octal).
     * @return bool
     */
    public function chmod(int $mode): bool
    {
        return chmod($this->path, $mode);
    }

    /**
     * Changes ownership of the file.
     *
     * @param string $user The new owner username.
     * @param string $group The new owner group name.
     * @return bool
     */
    public function chown(string $user, string $group): bool
    {
        return chown($this->path, $user) && chgrp($this->path, $group);
    }

    /**
     * Checks if file is a block special file.
     *
     * @return bool
     */
    public function is_block_device(): bool
    {
        return function_exists('posix_isatty') && is_file($this->path) && posix_isatty($this->path);
    }

    /**
     * Checks if file is a character special file.
     *
     * @return bool
     */
    public function is_char_device(): bool
    {
        return function_exists('filetype') && filetype($this->path) === 'char';
    }

    /**
     * Checks if file is a Named Pipe (FIFO) special file.
     *
     * @return bool
     */
    public function is_fifo(): bool
    {
        return function_exists('filetype') && filetype($this->path) === 'fifo';
    }

    /**
     * Checks if file is a socket.
     *
     * @return bool
     */
    public function is_socket(): bool
    {
        return function_exists('filetype') && 'socket' === filetype($this->path);
    }

    /**
     * Checks if the file is a symbolic link.
     *
     * @return bool
     */
    public function is_symlink(): bool
    {
        return is_link($this->path);
    }

    /**
     * Iterate over the files in this directory.
     *
     * @return \Generator
     * @throws \RuntimeException if the path is not a directory.
     */
    public function iterdir()
    {
        if (!$this->isDir()) {
            throw new \RuntimeException("{$this->path} is not a directory");
        }

        foreach (new \DirectoryIterator($this->path) as $fileInfo) {
            if ($fileInfo->isDot()) continue;
            yield $fileInfo->getFilename();
        }
    }



    /**
     * Change the mode of path to the numeric mode.
     * This method does not follow symbolic links.
     *
     * @param int $mode
     * @return bool
     */
    public function lchmod(int $mode): bool
    {
        if (!function_exists('lchmod')) {
            return false;
        }
        return lchmod($this->path, $mode);
    }

    /**
     * Change the owner and group id of path to the numeric uid and gid.
     * This method does not follow symbolic links.
     *
     * @param int $uid User id
     * @param int $gid Group id
     * @return bool
     */
    public function lchown(int $uid, int $gid): bool
    {
        if (!function_exists('lchown')) {
            return false;
        }
        return lchown($this->path, $uid) && lchgrp($this->path, $gid);
    }

    /**
     * Create a hard link pointing to a path.
     *
     * @param string $target
     * @return bool
     */
    public function link_to(string $target): bool
    {
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
    public function lstat()
    {
        return lstat($this->path);
    }

    /**
     * Returns the individual parts of this path.
     *
     * @return array
     */
    public function parts(): array
    {
        $separator = DIRECTORY_SEPARATOR;
        return explode($separator, $this->path);
    }

    /**
     * Opens the file in bytes mode, reads it, and closes the file.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function read_bytes(): string
    {
        $bytes = file_get_contents($this->path, FILE_BINARY);
        if ($bytes === false) {
            throw new \RuntimeException("Error reading file {$this->path}");
        }

        return $bytes;
    }

    /**
     * Open the file in text mode, read it, and close the file
     *
     * @return string
     * @throws \RuntimeException
     */
    public function read_text(): string
    {
        $text = file_get_contents($this->path);
        if ($text === false) {
            throw new \RuntimeException("Error reading file {$this->path}");
        }

        return $text;
    }

    /**
     * Compute a version of this path that is relative to another path.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function relative_to(string $other_path): string
    {
        $path = $this->absolute();
        $other = realpath($other_path);
        if ($other === false) {
            throw new \RuntimeException("$other_path does not exist or unable to get a real path");
        }

        $path_parts = explode(DIRECTORY_SEPARATOR, $path);
        $other_parts = explode(DIRECTORY_SEPARATOR, $other);

        while (count($path_parts) && count($other_parts) && ($path_parts[0] == $other_parts[0])) {
            array_shift($path_parts);
            array_shift($other_parts);
        }

        return str_repeat('..' . DIRECTORY_SEPARATOR, count($other_parts)) . implode(DIRECTORY_SEPARATOR, $path_parts);
    }

    /**
     * Renames this file or directory to the given target.
     *
     * @param string $target
     * @return bool
     * @throws \RuntimeException
     */
    public function rename(string $target): bool
    {
        // Check if file or directory exists
        if (!$this->isFile() && !$this->isDir()) {
            throw new \RuntimeException("{$this->path} does not exist");
        }

        return rename($this->path, $target);
    }
}