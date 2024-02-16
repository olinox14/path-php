<?php

namespace Path\Tests\unit;

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
}