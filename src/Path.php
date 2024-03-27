<?php

namespace Path;

use Generator;
use Path\Exception\FileExistsException;
use Path\Exception\FileNotFoundException;
use Path\Exception\IOException;
use RuntimeException;
use Throwable;

/**
 * An object representing a file or directory.
 *
 * Represents a filesystem path.
 * Most of the methods rely on the php builtin methods, see each method's documentation for more.
 *
 * @see https://github.com/olinox14/path-php#path-php
 *
 * @package olinox14/path
 */
class Path
{
    /**
     * File exists
     */
    public const F_OK = 0;
    /**
     * Has read permission on the file
     */
    public const R_OK = 4;
    /**
     * Has write permission on the file
     */
    public const W_OK = 2;
    /**
     * Has execute permission on the file
     */
    public const X_OK = 1;

    protected string $path;

    protected mixed $handle;
    protected BuiltinProxy $builtin;

    /**
     * Joins two or more parts of a path together.
     *
     * Joins two or more parts of a path together, inserting '/' as needed.
     * If any component is an absolute path, all previous path components
     * will be discarded. An empty last part will result in a path that
     * ends with a separator.
     *
     * @param string|Path $path The base path
     * @param string ...$parts The parts of the path to be joined.
     * @return self The resulting path after joining the parts using the directory separator.
     */
    public static function join(string|self $path, string|self ...$parts): self
    {
        $path = (string)$path;
        $parts = array_map(fn ($p) => (string)$p, $parts);

        foreach ($parts as $part) {
            if (str_starts_with($part, DIRECTORY_SEPARATOR)) {
                $path = $part;
            } elseif (!$path || str_ends_with($path, DIRECTORY_SEPARATOR)) {
                $path .= $part;
            } else {
                $path .= DIRECTORY_SEPARATOR . $part;
            }
        }
        return new self($path);
    }

    public function __construct(string|self $path)
    {
        $this->builtin = new BuiltinProxy();

        $this->path = (string)$path;
        $this->handle = null;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->path;
    }

    /**
     * Casts the input into a Path instance.
     *
     * @param string|self $path The input path to be cast.
     * @return self An instance of the current class.
     */
    protected function cast(string|self $path): self
    {
        return new self($path);
    }

    /**
     * Retrieves the current path of the file or directory as a string.
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
     * @return bool Returns true if the given path is equal to the current path, false otherwise.
     */
    public function eq(string|self $path): bool
    {
        return $this->cast($path)->path() === $this->path();
    }

    /**
     * Appends parts to the current path.
     *
     * @param string ...$parts The parts to be appended to the current path.
     * @return self Returns an instance of the class with the appended path.
     * @see Path::join()
     *
     */
    public function append(string|self ...$parts): self
    {
        return $this->cast(self::join($this->path, ...$parts));
    }

    /**
     * Returns an absolute version of the current path.
     *
     * @see https://www.php.net/manual/fr/function.realpath.php
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
     *
     * @throws IOException
     */
    public function realpath(): self
    {
        return $this->absPath();
    }

    /**
     * Checks the access rights for a given file or directory.
     *
     * > Inspired from the python `os.access` method
     *
     * @see https://www.php.net/manual/fr/function.file-exists.php
     * @see https://www.php.net/manual/fr/function.is-readable.php
     * @see https://www.php.net/manual/fr/function.is-writable.php
     * @see https://www.php.net/manual/fr/function.is-executable.php
     *
     * @param int $mode The access mode to check. Permitted values:
     *        - F_OK: checks for the existence of the file or directory.
     *        - R_OK: checks for read permission.
     *        - W_OK: checks for write permission.
     *        - X_OK: checks for execute permission.
     * @return bool Returns true if the permission check is successful; otherwise, returns false.
     */
    public function access(int $mode): bool
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
     * @see https://www.php.net/manual/fr/function.fileatime.php
     *
     * @return int The last access time of the file or directory as a timestamp.
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function atime(): int
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
     * @see https://www.php.net/manual/fr/function.filectime.php
     *
     * @return int The creation time of the file or directory as a timestamp.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function ctime(): int
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
     * @see https://www.php.net/manual/fr/function.filemtime.php
     *
     * @return int The last modified time of the file or directory as a timestamp.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function mtime(): int
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
     * @see https://www.php.net/manual/fr/function.is-file.php
     *
     * @return bool Returns true if the path refers to a regular file, false otherwise.
     */
    public function isFile(): bool
    {
        return $this->builtin->is_file($this->path);
    }

    /**
     * Check if the given path is a directory.
     *
     * @see https://www.php.net/manual/fr/function.is-dir.php
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
     * @see https://www.php.net/manual/fr/function.pathinfo.php
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
     * @see https://www.php.net/manual/fr/function.pathinfo.php
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
     * @see https://www.php.net/manual/fr/function.chdir.php
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
     * @see https://www.php.net/manual/fr/function.pathinfo.php
     *
     * @return string Returns the name of the file without its extension.
     */
    public function name(): string
    {
        return $this->builtin->pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * Normalize the case of a pathname.
     *
     * On Windows, convert all characters in the pathname to lowercase, and also convert
     * forward slashes to backward slashes. On other operating systems,
     * return the path unchanged.
     *
     * @return self The instance of the current object.
     */
    public function normCase(): self
    {
        return $this->cast(
            strtolower(
                str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->path())
            )
        );
    }

    /**
     * Normalizes the path of the file or directory.
     *
     * Normalize a pathname by collapsing redundant separators and up-level references so that A//B, A/B/, A/./B
     * and A/foo/../B all become A/B. This string manipulation may change the meaning of a path that contains
     * symbolic links. On Windows, it converts forward slashes to backward slashes. To normalize case, use normcase().
     *
     * > Thanks to https://stackoverflow.com/users/216254/troex
     * @return self A new instance of the class with the normalized path.
     */
    public function normPath(): self
    {
        $path = $this->normCase()->path();

        // TODO: handle case where path start with //
        if (empty($path)) {
            return $this->cast('.');
        }

        // Also tests some special cases we can't really do anything with
        if (!str_contains($path, '/') || $path === '/' || '.' === $path || '..' === $path) {
            return $this->cast($path);
        }

        $path = rtrim($path, '/');

        // Extract the scheme if any
        $scheme = null;
        if (strpos($path, '://')) {
            list($scheme, $path) = explode('://', $path, 2);
        }

        $parts = explode('/', $path);
        $newParts = [];

        foreach ($parts as $part) {

            if ($part === '' || $part === '.') {
                if (empty($newParts)) {
                    // First part is empty or '.' : path is absolute, keep an empty first part. Else, discard.
                    $newParts[] = '';
                }
                continue;
            }

            if ($part === '..') {
                if (empty($newParts)) {
                    // Path start with '..', we can't do anything with it so keep it
                    $newParts[] = $part;
                } else {
                    // Remove the last part
                    array_pop($newParts);
                }
                continue;
            }

            $newParts[] = $part;
        }

        // Rebuild path
        $newPath = implode('/', $newParts);

        // Add scheme if any
        if ($scheme !== null) {
            $newPath = $scheme . '://' . $newPath;
        }

        return $this->cast($newPath);
    }

    /**
     * Creates a new directory.
     *
     * @see https://www.php.net/manual/fr/function.mkdir.php
     *
     * @param int $mode The permissions for the new directory. Default is 0777.
     * @param bool $recursive Indicates whether to create parent directories if they do not exist. Default is false.
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
     * Deletes a file or a directory (non-recursively).
     *
     * @see https://www.php.net/manual/fr/function.unlink.php
     * @see https://www.php.net/manual/fr/function.rmdir.php
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
        } elseif ($this->isDir()) {
            $result = $this->builtin->rmdir($this->path);

            if (!$result) {
                throw new IOException("Error why deleting directory : " . $this->path);
            }
        } else {
            throw new FileNotFoundException("File or directory does not exist : " . $this);
        }
    }

    /**
     * Copy a file.
     *
     * Copy data and mode bits (“cp src dst”). The destination may be a directory.
     * Return the file’s destination as a Path.
     * If follow_symlinks is false, symlinks won’t be followed. This resembles GNU’s “cp -P src dst”.
     * @see https://www.php.net/manual/fr/function.copy.php
     * @see https://www.php.net/manual/fr/function.symlink.php
     *
     * @param string|self $destination The destination path or object to copy the file to.
     * @throws FileNotFoundException If the source file does not exist or is not a file.
     * @throws FileExistsException
     * @throws IOException
     */
    public function copy(string|self $destination, bool $follow_symlinks = false): self
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File does not exist or is not a file : " . $this);
        }

        $destination = $this->cast($destination);
        if ($destination->isDir()) {
            $destination = $destination->append($this->basename());
        }

        if ($destination->isFile()) {
            throw new FileExistsException("File already exists : " . $destination->path());
        }

        if (!$follow_symlinks && $this->isLink()) {
            return $this->symlink($destination);
        }

        $success = $this->builtin->copy($this->path, $destination->path());
        if (!$success) {
            throw new IOException("Error copying file {$this->path} to {$destination->path()}");
        }

        return $destination;
    }

    /**
     * Recursively copy a directory tree and return the destination directory.
     *
     * If the $follow_symlinks is true, symbolic links in the source tree result in symbolic links in
     * the destination tree; if it is false, the contents of the files pointed to by symbolic links are copied.
     *
     * @param string|self $destination The destination path or directory to copy the content to.
     * @param bool $follow_symlinks (Optional) Whether to follow symbolic links.
     * @return self The object on which the method is called.
     * @throws FileExistsException If the destination path or directory already exists.
     * @throws FileNotFoundException If the source file or directory does not exist.
     * @throws IOException
     *  TODO: implement an 'ignore' callback property or an 'ignorePattern' property
     *  TODO: implement a 'errorOnExistingDestination' property (default: True)
     */
    public function copyTree(string|self $destination, bool $follow_symlinks = false): self
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }

        $destination = $this->cast($destination);

        if ($destination->isFile()) {
            $destination->remove();
        }

        if ($this->isFile()) {
            return $this->copy($destination, $follow_symlinks);
        }

        if (!$destination->isDir()) {
            $mode = $this->getPermissions();
            $destination->mkdir($mode, true);
        }

        foreach ($this->dirs() as $dir) {
            $newDir = $destination->append(
                $dir->getRelativePath($this->path())
            );

            $dir->copyTree($newDir, $follow_symlinks);
        }

        foreach ($this->files() as $file) {
            $newFile = $destination->append(
                $file->getRelativePath($this->path())
            );

            $newFile->remove_p();
            $file->copy($newFile, $follow_symlinks);
        }

        return $destination;
    }

    /**
     * Recursively move a file or directory to another location.
     *
     * Moves a file or directory to a new location. Existing files or dirs will be overwritten.
     * Returns the path of the newly created file or directory.
     * If the destination is a directory or a symlink to a directory, the source is moved
     * inside the directory. The destination path must not already exist.
     * @see https://www.php.net/manual/fr/function.rename.php
     *
     * @param string|Path $destination The new location where the file or directory should be moved to.
     * @return Path
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function move(string|self $destination): self
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path . " does not exist");
        }

        $destination = $this->cast($destination);

        if ($destination->isDir()) {
            $destination = $destination->append($this->basename());
        }

        $success = $this->builtin->rename($this->path, $destination->path());

        if (!$success) {
            throw new IOException("Error while moving " . $this->path . " to " . $destination->path());
        }

        return $destination;
    }

    /**
     * Updates the access and modification time of a file or creates a new empty file if it doesn't exist.
     *
     * @see https://www.php.net/manual/en/function.touch.php
     *
     * @param int|\DateTime|null $time (optional) The access and modification time to set. Default is the current time.
     * @param int|\DateTime|null $atime (optional) The access time to set. Default is the value of $time.
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
     * Size of the file, in bytes.
     *
     * @see https://www.php.net/manual/fr/function.filesize.php
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
     * @see https://www.php.net/manual/fr/function.dirname.php
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
     * > Alias for Path->parent() method
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
     * This does not walk recursively into subdirectories (but see walkdirs())
     *
     * @return array<self>
     * @throws FileNotFoundException
     */
    public function dirs(): array
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Directory does not exist: " . $this->path);
        }

        $dirs = [];

        foreach ($this->builtin->scandir($this->path) as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $child = $this->append($filename);

            if ($child->isDir()) {
                $dirs[] = $child;
            }
        }

        return $dirs;
    }

    /**
     * Retrieves an array of files present in the directory.
     *
     * @return array<self> An array of files present in the directory.
     * @throws FileNotFoundException If the directory specified in the path does not exist.
     */
    public function files(): array
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Directory does not exist: " . $this->path);
        }

        $files = [];

        foreach ($this->builtin->scandir($this->path) as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $child = $this->append($filename);

            if ($child->isFile()) {
                $files[] = $child;
            }
        }

        return $files;
    }

    /**
     * Performs a pattern matching using the `fnmatch()` function.
     *
     * @see https://www.php.net/manual/fr/function.fnmatch.php
     *
     * @param string $pattern A filename pattern with wildcards.
     * @return bool True if the path matches the pattern, false otherwise.
     */
    public function fnmatch(string $pattern): bool
    {
        return $this->builtin->fnmatch($pattern, $this->path);
    }

    /**
     * Retrieves the content of a file.
     *
     * @see https://www.php.net/manual/fr/function.file-get-contents.php
     *
     * @return string The content of the file as a string.
     * @throws FileNotFoundException|IOException
     */
    public function getContent(): string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException("File does not exist : " . $this->path);
        }

        $text = $this->builtin->file_get_contents($this->path);

        if ($text === false) {
            throw new IOException("An error occurred while reading file {$this->path}");
        }
        return $text;
    }

    /**
     * > Alias for getContent()
     *
     * @return string
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function readText(): string
    {
        return $this->getContent();
    }

    /**
     * Retrieves the content of a file as an array of lines.
     *
     * @return array<string>
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function lines(): array
    {
        return explode(PHP_EOL, $this->getContent());
    }

    /**
     * Writes contents to a file.
     *
     * @see https://www.php.net/manual/fr/function.file-put-contents.php
     *
     * @param string $content The contents to be written to the file.
     * @param bool $append Append the content to the file's content instead of replacing it
     * @param bool $create Creates the file if it does not already exist
     * @return int The number of bytes that were written to the file
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function putContent(string $content, bool $append = false, bool $create = true): int
    {
        if (!$this->isFile() && !$create) {
            throw new FileNotFoundException("File does not exist : " . $this->path);
        }

        $result = $this->builtin->file_put_contents(
            $this->path,
            $content,
            $append ? FILE_APPEND : 0
        );

        if ($result === false) {
            throw new IOException("Error while putting content into $this->path");
        }

        return $result;
    }

    /**
     * Writes an array of lines to a file.
     *
     * @param array<string> $lines An array of lines to be written to the file.
     * @param bool $append Append the content to the file's content instead of replacing it
     * @param bool $create Creates the file if it does not already exist
     * @return int The number of bytes written to the file.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function putLines(array $lines, bool $append = false, bool $create = true): int
    {
        return $this->putContent(
            implode(PHP_EOL, $lines),
            $append,
            $create
        );
    }

    /**
     * Retrieves the permissions of a file or directory.
     *
     * @see https://www.php.net/manual/fr/function.fileperms.php
     *
     * @param bool $asOctal
     * @return int The permissions of the file or directory
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function getPermissions(bool $asOctal = true): int
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }

        $perms = $this->builtin->fileperms($this->path);

        if ($perms === false) {
            throw new IOException("Error while getting permissions on " . $this->path);
        }

        if (!$asOctal) {
            $perms = (int)substr(sprintf('%o', $perms), -4);
        }

        return $perms;
    }

    /**
     * Changes the permissions of a file or directory.
     *
     * @see https://www.php.net/manual/fr/function.chmod.php
     *
     * @param int $permissions The new permissions to set.
     * @param bool $asOctal
     * @param bool $clearStatCache
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function setPermissions(int $permissions, bool $asOctal = false, bool $clearStatCache = false): void
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }

        if (!$asOctal) {
            $permissions = decoct($permissions);
        }

        if ($clearStatCache) {
            $this->builtin->clearstatcache();
        }

        $success = $this->builtin->chmod($this->path, $permissions);

        if ($success === false) {
            throw new IOException("Error while setting permissions on " . $this->path);
        }
    }

    // TODO: implement getOwner()

    /**
     * Changes ownership of the file.
     *
     * @see https://www.php.net/manual/fr/function.chown.php
     * @see https://www.php.net/manual/fr/function.chgrp.php
     *
     * @param string $user The new owner username.
     * @param string $group The new owner group name.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function setOwner(string $user, string $group, bool $clearStatCache = false): void
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }

        if ($clearStatCache) {
            $this->builtin->clearstatcache();
        }

        $success =
            $this->builtin->chown($this->path, $user) &&
            $this->builtin->chgrp($this->path, $group);

        if ($success === false) {
            throw new IOException("Error while setting owner of " . $this->path);
        }
    }

    /**
     * Checks if a file or directory exists.
     *
     * @see https://www.php.net/manual/fr/function.file-exists.php
     *
     * @return bool Returns true if the file exists, false otherwise.
     */
    public function exists(): bool
    {
        return $this->builtin->file_exists($this->path);
    }

    /**
     * Return True if both pathname arguments refer to the same file or directory.
     *
     * // TODO: make explicit that the two files/dirs have to exist
     *
     * @throws IOException
     */
    public function sameFile(string | self $other): bool
    {
        return $this->absPath()->path() === $this->cast($other)->absPath()->path();
    }

    /**
     * Expands the path by performing three operations: expanding user, expanding variables, and normalizing the path.
     *
     * @see Path::expandUser()
     * @see Path::expandVars()
     * @see Path::normPath()
     *
     * @return self The expanded path.
     */
    public function expand(): self
    {
        return $this->expandUser()->expandVars()->normPath();
    }

    /**
     * Expands the user directory in the file path.
     *
     * @return self The modified instance with the expanded user path.
     */
    public function expandUser(): self
    {
        if (!str_starts_with($this->path(), '~/')) {
            return $this;
        }

        $home = $this->cast($_SERVER['HOME']);
        return $home->append(substr($this->path(), 2));
    }

    /**
     * Expands variables in the path.
     *
     * Searches for variable placeholders in the path and replaces them with their
     * corresponding values from the environment variables.
     *
     * @return self The path with expanded variables.
     */
    public function expandVars(): self
    {
        $path = preg_replace_callback(
            '/\$\{([^}]+)}|\$(\w+)/',
            function ($matches) {
                return $this->builtin->getenv($matches[1] ?: $matches[2]);
            },
            $this->path()
        );

        return $this->cast($path);
    }

    /**
     * Retrieves a list of files and directories that match a specified pattern.
     *
     * @see https://www.php.net/manual/fr/function.glob.php
     *
     * @param string $pattern The pattern to search for.
     * @return array<self> A list of files and directories that match the pattern.
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function glob(string $pattern): array
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Dir does not exist : " . $this->path);
        }

        $pattern = $this->append($pattern);

        $result = $this->builtin->glob($pattern->path());

        if ($result === false) {
            throw new IOException("Error while getting glob on " . $this->path);
        }

        return array_map(
            function (string $s) { return $this->append($s); },
            $result
        );
    }

    /**
     * Removes the file.
     *
     * @see https://www.php.net/manual/fr/function.unlink.php
     *
     * @return void
     * @throws IOException if there was an error while removing the file.
     * @throws IOException|FileNotFoundException if the file does not exist or is not a file.
     */
    public function remove(): void
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException($this->path . " is not a file");
        }
        $result = $this->builtin->unlink($this->path);
        if (!$result) {
            throw new IOException("Error while removing the file " . $this->path);
        }
    }

    /**
     * > Alias for Path->remove()
     *
     * @return void
     * @throws IOException|FileNotFoundException
     */
    public function unlink(): void
    {
        $this->remove();
    }

    /**
     * Like remove(), but does not throw an exception if the file does not exist.
     *
     * It will still raise a FileExistsException if the target is an existing directory.
     *
     * @return void
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function remove_p(): void
    {
        if ($this->isDir()) {
            throw new FileExistsException($this->path . " is a directory");
        }
        if (!$this->isFile()) {
            return;
        }
        $this->remove();
    }

    /**
     * Removes a directory, and its contents if recursive.
     *
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function rmdir(bool $recursive = false, bool $permissive = false): void
    {
        if (!$this->isDir()) {
            if ($permissive) {
                // TODO: should we throw an error if this path is a file?
                return;
            }
            throw new FileNotFoundException("{$this->path} is not a directory");
        }

        $subDirs = $this->dirs();
        $files = $this->files();

        if ((!empty($subDirs) || !empty($files)) && !$recursive) {
            throw new IOException("Directory is not empty : " . $this->path);
        }

        foreach ($subDirs as $dir) {
            $dir->rmdir(true, false);
        }

        foreach ($files as $file) {
            $file->remove();
        }

        $result = $this->builtin->rmdir($this->path());

        if ($result === false) {
            throw new IOException("Error while removing directory : " . $this->path);
        }
    }

    /**
     * > Alias for Path->move()
     *
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function rename(string|self $newPath): self
    {
        return $this->move($newPath);
    }

    /**
     * Retrieves the hash of a file or directory using the specified algorithm.
     *
     * @see https://www.php.net/manual/en/function.hash-file.php
     *
     * @param string $algo The hashing algorithm to use. Supported algorithms can be found at the PHP documentation.
     * @param bool $binary (optional) Determines whether the hash should be returned as binary or hexadecimal. Default is false.
     * @return string The computed hash of the file or directory.
     * @throws IOException If there is an error computing the hash.
     */
    public function readHash(string $algo, bool $binary = false): string
    {
        $result = $this->builtin->hash_file($algo, $this->path, $binary);
        if ($result === false) {
            throw new IOException("Error while computing the hash of " . $this->path);
        }
        return $result;
    }

    /**
     * Returns the target of a symbolic link.
     *
     * @see https://www.php.net/manual/en/function.readlink.php
     *
     * @return self The target of the symbolic link as a new instance of the current class.
     * @throws FileNotFoundException If the path does not exist or is not a symbolic link.
     * @throws IOException If there is an error while getting the target of the symbolic link.
     */
    public function readLink(): self
    {
        if (!$this->isLink()) {
            throw new FileNotFoundException($this->path() . " does not exist or is not a symbolic link");
        }
        $result = $this->builtin->readLink($this->path);
        if ($result === false) {
            throw new IOException("Error while getting the target of the symbolic link " . $this->path);
        }
        return $this->cast($result);
    }

    /**
     * Opens a file in the specified mode.
     *
     * @see https://www.php.net/manual/fr/function.fopen.php
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
     * Walks through the directories of a given directory and returns an iterator.
     *
     * This method uses the built-in `RecursiveIteratorIterator` and `RecursiveDirectoryIterator` classes to
     * traverse through all the files and directories within the given directory.
     *
     * @see https://www.php.net/manual/en/class.recursiveiteratoriterator.php
     * @see https://www.php.net/manual/en/class.iterator.php
     *
     * @return \Iterator An iterator that yields each file or directory within the given directory
     * @throws FileNotFoundException If the directory does not exist
     */
    public function walkDirs(): \Iterator
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Directory does not exist: " . $this->path);
        }

        $iterator = $this->builtin->getRecursiveIterator($this->path);

        foreach ($iterator as $file) {
            yield $this->cast($file);
        }
    }

    /**
     * Calls a callback with a file handle opened with the specified mode and closes the
     * handle afterward.
     *
     * @param callable $callback The callback function to be called with the file handle.
     * @param string $mode The mode in which to open the file. Defaults to 'r'.
     * @throws Throwable If an exception is thrown within the callback function.
     */
    public function with(callable $callback, string $mode = 'r'): mixed
    {
        $handle = $this->open($mode);
        try {
            $result = $callback($handle);
        } finally {
            $closed = $this->builtin->fclose($handle);
            if (!$closed) {
                throw new IOException("Could not close the file stream : " . $this->path);
            }
        }
        return $result;
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
     *
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
     *
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

    // TODO: implement chgrp()

    /**
     * Changes the root directory of the current process to the specified directory.
     *
     * @see https://www.php.net/manual/fr/function.chroot.php
     *
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function chroot(): void
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Dir does not exist : " . $this->path);
        }

        // TODO: not working as expected, see why (new working dir is '/' no matter what)
        $success = $this->builtin->chroot($this->absPath()->path());
        if (!$success) {
            throw new IOException("Error changing root directory to " . $this->path);
        }
    }

    /**
     * Checks if the file is a symbolic link.
     *
     * @see https://www.php.net/manual/fr/function.is-link.php
     *
     * @return bool
     */
    public function isLink(): bool
    {
        return $this->builtin->is_link($this->path);
    }

    /**
     * Checks if the path is a mount point.
     *
     * @return bool True if the path is a mount point, false otherwise.
     */
    public function isMount(): bool
    {
        return $this->builtin->disk_free_space($this->path) !== false;
    }

    /**
     * Create a hard link pointing to this path.
     *
     * @see https://www.php.net/manual/fr/function.link.php
     *
     * @param string|Path $newLink
     * @return Path
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function link(string|self $newLink): self
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }

        $newLink = $this->cast($newLink);

        if ($newLink->exists()) {
            throw new FileExistsException($newLink . " already exist");
        }

        $success = $this->builtin->link($this->path, (string)$newLink);

        if ($success === false) {
            throw new IOException("Error while creating the link from " . $this->path . " to " . $newLink);
        }

        return $newLink;
    }

    /**
     * Gives information about a file or symbolic link
     *
     * @see https://www.php.net/manual/fr/function.lstat.php
     *
     * @return array<string, int|float>
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

    /**
     * Creates a symbolic link to the specified destination.
     *
     * @see https://www.php.net/manual/fr/function.symlink.php
     *
     * @param string|self $newLink The path or the instance of the symbolic link to create.
     * @return self The instance of the symbolic link that was created.
     * @throws FileNotFoundException If the file or directory does not exist.
     * @throws FileExistsException If the symbolic link already exists.
     * @throws IOException If there was an error while creating the symbolic link.
     */
    public function symlink(string | self $newLink): self
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }

        $newLink = $this->cast($newLink);

        if ($newLink->exists()) {
            throw new FileExistsException($newLink . " already exist");
        }

        $success = $this->builtin->symlink($this->path, (string)$newLink);

        if ($success === false) {
            throw new IOException("Error while creating the symbolic link from " . $this->path . " to " . $newLink);
        }

        return $newLink;
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
     * @return array<string>
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
     * @return self
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function getRelativePath(string|self $basePath): self
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

        return $this->cast(
            str_repeat(
                '..' . DIRECTORY_SEPARATOR,
                count($baseParts)
            ) . implode(
                DIRECTORY_SEPARATOR,
                $pathParts
            )
        );
    }
}
