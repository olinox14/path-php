<?php

namespace Path\Tests\functionnal;

use Path\Exception\FileExistsException;
use Path\Exception\FileNotFoundException;
use Path\Exception\IOException;
use Path\Path;
use PHPUnit\Framework\TestCase;

// TODO: tested args should be both typed Path and string
class PathTest
{
    const TEMP_TEST_DIR = __DIR__ . "/temp";
    // TODO: consider using sys_get_temp_dir()

    protected Path $pathClass;

    public function setUp(): void
    {
        clearstatcache();
        mkdir(self::TEMP_TEST_DIR, 0777, true);
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

        Path::copytree($src, $dst);

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

        Path::copytree($src, $dst);
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

        Path::copytree($src, $dst);
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

    /**
     * Test 'Path' class 'touch' method to create a file that does not exist
     *
     * @return void
     */
    public function testTouchFileDoesNotExist(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        $path = new Path($src);

        $this->assertFalse(is_file($src));
        $path->touch();
        $this->assertTrue(is_file($src));
    }

    public function testTouchFileExistsNothingChange(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $this->assertTrue(is_file($src));

        $path = new Path($src);
        $path->touch();

        $this->assertTrue(is_file($src));
    }

    public function testTouchFileExistsUpdateMtimeWithInt(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $path = new Path($src);
        $timestamp = 1000;

        $path->touch($timestamp);

        $this->assertEquals(
            $timestamp,
            filemtime($src)
        );
    }

    public function testTouchFileExistsUpdateMtimeWithDatetime(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $path = new Path($src);
        $dateTime = new \DateTime("2000-01-01");

        $path->touch($dateTime);

        $this->assertEquals(
            $dateTime->getTimestamp(),
            filemtime($src)
        );
    }

    public function testTouchFileExistsUpdateAtimeWithInt(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $path = new Path($src);
        $timestamp = 1000;

        $path->touch($timestamp, $timestamp);

        $this->assertEquals(
            $timestamp,
            fileatime($src)
        );
    }

    public function testTouchFileExistsUpdateAtimeWithDatetime(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $path = new Path($src);
        $dateTime = new \DateTime("2000-01-01");

        $path->touch($dateTime, $dateTime);

        $this->assertEquals(
            $dateTime->getTimestamp(),
            fileatime($src)
        );
    }

    public function testLastModified(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        $dateTime = new \DateTime("2000-01-01");

        touch($src, $dateTime->getTimestamp());

        $path = new Path($src);

        $this->assertEquals(
            $dateTime->getTimestamp(),
            $path->lastModified()
        );
    }

    /**
     * Test 'Path' class 'size' method to get the size of the file
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function testSize(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";

        file_put_contents($src, "nova");

        $path = new Path($src);

        $this->assertEquals(
            4,
            $path->size()
        );
    }

    /**
     * Test 'Path' class 'size' method to get the size of the file
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function testSizeNotExistingFile(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";

        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File does not exist : " . $src);

        $path->size();
    }

    public function testParent(): void
    {
        $this->assertEquals(
            '/foo/bar',
            (new Path('/foo/bar/baz'))->parent()
        );
        $this->assertEquals(
            '/foo/bar',
            (new Path('/foo/bar/baz.txt'))->parent()
        );
        $this->assertEquals(
            '/',
            (new Path('/foo'))->parent()
        );
    }

    /**
     * Test 'Path' class 'getContent' method to retrieve the content of a file
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function testGetContent(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";

        file_put_contents($src, "nova");

        $path = new Path($src);

        $this->assertEquals(
            "nova",
            $path->getContent()
        );
    }

    /**
     * Test 'Path' class 'getContent' method to get the content of a non-existing file
     *
     * @throws FileNotFoundException If the file does not exist
     */
    public function testGetContentNotExistingFile(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";

        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File does not exist : " . $src);

        $path->getContent();
    }

    public function testPutContent(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";

        $path = new Path($src);
        $path->putContent("ocarina");

        $this->assertEquals(
            "ocarina",
            file_get_contents($src)
        );
    }

    public function testAppendContent(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);
        file_put_contents($src, "oca");

        $path = new Path($src);
        $path->appendContent("rina");

        $this->assertEquals(
            "ocarina",
            file_get_contents($src)
        );
    }

    /**
     * Test 'Path' class 'getPermissions' method to retrieve the file permissions
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function testGetPermissions(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);
        chmod($src, 0777);

        $path = new Path($src);
        $this->assertEquals(
            777,
            $path->getPermissions()
        );
    }

    /**
     * Test 'Path' class 'getPermissions' method to retrieve file permissions
     * @throws FileNotFoundException
     */
    public function testGetPermissionsAlt(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);
        chmod($src, 0755);

        $path = new Path($src);

        $this->assertEquals(
            755,
            $path->getPermissions()
        );
    }

    /**
     * Test 'Path' class 'getPermissions' method to retrieve file permissions
     * @throws FileNotFoundException
     */
    public function testGetPermissionsFileNotExists(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";

        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File or dir does not exist : " . $src);

        $path->getPermissions();
    }

    /**
     * Test 'Path' class 'setPermissions' method to change the permissions of a file
     * @throws FileNotFoundException
     */
    public function testSetPermissions(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);
        chmod($src, 0777);

        $path = new Path($src);
        $result = $path->setPermissions(0666);

        $this->assertTrue($result);

        $this->assertEquals(
            '0666',
            substr(sprintf('%o', fileperms($src)), -4)
        );
    }

    /**
     * Test 'Path' class 'setPermissions' method when file does not exist
     *
     * @throws FileNotFoundException If the file does not exist
     */
    public function testSetPermissionsFileNotExists(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("File or dir does not exist : " . $src);

        $path->setPermissions(777);
    }

    /**
     * Test 'Path' class 'exists' method to check existence of the file
     *
     * @return void
     */
    public function testExistsExistingFile(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $path = new Path($src);

        $this->assertTrue(
            $path->exists()
        );
    }

    /**
     * Test 'Path' class 'exists' method to check existence of the directory
     *
     * @return void
     */
    public function testExistsExistingDir(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo";
        mkdir($src);

        $path = new Path($src);

        $this->assertTrue(
            $path->exists()
        );
    }

    /**
     * Test 'Path' class 'exists' method to check existence of a non-existing file
     *
     * @return void
     */
    public function testExistsNonExistingFile(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";

        $path = new Path($src);

        $this->assertFalse(
            $path->exists()
        );
    }

    /**
     * Test 'Path' class 'glob' method to match and retrieve file names in a directory
     *
     * @return void
     */
    public function testGlob(): void
    {
        $src = self::TEMP_TEST_DIR;
        touch($src . "/foo.txt");
        touch($src . "/bar.txt");
        touch($src . "/pic.png");

        $path = new Path($src);

        $results = [];

        foreach ($path->glob('*.txt') as $filename) {
            $results[] = (string)$filename;
        }

        $this->assertEquals(
            ['bar.txt', 'foo.txt'],
            $results
        );
    }

    /**
     * Test 'Path' class 'rmdir' method to remove a directory and its contents
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function testRmDir(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo";
        mkdir($src);

        $path = new Path($src);
        $path->rmdir();

        $this->assertFalse(
            is_dir($src)
        );
    }

    public function testRmDirIsFile(): void {
        $src = self::TEMP_TEST_DIR . "/foo";
        touch($src);

        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage($src . " is not a directory");

        $path->rmdir();
    }

    public function testRmDirNonExistingDir(): void {
        $src = self::TEMP_TEST_DIR . "/foo";

        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage($src . " is not a directory");

        $path->rmdir();
    }

    /**
     * Test 'Path' class 'rmdir' method to remove a directory and its contents recursively
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function testRmDirRecursive(): void {
        $src = self::TEMP_TEST_DIR . "/foo/bar";
        mkdir($src, 0777, true);
        touch($src . "/file.txt");

        $path = new Path($src);
        $path->rmdir(true);

        $this->assertFalse(
            is_dir($src)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testOpen(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $path = new Path($src);

        $handle = $path->open();

        $this->assertIsResource($handle);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testOpenNotExistingFile(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";

        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(self::TEMP_TEST_DIR . "/foo.txt is not a file");

        $path->open();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testOpenIsDir(): void {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(self::TEMP_TEST_DIR . "/foo.txt is not a file");

        $path->open();
    }

    public function testIsAbs() {
        $path1 = new Path('/absolute/path');
        $this->assertTrue($path1->isAbs());

        $path2 = new Path('relative/path');
        $this->assertFalse($path2->isAbs());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testChmod(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        $path = $this
            ->getMockBuilder(Path::class)
            ->onlyMethods(['setPermissions'])
            ->setConstructorArgs([$src])
            ->getMock();

        $path->expects(self::once())->method('setPermissions')->with(0770);

        $path->chmod(0770);
    }

    /**
     * @throws FileNotFoundException
     */
    public function testChown(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        $path = $this
            ->getMockBuilder(Path::class)
            ->onlyMethods(['setOwner'])
            ->setConstructorArgs([$src])
            ->getMock();

        $path->expects(self::once())->method('setOwner')->with(2, 2);

        $path->chown(2, 2);
    }

    public function testIsLink(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);
        $path1 = new Path($src);

        $target = self::TEMP_TEST_DIR . "/link.txt";
        symlink($src, $target);
        $path2 = new Path($target);

        $this->assertFalse($path1->isLink());
        $this->assertTrue($path2->isLink());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIterDir(): void {
        $dir = self::TEMP_TEST_DIR . "/foo";
        mkdir($dir);

        $file1 = $dir . "/file1.ext";
        touch($file1);

        $file2 = $dir . "/file2.ext";
        touch($file2);

        $subDir = $dir . "/sub_dir";
        mkdir($subDir);

        $path = new Path($dir);

        // TODO: test the generator behavior without array conversion
        $this->assertEquals(
            ['file1.ext', 'file2.ext', 'sub_dir'],
            iterator_to_array($path->iterDir())
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIterDirNotExisting(): void {
        $dir = self::TEMP_TEST_DIR . "/foo";
        $path = new Path($dir);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage($dir . " is not a directory");

        iterator_to_array($path->iterDir());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIterDirIsFile(): void {
        $src = self::TEMP_TEST_DIR . "/foo";
        touch($src);
        $path = new Path($src);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage($src . " is not a directory");

        iterator_to_array($path->iterDir());
    }

    public function testLink(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $dst = self::TEMP_TEST_DIR . "/link.txt";

        $path = new Path($src);

        $path->link($dst);
        $this->assertTrue(file_exists($dst));
    }

    public function testLinkWithPath(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        touch($src);

        $dst = self::TEMP_TEST_DIR . "/link.txt";

        $path = new Path($src);

        $path->link(new Path($dst));
        $this->assertTrue(file_exists($dst));
    }

    public function testLstat(): void
    {
        $src = self::TEMP_TEST_DIR . "/foo.txt";
        file_put_contents($src,'foo');

        $path = new Path($src);

        self::assertSame(
            lstat($src),
            $path->lstat()
        );
    }

    public function testParts(): void
    {
        $path = new Path("foo/bar/my_file.txt");

        self::assertEquals(
            ['foo', 'bar', 'my_file.txt'],
            $path->parts()
        );
    }

    public function testPartsWithLeadingSeparator(): void
    {
        $path = new Path("/foo/bar/my_file.txt");

        self::assertEquals(
            ['/', 'foo', 'bar', 'my_file.txt'],
            $path->parts()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testGetRelativePath()
    {
        $src = self::TEMP_TEST_DIR . "/foo";
        touch($src);

        $path = new Path($src);

        $this->assertSame(
            "foo",
            $path->getRelativePath(self::TEMP_TEST_DIR)
        );
    }
}