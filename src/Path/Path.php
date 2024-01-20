<?php

namespace Path\Path;

class Path
{
    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Resolve the path.
     *
     * @return bool|string Returns the resolved path as a string if successful, otherwise returns false.
     */
    public function resolve(): bool|string
    {
        return realpath($this->path);
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
    public function isDir()
    {
        return is_dir($this->path);
    }

    /**
     * Get the extension of the given path.
     *
     * @return array|string Returns the extension of the path as a string if it exists, or an empty string otherwise.
     */
    public function extension(): array|string
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
        }
        else if (is_dir($this->path)) {
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
        }
        else if (is_dir($this->path)) {
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
}