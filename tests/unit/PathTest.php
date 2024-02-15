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
}