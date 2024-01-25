<?php

namespace Path\Tests;

use Path\Exception\FileExistsException;
use Path\Exception\FileNotFoundException;
use Path\Path;
use PHPUnit\Framework\TestCase;

// TODO: tested args should be both typed Path and string
class PathTest extends TestCase
{
    const TEMP_TEST_DIR = __DIR__ . "/temp";
    // TODO: consider using sys_get_temp_dir()

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
     * Test 'Path' class 'copy_dir' method to copy a directory
     *
     * @return void
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function testCopyDir(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $srcContent = $src . DIRECTORY_SEPARATOR . "foo.txt";
        $dst = self::TEMP_TEST_DIR . "/some_other_dir";

        mkdir($src);
        touch($srcContent);
        mkdir($dst);

        Path::copy_dir($src, $dst);

        $this->assertTrue(
            file_exists($dst . DIRECTORY_SEPARATOR . "foo.txt")
        );
    }

    /**
     * Test 'Path' class 'copy_dir' method when the destination directory does not exist
     *
     * @throws FileNotFoundException|FileExistsException
     */
    public function testCopyDirWhenDestinationDirectoryNotExists(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $dst = self::TEMP_TEST_DIR . "/non_existing_dir";

        mkdir($src);
        touch($src . DIRECTORY_SEPARATOR . "foo.txt");

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("Directory does not exist : " . $dst);

        Path::copy_dir($src, $dst);
    }

    /**
     * Test 'Path' class 'copy_dir' method when the destination directory already exists.
     *
     * @throws FileNotFoundException|FileExistsException if the destination directory already exists.
     */
    public function testCopyDirWhenDirectoryAlreadyExistsAtDestination(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $dst = self::TEMP_TEST_DIR . "/other_dir";

        mkdir($src);
        touch($src . DIRECTORY_SEPARATOR . "foo.txt");

        mkdir($dst);
        mkdir($dst . DIRECTORY_SEPARATOR . "some_dir");

        $this->expectException(FileExistsException::class);
        $this->expectExceptionMessage("Directory already exists : " . $dst);

        Path::copy_dir($src, $dst);
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

    /**
     * Test 'Path' class 'ext' method to get the extension of the file
     *
     * @return void
     */
    public function testFileExtension(): void
    {
        $this->assertEquals(
            'txt',
            (new Path("foo.txt"))->ext()
        );
    }

    /**
     * Test 'Path' class 'ext' method to get the extension of the file
     *
     * @return void
     */
    public function testEmptyExtension(): void
    {
        $this->assertEquals(
            '',
            (new Path("foo"))->ext()
        );
    }

    /**
     * Test 'Path' class 'basename' method to get the base name of a file
     *
     * @return void
     */
    public function testBaseName()
    {
        touch(self::TEMP_TEST_DIR . "/foo.txt");

        $this->assertEquals(
            'foo.txt',
            (new Path("foo.txt"))->basename()
        );
    }

    /**
     * Test 'Path' class 'name' method to get the name of the file without extension
     *
     * @return void
     */
    public function testName()
    {
        touch(self::TEMP_TEST_DIR . "/foo");

        $this->assertEquals(
            'foo',
            (new Path("foo"))->name()
        );
    }

    /**
     * Test 'Path' class 'name' method to get the name of the file with extension
     *
     * @return void
     */
    public function testNameWithExt()
    {
        touch(self::TEMP_TEST_DIR . "/foo.txt");

        $this->assertEquals(
            'foo',
            (new Path("foo.txt"))->name()
        );
    }

    /**
     * Test 'Path' class 'mkdir' method to create a directory
     *
     * @return void
     * @throws FileExistsException
     */
    public function testMkDir(): void
    {
        $path = new Path(self::TEMP_TEST_DIR . "/foo");
        $path->mkdir();
        $this->assertTrue($path->isDir());
    }

    /**
     * Test 'Path' class 'mkdir' method when directory already exists
     *
     * @throws FileExistsException If directory already exists
     */
    public function testMkDirExistingDir(): void {
        mkdir(self::TEMP_TEST_DIR . "/foo");
        $path = new Path(self::TEMP_TEST_DIR . "/foo");

        $this->expectException(FileExistsException::class);
        $this->expectExceptionMessage("Directory already exists : " . self::TEMP_TEST_DIR . "/foo");
        
        $path->mkdir();
    }

    /**
     * Test 'Path' class 'mkdir' method to create a directory with existing directory and recursive option
     *
     * @return void
     * @throws FileExistsException
     */
    public function testMkDirExistingDirAndRecursive(): void {
        mkdir(self::TEMP_TEST_DIR . "/foo");
        $path = new Path(self::TEMP_TEST_DIR . "/foo");

        $path->mkdir(0777, true);
        $this->assertTrue($path->isDir());
    }

    /**
     * Test 'Path' class 'mkdir' method to create a directory when a file with the same name already exists
     *
     * @return void
     * @throws FileExistsException When a file with the same name already exists
     */
    public function testMkDirExistingFile(): void {
        touch(self::TEMP_TEST_DIR . "/foo");
        $path = new Path(self::TEMP_TEST_DIR . "/foo");

        $this->expectException(FileExistsException::class);
        $this->expectExceptionMessage("A file with this name already exists : " . self::TEMP_TEST_DIR . "/foo");

        $path->mkdir();
    }

    /**
     * Test 'Path' class 'mkdir' method to create a directory recursively
     *
     * @return void
     * @throws FileExistsException
     */
    public function testMkDirRecursive(): void {
        $path = new Path(self::TEMP_TEST_DIR . "/foo/bar");
        $path->mkdir(0777, true);
        $this->assertTrue($path->isDir());
    }

    /**
     * Test 'Path' class 'delete' method to delete a file.
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function testDeleteFileSuccess(): void
    {
        touch(self::TEMP_TEST_DIR . "/foo");
        $path = new Path(self::TEMP_TEST_DIR . "/foo");

        $this->assertTrue($path->isFile());
        $path->delete();
        $this->assertFalse($path->isFile());
    }

    /**
     * Test 'Path' class 'delete' method to delete a directory successfully
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function testDeleteDirSuccess(): void
    {
        mkdir(self::TEMP_TEST_DIR . "/foo");
        $path = new Path(self::TEMP_TEST_DIR . "/foo");

        $this->assertTrue($path->isDir());
        $path->delete();
        $this->assertFalse($path->isDir());
    }

    /**
     * Test 'Path' class 'delete' method to delete a non-existing file or dir
     *
     * @throws FileNotFoundException When the file does not exist
     */
    public function testDeleteNonExistingFile(): void
    {
        $path = new Path(self::TEMP_TEST_DIR . "/foo");

        $this->assertFalse($path->isDir());

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File does not exist : " . self::TEMP_TEST_DIR . "/foo");

        $path->delete();
    }

    /**
     * Test 'Path' class 'copy_dir' method to copy a file
     *
     * @return void
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function testCopyWithFile(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $srcContent = $src . DIRECTORY_SEPARATOR . "foo.txt";
        $dst = self::TEMP_TEST_DIR . "/some_other_dir";

        mkdir($src);
        mkdir($dst);
        touch($srcContent);

        $path = new Path($srcContent);
        $path->copy($dst);

        $this->assertTrue(
            file_exists($dst . DIRECTORY_SEPARATOR . "foo.txt")
        );
    }

    /**
     * Test 'Path' class 'copy_dir' method to copy a directory
     *
     * @return void
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function testCopyWithDir(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $srcContent = $src . DIRECTORY_SEPARATOR . "foo.txt";
        $dst = self::TEMP_TEST_DIR . "/some_other_dir";

        mkdir($src);
        mkdir($dst);
        touch($srcContent);

        $path = new Path($src);
        $path->copy($dst);

        $this->assertTrue(
            file_exists($srcContent)
        );
    }

    /**
     * Test 'Path' class 'copy' method when the source file does not exist.
     *
     * @return void
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function testCopyFileNotExists(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $srcContent = $src . DIRECTORY_SEPARATOR . "foo.txt";
        $dst = self::TEMP_TEST_DIR . "/some_other_dir";

        mkdir($src);
        mkdir($dst);
        touch($srcContent);

        $path = new Path($src);
        $path->copy($dst);

        $this->assertTrue(
            file_exists($srcContent)
        );
    }

    /**
     * Test 'Path' class 'copy' method when the source file does not exist.
     *
     * @return void
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function testCopyDirDestAlreadyExists(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $srcContent = $src . DIRECTORY_SEPARATOR . "foo.txt";
        $dst = self::TEMP_TEST_DIR . "/some_other_dir";
        $dstContent = $dst . DIRECTORY_SEPARATOR . "foo.txt";

        mkdir($src);
        touch($srcContent);
        mkdir($dst);
        mkdir($dstContent);

        $this->expectException(FileExistsException::class);
        $this->expectExceptionMessage("File or dir already exists : " . $dstContent);

        $path = new Path($srcContent);
        $path->copy($dst);
    }

    /**
     * Test 'Path' class 'copy' method when the source file does not exist.
     *
     * @return void
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function testCopyFileDestAlreadyExists(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $srcContent = $src . DIRECTORY_SEPARATOR . "foo.txt";
        $dst = self::TEMP_TEST_DIR . "/some_other_dir";
        $dstContent = $dst . DIRECTORY_SEPARATOR . "foo.txt";

        mkdir($src);
        touch($srcContent);
        mkdir($dst);
        touch($dstContent);

        $this->expectException(FileExistsException::class);
        $this->expectExceptionMessage("File or dir already exists : " . $dstContent);

        $path = new Path($srcContent);
        $path->copy($dst);
    }

    /**
     * Test 'Path' class 'move' method to move a file from source directory to destination directory
     * @throws FileExistsException
     */
    public function testMoveWithFile(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $srcContent = $src . DIRECTORY_SEPARATOR . "foo.txt";
        $dst = self::TEMP_TEST_DIR . "/some_other_dir";
        $dstContent = $dst . DIRECTORY_SEPARATOR . "foo.txt";

        mkdir($src);
        touch($srcContent);
        mkdir($dst);

        $path = new Path($srcContent);
        $path->move($dst);

        $this->assertFalse(
            file_exists($srcContent)
        );
        $this->assertTrue(
            file_exists($dstContent)
        );
    }

    /**
     * Test 'Path' class 'move' method to move a directory to a different location
     *
     * @return void
     * @throws FileExistsException
     */
    public function testMoveWithDir(): void
    {
        $src = self::TEMP_TEST_DIR . "/some_dir";
        $srcContent = $src . DIRECTORY_SEPARATOR . "foo.txt";
        $dst = self::TEMP_TEST_DIR . "/some_other_dir";
        $dstContent = $dst . DIRECTORY_SEPARATOR . "foo.txt";

        mkdir($src);
        touch($srcContent);

        $path = new Path($src);
        $path->move($dst);

        $this->assertFalse(
            file_exists($srcContent)
        );
        $this->assertTrue(
            file_exists($dstContent)
        );
    }
}