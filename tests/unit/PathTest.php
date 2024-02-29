<?php

namespace Path\Tests\unit;

use http\Params;
use Path\BuiltinProxy;
use Path\Exception\FileExistsException;
use Path\Exception\FileNotFoundException;
use Path\Exception\IOException;
use Path\Path;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TestablePath extends Path {
    public function setBuiltin(BuiltinProxy $builtinProxy): void
    {
        $this->builtin = $builtinProxy;
    }

    public function cast(string|Path $path): Path
    {
        return parent::cast($path);
    }

    public function rrmdir(): bool {
        return $this->rrmdir();
    }

    public function getDirectoryIterator(): \DirectoryIterator
    {
        return parent::getDirectoryIterator();
    }
}

class PathTest extends TestCase
{
    private BuiltinProxy | MockObject $builtin;

    public function setUp(): void
    {
        $this->builtin = $this->getMockBuilder(BuiltinProxy::class)->getMock();
    }

    public function getMock(string $path, string $methodName): TestablePath | MockObject
    {
        $mock = $this
            ->getMockBuilder(TestablePath::class)
            ->setConstructorArgs([$path])
            ->setMethodsExcept(['__toString', 'setBuiltin', $methodName])
            ->getMock();
        $mock->setBuiltin($this->builtin);
        return $mock;
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

    public function testToString(): void
    {
        $path = new Path('/foo/bar');
        $this->assertEquals('/foo/bar', $path->__toString());
    }

    public function testPath()
    {
        $path = $this->getMock('bar', 'path');

        $this->assertEquals(
            'bar',
            $path->path()
        );
    }

    public function testEq(): void
    {
        $path = $this->getMock('bar', 'eq');
        $path
            ->method('path')
            ->willReturn('bar');

        $path2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path2
            ->method('path')
            ->willReturn('bar');

        $path3 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path3
            ->method('path')
            ->willReturn('/foo/bar');

        $path->method('cast')->willReturnMap(
            [
                [$path2, $path2],
                [$path3, $path3],
                ['bar', $path2],
                ['/foo/bar', $path3],
            ]
        );

        $this->assertTrue($path->eq($path2));
        $this->assertFalse($path->eq($path3));

        $this->assertTrue($path->eq('bar'));
        $this->assertFalse($path->eq('/foo/bar'));
    }

    /**
     * @throws IOException
     */
    public function testAbsPath(): void {
        $path = $this->getMock('bar', 'absPath');

        $this->builtin
            ->expects(self::once())
            ->method('realpath')
            ->with('bar')
            ->willReturn('/foo/bar');

        $newPath = $this->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $newPath->method('path')->willReturn('/foo/bar');

        $path->method('cast')->with('/foo/bar')->willReturn($newPath);

        $this->assertEquals(
            '/foo/bar',
            $path->absPath()->path()
        );
    }

    /**
     * @throws IOException
     */
    public function testRealPath()
    {
        $path = $this->getMock('bar', 'realpath');

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath
            ->method('eq')
            ->with('/foo/bar')
            ->willReturn(True);

        $path
            ->expects(self::once())
            ->method('absPath')
            ->willReturn($newPath);

        $this->assertTrue(
            $path->realpath()->eq('/foo/bar')
        );
    }

    public function testAccessFileExist(): void
    {
        $path = $this->getMock('bar', 'access');

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_readable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_writable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_executable')
            ->with('bar')
            ->willReturn(true);

        $this->assertTrue(
            $path->access(Path::F_OK)
        );
    }

    public function testAccessIsReadable(): void
    {
        $path = $this->getMock('bar', 'access');

        $this->builtin
            ->expects(self::never())
            ->method('file_exists')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('is_readable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_writable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_executable')
            ->with('bar')
            ->willReturn(true);

        $this->assertTrue(
            $path->access(Path::R_OK)
        );
    }

    public function testAccessIsWritable(): void
    {
        $path = $this->getMock('bar', 'access');

        $this->builtin
            ->expects(self::never())
            ->method('file_exists')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_readable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('is_writable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_executable')
            ->with('bar')
            ->willReturn(true);

        $this->assertTrue(
            $path->access(Path::W_OK)
        );
    }

    public function testAccessIsExecutable(): void
    {
        $path = $this->getMock('bar', 'access');

        $this->builtin
            ->expects(self::never())
            ->method('file_exists')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_readable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_writable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('is_executable')
            ->with('bar')
            ->willReturn(true);

        $this->assertTrue(
            $path->access(Path::X_OK)
        );
    }

    public function testAccessInvalidMode(): void
    {
        $path = $this->getMock('bar', 'access');

        $this->builtin
            ->expects(self::never())
            ->method('file_exists')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_readable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_writable')
            ->with('bar')
            ->willReturn(true);

        $this->builtin
            ->expects(self::never())
            ->method('is_executable')
            ->with('bar')
            ->willReturn(true);

        $this->expectException(\RuntimeException::class);

        $path->access(-1);
    }

    public function testATime(): void
    {
        $path = $this->getMock('bar', 'atime');

        $this->builtin
            ->expects(self::once())
            ->method('fileatime')
            ->with('bar')
            ->willReturn(1000);

        $date = '2000-01-01';

        $this->builtin
            ->expects(self::once())
            ->method('date')
            ->with('Y-m-d H:i:s', 1000)
            ->willReturn($date);

        $this->assertEquals(
            $date,
            $path->atime()
        );
    }

    public function testATimeError(): void
    {
        $path = $this->getMock('bar', 'atime');

        $this->builtin
            ->expects(self::once())
            ->method('fileatime')
            ->with('bar')
            ->willReturn(false);

        $this->assertEquals(
            null,
            $path->atime()
        );
    }

    public function testCTime(): void
    {
        $path = $this->getMock('bar', 'ctime');

        $this->builtin
            ->expects(self::once())
            ->method('filectime')
            ->with('bar')
            ->willReturn(1000);

        $date = '2000-01-01';

        $this->builtin
            ->expects(self::once())
            ->method('date')
            ->with('Y-m-d H:i:s', 1000)
            ->willReturn($date);

        $this->assertEquals(
            $date,
            $path->ctime()
        );
    }

    public function testCTimeError(): void
    {
        $path = $this->getMock('bar', 'ctime');

        $this->builtin
            ->expects(self::once())
            ->method('filectime')
            ->with('bar')
            ->willReturn(false);

        $this->assertEquals(
            null,
            $path->ctime()
        );
    }

    public function testMTime(): void
    {
        $path = $this->getMock('bar', 'mtime');

        $this->builtin
            ->expects(self::once())
            ->method('filemtime')
            ->with('bar')
            ->willReturn(1000);

        $date = '2000-01-01';

        $this->builtin
            ->expects(self::once())
            ->method('date')
            ->with('Y-m-d H:i:s', 1000)
            ->willReturn($date);

        $this->assertEquals(
            $date,
            $path->mtime()
        );
    }

    public function testMTimeError(): void
    {
        $path = $this->getMock('bar', 'mtime');

        $this->builtin
            ->expects(self::once())
            ->method('filemtime')
            ->with('bar')
            ->willReturn(false);

        $this->assertEquals(
            null,
            $path->mtime()
        );
    }

    public function testIsFile(): void
    {
        $path = $this->getMock('bar', 'isFile');

        $this->builtin
            ->expects(self::once())
            ->method('is_file')
            ->with('bar')
            ->willReturn(true);

        $this->assertTrue(
            $path->isFile()
        );
    }

    public function testIsDir(): void
    {
        $path = $this->getMock('bar', 'isDir');

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with('bar')
            ->willReturn(true);

        $this->assertTrue(
            $path->isDir()
        );
    }

    public function testExt(): void
    {
        $path = $this->getMock('bar.ext', 'ext');

        $this->builtin
            ->expects(self::once())
            ->method('pathinfo')
            ->with('bar.ext', PATHINFO_EXTENSION)
            ->willReturn('ext');

        $this->assertEquals(
            'ext',
            $path->ext()
        );
    }

    public function testBaseName(): void
    {
        $path = $this->getMock('bar.ext', 'basename');

        $this->builtin
            ->expects(self::once())
            ->method('pathinfo')
            ->with('bar.ext', PATHINFO_BASENAME)
            ->willReturn('bar.ext');

        $this->assertEquals(
            'bar.ext',
            $path->basename()
        );
    }

    public function testCD(): void
    {
        $path = $this->getMock('bar', 'cd');

        $this->builtin
            ->expects(self::once())
            ->method('chdir')
            ->with('foo')
            ->willReturn(true);

        $this->assertTrue(
            $path->cd('foo')
        );
    }

    public function testChDir(): void
    {
        $path = $this->getMock('bar', 'chdir');

        $path
            ->expects(self::once())
            ->method('cd')
            ->with('foo')
            ->willReturn(true);

        $this->assertTrue(
            $path->cd('foo')
        );
    }

    public function testName(): void
    {
        $path = $this->getMock('bar.ext', 'name');

        $this->builtin
            ->expects(self::once())
            ->method('pathinfo')
            ->with('bar.ext', PATHINFO_FILENAME)
            ->willReturn('bar');

        $this->assertEquals(
            'bar',
            $path->name()
        );
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMkDir(): void
    {
        $path = $this->getMock('foo', 'mkdir');
        $path->method('isDir')->willReturn(False);
        $path->method('isFile')->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('mkdir')
            ->with('foo', 0777, false)
            ->willReturn(true);

        $path->mkdir();
    }

    /**
     * @throws IOException
     */
    public function testMkDirAlreadyExist(): void
    {
        $path = $this->getMock('foo', 'mkdir');
        $path->method('isDir')->willReturn(True);

        $this->expectException(FileExistsException::class);

        $path->mkdir();
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMkDirAlreadyExistButRecursive(): void
    {
        $path = $this->getMock('foo', 'mkdir');
        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->expects(self::never())
            ->method('mkdir');

        $path->mkdir(0777, true);
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMkDirFileExists(): void
    {
        $path = $this->getMock('foo', 'mkdir');
        $path->method('isDir')->willReturn(False);
        $path->method('isFile')->willReturn(True);

        $this->expectException(FileExistsException::class);

        $path->mkdir();
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMkDirFileExistsButRecursive(): void
    {
        $path = $this->getMock('foo', 'mkdir');
        $path->method('isDir')->willReturn(False);
        $path->method('isFile')->willReturn(True);

        $this->expectException(FileExistsException::class);

        $path->mkdir(0777, true);
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMkDirIoError(): void
    {
        $path = $this->getMock('foo', 'mkdir');
        $path->method('isDir')->willReturn(False);
        $path->method('isFile')->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('mkdir')
            ->with('foo', 0777, false)
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->mkdir();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testDeleteIsFile(): void
    {
        $path = $this->getMock('foo', 'delete');
        $path->method('isFile')->willReturn(True);
        $path->method('isDir')->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('unlink')
            ->with('foo')
            ->willReturn(true);

        $path->delete();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testDeleteIsFileWithError(): void
    {
        $path = $this->getMock('foo', 'delete');
        $path->method('isFile')->willReturn(True);
        $path->method('isDir')->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('unlink')
            ->with('foo')
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->delete();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testDeleteIsDir(): void
    {
        $path = $this->getMock('foo', 'delete');
        $path->method('isFile')->willReturn(False);
        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('rmdir')
            ->with('foo')
            ->willReturn(true);

        $path->delete();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testDeleteIsDirWithError(): void
    {
        $path = $this->getMock('foo', 'delete');
        $path->method('isFile')->willReturn(False);
        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('rmdir')
            ->with('foo')
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->delete();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testDeleteNonExistentFile(): void
    {
        $path = $this->getMock('foo', 'delete');
        $path->method('isFile')->willReturn(False);
        $path->method('isDir')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('unlink');
        $this->builtin
            ->expects(self::never())
            ->method('rmdir');

        $this->expectException(FileNotFoundException::class);

        $path->delete();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopy()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);

        $destination = "/bar/foo2.ext";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $destination)
            ->willReturn(True);

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('eq')->with($destination)->willReturn(True);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $result = $path->copy($destination);

        $this->assertTrue(
            $result->eq($destination)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyFileDoesNotExist()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(False);

        $destination = "/bar/foo2.ext";

        $this->builtin
            ->expects(self::never())
            ->method('is_dir');

        $this->builtin
            ->expects(self::never())
            ->method('file_exists');

        $this->builtin
            ->expects(self::never())
            ->method('copy');

        $this->expectException(FileNotFoundException::class);

        $path->copy($destination);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyDestIsDir()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(True);

        $newDest = $destination . "/foo.ext";

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($newDest)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $newDest)
            ->willReturn(True);

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('eq')->with($newDest)->willReturn(True);

        $path->method('cast')->with($newDest)->willReturn($newPath);

        $result = $path->copy($destination);

        $this->assertTrue(
            $result->eq($newDest)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyFileAlreadyExists()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);

        $destination = "/bar/foo2.ext";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($destination)
            ->willReturn(True);

        $this->builtin
            ->expects(self::never())
            ->method('copy');

        $this->expectException(FileExistsException::class);

        $path->copy($destination);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyFileDestIsDirButFileAlreadyExists()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(True);

        $newDest = $destination . "/foo.ext";

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($newDest)
            ->willReturn(True);

        $this->builtin
            ->expects(self::never())
            ->method('copy');

        $this->expectException(FileExistsException::class);

        $path->copy($destination);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyFileWithError()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);

        $destination = "/bar/foo2.ext";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $destination)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->copy($destination);
    }

//    public function testCopyTree(): void {
//         TODO: implement
//    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMove()
    {
        $path = $this->getMock('foo.ext', 'move');

        $destination = "/bar/foo2.ext";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('rename')
            ->with('foo.ext', $destination)
            ->willReturn(True);

        $destinationPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $destinationPath->method('eq')->with($destination)->willReturn(True);

        $path->method('cast')->with($destination)->willReturn($destinationPath);

        $result = $path->move($destination);

        $this->assertTrue(
            $result->eq($destination)
        );
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMoveWithPath()
    {
        $path = $this->getMock('foo.ext', 'move');

        $destination = new Path("/bar/foo2.ext");

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('rename')
            ->with('foo.ext', $destination)
            ->willReturn(True);

        $destinationPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $destinationPath->method('eq')->with($destination)->willReturn(True);

        $path->method('cast')->with($destination)->willReturn($destinationPath);

        $result = $path->move($destination);

        $this->assertTrue(
            $result->eq($destination)
        );
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMoveDestIsDir()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(True);

        $newDestination = $destination . "/foo.ext";

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($newDestination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('rename')
            ->with('foo.ext', $newDestination)
            ->willReturn(True);

        $destinationPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $destinationPath->method('eq')->with($newDestination)->willReturn(True);

        $path->method('cast')->with($newDestination)->willReturn($destinationPath);

        $result = $path->move($destination);

        $this->assertTrue(
            $result->eq($newDestination)
        );
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMoveFileExist()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar/foo2.ext";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($destination)
            ->willReturn(True);

        $this->builtin
            ->expects(self::never())
            ->method('rename');

        $this->expectException(FileExistsException::class);

        $path->move($destination);
    }

    /**
     * @throws IOException
     * @throws FileExistsException
     */
    public function testMoveWithError()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar/foo2.ext";

        $this->builtin
            ->expects(self::once())
            ->method('is_dir')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with($destination)
            ->willReturn(False);

        $this->builtin
            ->expects(self::once())
            ->method('rename')
            ->with('foo.ext', $destination)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->move($destination);
    }

    /**
     * @throws IOException
     */
    public function testTouchWithNoTimestamps()
    {
        $path = $this->getMock('foo.ext', 'touch');

        $this->builtin
            ->expects(self::once())
            ->method('touch')
            ->with('foo.ext', null, null)
            ->willReturn(True);

        $path->touch();
    }

    /**
     * @throws IOException
     */
    public function testTouchWithTimestamps()
    {
        $path = $this->getMock('foo.ext', 'touch');

        $this->builtin
            ->expects(self::once())
            ->method('touch')
            ->with('foo.ext', 123, 456)
            ->willReturn(True);

        $path->touch(123, 456);
    }

    /**
     * @throws IOException
     */
    public function testTouchWithDatetimes()
    {
        $path = $this->getMock('foo.ext', 'touch');

        $datetime1 = $this->getMockBuilder(\DateTime::class)->getMock();
        $datetime1->method('getTimestamp')->willReturn(123);

        $datetime2 = $this->getMockBuilder(\DateTime::class)->getMock();
        $datetime2->method('getTimestamp')->willReturn(456);

        $this->builtin
            ->expects(self::once())
            ->method('touch')
            ->with('foo.ext', 123, 456)
            ->willReturn(True);

        $path->touch($datetime1, $datetime2);
    }

    /**
     * @throws IOException
     */
    public function testTouchWithError()
    {
        $path = $this->getMock('foo.ext', 'touch');

        $this->builtin
            ->expects(self::once())
            ->method('touch')
            ->with('foo.ext')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->touch();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testSize()
    {
        $path = $this->getMock('foo.ext', 'size');
        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('filesize')
            ->with('foo.ext')
            ->willReturn(123456);

        $this->assertEquals(
            123456,
            $path->size()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testSizeFileNotExist()
    {
        $path = $this->getMock('foo.ext', 'size');
        $path->method('isFile')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('filesize');

        $this->expectException(FileNotFoundException::class);

        $path->size();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testSizeFileWithError()
    {
        $path = $this->getMock('foo.ext', 'size');
        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('filesize')
            ->with('foo.ext')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->size();
    }

    public function testParent(): void
    {
        $path = $this->getMock('/foo/foo.ext', 'parent');

        $this->builtin
            ->expects(self::once())
            ->method('dirname')
            ->with('/foo/foo.ext')
            ->willReturn('/foo');

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->method('cast')->with('/foo')->willReturn($newPath);

        $newPath->method('eq')->with('/foo')->willReturn(True);

        $this->assertTrue(
            $path->parent()->eq('/foo')
        );
    }

    public function testDirName(): void
    {
        $path = $this->getMock('/foo/foo.ext', 'dirname');

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('eq')->with('/foo')->willReturn(True);

        $path
            ->expects(self::once())
            ->method('parent')
            ->willReturn($newPath);

        $this->assertTrue(
            $path->dirname()->eq('/foo')
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testDirs()
    {
        $path = $this->getMock('/foo', 'dirs');

        $this->builtin
            ->method('is_dir')
            ->willReturnMap(
                [
                    ['/foo', True],
                    ['/foo/file.ext', False],
                    ['/foo/dir1', True],
                    ['/foo/dir2', True],
                ]
            );

        $results = [
            '..',
            '.',
            'file.ext',
            'dir1',
            'dir2'
        ];

        $this->builtin
            ->expects(self::once())
            ->method('scandir')
            ->with('/foo')
            ->willReturn($results);

        $this->assertEquals(
            ['dir1', 'dir2'],
            $path->dirs()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testDirsIsNotDir()
    {
        $path = $this->getMock('/foo', 'dirs');

        $this->builtin
            ->method('is_dir')
            ->with('/foo')
            ->willReturn(False);

        $this->expectException(FileNotFoundException::class);

        $path->dirs();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testFiles()
    {
        $path = $this->getMock('/foo', 'files');

        $this->builtin
            ->method('is_dir')
            ->with('/foo')
            ->willReturn(True);

        $this->builtin
            ->method('is_file')
            ->willReturnMap(
                [
                    ['/foo/file1.ext', True],
                    ['/foo/file2.ext', True],
                    ['/foo/dir1', False],
                ]
            );

        $results = [
            '..',
            '.',
            'file1.ext',
            'file2.ext',
            'dir1'
        ];

        $this->builtin
            ->expects(self::once())
            ->method('scandir')
            ->with('/foo')
            ->willReturn($results);

        $this->assertEquals(
            ['file1.ext', 'file2.ext'],
            $path->files()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testFilesIsNotDir()
    {
        $path = $this->getMock('/foo', 'files');

        $this->builtin
            ->method('is_dir')
            ->with('/foo')
            ->willReturn(False);

        $this->expectException(FileNotFoundException::class);

        $path->files();
    }

//    public function testFnMatch(): void
//    {
//        //TODO: implements
//    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetContent(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getContent');

        $this->builtin
            ->method('is_file')
            ->with('/foo/file.ext')
            ->willReturn(True);

        $this->builtin
            ->method('file_get_contents')
            ->with('/foo/file.ext')
            ->willReturn('azerty');

        $this->assertEquals(
            'azerty',
            $path->getContent()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetContentFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getContent');

        $this->builtin
            ->method('is_file')
            ->with('/foo/file.ext')
            ->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('file_get_contents');

        $this->expectException(FileNotFoundException::class);

        $path->getContent();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetContentErrorWhileReading(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getContent');

        $this->builtin
            ->method('is_file')
            ->with('/foo/file.ext')
            ->willReturn(True);

        $this->builtin
            ->method('file_get_contents')
            ->with('/foo/file.ext')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->getContent();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testPutContent(): void
    {
        $path = $this->getMock('/foo/file.ext', 'putContent');

        $this->builtin
            ->method('is_file')
            ->with('/foo/file.ext')
            ->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('file_put_contents')
            ->with('/foo/file.ext', 'azerty')
            ->willReturn(6);

        $this->assertEquals(
            6,
            $path->putContent('azerty')
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testPutContentAndAppend(): void
    {
        $path = $this->getMock('/foo/file.ext', 'putContent');

        $this->builtin
            ->method('is_file')
            ->with('/foo/file.ext')
            ->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('file_put_contents')
            ->with('/foo/file.ext', 'azerty', FILE_APPEND)
            ->willReturn(6);

        $this->assertEquals(
            6,
            $path->putContent('azerty', True)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testPutContentFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'putContent');

        $this->builtin
            ->method('is_file')
            ->with('/foo/file.ext')
            ->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('file_put_contents');

        $this->expectException(FileNotFoundException::class);

        $path->putContent('azerty');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testPutContentErrorWhileWriting(): void
    {
        $path = $this->getMock('/foo/file.ext', 'putContent');

        $this->builtin
            ->method('is_file')
            ->with('/foo/file.ext')
            ->willReturn(True);

        $this->builtin
            ->method('file_put_contents')
            ->with('/foo/file.ext')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->putContent('azerty');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testPutLines(): void
    {
        $path = $this->getMock('/foo/file.ext', 'putLines');

        $lines = [
            'once upon a time',
            'a man and a spoon',
            'had a great time drinking coffee'
        ];

        $path
            ->expects(self::once())
            ->method('putContent')
            ->with(implode(PHP_EOL, $lines))
            ->willReturn(123);

        $this->assertEquals(
            123,
            $path->putLines($lines)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetPermissions(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getPermissions');
        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->method('fileperms')
            ->with('/foo/file.ext')
            ->willReturn(16895);

        $this->assertEquals(
            777,
            $path->getPermissions()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetPermissionsFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getPermissions');
        $path->method('isFile')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('fileperms');

        $this->expectException(FileNotFoundException::class);

        $path->getPermissions();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetPermissionsWithError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getPermissions');
        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->method('fileperms')
            ->with('/foo/file.ext')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->getPermissions();
    }

    /**
     * @throws FileNotFoundException|IOException
     */
    public function testSetPermissions(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setPermissions');

        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->method('chmod')
            ->with('/foo/file.ext', 0777)
            ->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('clearstatcache');

        $path->setPermissions(0777);
    }

    /**
     * @throws FileNotFoundException|IOException
     */
    public function testSetPermissionsFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setPermissions');

        $path->method('isFile')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('clearstatcache');

        $this->builtin
            ->expects(self::never())
            ->method('chmod');

        $this->expectException(FileNotFoundException::class);

        $path->setPermissions(0777);
    }

    /**
     * @throws FileNotFoundException
     */
    public function testSetPermissionsWithError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setPermissions');

        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('clearstatcache');

        $this->builtin
            ->method('chmod')
            ->with('/foo/file.ext', 0777)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->setPermissions(0777);
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testSetOwner(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setOwner');

        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('clearstatcache');

        $this->builtin
            ->method('chown')
            ->with('/foo/file.ext', 'user')
            ->willReturn(True);

        $this->builtin
            ->method('chgrp')
            ->with('/foo/file.ext', 'group')
            ->willReturn(True);

        $path->setOwner('user', 'group');
    }

    /**
     * @throws FileNotFoundException|IOException
     */
    public function testSetOwnerFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setOwner');

        $path->method('isFile')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('clearstatcache');

        $this->builtin
            ->expects(self::never())
            ->method('chown');

        $this->builtin
            ->expects(self::never())
            ->method('chgrp');

        $this->expectException(FileNotFoundException::class);

        $path->setOwner('user', 'group');
    }

    /**
     * @throws FileNotFoundException
     */
    public function testSetPermissionsWithChownError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setOwner');

        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('clearstatcache');

        $this->builtin
            ->method('chown')
            ->with('/foo/file.ext', 'user')
            ->willReturn(False);

        $this->builtin
            ->method('chgrp')
            ->with('/foo/file.ext', 'group')
            ->willReturn(True);

        $this->expectException(IOException::class);

        $path->setOwner('user', 'group');
    }

    /**
     * @throws FileNotFoundException
     */
    public function testSetPermissionsWithChGroupError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setOwner');

        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->method('chown')
            ->with('/foo/file.ext', 'user')
            ->willReturn(True);

        $this->builtin
            ->method('chgrp')
            ->with('/foo/file.ext', 'group')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->setOwner('user', 'group');
    }

    public function testExists(): void
    {
        $path = $this->getMock('/foo/file.ext', 'exists');

        $this->builtin
            ->expects(self::once())
            ->method('file_exists')
            ->with('/foo/file.ext')
            ->willReturn(True);

        $this->assertTrue(
            $path->exists()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGlob(): void {
        $path = $this->getMock('/foo', 'glob');
        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('glob')
            ->with('/foo/*')
            ->willReturn(['a', 'b', 'c']);

        $this->assertEquals(
            ['a', 'b', 'c'],
            array_map(
                function (Path $p) { return (string)$p; },
                $path->glob('*')
            )
        );
    }

    /**
     * @throws IOException
     */
    public function testGlobDirDoesNotExist(): void {
        $path = $this->getMock('/foo', 'glob');
        $path->method('isDir')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('glob');

        $this->expectException(FileNotFoundException::class);

        $path->glob('*');
    }

    /**
     * @throws FileNotFoundException
     */
    public function testGlobWithError(): void {
        $path = $this->getMock('/foo', 'glob');
        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('glob')
            ->with('/foo/*')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->glob('*');
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testRmDirNonRecursive(): void
    {
        $path = $this->getMock('/foo', 'rmdir');

        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->method('rmdir')
            ->with('/foo')
            ->willReturn(True);

        $path
            ->expects(self::never())
            ->method('rrmdir');

        $path->rmdir();
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testRmDirRecursive(): void
    {
        $path = $this->getMock('/foo', 'rmdir');

        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->expects(self::never())
            ->method('rmdir');

        $path
            ->method('rrmdir')
            ->willReturn(True);

        $path->rmdir(True);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testOpen(): void
    {
        $path = $this->getMock('/foo/file.ext', 'open');
        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('fopen')
            ->with('/foo/file.ext', 'r')
            ->willReturn('the_handle');

        $this->assertEquals(
            'the_handle',
            $path->open()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testOpenWithMode(): void
    {
        $path = $this->getMock('/foo/file.ext', 'open');
        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('fopen')
            ->with('/foo/file.ext', 'w')
            ->willReturn('the_handle');

        $this->assertEquals(
            'the_handle',
            $path->open('w')
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testOpenFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'open');
        $path->method('isFile')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('fopen');

        $this->expectException(FileNotFoundException::class);

        $path->open();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testOpenWithError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'open');
        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('fopen')
            ->with('/foo/file.ext', 'r')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->open();
    }

    /**
     * @throws \Throwable
     */
    public function testWith(): void
    {
        $path = $this->getMock('/foo/file.ext', 'with');
        $path->method('open')->with('r')->willReturn('the_handle');

        $callback = function ($handle) {
            $this->assertEquals('the_handle', $handle);
            return 'content';
        };

        $this->builtin
            ->expects(self::once())
            ->method('fclose')
            ->with('the_handle')
            ->willReturn(True);

        $this->assertEquals(
            'content',
            $path->with($callback)
        );
    }

    /**
     * @throws \Throwable
     */
    public function testWithWithMode(): void
    {
        $path = $this->getMock('/foo/file.ext', 'with');
        $path->method('open')->with('w+')->willReturn('the_handle');

        $callback = function ($handle) {
            $this->assertEquals('the_handle', $handle);
            return 'content';
        };

        $this->builtin
            ->expects(self::once())
            ->method('fclose')
            ->with('the_handle')
            ->willReturn(True);

        $this->assertEquals(
            'content',
            $path->with($callback, 'w+')
        );
    }

    /**
     * @throws \Throwable
     */
    public function testWithWithCallbackError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'with');
        $path->method('open')->with('w+')->willReturn('the_handle');

        $callback = function ($handle) {
            $this->assertEquals('the_handle', $handle);
            throw new \Exception('some_error');
        };

        $this->builtin
            ->expects(self::once())
            ->method('fclose')
            ->with('the_handle')
            ->willReturn(True);

        $this->expectException(\Exception::class);

        $path->with($callback, 'w+');
    }

    /**
     * @throws \Throwable
     */
    public function testWithWithCallbackErrorAndClosingError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'with');
        $path->method('open')->with('w+')->willReturn('the_handle');

        $callback = function ($handle) {
            $this->assertEquals('the_handle', $handle);
            throw new \Exception('some_error');
        };

        $this->builtin
            ->expects(self::once())
            ->method('fclose')
            ->with('the_handle')
            ->willReturn(False);

        $this->expectException(\Exception::class);
        $this->expectException(IOException::class);

        $path->with($callback, 'w+');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws \Throwable
     */
    public function testChunks(): void
    {
        $path = $this->getMock('/foo/file.ext', 'chunks');
        $path->method('open')->with('rb')->willReturn('the_handle');

        $this->builtin
            ->method('feof')
            ->with('the_handle')
            ->willReturnOnConsecutiveCalls(
                False,
                False,
                False,
                True
            );

        $this->builtin
            ->method('fread')
            ->with('the_handle', 8192)
            ->willReturnOnConsecutiveCalls(
                'abc',
                'def',
                'ghi'
            );

        $this->builtin
            ->expects(self::once())
            ->method('fclose')
            ->with('the_handle')
            ->willReturn(True);

        $this->assertEquals(
            ['abc', 'def', 'ghi'],
            iterator_to_array($path->chunks())
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws \Throwable
     */
    public function testChunksWithDifferentLength(): void
    {
        $path = $this->getMock('/foo/file.ext', 'chunks');
        $path->method('open')->with('rb')->willReturn('the_handle');

        $this->builtin
            ->method('feof')
            ->with('the_handle')
            ->willReturnOnConsecutiveCalls(
                False,
                False,
                False,
                True
            );

        $this->builtin
            ->method('fread')
            ->with('the_handle', 123)
            ->willReturnOnConsecutiveCalls(
                'abc',
                'def',
                'ghi'
            );

        $this->builtin
            ->expects(self::once())
            ->method('fclose')
            ->with('the_handle')
            ->willReturn(True);

        $this->assertEquals(
            ['abc', 'def', 'ghi'],
            iterator_to_array($path->chunks(123))
        );
    }

    /**
     * @throws \Throwable
     */
    public function testChunksWithClosingError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'chunks');
        $path->method('open')->with('rb')->willReturn('the_handle');

        $this->builtin
            ->method('feof')
            ->with('the_handle')
            ->willReturnOnConsecutiveCalls(
                False,
                False,
                False,
                True
            );

        $this->builtin
            ->method('fread')
            ->with('the_handle', 8192)
            ->willReturnOnConsecutiveCalls(
                'abc',
                'def',
                'ghi'
            );

        $this->builtin
            ->expects(self::once())
            ->method('fclose')
            ->with('the_handle')
            ->willReturn(False);

        $this->expectException(IOException::class);

        iterator_to_array($path->chunks());
    }

    public function testIsAbs(): void
    {
        $path = $this->getMock('/foo/file.ext', 'isAbs');

        $this->assertTrue(
            $path->isAbs()
        );
    }

    public function testIsAbsWithRelative(): void
    {
        $path = $this->getMock('foo/file.ext', 'isAbs');

        $this->assertFalse(
            $path->isAbs()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChmod(): void
    {
        $path = $this->getMock('foo/file.ext', 'chmod');

        $path
            ->expects(self::once())
            ->method('setPermissions')
            ->with('777');

        $path->chmod(777);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChown(): void
    {
        $path = $this->getMock('foo/file.ext', 'chown');

        $path
            ->expects(self::once())
            ->method('setOwner')
            ->with('user', 'group');

        $path->chown('user', 'group');
    }

    /**
     * @throws IOException
     */
    public function testChRoot(): void
    {
        $path = $this->getMock('/foo', 'chroot');

        $this->builtin
            ->expects(self::once())
            ->method('chroot')
            ->with('/foo')
            ->willReturn(True);

        $path->chroot();
    }

    /**
     * @throws IOException
     */
    public function testChRootWithError(): void
    {
        $path = $this->getMock('/foo', 'chroot');

        $this->builtin
            ->expects(self::once())
            ->method('chroot')
            ->with('/foo')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->chroot();
    }

    public function testIsLink(): void
    {
        $path = $this->getMock('/foo/file.ext', 'isLink');

        $this->builtin
            ->expects(self::once())
            ->method('is_link')
            ->with('/foo/file.ext')
            ->willReturn(True);

        $this->assertTrue(
            $path->isLink()
        );
    }

    public function testIsLinkIsNot(): void
    {
        $path = $this->getMock('/foo/file.ext', 'isLink');

        $this->builtin
            ->expects(self::once())
            ->method('is_link')
            ->with('/foo/file.ext')
            ->willReturn(False);

        $this->assertFalse(
            $path->isLink()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIterDir(): void
    {
        $path = $this->getMock('/foo', 'iterDir');
        $path->method('isDir')->willReturn(True);

        $fileInfo1 = $this->getMockBuilder(\DirectoryIterator::class)->disableOriginalConstructor()->getMock();
        $fileInfo1->expects(self::once())->method('isDot')->willReturn(True);
        $fileInfo1->expects(self::never())->method('getFilename');

        $fileInfo2 = $this->getMockBuilder(\DirectoryIterator::class)->disableOriginalConstructor()->getMock();
        $fileInfo2->expects(self::once())->method('isDot')->willReturn(False);
        $fileInfo2->expects(self::once())->method('getFilename')->willReturn('file1.ext');

        $fileInfo3 = $this->getMockBuilder(\DirectoryIterator::class)->disableOriginalConstructor()->getMock();
        $fileInfo3->expects(self::once())->method('isDot')->willReturn(False);
        $fileInfo3->expects(self::once())->method('getFilename')->willReturn('file2.ext');

        $result = [$fileInfo1, $fileInfo2, $fileInfo3];

        $directoryIterator = $this->getMockBuilder(\DirectoryIterator::class)->disableOriginalConstructor()->getMock();
        $directoryIterator->method('current')->willReturnCallback(function () use (&$result) {
            return current($result);
        });
        $directoryIterator->method('key')->willReturnCallback(function () use (&$result) {
            return key($result);
        });
        $directoryIterator->method('next')->willReturnCallback(function () use (&$result) {
            return next($result);
        });
        $directoryIterator->method('valid')->willReturnCallback(function () use (&$result) {
            $key = key($result);
            return ($key !== NULL);
        });

        $path
            ->method('getDirectoryIterator')
            ->willReturn($directoryIterator);

        $this->assertEquals(
            ['file1.ext', 'file2.ext'],
            iterator_to_array($path->iterDir())
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIterDirDirDoesNotExist(): void
    {
        $path = $this->getMock('/foo', 'iterDir');
        $path->method('isDir')->willReturn(False);

        $this->expectException(FileNotFoundException::class);

        iterator_to_array($path->iterDir());
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testLink(): void
    {
        $path = $this->getMock('/foo/file.ext', 'link');
        $path->method('isFile')->willReturn(True);

        $target = $this
            ->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $target->method('isFile')->willReturn(False);
        $target->method('__toString')->willReturn('/bar/link.ext');

        $path->method('cast')->with($target)->willReturn($target);

        $this->builtin
                ->expects(self::once())
                ->method('link')
                ->with('/foo/file.ext', '/bar/link.ext')
                ->willReturn(True);

        $path->link($target);
    }


    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testLinkWithStringTarget(): void
    {
        $path = $this->getMock('/foo/file.ext', 'link');
        $path->method('isFile')->willReturn(True);

        $target = $this
            ->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $target->method('isFile')->willReturn(False);
        $target->method('__toString')->willReturn('/bar/link.ext');

        $path->method('cast')->with('/bar/link.ext')->willReturn($target);

        $this->builtin
            ->expects(self::once())
            ->method('link')
            ->with('/foo/file.ext', '/bar/link.ext')
            ->willReturn(True);

        $path->link($target);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testLinkFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'link');
        $path->method('isFile')->willReturn(False);

        $this->builtin
                ->expects(self::never())
                ->method('link');

        $this->expectException(FileNotFoundException::class);

        $path->link('/bar/link.ext');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testLinkTargetExists(): void
    {
        $path = $this->getMock('/foo/file.ext', 'link');
        $path->method('isFile')->willReturn(True);

        $target = $this
            ->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $target->method('isFile')->willReturn(True);

        $path->method('cast')->with($target)->willReturn($target);

        $this->builtin
            ->expects(self::never())
            ->method('link');

        $this->expectException(FileExistsException::class);

        $path->link($target);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testLinkWithError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'link');
        $path->method('isFile')->willReturn(True);

        $target = $this
            ->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $target->method('isFile')->willReturn(False);
        $target->method('__toString')->willReturn('/bar/link.ext');

        $path->method('cast')->with($target)->willReturn($target);

        $this->builtin
            ->expects(self::once())
            ->method('link')
            ->with('/foo/file.ext', '/bar/link.ext')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->link($target);
    }

    /**
     * @throws IOException
     */
    public function testLStat(): void
    {
        $path = $this->getMock('foo/file.ext', 'lstat');

        $this->builtin
            ->expects(self::once())
            ->method('lstat')
            ->willReturn(['a', 'b', 'c']);

        $this->assertEquals(
            ['a', 'b', 'c'],
            $path->lstat()
        );
    }

    /**
     * @throws IOException
     */
    public function testLStatWithError(): void
    {
        $path = $this->getMock('foo/file.ext', 'lstat');

        $this->builtin
            ->expects(self::once())
            ->method('lstat')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->lstat();
    }

    public function testParts(): void
    {
        $path = $this->getMock('foo/bar/file.ext', 'parts');

        $this->assertEquals(
            ['foo', 'bar', 'file.ext'],
            $path->parts()
        );
    }

    public function testPartsWithLeadingSlash(): void
    {
        $path = $this->getMock('/foo/bar/file.ext', 'parts');

        $this->assertEquals(
            ['/', 'foo', 'bar', 'file.ext'],
            $path->parts()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetRelativePath(): void
    {
        $path = $this->getMock('./bar/file.ext', 'getRelativePath');
        $path->method('exists')->willReturn(True);

        $absPath = $this
            ->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $absPath->method('__toString')->willReturn('/foo/bar/file.ext');

        $path->method('absPath')->willReturn($absPath);

        $basePath = '/other/path';

        $this->builtin
            ->expects(self::once())
            ->method('realpath')
            ->with($basePath)
            ->willReturn('/other/path');

        $this->assertEquals(
            '../../foo/bar/file.ext',
            $path->getRelativePath($basePath)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetRelativePathFileDoesNotExist(): void
    {
        $path = $this->getMock('./bar/file.ext', 'getRelativePath');
        $path->method('exists')->willReturn(False);

        $this->expectException(FileNotFoundException::class);

        $path->getRelativePath('/other/path');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetRelativePathTargetDoesNotExist(): void
    {
        $path = $this->getMock('./bar/file.ext', 'getRelativePath');
        $path->method('exists')->willReturn(True);

        $absPath = $this
            ->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $absPath->method('__toString')->willReturn('/foo/bar/file.ext');

        $path->method('absPath')->willReturn($absPath);

        $basePath = '/other/path';

        $this->builtin
            ->expects(self::once())
            ->method('realpath')
            ->with($basePath)
            ->willReturn(False);

        $this->expectException(FileNotFoundException::class);

        $path->getRelativePath($basePath);
    }
}