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
    protected BuiltinProxy $builtin;

    /**
     * Joins two or more parts of a path together, inserting '/' as needed.
     * If any component is an absolute path, all previous path components
     * will be discarded. An empty last part will result in a path that
     * ends with a separator.
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

    public function __construct(string|self $path)
    {
        $this->builtin = new BuiltinProxy();
        
        $this->path = (string)$path;
        $this->handle = null;
        return $this;
    }

    public function __toString(): string {
        return $this->path;
    }

    /**
     * Casts the input into an instance of the current class.
     *
     * @param string|self $path The input path to be cast.
     * @return self An instance of the current class.
     */
    protected function cast(string|self $path): self
    {
        return new self($path);
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
        return $this->cast($path)->path() === $this->path();
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
     * @return self
     * @throws IOException
     */
    public function absPath(): self
    {
        $absPath = $this->builtin->realpath($this->path);
        if ($absPath === false) {
            throw new IOException("Error while getting abspath of `" . $this->path . "`");
        }
        return $this->cast($absPath);
    }

    /**
     * > Alias for absPath()
     * @throws IOException
     */
    public function realpath(): self
    {
        return $this->absPath();
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
     */
    function access(int $mode): bool
    {
        return match ($mode) {
            self::F_OK => $this->builtin->file_exists($this->path),
            self::R_OK => $this->builtin->is_readable($this->path),
            self::W_OK => $this->builtin->is_writable($this->path),
            self::X_OK => $this->builtin->is_executable($this->path),
            default => throw new RuntimeException('Invalid mode'),
        };
    }

    /**
     * Retrieves the last access time of a file or directory.
     *
     * @return int The last access time of the file or directory as a timestamp.
     * @throws IOException
     * @throws FileNotFoundException
     */
    function atime(): int
    {
        if (!$this->exists()) {
            throw new FileNotFoundException('File does not exists : ' . $this->path);
        }
        $time = $this->builtin->fileatime($this->path);
        if ($time === false) {
            throw new IOException('Could not get the last access time of ' . $this->path);
        }
        return $time;
    }

    /**
     * Retrieves the creation time of a file or directory.
     *
     * @return int The creation time of the file or directory as a timestamp.
     * @throws FileNotFoundException
     * @throws IOException
     */
    function ctime(): int
    {
        if (!$this->exists()) {
            throw new FileNotFoundException('File does not exists : ' . $this->path);
        }
        $time = $this->builtin->filectime($this->path);
        if ($time === false) {
            throw new IOException('Could not get the creation time of ' . $this->path);
        }
        return $time;
    }

    /**
     * Retrieves the last modified time of a file or directory.
     *
     * @return int The last modified time of the file or directory as a timestamp.
     * @throws FileNotFoundException
     * @throws IOException
     */
    function mtime(): int
    {
        if (!$this->exists()) {
            throw new FileNotFoundException('File does not exists : ' . $this->path);
        }
        $time = $this->builtin->filemtime($this->path);
        if ($time === false) {
            throw new IOException('Could not get the creation time of ' . $this->path);
        }
        return $time;
    }

    /**
     * Check if the path refers to a regular file.
     *
     * @return bool Returns true if the path refers to a regular file, otherwise returns false.
     */
    public function isFile(): bool
    {
        return $this->builtin->is_file($this->path);
    }

    /**
     * Check if the given path is a directory.
     *
     * @return bool Returns true if the path is a directory, false otherwise.
     */
    public function isDir(): bool
    {
        return $this->builtin->is_dir($this->path);
    }

    /**
     * Get the extension of the given path.
     *
     * @return string Returns the extension of the path as a string if it exists, or an empty string otherwise.
     */
    public function ext(): string
    {
        return $this->builtin->pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /**
     * Get the base name of the path.
     *
     * Ex: Path('path/to/file.ext').basename() => 'file.ext'
     *
     * @return string The base name of the path.
     */
    public function basename(): string
    {
        return $this->builtin->pathinfo($this->path, PATHINFO_BASENAME);
    }

    /**
     * Changes the current working directory to this path.
     *
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function cd(): void
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Dir does not exist : " . $this->path);
        }
        $result = $this->builtin->chdir($this->path);
        if (!$result) {
            throw new IOException('Error while changing working directory to ' . $this->path);
        }
    }

    /**
     * > Alias for Path->cd($path)
     *
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function chdir(): void
    {
        $this->cd();
    }

    /**
     * Get the name of the file or path.
     *
     * Ex: Path('path/to/file.ext').name() => 'file'
     *
     * @return string Returns the name of the file without its extension.
     */
    public function name(): string
    {
        return $this->builtin->pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * Converts the path to the normalized form.
     *
     * @return self The instance of the current object.
     */
    public function normCase(): self
    {
        return $this->cast(
            strtolower(
                str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->path)
            )
        );
    }

    /**
     * Normalizes the path of the file or directory.
     *
     * > Thanks to https://stackoverflow.com/users/216254/troex
     * @return self A new instance of the class with the normalized path.
     */
    //TODO: review 2
    public function normPath(): self
    {
        if (empty($path)) {
            return $this->cast('.');
        }

        $initialSlashes = str_starts_with($path, '//')
            ? 2
            : (str_starts_with($path, '/') ? 1 : 0);

        $parts = explode('/', $path);
        $newParts = [];

        foreach ($parts as $part)
        {
            if (!$part || $part === '.') {
                continue;
            }

            if (
                ($part != '..') ||
                (!$initialSlashes && !$newParts) ||
                ($newParts && (end($newParts) == '..'))
            ) {
                $newParts[] = $part;
            }
            elseif ($newParts) {
                array_pop($new_comps);
            }
        }

        $path = implode('/', $newParts);
        if ($initialSlashes)
            $path = str_repeat('/', $initialSlashes) . $path;

        return $this->cast($path);
    }

    /**
     * Creates a new directory.
     *
     * @param int $mode The permissions for the new directory. Default is 0777.
     * @param bool $recursive Indicates whether to create parent directories if they do not exist. Default is false.
     *
     * @return void
     * @throws FileExistsException
     * @throws IOException
     */
    public function mkdir(int $mode = 0777, bool $recursive = false): void
    {
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

        $result = $this->builtin->mkdir($this->path, $mode, $recursive);

        if (!$result) {
            throw new IOException("Error why creating the new directory : " . $this->path);
        }
    }

    /**
     * Deletes a file or a directory.
     *
     * @return void
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function delete(): void
    {
        if ($this->isFile()) {
            $result = $this->builtin->unlink($this->path);

            if (!$result) {
                throw new IOException("Error why deleting file : " . $this->path);
            }
        } else if ($this->isDir()) {
            $result = $this->builtin->rmdir($this->path);

            if (!$result) {
                throw new IOException("Error why deleting directory : " . $this->path);
            }
        } else {
            throw new FileNotFoundException("File or directory does not exist : " . $this);
        }
    }

    /**
     * Copy data and mode bits (“cp src dst”). Return the file’s destination.
     * The destination may be a directory.
     * If follow_symlinks is false, symlinks won’t be followed. This resembles GNU’s “cp -P src dst”.
     * TODO: implements the follow_symlinks functionality
     *
     * @param string|self $destination The destination path or object to copy the file to.
     * @throws FileNotFoundException If the source file does not exist or is not a file.
     * @throws FileExistsException
     * @throws IOException
     */
    //TODO: review2
    public function copy(string|self $destination, bool $follow_symlinks = false): self
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File does not exist or is not a file : " . $this);
        }

        $destination = (string)$destination; // TODO: add an absPath method to the dest?
        if ($this->builtin->is_dir($destination)) {
            $destination = self::join($destination, $this->basename());
        }

        if ($this->builtin->file_exists($destination)) {
            throw new FileExistsException("File already exists : " . $destination);
        }

        $success = $this->builtin->copy($this->path, $destination);
        if (!$success) {
            throw new IOException("Error copying file {$this->path} to {$destination}");
        }

        return $this->cast($destination);
    }

    /**
     * Copies the content of a file or directory to the specified destination.
     *
     * @param string|self $destination The destination path or directory to copy the content to.
     * @param bool $follow_symlinks (Optional) Whether to follow symbolic links.
     * @return self The object on which the method is called.
     * @throws FileExistsException If the destination path or directory already exists.
     * @throws FileNotFoundException If the source file or directory does not exist.
     * @throws IOException
     */
    //TODO: review2
    public function copyTree(string|self $destination, bool $follow_symlinks = false): self
    {
        // TODO: voir à faire la synthèse de copytree et https://path.readthedocs.io/en/latest/api.html#path.Path.merge_tree
        if ($this->isFile()) {
            $destination = (string)$destination;
            if ($this->builtin->is_dir($destination)) {
                $destination = self::join($destination, $this->basename());
            }

            if ($this->builtin->file_exists($destination)) {
                throw new FileExistsException("File or dir already exists : " . $destination);
            }

            $success = $this->builtin->copy($this->path, $destination);
            if (!$success) {
                throw new IOException("Error copying file {$this->path} to {$destination}");
            }
        } else if ($this->isDir()) {
            self::copyTree($this, $destination);
        } else {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }

        return new self($destination);
    }

    /**
     * Moves a file or directory to a new location.
     * Returns the path of the newly created file or directory.
     *
     * @param string|Path $destination The new location where the file or directory should be moved to.
     *
     * @return Path
     * @throws FileExistsException
     * @throws IOException
     */
    //TODO: review2
    public function move(string|self $destination): self
    {
        // TODO: comparer à https://path.readthedocs.io/en/latest/api.html#path.Path.move
        $destination = (string)$destination;

        if ($this->builtin->is_dir($destination)) {
            $destination = self::join($destination, $this->basename());
        }

        if ($this->builtin->file_exists($destination)) {
            throw new FileExistsException("File or dir already exists : " . $destination);
        }

        $success = $this->builtin->rename($this->path, $destination);

        if (!$success) {
            throw new IOException("Error while moving " . $this->path . " to " . $destination);
        }

        return $this->cast($destination);
    }

    /**
     * Updates the access and modification time of a file or creates a new empty file if it doesn't exist.
     *
     * @param int|\DateTime|null $time (optional) The access and modification time to set. Default is the current time.
     * @param int|\DateTime|null $atime (optional) The access time to set. Default is the value of $time.
     *
     * @return void
     * @throws IOException
     */
    public function touch(int|\DateTime $time = null, int|\DateTime $atime = null): void
    {
        if ($time instanceof \DateTime) {
            $time = $time->getTimestamp();
        }
        if ($atime instanceof \DateTime) {
            $atime = $atime->getTimestamp();
        }

        $success = $this->builtin->touch($this->path, $time, $atime);

        if (!$success) {
            throw new IOException("Error while touching " . $this->path);
        }
    }

    /**
     * Calculates the size of a file.
     *
     * @return int The size of the file in bytes.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function size(): int
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File does not exist : " . $this->path);
        }

        $result = $this->builtin->filesize($this->path);

        if ($result === false) {
            throw new IOException("Error while getting the size of " . $this->path);
        }

        return $result;
    }

    /**
     * Retrieves the parent directory of a file or directory path.
     *
     * @return self The parent directory of the specified path.
     */
    public function parent(int $levels = 1): self
    {
        return $this->cast(
            $this->builtin->dirname($this->path ?? ".", $levels)
        );
    }

    /**
     * Alias for Path->parent() method
     *
     * @param int $levels
     * @return self
     */
    public function dirname(int $levels = 1): self
    {
        return $this->parent($levels);
    }

    /**
     * Retrieves an array of this directory’s subdirectories.
     *
     * The elements of the list are Path objects.
     * This does not walk recursively into subdirectories (but see walkdirs() //TODO: implement).
     *
     * @return array
     * @throws FileNotFoundException
     */
    public function dirs(): array
    {
        if (!$this->builtin->is_dir($this->path)) {
            throw new FileNotFoundException("Directory does not exist: " . $this->path);
        }

        $dirs = [];

        foreach ($this->builtin->scandir($this->path) as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            if ($this->builtin->is_dir(self::join($this->path, $filename))) {
                $dirs[] = $filename;
            }
        }

        return $dirs;
    }

    /**
     * Retrieves an array of files present in the directory.
     *
     * @return array An array of files present in the directory.
     * @throws FileNotFoundException If the directory specified in the path does not exist.
     */
    public function files(): array
    {
        if (!$this->builtin->is_dir($this->path)) {
            throw new FileNotFoundException("Directory does not exist: " . $this->path);
        }

        $files = [];

        foreach ($this->builtin->scandir($this->path) as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            if ($this->builtin->is_file(self::join($this->path, $filename))) {
                $files[] = $filename;
            }
        }

        return $files;
    }

    /**
     * Performs a pattern matching using the `fnmatch()` function.
     *
     * @param string $pattern The pattern to match against.
     * @return bool True if the path matches the pattern, false otherwise.
     */
    public function fnmatch(string $pattern): bool
    {
        return $this->builtin->fnmatch($pattern, $this->path);
    }

    /**
     * Retrieves the content of a file.
     *
     * @return string The content of the file as a string.
     * @throws FileNotFoundException|IOException
     */
    public function getContent(): string
    {
        if (!$this->builtin->is_file($this->path)) {
            throw new FileNotFoundException("File does not exist : " . $this->path);
        }

        $text = $this->builtin->file_get_contents($this->path);

        if ($text === false) {
            throw new IOException("An error occurred while reading file {$this->path}");
        }
        return $text;
    }

    /**
     * Writes contents to a file.
     *
     * @param string $content The contents to be written to the file.
     * @param bool $append
     * @return int The number of bytes that were written to the file
     * @throws FileNotFoundException
     * @throws IOException
     */
    //TODO: review2
    public function putContent(string $content, bool $append = false): int
    {
        if (!$this->builtin->is_file($this->path)) {
            throw new FileNotFoundException("File does not exist : " . $this->path);
        }

        // TODO: review use-cases
        // TODO: complete the input types
        // TODO: add a condition on the creation of the file if not existing
        $result = $this->builtin->file_put_contents(
            $this->path,
            $content,
            $append ? FILE_APPEND : 0
        );

        if ($result === False) {
            throw new IOException("Error while putting content into $this->path");
        }

        return $result;
    }

    /**
     * Writes an array of lines to a file.
     *
     * @param array<string> $lines An array of lines to be written to the file.
     * @return int The number of bytes written to the file.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function putLines(array $lines): int
    {
        return $this->putContent(
            implode(PHP_EOL, $lines)
        );
    }

    /**
     * Retrieves the permissions of a file or directory.
     *
     * @return int The permissions of the file or directory in octal notation.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function getPermissions(): int
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }

        $perms = $this->builtin->fileperms($this->path);

        if ($perms === false) {
            throw new IOException("Error while getting permissions on " . $this->path);
        }

        return (int)substr(sprintf('%o', $perms), -4);
    }

    // TODO: add some more user-friendly methods to get permissions (read, write, exec...)

    /**
     * Changes the permissions of a file or directory.
     *
     * @param int $permissions The new permissions to set. The value should be an octal number.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function setPermissions(int $permissions): void
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }
        $this->builtin->clearstatcache(); // TODO: check for a better way of dealing with PHP cache

        $success = $this->builtin->chmod($this->path, $permissions);

        if ($success === false) {
            throw new IOException("Error while setting permissions on " . $this->path);
        }
    }

    /**
     * Changes ownership of the file.
     *
     * @param string $user The new owner username.
     * @param string $group The new owner group name.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function setOwner(string $user, string $group): void
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }

        $this->builtin->clearstatcache(); // TODO: check for a better way of dealing with PHP cache

        $success =
            $this->builtin->chown($this->path, $user) &&
            $this->builtin->chgrp($this->path, $group);

        if ($success === false) {
            throw new IOException("Error while setting owner of " . $this->path);
        }
    }

    public function setATime()
    {
        // TODO: implement
    }
    public function setCTime()
    {
        // TODO: implement
    }
    public function setMTime()
    {
        // TODO: implement
    }
    public function setUTime()
    {
        // TODO: implement
    }

    /**
     * Checks if a file exists.
     *
     * @return bool Returns true if the file exists, false otherwise.
     */
    public function exists(): bool
    {
        return $this->builtin->file_exists($this->path);
    }

    public function samefile()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.samefile
    }

    public function expand()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.expand
    }

    public function expand_user()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.expanduser
    }

    public function expand_vars()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.expandvars
    }

    /**
     * Retrieves a list of files and directories that match a specified pattern.
     *
     * @param string $pattern The pattern to search for.
     * @return array A list of files and directories that match the pattern.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function glob(string $pattern): array
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Dir does not exist : " . $this->path);
        }

        $pattern = self::join($this->path, $pattern);

        $result = $this->builtin->glob($pattern);

        if ($result === false) {
            throw new IOException("Error while getting glob on " . $this->path);
        }

        return array_map(
            function (string $s) { return new static(self::join($this->path, $s)); },
            $result
        );
    }

    public function remove()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.remove
    }
    public function remove_p()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.remove_p
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * @return bool True if the directory was successfully removed, false otherwise.
     */
    //TODO: review2
    protected function rrmdir(): bool
    {
        if (!is_dir($this->path)) {
            return false;
        }

        foreach (scandir($this->path) as $object) {
            if ($object == "." || $object == "..") {
                continue;
            }

            if (is_dir($this->path. DIRECTORY_SEPARATOR .$object) && !is_link($this->path ."/".$object)) {
                $this->rrmdir();
            }
            else {
                unlink($this->path . DIRECTORY_SEPARATOR . $object);
            }
        }
        return rmdir($this->path);
    }

    /**
     * Removes a directory and its contents.
     *
     * @throws FileNotFoundException
     * @throws IOException
     */
    //TODO: review2
    public function rmdir(bool $recursive = false): void
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("{$this->path} is not a directory");
        }

        // TODO: maybe we could only rely on the recursive method?
        $result = $recursive ? $this->rrmdir() : $this->builtin->rmdir($this->path);

        if ($result === false) {
            throw new IOException("Error while removing directory : " . $this->path);
        }
    }

    public function rename()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.rename
    }

    public function renames()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.renames
    }

    public function read_hash()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.read_hash
    }

    public function read_hexhash()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.read_hexhash
    }

    public function read_md5()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.read_md5
    }

    public function read_text()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.read_text
    }

    public function readlink()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.readlink
    }

    public function readlinkabs()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.readlinkabs
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
            throw new FileNotFoundException($this->path . " is not a file");
        }

        $handle = $this->builtin->fopen($this->path, $mode);
        if ($handle === false) {
            throw new IOException("Failed opening file " . $this->path);
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
    public function with(callable $callback, string $mode = 'r'): mixed
    {
        $handle = $this->open($mode);
        try {
            return $callback($handle);
        } finally {
            $closed = $this->builtin->fclose($handle);
            if (!$closed) {
                throw new IOException("Could not close the file stream : " . $this->path);
            }
        }
    }

    /**
     * Retrieves chunks of data from the file.
     *
     * @param int $chunk_size The size of each chunk in bytes. Defaults to 8192.
     * @return Generator Returns a generator that yields each chunk of data read from the file.
     * @throws FileNotFoundException
     * @throws IOException
     * @throws Throwable
     */
    public function chunks(int $chunk_size = 8192): Generator
    {
        $handle = $this->open('rb');
        try {
            while (!$this->builtin->feof($handle)) {
                yield $this->builtin->fread($handle, $chunk_size);
            }
        } finally {
            $closed = $this->builtin->fclose($handle);
            if (!$closed) {
                throw new IOException("Could not close the file stream : " . $this->path);
            }
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
     * @throws FileNotFoundException|IOException
     */
    public function chmod(int $mode): void
    {
        $this->setPermissions($mode);
    }

    /**
     * > Alias for Path->setOwner() method
     * Changes ownership of the file.
     *
     * @param string $user The new owner username.
     * @param string $group The new owner group name.
     * @throws FileNotFoundException|IOException
     */
    public function chown(string $user, string $group): void
    {
        $this->setOwner($user, $group);
    }

    /**
     * Changes the root directory of the current process to the specified directory.
     *
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function chroot(): void
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Dir does not exist : " . $this->path);
        }

        $success = $this->builtin->chroot($this->path);
        if (!$success) {
            throw new IOException("Error changing root directory to " . $this->path);
        }
    }

    /**
     * Checks if the file is a symbolic link.
     *
     * @return bool
     */
    public function isLink(): bool
    {
        return $this->builtin->is_link($this->path);
    }

    public function isMount()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.ismount
    }

    /**
     * TODO: review2
     * @return \DirectoryIterator
     */
    protected function getDirectoryIterator(): \DirectoryIterator
    {
        // TODO: make it public?
         return new \DirectoryIterator($this->path);
    }

    /**
     * Iterate over the files in this directory.
     *
     * @return Generator
     * @throws FileNotFoundException If the path is not a directory.
     */
    //TODO: review2
    public function iterDir(): Generator
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException($this->path . " is not a directory");
        }

        foreach ($this->getDirectoryIterator() as $fileInfo) {
            // TODO: use the DirectoryIterator everywhere else?
            if ($fileInfo->isDot()) {
                continue;
            }
            yield $fileInfo->getFilename();
        }
    }

    public function lines()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.lines
    }

    /**
     * Create a hard link pointing to this path.
     *
     * @param string|Path $target
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function link(string|self $target): void
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }

        $target = $this->cast($target);

        if ($target->exists()) {
            throw new FileExistsException($target . " already exist");
        }

        $success = $this->builtin->link($this->path, (string)$target);

        if ($success === false) {
            throw new IOException("Error while creating the link from " . $this->path . " to " . $target);
        }
    }

    /**
     * Like stat(), but do not follow symbolic links.
     *
     * @return array
     * @throws IOException
     */
    public function lstat(): array
    {
        $result = $this->builtin->lstat($this->path);
        if ($result === false) {
            throw new IOException("Error while getting lstat of " . $this->path);
        }
        return $result;
    }

    public function splitDrive()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.splitdrive
    }

    public function stat() {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.stat
    }

    public function symlink()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.symlink
    }

    public function unlink()
    {
        // TODO: implement https://path.readthedocs.io/en/latest/api.html#path.Path.unlink
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
     * @throws IOException
     */
    //TODO: review2
    public function getRelativePath(string|self $basePath): string
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("{$this->path} is not a file or directory");
        }

        $path = (string)$this->absPath();
        $basePath = (string)$basePath;

        $realBasePath = $this->builtin->realpath($basePath);
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