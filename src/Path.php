<?php

namespace Path;

use Generator;
use Path\Exception\FileExistsException;
use Path\Exception\FileNotFoundException;
use Path\Exception\IOException;
use Throwable;

/**
 * Represents a filesystem path.
 *
 * Most of the methods rely on the php builtin methods, see each method's documentation for more.
 *
 * @see https://github.com/olinox14/path-php#path-php
 * @package olinox14/path
 */
class Path
{
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
     * Example :
     *
     *     Path::join('/home', 'user')
     *     >>> '/home/user'
     *
     * @param string|Path $path The base path
     * @param string ...$parts The parts of the path to be joined.
     * @return self The resulting path after joining the parts using the directory separator.
     */
    public static function join(string|self $path, string|self ...$parts): self
    {
        $path = (string)$path;
        $parts = \array_map(fn ($p) => (string)$p, $parts);

        foreach ($parts as $part) {
            if (\str_starts_with($part, BuiltinProxy::$DIRECTORY_SEPARATOR)) {
                $path = $part;
            } elseif (!$path || \str_ends_with($path, BuiltinProxy::$DIRECTORY_SEPARATOR)) {
                $path .= $part;
            } else {
                $path .= BuiltinProxy::$DIRECTORY_SEPARATOR . $part;
            }
        }
        return new self($path);
    }

    /**
     * Split the pathname path into a pair (drive, tail) where drive is either a mount point or the empty string.
     *
     * If the path contains a drive letter, drive will contain everything up to and including the colon:
     *
     *     Path::splitDrive("c:/dir")
     *     >>> ["c:", "/dir"]
     *
     * If the path contains a UNC path, drive will contain the host name and share:
     *
     *     Path::splitDrive("//host/computer/dir")
     *     >>> ["//host/computer", "/dir"]
     *
     * @param string|self $path
     * @return array<string> An 2-members array containing the drive and the path.
     */
    public static function splitDrive(string|self $path): array
    {
        $path = (string)$path;

        $matches = [];

        \preg_match('/(^[a-zA-Z]:)(.*)/', $path, $matches);
        if ($matches) {
            return \array_slice($matches, -2);
        }

        $rx =
            BuiltinProxy::$DIRECTORY_SEPARATOR === '/' ?
                '/(^\/\/[\w\-\s]{2,15}\/[\w\-\s]+)(.*)/' :
                '/(^\\\\\\\\[\w\-\s]{2,15}\\\[\w\-\s]+)(.*)/';

        \preg_match($rx, $path, $matches);
        if ($matches) {
            return \array_slice($matches, -2);
        }

        return ['', $path];
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
     * @return self
     */
    protected function cast(string|self $path): self
    {
        return new self($path);
    }

    /**
     * The current path as a string.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Checks if the given path is equal to the current path.
     * NB: This method does not perform any path resolution.
     *
     * @param string|Path $path The path to compare against.
     * @return bool
     */
    public function eq(string|self $path): bool
    {
        return $this->cast($path)->path() === $this->path();
    }

    /**
     * Appends part(s) to the current path.
     *
     * @param string ...$parts The part(s) to be appended to the current path.
     * @return self
     * @see Path::join()
     */
    public function append(string|self ...$parts): self
    {
        return $this->cast(self::join($this->path, ...$parts));
    }

    /**
     * Returns an absolute version of the current path.
     *
     * As this method relies on the php `realpath` method, it will fail if the path refers to a
     * non-existing file.
     * @see https://www.php.net/manual/en/function.realpath.php
     *
     * @return self
     * @throws IOException
     */
    public function absPath(): self
    {
        $absPath = $this->builtin->realpath($this->path);
        if ($absPath === false) {
            throw new IOException("An error occurred while getting abspath of `" . $this->path . "`");
        }
        return $this->cast($absPath);
    }

    /**
     * > Alias for absPath()
     *
     * @see absPath()
     * @throws IOException
     */
    public function realpath(): self
    {
        return $this->absPath();
    }

    /**
     * Retrieves and returns the home directory of the current user.
     *
     * @return self The Path instance representing the home directory.
     * @throws \RuntimeException When unable to determine the home directory.
     * TODO: move to a utils class and test
     */
    public function getHomeDir(): self
    {
        $homeDir = $this->builtin->getServerEnvVar('HOME');
        if (!empty($homeDir)) {
            return new self($homeDir);
        }

        $homeDir = $this->builtin->getenv('HOME');
        if (!empty($homeDir)) {
            return new self($homeDir);
        }

        $isWindows = $this->builtin->strncasecmp(BuiltinProxy::$PHP_OS, "WIN", 3) === 0;

        if ($isWindows) {
            $homeDrive = $this->builtin->getServerEnvVar('HOMEDRIVE');
            $homePath = $this->builtin->getServerEnvVar('HOMEPATH');
            if ($homeDrive && $homePath) {
                return new self($homeDrive . $homePath);
            }
        }

        if ($this->builtin->function_exists('exec')) {
            $homeDir = $isWindows ?
                $this->builtin->exec('echo %userprofile%') :
                $this->builtin->exec('echo ~');

            if ($homeDir) {
                return new self($homeDir);
            }
        }

        throw new \RuntimeException('Unable to determine home directory');
    }

    /**
     * Retrieves the last access time of a file or directory.
     *
     * @see https://www.php.net/manual/en/function.fileatime.php
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
     * @see https://www.php.net/manual/en/function.filectime.php
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
     * @see https://www.php.net/manual/en/function.filemtime.php
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
     * @see https://www.php.net/manual/en/function.is-file.php
     *
     * @return bool
     */
    public function isFile(): bool
    {
        return $this->builtin->is_file($this->path);
    }

    /**
     * Check if the given path is a directory.
     *
     * @see https://www.php.net/manual/en/function.is-dir.php
     *
     * @return bool
     */
    public function isDir(): bool
    {
        return $this->builtin->is_dir($this->path);
    }

    /**
     * Get the extension of the given path.
     *
     * @see https://www.php.net/manual/en/function.pathinfo.php
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
     * Example :
     *
     *     Path('path/to/file.ext').basename()
     *     >>> 'file.ext'
     *
     * .
     * @see https://www.php.net/manual/en/function.pathinfo.php
     *
     * @return string
     */
    public function basename(): string
    {
        return $this->builtin->pathinfo($this->path, PATHINFO_BASENAME);
    }

    /**
     * Changes the current working directory to this path.
     *
     * @see https://www.php.net/manual/en/function.chdir.php
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
            throw new IOException('An error occurred while changing working directory to ' . $this->path);
        }
    }

    /**
     * Alias for Path->cd($path)
     *
     * @see cd()
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function chdir(): void
    {
        $this->cd();
    }

    /**
     * Get the name of the file or path, without its extension.
     *
     * Example:
     *
     *     Path('path/to/file.ext').name()
     *     >>> 'file'
     *
     * .
     * @see https://www.php.net/manual/en/function.pathinfo.php
     * @return string
     */
    public function name(): string
    {
        return $this->builtin->pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * Normalize the case of a pathname.
     *
     * Convert all characters in the pathname to lowercase, and also convert
     * forward slashes and backslashes to the current directory separator.
     *
     * @return self
     */
    public function normCase(): self
    {
        return $this->cast(
            strtolower(
                str_replace(['/', '\\'], BuiltinProxy::$DIRECTORY_SEPARATOR, $this->path())
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
     * @return self A new instance of the class with the normalized path.
     */
    public function normPath(): self
    {
        $path = str_replace(['/', '\\'], BuiltinProxy::$DIRECTORY_SEPARATOR, $this->path());

        if (empty($path)) {
            return $this->cast('.');
        }

        // Also tests some special cases we can't really do anything with
        if (!str_contains($path, BuiltinProxy::$DIRECTORY_SEPARATOR) || $path === '/' || '.' === $path || '..' === $path) {
            return $this->cast($path);
        }

        $path = rtrim($path, BuiltinProxy::$DIRECTORY_SEPARATOR);

        [$prefix, $path] = self::splitDrive($path);

        $parts = explode(BuiltinProxy::$DIRECTORY_SEPARATOR, $path);
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

        if ($prefix) {
            array_shift($newParts); // Get rid of the leading empty string resulting from slitDrive result
            array_unshift($newParts, rtrim($prefix, BuiltinProxy::$DIRECTORY_SEPARATOR));
        }

        $newPath = implode(BuiltinProxy::$DIRECTORY_SEPARATOR, $newParts);

        return $this->cast($newPath);
    }

    /**
     * Creates a new directory.
     *
     * @see https://www.php.net/manual/en/function.mkdir.php
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
            throw new IOException("An error occurred while creating the new directory : " . $this->path);
        }
    }

    /**
     * Deletes a file or a directory (non-recursively).
     *
     * @see https://www.php.net/manual/en/function.unlink.php
     * @see https://www.php.net/manual/en/function.rmdir.php
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
                throw new IOException("Error while deleting file : " . $this->path);
            }
        } elseif ($this->isDir()) {
            $result = $this->builtin->rmdir($this->path);

            if (!$result) {
                throw new IOException("An error occurred while deleting directory : " . $this->path);
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
     * This method does *not* conserve permissions.
     * If $follow_symlinks is true and if $destination is a directory, the newly created file will have the
     * filename of the symlink, and not the one of its target.
     *
     * @see https://www.php.net/manual/en/function.copy.php
     * @see https://www.php.net/manual/en/function.symlink.php
     *
     * @param string|self $destination The destination path or object to copy the file to.
     * @param bool $follow_symlinks
     * @param bool $erase
     * @return Path
     * @throws FileExistsException
     * @throws FileNotFoundException If the source file does not exist or is not a file.
     * @throws IOException
     */
    public function copy(string|self $destination, bool $follow_symlinks = false, bool $erase = true): self
    {
        if (!$this->isFile() && !$this->isLink()) {
            throw new FileNotFoundException("File does not exist or is not a file : " . $this);
        }

        $destination = $this->cast($destination);
        if ($destination->isDir()) {
            $destination = $destination->append($this->basename());
        }

        if ($this->isLink()) {
            $target = $this->readLink();
            if ($follow_symlinks) {
                if (!$target->isFile()) {
                    throw new FileNotFoundException("File does not exist or is not a file : " . $target);
                }
                return $target->copy($destination);
            } else {
                return $target->symlink($destination);
            }
        }

        if ($destination->isFile() && !$erase) {
            throw new FileExistsException("File already exists : " . $destination->path());
        }

        $success = $this->builtin->copy($this->path, $destination->path());
        if (!$success) {
            throw new IOException("An error occurred while copying file {$this->path} to {$destination->path()}");
        }

        return $destination;
    }

    /**
     * Recursively copy a directory tree and return the destination directory.
     *
     * If $follow_symlinks is false, symbolic links in the source tree result in symbolic links in
     * the destination tree; if it is true, the contents of the files pointed to by symbolic links are copied.
     *
     * @param string|self $destination The destination path or directory to copy the content to.
     * @param bool $followSymlinks Whether to follow symbolic links (default is false).
     * @return self The newly created file or directory as a Path
     *
     * @throws FileExistsException If the destination path or directory already exists.
     * @throws FileNotFoundException If the source file or directory does not exist.
     * @throws IOException
     */
    public function copyTree(string|self $destination, bool $followSymlinks = false): self
    {
        if (!$this->isDir() || (!$followSymlinks && $this->isLink())) {
            throw new FileNotFoundException("Directory does not exist or is not a directory : " . $this);
        }

        $destination = $this->cast($destination);

        if ($destination->exists() || $destination->isLink()) {
            throw new FileExistsException('A file or directory already exists at ' . $destination);
        }

        foreach ($this->dirs() as $dir) {
            $newDir = $destination->append(
                $dir->basename()
            );

            $dir->copyTree($newDir, $followSymlinks);
        }

        if (!$destination->isDir()) {
            $mode = $this->getPermissions();
            $destination->mkdir($mode, true);
        }

        foreach ($this->files() as $file) {
            $newFile = $destination->append(
                $file->basename()
            );

            $newFile->remove_p();
            $file->copy($newFile, $followSymlinks);
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
     * @see https://www.php.net/manual/en/function.rename.php
     *
     * @param string|Path $destination The new location where the file or directory should be moved to.
     * @return Path
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
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

        if ($destination->exists()) {
            throw new FileExistsException('File or directory already exists at ' . $destination->path());
        }

        $success = $this->builtin->rename($this->path, $destination->path());

        if (!$success) {
            throw new IOException("An error occurred while moving " . $this->path . " to " . $destination->path());
        }

        return $destination;
    }

    /**
     * Updates the access and modification time of a file, or creates a new empty file if it doesn't exist.
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
            throw new IOException("An error occurred while touching " . $this->path);
        }
    }

    /**
     * Size of the file, in bytes.
     *
     * @see https://www.php.net/manual/en/function.filesize.php
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
            throw new IOException("An error occurred while getting the size of " . $this->path);
        }

        return $result;
    }

    /**
     * Retrieves the parent directory of a file or directory path.
     *
     * @see https://www.php.net/manual/en/function.dirname.php
     *
     * @param int $levels
     * @return self
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
     * @see parent()
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
     * This does not walk recursively into subdirectories (but @see walkDirs())
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
    public function files(bool $includeSymlinks = true): array
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

            if ($child->isFile() || ($includeSymlinks && $child->isLink())) {
                $files[] = $child;
            }
        }

        return $files;
    }

    /**
     * Performs a pattern matching using the `fnmatch()` function.
     *
     * @see https://www.php.net/manual/en/function.fnmatch.php
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
     * @see https://www.php.net/manual/en/function.file-get-contents.php
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
     * @see getContent()
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
     * @see https://www.php.net/manual/en/function.file-put-contents.php
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
            throw new IOException("An error occurred while putting content into $this->path");
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
     * @see https://www.php.net/manual/en/function.fileperms.php
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
            throw new IOException("An error occurred while getting permissions on " . $this->path);
        }

        if (!$asOctal) {
            $perms = (int)substr(sprintf('%o', $perms), -4);
        }

        return $perms;
    }

    /**
     * Changes the permissions of a file or directory.
     *
     * @see https://www.php.net/manual/en/function.chmod.php
     *
     * @param int $permissions The new permissions to set.
     * @param bool $asOctal Set to true if permissions are given as octal
     * @param bool $clearStatCache Force a clear cache of the php stat cache
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
            throw new IOException("An error occurred while setting permissions on " . $this->path);
        }
    }

    /**
     * Retrieves the id of the owner of the file or directory.
     *
     * @see https://www.php.net/manual/en/function.fileowner.php
     *
     * @return int The owner identifier of the file or directory
     * @throws FileNotFoundException if the file or directory does not exist
     * @throws IOException if there is an error while retrieving the owner
     */
    public function getOwnerId(): int
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }

        $owner = $this->builtin->fileowner($this->path);

        if ($owner === false) {
            throw new IOException("An error occurred while getting owner of " . $this->path);
        }

        return $owner;
    }

    /**
     * Retrieves the name of the owner of the file or directory.
     *
     * @see https://www.php.net/manual/en/function.posix-getpwuid.php
     *
     * @return string The name of the owner
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function getOwnerName(): string
    {
        $ownerId = $this->getOwnerId();

        $userInfo = $this->builtin->posix_getpwuid($ownerId);

        if ($userInfo === false) {
            throw new IOException("An error occurred while getting infos about owner of " . $this->path);
        }

        return $userInfo['name'];
    }

    /**
     * Changes ownership of the file.
     *
     * @see https://www.php.net/manual/en/function.chown.php
     * @see https://www.php.net/manual/en/function.chgrp.php
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
            throw new IOException("An error occurred while setting owner of " . $this->path);
        }
    }

    /**
     * Checks if a file or directory exists.
     *
     * @see https://www.php.net/manual/en/function.file-exists.php
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->builtin->file_exists($this->path);
    }

    /**
     * Return True if both pathname arguments refer to the same file or directory.
     *
     * As this method relies on the `realpath` method, this will throw an exception if any of the two files does
     * not exist.
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
     * @return self The expanded path.
     * @throws IOException
     * @see Path::expandUser()
     * @see Path::expandVars()
     * @see Path::normPath()
     */
    public function expand(): self
    {
        return $this->expandUser()->expandVars()->normPath();
    }

    /**
     * Expands the user directory in the file path.
     *
     * @return self The modified instance with the expanded user path.
     * @throws IOException
     */
    public function expandUser(): self
    {
        if (!str_starts_with($this->path(), '~/')) {
            return $this;
        }
        $home = $this->getHomeDir();

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
     * @see https://www.php.net/manual/en/function.glob.php
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
            throw new IOException("An error occurred while getting glob on " . $this->path);
        }

        return array_map(
            function (string $s) { return $this->append($s); },
            $result
        );
    }

    /**
     * Removes the file.
     *
     * @see https://www.php.net/manual/en/function.unlink.php
     *
     * @return void
     * @throws IOException if there was an error while removing the file.
     * @throws IOException|FileNotFoundException If the file does not exist or is not a file.
     */
    public function remove(): void
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException($this->path . " is not a file");
        }
        $result = $this->builtin->unlink($this->path);
        if (!$result) {
            throw new IOException("An error occurred while removing the file " . $this->path);
        }
    }

    /**
     * > Alias for Path->remove()
     *
     * @see remove()
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
     * It will still raise a `FileExistsException` if the target is an existing directory.
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
     * Removes a directory.
     *
     * If $recursive is true, the directory will be removed with its content. Else, an IOException will be
     * raised. If the target directory does not exist, a FileNotFoundException will be raised, except if
     * $permissive is set to true. If the target is an existing file, a FileNotFoundException will be raised even
     * if $permissive is true.
     *
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function rmdir(bool $recursive = false, bool $permissive = false): void
    {
        if (!$this->isDir()) {
            if ($permissive && !$this->isFile()) {
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
            throw new IOException("An error occurred while removing directory : " . $this->path);
        }
    }

    /**
     * > Alias for Path->move()
     *
     * @see move()
     * @throws FileNotFoundException
     * @throws IOException
     * @throws FileExistsException
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
            throw new IOException("An error occurred while computing the hash of " . $this->path);
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
            throw new IOException("An error occurred while getting the target of the symbolic link " . $this->path);
        }
        return $this->cast($result);
    }

    /**
     * Opens a file in the specified mode.
     *
     * @see https://www.php.net/manual/en/function.fopen.php
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
        [$drive, $path] = Path::splitDrive($this->path());
        return !empty($drive) || str_starts_with($path, '/');
    }

    /**
     * > Alias for Path->setPermissions() method
     *
     * @see setPermissions()
     * @param int $mode The new permissions (octal).
     * @param bool $asOctal
     * @param bool $clearStatCache
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function chmod(int $mode, bool $asOctal = false, bool $clearStatCache = false): void
    {
        $this->setPermissions($mode, $asOctal, $clearStatCache);
    }

    /**
     * Changes the owner of a file or directory.
     *
     * @see https://www.php.net/manual/en/function.chown.php
     *
     * @param int|string $user The new owner of the file or directory. Accepts either the user's numeric ID or username.
     * @param bool $clearStatCache Optional. Whether to clear the stat cache before changing the owner. Default is false.
     * @throws FileNotFoundException If the file or directory does not exist.
     * @throws IOException If an error occurs while changing the owner of the file or directory.
     * @return void
     */
    public function chown(int|string $user, bool $clearStatCache = false): void
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }

        if ($clearStatCache) {
            $this->builtin->clearstatcache();
        }

        $success = $this->builtin->chown($this->path, $user);

        if (!$success) {
            throw new IOException("An error occurred while changing the owner of " . $this->path);
        }
    }

    /**
     * Changes the group ownership of a file or directory.
     *
     * @see https://www.php.net/manual/en/function.chgrp.php
     *
     * @param int|string $group The group name or ID. If a string is provided, it must be a valid group name.
     *                          If an integer is provided, it must be a valid group ID.
     * @param bool $clearStatCache Whether to clear the stat cache before changing the group ownership.
     *                             Defaults to false.
     * @return void
     * @throws FileNotFoundException If the file or directory does not exist.
     * @throws IOException If there is an error changing the group ownership.
     */
    public function chgrp(int|string $group, bool $clearStatCache = false): void
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this->path);
        }

        if ($clearStatCache) {
            $this->builtin->clearstatcache();
        }

        $success = $this->builtin->chgrp($this->path, $group);

        if (!$success) {
            throw new IOException("An error occurred while changing root directory to " . $this->path);
        }
    }

    /**
     * Changes the root directory of the current process to the specified directory.
     *
     * @see https://www.php.net/manual/en/function.chroot.php
     *
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function chroot(): void
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException("Dir does not exist : " . $this->path);
        }

        $success = $this->builtin->chroot($this->absPath()->path());
        if (!$success) {
            throw new IOException("An error occurred while changing root directory to " . $this->path);
        }
    }

    /**
     * Checks if the file is a symbolic link.
     *
     * @see https://www.php.net/manual/en/function.is-link.php
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
     * @return bool
     */
    public function isMount(): bool
    {
        return $this->builtin->disk_free_space($this->path) !== false;
    }

    /**
     * Checks if the file or directory is readable.
     *
     * @see https://www.php.net/manual/en/function.is-readable.php
     *
     * @return bool
     * @throws FileNotFoundException If the file or directory does not exist
     */
    public function isReadable(): bool
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }

        return $this->builtin->is_readable($this->path);
    }

    /**
     * Determines if the file or directory is writable.
     *
     * @return bool
     * @throws FileNotFoundException If the file or directory does not exist.
     *
     */
    public function isWritable(): bool
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }

        return $this->builtin->is_writable($this->path);
    }

    /**
     * Determines if the file or directory is executable.
     *
     * @return bool
     * @throws FileNotFoundException If the file or directory does not exist.
     */
    public function isExecutable(): bool
    {
        if (!$this->exists()) {
            throw new FileNotFoundException("File or dir does not exist : " . $this);
        }

        return $this->builtin->is_executable($this->path);
    }

    /**
     * Create a hard link pointing to this path.
     *
     * @see https://www.php.net/manual/en/function.link.php
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
            throw new IOException("An error occurred while creating the link from " . $this->path . " to " . $newLink);
        }

        return $newLink;
    }

    /**
     * Gives information about a file or symbolic link
     *
     * @see https://www.php.net/manual/en/function.lstat.php
     *
     * @return array<string, int|float>
     * @throws IOException
     */
    public function lstat(): array
    {
        $result = $this->builtin->lstat($this->path);
        if ($result === false) {
            throw new IOException("An error occurred while getting lstat of " . $this->path);
        }
        return $result;
    }

    /**
     * Creates a symbolic link to the specified destination.
     *
     * @see https://www.php.net/manual/en/function.symlink.php
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
            throw new IOException("An error occurred while creating the symbolic link from " . $this->path . " to " . $newLink);
        }

        return $newLink;
    }

    /**
     * Returns the individual parts of this path.
     *
     * The eventual leading directory separator is kept.
     *
     * Example :
     *
     *     Path('/foo/bar/baz').parts()
     *     >>> '/', 'foo', 'bar', 'baz'
     *
     * @return array<string>
     */
    public function parts(): array
    {
        [$prefix, $path] = self::splitDrive($this->path());
        $parts = [];

        if ($prefix) {
            $path = ltrim($path, BuiltinProxy::$DIRECTORY_SEPARATOR);
        } elseif (str_starts_with($path, BuiltinProxy::$DIRECTORY_SEPARATOR)) {
            $parts[] = BuiltinProxy::$DIRECTORY_SEPARATOR;
        }

        $parts += explode(BuiltinProxy::$DIRECTORY_SEPARATOR, $path);

        if ($prefix) {
            array_unshift($parts, $prefix);
        }
        return $parts;
    }

    /**
     * Computes a version of this path that is relative to another path.
     *
     * This method relies on the php `realpath` method and then requires the path to refer to
     * an existing file.
     *
     * @param string|Path $basePath
     * @return self
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function getRelativePath(string|self $basePath): self
    {
        if (!$this->exists() && !$this->isLink()) {
            throw new FileNotFoundException("{$this->path} is not a file or directory");
        }

        $path = (string)$this->absPath();
        $basePath = (string)$basePath;

        $realBasePath = $this->builtin->realpath($basePath);
        if ($realBasePath === false) {
            throw new FileNotFoundException("$basePath does not exist or unable to get a real path");
        }

        $pathParts = explode(BuiltinProxy::$DIRECTORY_SEPARATOR, $path);
        $baseParts = explode(BuiltinProxy::$DIRECTORY_SEPARATOR, $realBasePath);

        while (count($pathParts) && count($baseParts) && ($pathParts[0] == $baseParts[0])) {
            array_shift($pathParts);
            array_shift($baseParts);
        }

        return $this->cast(
            str_repeat(
                '..' . BuiltinProxy::$DIRECTORY_SEPARATOR,
                count($baseParts)
            ) . implode(
                BuiltinProxy::$DIRECTORY_SEPARATOR,
                $pathParts
            )
        );
    }
}
