<?php

namespace Path\Tests;

use Path\Path;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    const TEMP_TEST_DIR = __DIR__ . "/temp";

    protected Path $pathClass;

    public function setUp(): void
    {
        mkdir(self::TEMP_TEST_DIR);
    }

    private function rmDirs(string $dir): void {
        // Remove and replace by a proper tempdir method
        foreach(scandir($dir) as $file) {
            if ('.' === $file || '..' === $file) continue;
            if (is_dir($dir . DIRECTORY_SEPARATOR . $file)) $this->rmDirs($dir . DIRECTORY_SEPARATOR . $file);
            else unlink($dir . DIRECTORY_SEPARATOR . $file);
        }
        rmdir($dir);
    }

    public function tearDown(): void
    {
        $this->rmDirs(self::TEMP_TEST_DIR);
        chdir(__DIR__);
    }

    public function testToString(): void
    {
        $path = new Path('/foo/bar');
        $this->assertEquals('/foo/bar', $path->__toString());
    }

    /**
     * Test 'join' method.
     */
    public function testJoin(): void
    {
        // One part
        $this->assertEquals(
            '/home/user',
            Path::join('/home', 'user')
        );

        // Multiple parts
        $this->assertEquals(
            '/home/user/documents',
            Path::join('/home', 'user', 'documents')
        );

        // Absolute path passed in $parts
        $this->assertEquals(
            '/user/documents',
            Path::join('home', '/user', 'documents')
        );
    }

    /**
     * Test `eq` method with equal paths.
     *
     * Check that the method returns the correct result when the paths are equal.
     */
    public function testEqWithEqualPaths(): void
    {
        $path = new Path('/foo/bar');
        $this->assertTrue($path->eq('/foo/bar'));
    }

    /**
     * Test `eq` method with different paths.
     *
     * Check that the method returns the correct result when the paths are different.
     */
    public function testEqWithDifferentPaths(): void
    {
        $path = new Path('/foo/bar');
        $this->assertFalse($path->eq('/foo/zzz'));
    }

    /**
     * Test `eq` method with empty path.
     *
     * Check that the method returns the correct result when the path is empty.
     */
    public function testEqWithEmptyPath(): void
    {
        $path = new Path('/foo/bar');
        $this->assertFalse($path->eq(''));
    }

    /**
     * Test the append method of the Path class.
     *
     * @return void
     */
    public function testAppend(): void
    {
        $path = new Path('/foo');
        $this->assertEquals(
            "/foo/bar",
            $path->append('bar')
        );

        // One part
        $this->assertTrue(
            (new Path('/home'))->append('user')->eq('/home/user')
        );

        // Multiple parts
        $this->assertTrue(
            (new Path('/home'))->append('user', 'documents')->eq('/home/user/documents')
        );

        // Absolute path passed in $parts
        $this->assertTrue(
            (new Path('/home'))->append('/user', 'documents')->eq('/user/documents')
        );
    }

    /**
     * Test the abspath method of the Path class.
     *
     * @return void
     */
    public function testAbsPath(): void
    {
        touch(self::TEMP_TEST_DIR . "/foo");
        chdir(self::TEMP_TEST_DIR);

        $this->assertEquals(
            self::TEMP_TEST_DIR . "/foo",
            (new Path('foo'))->abspath()
        );
    }

    /**
     * Test the abspath method of the Path class with a relative path.
     *
     * @return void
     */
    public function testAbsPathWithRelative(): void
    {
        mkdir(self::TEMP_TEST_DIR . "/foo");
        touch(self::TEMP_TEST_DIR . "/bar");
        chdir(self::TEMP_TEST_DIR . "/foo");

        $this->assertEquals(
            self::TEMP_TEST_DIR . "/bar",
            (new Path('../bar'))->abspath()
        );
    }
}