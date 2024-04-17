<?php

namespace Path;

use JetBrains\PhpStorm\ExpectedValues;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A proxy for PHP builtin file methods
 * Mostly for compatibility and testing purposes
 */
class BuiltinProxy
{
    public static string $DIRECTORY_SEPARATOR = DIRECTORY_SEPARATOR;

    public function getHome(): string
    {
        return PHP_OS_FAMILY == 'Windows' ?
            $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'] :
            $_SERVER['HOME'];
    }

    public function date(string $format, int $time): string
    {
        return date($format, $time);
    }

    public function is_dir(string $filename): bool
    {
        return is_dir($filename);
    }

    public function is_file(string $filename): bool
    {
        return is_file($filename);
    }

    public function file_exists(string $filename): bool
    {
        return file_exists($filename);
    }

    public function pathinfo(
        string $path,
        #[ExpectedValues([PATHINFO_DIRNAME, PATHINFO_BASENAME, PATHINFO_EXTENSION, PATHINFO_FILENAME])] int $flags = PATHINFO_ALL
    ): array|string {
        return pathinfo($path, $flags);
    }

    public function opendir(string $directory, $context = null)
    {
        return opendir($directory, $context);
    }

    public function mkdir(string $directory, int $permissions = 0777, bool $recursive = false, $context = null): bool
    {
        return mkdir($directory, $permissions, $recursive, $context);
    }

    public function readdir($dir_handle): false|string
    {
        return readdir($dir_handle);
    }

    public function readlink(string $path): false|string
    {
        return readlink($path);
    }

    public function copy(string $from, string $to, $context = null): bool
    {
        return copy($from, $to, $context);
    }

    public function closedir($dir_handle): void
    {
        closedir($dir_handle);
    }

    public function scandir(string $directory, int $sorting_order = 0, $context = null): array|false
    {
        return scandir($directory, $sorting_order, $context);
    }

    public function unlink(string $filename, $context = null): bool
    {
        return unlink($filename, $context);
    }

    public function umask(?int $mode): int
    {
        return umask($mode);
    }

    public function rmdir(string $directory, $context = null): bool
    {
        return rmdir($directory, $context);
    }

    public function realpath(string $path): false|string
    {
        return realpath($path);
    }

    public function is_readable(string $filename): bool
    {
        return is_readable($filename);
    }

    public function is_writable(string $filename): bool
    {
        return is_writable($filename);
    }

    public function is_executable(string $filename): bool
    {
        return is_executable($filename);
    }

    public function is_link(string $filename): bool
    {
        return is_link($filename);
    }

    public function fileatime(string $filename): false|int
    {
        return fileatime($filename);
    }

    public function filectime(string $filename): false|int
    {
        return filectime($filename);
    }

    public function filemtime(string $filename): false|int
    {
        return filemtime($filename);
    }

    public function chdir(string $directory): bool
    {
        return chdir($directory);
    }

    public function rename(string $from, string $to, $context = null): bool
    {
        return rename($from, $to, $context);
    }

    public function touch(string $filename, ?int $mtime, ?int $atime): bool
    {
        return touch($filename, $mtime, $atime);
    }

    public function filesize(string $filename): false|int
    {
        return filesize($filename);
    }

    public function dirname(string $path, int $levels = 1): string
    {
        return dirname($path, $levels);
    }

    public function fnmatch(string $pattern, string $filename): bool
    {
        return fnmatch($pattern, $filename);
    }

    public function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): false|string
    {
        return file_get_contents($filename, $use_include_path, $context, $offset, $length);
    }

    public function file_put_contents(string $filename, mixed $data, int $flags = 0, $context = null): false|int
    {
        return file_put_contents($filename, $data, $flags, $context);
    }

    public function fileperms(string $filename): false|int
    {
        return fileperms($filename);
    }

    public function clearstatcache(bool $clear_realpath_cache = false, string $filename = ''): void
    {
        clearstatcache($clear_realpath_cache, $filename);
    }

    public function chmod(string $filename, int $permissions): bool
    {
        return chmod($filename, $permissions);
    }

    public function chown(string $filename, int|string $user): bool
    {
        return chown($filename, $user);
    }

    public function chgrp(string $filename, int|string $group): bool
    {
        return chgrp($filename, $group);
    }

    public function fileowner(string $filename): false|int
    {
        return fileowner($filename);
    }

    public function posix_getpwuid(int $id): false|array
    {
        return posix_getpwuid($id);
    }

    public function chroot(string $directory): bool
    {
        return chroot($directory);
    }

    public function glob(string $pattern, int $flags = 0): array|false
    {
        return glob($pattern, $flags);
    }

    public function hash_file(string $algo, string $filename, bool $binary): false|string
    {
        return hash_file($algo, $filename, $binary);
    }

    public function fopen(string $filename, string $mode, bool $use_include_path = false, $context = null)
    {
        return fopen($filename, $mode, $use_include_path, $context);
    }

    public function fclose($stream): bool
    {
        return fclose($stream);
    }

    public function feof($stream): bool
    {
        return feof($stream);
    }

    public function fread($stream, int $length): false|string
    {
        return fread($stream, $length);
    }

    public function link(string $target, string $link): bool
    {
        return link($target, $link);
    }

    public function symlink(string $target, string $link): bool
    {
        return symlink($target, $link);
    }

    public function lstat(string $filename): array|false
    {
        return lstat($filename);
    }

    public function getenv(?string $name = null, bool $local_only = false): array|false|string
    {
        return getenv($name, $local_only);
    }

    public function getRecursiveIterator(string $directory): \Iterator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            yield $file;
        }
    }

    public function disk_free_space(string $directory): float|false
    {
        return disk_free_space($directory);
    }
}
