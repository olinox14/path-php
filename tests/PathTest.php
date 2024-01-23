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
        chdir(self::TEMP_TEST_DIR);
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

    /**
     * Test 'Path' class 'access' method to check existence of the file
     */
    public function testAccessCheckExistenceOfFile(): void
    {
        $filePath = self::TEMP_TEST_DIR . "/foo";
        touch($filePath);
        chmod($filePath, 777);

        $result = (new Path('foo'))->access(Path::F_OK);
        $this->assertTrue($result);
    }

    /**
     * Test 'Path' class 'access' method to check existence of the non-existent file
     */
    public function testAccessCheckExistenceOfNonExistingFile(): void
    {
        $result = (new Path('foo'))->access(Path::F_OK);
        $this->assertFalse($result);
    }

    /**
     * Test 'Path' class 'access' method to check read permission of the file
     */
    public function testAccessCheckReadPermissionOfFile(): void
    {
        $filePath = self::TEMP_TEST_DIR . "/foo";
        touch($filePath);
        chmod($filePath, 777);

        $result = (new Path('foo'))->access(Path::R_OK);
        $this->assertTrue($result);
    }

//    /**
//     * Test 'Path' class 'access' method to check read permission of the file (no permission)
//     */
//    public function testAccessCheckReadPermissionOfFileNoRight(): void
//    {
//        $filePath = self::TEMP_TEST_DIR . "/foo";
//        touch($filePath);
//        chmod($filePath, 000);
//
//        $result = (new Path('foo'))->access(Path::R_OK);
//        $this->assertFalse($result);
//    }

    /**
     * Test 'Path' class 'access' method to check write permission of the file
     */
    public function testAccessCheckWritePermissionOfFile(): void
    {
        $filePath = self::TEMP_TEST_DIR . "/foo";
        touch($filePath);
        chmod($filePath, 777);

        $result = (new Path('foo'))->access(Path::W_OK);
        $this->assertTrue($result);
    }

//    /**
//     * Test 'Path' class 'access' method to check write permission of the file (no permission)
//     */
//    public function testAccessCheckWritePermissionOfFileNoRight(): void
//    {
//        $filePath = self::TEMP_TEST_DIR . "/foo";
//        touch($filePath);
//        chmod($filePath, 000);
//
//        $result = (new Path('foo'))->access(Path::W_OK);
//        $this->assertFalse($result);
//    }

    /**
     * Test 'Path' class 'access' method to check execute permission of the file
     */
    public function testAccessCheckExecutePermissionOfFile(): void
    {
        $filePath = self::TEMP_TEST_DIR . "/foo";
        touch($filePath);
        chmod($filePath, 777);

        $result = (new Path('foo'))->access(Path::X_OK);
        $this->assertTrue($result);
    }

    /**
     * Test 'Path' class 'access' method to check existence of the file
     */
    public function testAccessCheckExecutePermissionOfFileNoRight(): void
    {
        $filePath = self::TEMP_TEST_DIR . "/foo";
        touch($filePath);
        chmod($filePath, 000);

        $result = (new Path('foo'))->access(Path::X_OK);
        $this->assertFalse($result);
    }

    /**
     * Test 'Path' class 'access' method with an invalid mode parameter
     */
    public function testAccessInvalidModeParameter(): void
    {
        $this->expectException(\RuntimeException::class);
        (new Path('foo'))->access(123);
    }

    /**
     * Test 'Path' class 'atime' method to get the access time of a file
     *
     * @return void
     */
    public function testATime()
    {
        touch(self::TEMP_TEST_DIR . "/foo");
        $atime = (new Path('foo'))->atime();
        $this->assertTrue(abs(time() - strtotime($atime)) <= 60);
        $this->assertMatchesRegularExpression(
            "/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/",
            $atime
        );
    }

    /**
     * Test 'Path' class 'isFile' method to check if the file exists
     *
     * @return void
     */
    public function testIsFileOnActualFile(): void
    {
        touch(self::TEMP_TEST_DIR . "/foo");

        $this->assertTrue((new Path('foo'))->isFile());
    }

    /**
     * Test 'Path' class 'isFile' method to check if a non-existent file exists
     *
     * @return void
     */
    public function testIsFileOnNonExistentFile(): void
    {
        $this->assertFalse((new Path('foo'))->isFile());
    }

    /**
     * Test 'Path' class 'isFile' method to check if a file exists on the given directory
     *
     * @return void
     */
    public function testIsFileOnExistentDir(): void
    {
        mkdir(self::TEMP_TEST_DIR . "/some_dir");

        $this->assertFalse((new Path('some_dir'))->isFile());
    }

    /**
     * Test 'Path' class 'isDir' method to check if the path is a directory
     *
     * @return void
     */
    public function testIsDirOnActualDir(): void
    {
        mkdir(self::TEMP_TEST_DIR . "/some_dir");

        $this->assertTrue((new Path('some_dir'))->isDir());
    }

    /**
     * Test 'Path' class 'isDir' method to check if a file's path is a directory
     *
     * @return void
     */
    public function testIsDirOnExistentFile(): void
    {
        touch(self::TEMP_TEST_DIR . "/foo");

        $this->assertFalse((new Path('some_dir'))->isDir());
    }

    /**
     * Test 'Path' class 'isDir' method on a non-existent directory
     *
     * @return void
     */
    public function testIsDirOnNonExistentDir(): void
    {
        $this->assertFalse((new Path('some_dir'))->isDir());
    }

    public function testFileExtension(): void
    {
        $this->assertEquals(
            'txt',
            (new Path("foo.txt"))->ext()
        );
    }

    public function testEmptyExtension(): void
    {
        $this->assertSame(
            '',
            (new Path("foo"))->ext()
        );
    }
}