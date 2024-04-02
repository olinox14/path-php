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
    private BuiltinProxy|MockObject $builtin;

    public function setUp(): void
    {
        $this->builtin = $this->getMockBuilder(BuiltinProxy::class)->getMock();
    }

    public function getMock(string $path, string $methodName): TestablePath|MockObject
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
            Path::join('/home', 'user', '', 'documents')
        );

        // Absolute path passed in $parts
        $this->assertEquals(
            '/user/documents',
            Path::join('home/', '/user', 'documents')
        );
    }

    public function testCastWithString(): void
    {
        $path = $this->getMock('bar', 'cast');

        $strPath = "/foo/file.ext";

        $newPath = $path->cast($strPath);

        $this->assertEquals(
            '/foo/file.ext',
            $newPath->path()
        );
    }

    public function testCastWithPath(): void
    {
        $path = $this->getMock('bar', 'cast');

        $otherPath = new Path("/foo/file.ext");

        $newPath = $path->cast($otherPath);

        $this->assertEquals(
            '/foo/file.ext',
            $newPath->path()
        );
    }

    public function testToString(): void
    {
        $path = new Path('/foo/bar');

        $this->assertEquals(
            '/foo/bar',
            $path->__toString()
        );
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

    public function testAppendOnePart(): void
    {
        $path = new Path('/home');

        $this->assertEquals(
            Path::join('/home', 'user'),
            $path->append('user')->path()
        );
    }

    public function testAppendMultiplePart(): void {
        $path = new Path('/home');

        $this->assertEquals(
            Path::join('/home', 'user', '', 'documents'),
            $path->append('user', '', 'documents')->path()
        );
    }

    public function testAppendAbsoluteInParts(): void {
        $path = new Path('home');

        $this->assertEquals(
            Path::join('home', '/user', 'documents'),
            $path->append('/user', 'documents')->path()
        );
    }

    /**
     * @throws IOException
     */
    public function testAbsPath(): void {
        $path = $this->getMock('./file.ext', 'absPath');
        $path->method('path')->willReturn('./file.ext');

        $this->builtin
            ->expects(self::once())
            ->method('realpath')
            ->with('./file.ext')
            ->willReturn('/foo/file.ext');

        $newPath = $this->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $newPath->method('path')->willReturn('/foo/file.ext');

        $path->method('cast')->with('/foo/file.ext')->willReturn($newPath);

        $this->assertEquals(
            '/foo/file.ext',
            $path->absPath()->path()
        );
    }

    /**
     * @throws IOException
     */
    public function testAbsPathWithError(): void {
        $path = $this->getMock('./file.ext', 'absPath');
        $path->method('path')->willReturn('./file.ext');

        $this->builtin
            ->expects(self::once())
            ->method('realpath')
            ->with('./file.ext')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->absPath()->path();
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

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testATime(): void
    {
        $path = $this->getMock('bar', 'atime');
        $path->method('exists')->willReturn(True);

        $ts = 1000;

        $this->builtin
            ->expects(self::once())
            ->method('fileatime')
            ->with('bar')
            ->willReturn($ts);

        $this->assertEquals(
            $ts,
            $path->atime()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testATimeFileDoesNotExist(): void
    {
        $path = $this->getMock('bar', 'atime');
        $path->method('exists')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('fileatime');

        $this->expectException(FileNotFoundException::class);

        $path->atime();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testATimeError(): void
    {
        $path = $this->getMock('bar', 'atime');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('fileatime')
            ->with('bar')
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->atime();
    }

    public function testCTime(): void
    {
        $path = $this->getMock('bar', 'ctime');
        $path->expects(self::once())->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('filectime')
            ->with('bar')
            ->willReturn(1000);

        $this->assertEquals(
            1000,
            $path->ctime()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testCTimeFileDoesNotExist(): void
    {
        $path = $this->getMock('bar', 'ctime');
        $path->method('exists')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('filectime');

        $this->expectException(FileNotFoundException::class);

        $path->ctime();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testCTimeError(): void
    {
        $path = $this->getMock('bar', 'ctime');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('filectime')
            ->with('bar')
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->ctime();
    }

    public function testMTime(): void
    {
        $path = $this->getMock('bar', 'mtime');
        $path->expects(self::once())->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('filemtime')
            ->with('bar')
            ->willReturn(1000);

        $this->assertEquals(
            1000,
            $path->mtime()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testMTimeFileDoesNotExist(): void
    {
        $path = $this->getMock('bar', 'mtime');
        $path->method('exists')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('filemtime');

        $this->expectException(FileNotFoundException::class);

        $path->mtime();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testMTimeError(): void
    {
        $path = $this->getMock('bar', 'mtime');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('filemtime')
            ->with('bar')
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->mtime();
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

    public function testExtNoExt(): void
    {
        $path = $this->getMock('bar', 'ext');

        $this->builtin
            ->expects(self::once())
            ->method('pathinfo')
            ->with('bar', PATHINFO_EXTENSION)
            ->willReturn('');

        $this->assertEquals(
            '',
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

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testCD(): void
    {
        $path = $this->getMock('bar', 'cd');
        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chdir')
            ->with('bar')
            ->willReturn(True);

        $path->cd();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testCDDirDoesNotExist(): void
    {
        $path = $this->getMock('bar', 'cd');
        $path->method('exists')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('chdir');

        $this->expectException(FileNotFoundException::class);

        $path->cd();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testCDWithError(): void
    {
        $path = $this->getMock('bar', 'cd');
        $path->method('isDir')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chdir')
            ->with('bar')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->cd();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChDir(): void
    {
        $path = $this->getMock('bar', 'chdir');

        $path
            ->expects(self::once())
            ->method('cd');

        $path->chdir();
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

    public function testNormCase(): void
    {
        $path = $this->getMock('', 'normCase');
        $path->method('path')->willReturn('/Foo\\bar');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->expects(self::once())->method('cast')->with('/foo/bar')->willReturn($normed);

        $this->assertEquals(
            $normed,
            $path->normCase()
        );

    }

    public function testNormPath(): void {
        $path = $this->getMock('', 'normPath');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed->method('path')->willReturn('/foo/bar');
        $path->expects(self::once())->method('normCase')->willReturn($normed);

        $normed2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed2->method('path')->willReturn('/foo/bar');
        $path->expects(self::once())->method('cast')->with('/foo/bar')->willReturn($normed2);

        $this->assertEquals(
            '/foo/bar',
            $path->normPath()->path()
        );
    }

    public function testNormPathNoSep(): void {
        $path = $this->getMock('', 'normPath');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed->method('path')->willReturn('foo');
        $path->expects(self::once())->method('normCase')->willReturn($normed);

        $normed2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed2->method('path')->willReturn('foo');
        $path->expects(self::once())->method('cast')->with('foo')->willReturn($normed2);

        $this->assertEquals(
            'foo',
            $path->normPath()->path()
        );
    }

    public function testNormPathIsRoot(): void {
        $path = $this->getMock('', 'normPath');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed->method('path')->willReturn('/');
        $path->expects(self::once())->method('normCase')->willReturn($normed);

        $normed2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed2->method('path')->willReturn('/');
        $path->expects(self::once())->method('cast')->with('/')->willReturn($normed2);

        $this->assertEquals(
            '/',
            $path->normPath()->path()
        );
    }

    public function testNormPathIsCurrent(): void {
        $path = $this->getMock('', 'normPath');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed->method('path')->willReturn('..');
        $path->expects(self::once())->method('normCase')->willReturn($normed);

        $normed2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed2->method('path')->willReturn('..');
        $path->expects(self::once())->method('cast')->with('..')->willReturn($normed2);

        $this->assertEquals(
            '..',
            $path->normPath()->path()
        );
    }

    public function testNormPathIsParent(): void {
        $path = $this->getMock('', 'normPath');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed->method('path')->willReturn('.');
        $path->expects(self::once())->method('normCase')->willReturn($normed);

        $normed2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed2->method('path')->willReturn('.');
        $path->expects(self::once())->method('cast')->with('.')->willReturn($normed2);

        $this->assertEquals(
            '.',
            $path->normPath()->path()
        );
    }

    public function testNormPathIsEmpty(): void {
        $path = $this->getMock('', 'normPath');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed->method('path')->willReturn('');
        $path->expects(self::once())->method('normCase')->willReturn($normed);

        $normed2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed2->method('path')->willReturn('.');
        $path->expects(self::once())->method('cast')->with('.')->willReturn($normed2);

        $this->assertEquals(
            '.',
            $path->normPath()->path()
        );
    }

    public function testNormPathWithParentPart(): void {
        $path = $this->getMock('', 'normPath');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed->method('path')->willReturn('/foo/../bar');
        $path->expects(self::once())->method('normCase')->willReturn($normed);

        $normed2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed2->method('path')->willReturn('/bar');
        $path->expects(self::once())->method('cast')->with('/bar')->willReturn($normed2);

        $this->assertEquals(
            '/bar',
            $path->normPath()->path()
        );
    }

    public function testNormPathWithLeadingParentPart(): void {
        $path = $this->getMock('', 'normPath');

        $normed = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed->method('path')->willReturn('../foo/../bar');
        $path->expects(self::once())->method('normCase')->willReturn($normed);

        $normed2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $normed2->method('path')->willReturn('../bar');
        $path->expects(self::once())->method('cast')->with('../bar')->willReturn($normed2);

        $this->assertEquals(
            '../bar',
            $path->normPath()->path()
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
        $path->method('isLink')->willReturn(False);

        $destination = "/bar/foo2.ext";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('eq')->with($destination)->willReturn(True);
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('isFile')->willReturn(False);
        $newPath->method('path')->willReturn($destination);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $destination)
            ->willReturn(True);

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
        $path->method('isLink')->willReturn(False);
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar";

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(True);

        $newDest = $destination . "/foo.ext";

        $extendedNewPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $extendedNewPath->method('path')->willReturn($newDest);
        $extendedNewPath->method('isFile')->willReturn(False);

        $newPath
            ->expects(self::once())
            ->method('append')
            ->with('foo.ext')
            ->willReturn($extendedNewPath);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $newDest)
            ->willReturn(True);

        $result = $path->copy($destination);

        $this->assertEquals(
            $result->path(),
            $extendedNewPath->path()
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
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('eq')->with($destination)->willReturn(True);
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('isFile')->willReturn(False);
        $newPath->method('path')->willReturn($destination);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $destination)
            ->willReturn(True);

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
    public function testCopyFileAlreadyExistsNotErasing()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);

        $destination = "/bar/foo2.ext";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('isFile')->willReturn(True);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::never())
            ->method('copy');

        $this->expectException(FileExistsException::class);

        $path->copy($destination, false, false);
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
        $path->method('isLink')->willReturn(False);
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar";

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(True);

        $newDest = $destination . "/foo.ext";

        $extendedNewPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $extendedNewPath->method('path')->willReturn($newDest);
        $extendedNewPath->method('isFile')->willReturn(False);

        $newPath
            ->expects(self::once())
            ->method('append')
            ->with('foo.ext')
            ->willReturn($extendedNewPath);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $newDest)
            ->willReturn(True);

        $result = $path->copy($destination);

        $this->assertEquals(
            $result->path(),
            $extendedNewPath->path()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyFileDestIsDirButFileAlreadyExistsNotErasing()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);
        $path->method('isLink')->willReturn(False);
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar";

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(True);

        $newDest = $destination . "/foo.ext";

        $extendedNewPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $extendedNewPath->method('path')->willReturn($newDest);
        $extendedNewPath->method('isFile')->willReturn(True);

        $newPath
            ->expects(self::once())
            ->method('append')
            ->with('foo.ext')
            ->willReturn($extendedNewPath);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::never())
            ->method('copy');

        $this->expectException(FileExistsException::class);

        $path->copy($destination, false, false);
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
        $path->method('isLink')->willReturn(False);

        $destination = "/bar/foo.ext";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('isFile')->willReturn(False);
        $newPath->method('path')->willReturn($destination);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $destination)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->copy($destination);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyIsLinkWithFollow()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);
        $path->method('isLink')->willReturn(True);

        $destination = "/bar/foo2.ext";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('eq')->with($destination)->willReturn(True);
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('isFile')->willReturn(False);
        $newPath->method('path')->willReturn($destination);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('copy')
            ->with('foo.ext', $destination)
            ->willReturn(True);

        $result = $path->copy($destination, True);

        $this->assertTrue(
            $result->eq($destination)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyIsLinkNoFollow()
    {
        $path = $this->getMock('foo.ext', 'copy');
        $path->method('isFile')->willReturn(True);
        $path->method('isLink')->willReturn(True);

        $destination = "/bar/foo2.ext";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('isFile')->willReturn(False);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $targetPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->method('readLink')->willReturn($targetPath);

        $newPath
            ->expects(self::once())
            ->method('symlink')
            ->with($targetPath);

        $this->builtin
            ->expects(self::never())
            ->method('copy');

        $path->copy($destination, False);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyTreeIsFile(): void
    {
        $path = $this->getMock('foo.ext', 'copyTree');
        $path->method('exists')->willReturn(True);
        $path->method('isFile')->willReturn(True);

        $destination = "/bar/foo2.ext";
        $destinationPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->method('cast')->with($destination)->willReturn($destinationPath);

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path
            ->expects(self::once())
            ->method('copy')
            ->with($destinationPath, False)
            ->willReturn($newPath);

        $this->assertEquals(
            $newPath,
            $path->copyTree($destination)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyTreeDestinationIsFile(): void
    {
        $path = $this->getMock('foo.ext', 'copyTree');
        $path->method('exists')->willReturn(True);
        $path->method('isFile')->willReturn(True);

        $destination = "/bar/foo2.ext";
        $destinationPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->method('cast')->with($destination)->willReturn($destinationPath);

        $destinationPath->method('isFile')->willReturn(True);

        $destinationPath->expects(self::once())->method('remove');

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path
            ->method('copy')
            ->with($destinationPath, False)
            ->willReturn($newPath);

        $this->assertEquals(
            $newPath,
            $path->copyTree($destination)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyTreeDoesNotExist(): void
    {
        $path = $this->getMock('foo.ext', 'copyTree');
        $path->method('exists')->willReturn(False);

        $destination = "/bar/foo2.ext";

        $path
            ->expects(self::never())
            ->method('copy');

        $this->expectException(FileNotFoundException::class);

        $path->copyTree($destination);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyTreeIsDirWithNoSubDirs(): void
    {
        $path = $this->getMock('/var', 'copyTree');
        $path->method('exists')->willReturn(True);
        $path->method('isFile')->willReturn(False);
        $path->method('path')->willReturn('/var');

        $destination = "/var/www";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path->method('cast')->with($destination)->willReturn($newPath);

        $file1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $relativePath1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $file1->method('getRelativePath')->with('/var')->willReturn($relativePath1);

        $file2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $relativePath2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $file2->method('getRelativePath')->with('/var')->willReturn($relativePath2);

        $path->method('files')->willReturn([$file1, $file2]);
        $path->method('dirs')->willReturn([]);

        $newFilePath1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newFilePath2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $newPath
            ->method('append')
            ->willReturnMap([
                [$relativePath1, $newFilePath1],
                [$relativePath2, $newFilePath2],
            ]);

        $newFilePath1
            ->expects(self::once())
            ->method('remove_p');

        $file1
            ->expects(self::once())
            ->method('copy')
            ->with($newFilePath1, False);

        $newFilePath2
            ->expects(self::once())
            ->method('remove_p');

        $file2
            ->expects(self::once())
            ->method('copy')
            ->with($newFilePath2, False);

        $this->assertEquals(
            $newPath,
            $path->copyTree($destination)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testCopyTreeIsDirWithSubDirs(): void
    {
        $path = $this->getMock('/var', 'copyTree');
        $path->method('exists')->willReturn(True);
        $path->method('isFile')->willReturn(False);
        $path->method('path')->willReturn('/var');

        $dir1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir1->method('exists')->willReturn(True);
        $dir1->method('isFile')->willReturn(False);
        $relativeNewDir1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir1->method('getRelativePath')->with('/var')->willReturn($relativeNewDir1);

        $dir2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir2->method('exists')->willReturn(True);
        $dir2->method('isFile')->willReturn(False);
        $relativeNewDir2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir2->method('getRelativePath')->with('/var')->willReturn($relativeNewDir2);

        $path->method('files')->willReturn([]);
        $path->method('dirs')->willReturn([$dir1, $dir2]);

        $destination = "/bar/foo2.ext";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->method('cast')->with($destination)->willReturn($newPath);

        $newDirPath1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newDirPath2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $newPath
            ->method('append')
            ->willReturnMap([
                [$relativeNewDir1, $newDirPath1],
                [$relativeNewDir2, $newDirPath2],
            ]);

        $dir1
            ->expects(self::once())
            ->method('copyTree')
            ->with($newDirPath1);

        $dir2
            ->expects(self::once())
            ->method('copyTree')
            ->with($newDirPath2);

        $this->assertEquals(
            $newPath,
            $path->copyTree($destination)
        );
    }

    /**
     * @throws IOException|FileNotFoundException
     */
    public function testMove()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('exists')->willReturn(True);

        $destination = "/bar/foo2.ext";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('path')->willReturn($destination);
        $newPath->method('eq')->with($destination)->willReturn(True);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('rename')
            ->with('foo.ext', $destination)
            ->willReturn(True);

        $result = $path->move($destination);

        $this->assertTrue(
            $result->eq($destination)
        );
    }

    /**
     * @throws IOException|FileNotFoundException
     */
    public function testMoveFileDoesNotExist()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('exists')->willReturn(False);

        $destination = "/bar/foo2.ext";

        $this->builtin
            ->expects(self::never())
            ->method('rename');

        $this->expectException(FileNotFoundException::class);

        $path->move($destination);
    }

    /**
     * @throws IOException|FileNotFoundException
     */
    public function testMoveWithPath()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('exists')->willReturn(True);

        $destination = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $destinationPath = "/bar/foo2.ext";

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('path')->willReturn($destinationPath);
        $newPath->method('eq')->with($destination)->willReturn(True);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('rename')
            ->with('foo.ext', $destinationPath)
            ->willReturn(True);

        $result = $path->move($destination);

        $this->assertTrue(
            $result->eq($destination)
        );
    }

    /**
     * @throws IOException|FileNotFoundException
     */
    public function testMoveDestIsDir()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('exists')->willReturn(True);
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar";

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(True);

        $newDest = $destination . "/foo.ext";
        $extendedNewPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $extendedNewPath->method('path')->willReturn($newDest);

        $newPath->method('append')->with('foo.ext')->willReturn($extendedNewPath);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('rename')
            ->with('foo.ext', $newDest)
            ->willReturn(True);

        $result = $path->move($destination);

        $this->assertEquals(
            $newDest,
            $result->path()
        );
    }

    /**
     * @throws IOException|FileNotFoundException
     */
    public function testMoveWithError()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('exists')->willReturn(True);

        $destination = "/bar/foo2.ext";
        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(False);
        $newPath->method('path')->willReturn($destination);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::once())
            ->method('rename')
            ->with('foo.ext', $destination)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->move($destination);
    }


    /**
     * @throws IOException|FileNotFoundException
     * @throws FileExistsException
     */
    public function testMoveTargetExist()
    {
        $path = $this->getMock('foo.ext', 'move');
        $path->method('exists')->willReturn(True);
        $path->method('basename')->willReturn('foo.ext');

        $destination = "/bar";

        $newPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newPath->method('isDir')->willReturn(True);

        $newDest = $destination . "/foo.ext";
        $extendedNewPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $extendedNewPath->method('path')->willReturn($newDest);
        $extendedNewPath->method('exists')->willReturn(true);

        $newPath->method('append')->with('foo.ext')->willReturn($extendedNewPath);

        $path->method('cast')->with($destination)->willReturn($newPath);

        $this->builtin
            ->expects(self::never())
            ->method('rename');

        $this->expectException(FileExistsException::class);

        $result = $path->move($destination);
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
        $path->method('isDir')->willReturn(True);

        $child1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child1->method('isDir')->willReturn(False);
        $child1->method('path')->willReturn('/foo/file.ext');

        $child2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child2->method('isDir')->willReturn(True);
        $child2->method('path')->willReturn('/foo/dir1');

        $child3 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child3->method('isDir')->willReturn(True);
        $child3->method('path')->willReturn('/foo/dir2');

        $path
            ->method('append')
            ->willReturnMap([
                ['file.ext', $child1],
                ['dir1', $child2],
                ['dir2', $child3]
            ]);

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

        $dirs = $path->dirs();

        $this->assertCount(
            2,
            $dirs
        );

        $this->assertEquals(
            '/foo/dir1',
            $dirs[0]->path()
        );

        $this->assertEquals(
            '/foo/dir2',
            $dirs[1]->path()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testDirsIsNotDir()
    {
        $path = $this->getMock('/foo', 'dirs');
        $path->method('isDir')->willReturn(False);

        $this->expectException(FileNotFoundException::class);

        $path->dirs();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testFiles()
    {
        $path = $this->getMock('/foo', 'files');
        $path->method('isDir')->willReturn(True);

        $child1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child1->method('isFile')->willReturn(True);
        $child1->method('path')->willReturn('/foo/file1.ext');

        $child2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child2->method('isFile')->willReturn(True);
        $child2->method('path')->willReturn('/foo/file2.ext');

        $child3 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child3->method('isFile')->willReturn(False);
        $child3->method('path')->willReturn('/foo/dir');

        $path
            ->method('append')
            ->willReturnMap([
                ['file1.ext', $child1],
                ['file2.ext', $child2],
                ['dir', $child3]
            ]);

        $results = [
            '..',
            '.',
            'file1.ext',
            'file2.ext',
            'dir'
        ];

        $this->builtin
            ->expects(self::once())
            ->method('scandir')
            ->with('/foo')
            ->willReturn($results);

        $files = $path->files();

        $this->assertCount(
            2,
            $files
        );

        $this->assertEquals(
            '/foo/file1.ext',
            $files[0]->path()
        );

        $this->assertEquals(
            '/foo/file2.ext',
            $files[1]->path()
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

    public function testFnMatch(): void
    {
        $path = $this->getMock('/foo', 'fnmatch');

        $pattern = "*.txt";

        $this->builtin
            ->expects(self::once())
            ->method('fnmatch')
            ->with($pattern, '/foo')
            ->willReturn(True);

        $this->assertTrue(
            $path->fnmatch($pattern)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetContent(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getContent');
        $path->method('isFile')->willReturn(True);

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
        $path->method('isFile')->willReturn(False);

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
        $path->method('isFile')->willReturn(True);

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
    public function testReadText(): void
    {
        $path = $this->getMock('/foo/file.ext', 'readText');

        $path->expects(self::once())->method('getContent')->willReturn('abcd');

        $this->assertEquals(
            'abcd',
            $path->readText()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testLines(): void
    {
        $path = $this->getMock('/foo/file.ext', 'lines');

        $path
            ->expects(self::once())
            ->method('getContent')
            ->willReturn(implode(PHP_EOL, ['a', 'b', 'c']));

        $this->assertEquals(
            ['a', 'b', 'c'],
            $path->lines()
        );
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
        $path->method('isFile')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('file_put_contents');

        $this->expectException(FileNotFoundException::class);

        $path->putContent('azerty', false, false);
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
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->method('fileperms')
            ->with('/foo/file.ext')
            ->willReturn(16895);

        $this->assertEquals(
            16895,
            $path->getPermissions()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetPermissionsAsDecimal(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getPermissions');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->method('fileperms')
            ->with('/foo/file.ext')
            ->willReturn(16895);

        $this->assertEquals(
            777,
            $path->getPermissions(false)
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetPermissionsFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'getPermissions');
        $path->method('exists')->willReturn(False);

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
        $path->method('exists')->willReturn(True);

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
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chmod')
            ->with('/foo/file.ext', 1411)
            ->willReturn(True);

        $path->setPermissions(777);
    }

    /**
     * @throws FileNotFoundException|IOException
     */
    public function testSetPermissionsWithCacheClearing(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setPermissions');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chmod')
            ->with('/foo/file.ext', 1411)
            ->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('clearstatcache');

        $path->setPermissions(777, false, true);
    }

    /**
     * @throws FileNotFoundException|IOException
     */
    public function testSetPermissionsWithDecimal(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setPermissions');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chmod')
            ->with('/foo/file.ext', 16895)
            ->willReturn(True);

        $path->setPermissions(16895, true);
    }

    /**
     * @throws FileNotFoundException|IOException
     */
    public function testSetPermissionsFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setPermissions');
        $path->method('exists')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('clearstatcache');

        $this->builtin
            ->expects(self::never())
            ->method('chmod');

        $this->expectException(FileNotFoundException::class);

        $path->setPermissions(0777, true);
    }

    /**
     * @throws FileNotFoundException
     */
    public function testSetPermissionsWithError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setPermissions');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->method('chmod')
            ->with('/foo/file.ext', 1411)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->setPermissions(777);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetOwnerId(): void
    {
        $path = $this->getMock('foo/file.ext', 'getOwnerId');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('fileowner')
            ->with('foo/file.ext')
            ->willReturn(1000);

        $this->assertEquals(
            1000,
            $path->getOwnerId()
        );
    }

    /**
     * @throws IOException
     */
    public function testGetOwnerIdFileDoesNotExist(): void
    {
        $path = $this->getMock('foo/file.ext', 'getOwnerId');
        $path->method('exists')->willReturn(false);

        $this->builtin
            ->expects(self::never())
            ->method('fileowner');

        $this->expectException(FileNotFoundException::class);

        $path->getOwnerId();
    }

    /**
     * @throws FileNotFoundException
     */
    public function testGetOwnerIdWithError(): void
    {
        $path = $this->getMock('foo/file.ext', 'getOwnerId');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('fileowner')
            ->with('foo/file.ext')
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->getOwnerId();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGetOwnerName(): void
    {
        $path = $this->getMock('foo/file.ext', 'getOwnerName');

        $path->method('getOwnerId')->willReturn(1000);

        $this->builtin
            ->expects(self::once())
            ->method('posix_getpwuid')
            ->with(1000)
            ->willReturn(['name' => 'foo']);

        $this->assertEquals(
            'foo',
            $path->getOwnerName()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testGetOwnerNameWithError(): void
    {
        $path = $this->getMock('foo/file.ext', 'getOwnerName');

        $path->method('getOwnerId')->willReturn(1000);

        $this->builtin
            ->expects(self::once())
            ->method('posix_getpwuid')
            ->with(1000)
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->getOwnerName();
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testSetOwner(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setOwner');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chown')
            ->with('/foo/file.ext', 'user')
            ->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chgrp')
            ->with('/foo/file.ext', 'group')
            ->willReturn(True);

        $path->setOwner('user', 'group');
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testSetOwnerClearCache(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setOwner');
        $path->method('exists')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chown')
            ->with('/foo/file.ext', 'user')
            ->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('chgrp')
            ->with('/foo/file.ext', 'group')
            ->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('clearstatcache');

        $path->setOwner('user', 'group', True);
    }

    /**
     * @throws FileNotFoundException|IOException
     */
    public function testSetOwnerFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo/file.ext', 'setOwner');
        $path->method('exists')->willReturn(False);

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
        $path->method('exists')->willReturn(True);

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
        $path->method('exists')->willReturn(True);

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
     */
    public function testSameFileWithString(): void
    {
        $path = $this->getMock('./file.ext', 'sameFile');

        $absPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $absPath->method('path')->willReturn('/foo/file.ext');
        $path->method('absPath')->willReturn($absPath);

        $otherAbsPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $otherAbsPath->method('path')->willReturn('/foo/file.ext');

        $other = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $other->method('absPath')->willReturn($otherAbsPath);

        $path->method('cast')->with('/foo/file.ext')->willReturn($other);

        $this->assertTrue(
            $path->sameFile('/foo/file.ext')
        );
    }

    /**
     * @throws IOException
     */
    public function testSameFileWithPath(): void
    {
        $path = $this->getMock('./file.ext', 'sameFile');

        $absPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $absPath->method('path')->willReturn('/foo/file.ext');
        $path->method('absPath')->willReturn($absPath);

        $otherAbsPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $otherAbsPath->method('path')->willReturn('/foo/file.ext');

        $other = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $other->method('absPath')->willReturn($otherAbsPath);

        $path->method('cast')->with($other)->willReturn($other);

        $this->assertTrue(
            $path->sameFile($other)
        );
    }

    public function testExpand(): void
    {
        $path = $this->getMock('./file.ext', 'expand');

        $path
            ->expects(self::once())
            ->method('expandUser')
            ->willReturn($path);

        $path
            ->expects(self::once())
            ->method('expandVars')
            ->willReturn($path);

        $path
            ->expects(self::once())
            ->method('normPath')
            ->willReturn($path);

        $this->assertEquals(
            $path,
            $path->expand()
        );
    }

    public function testExpandUser(): void
    {
        $path = $this->getMock('~/file.ext', 'expandUser');
        $path->method('path')->willReturn('~/file.ext');

        $expandedPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $homePath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->method('cast')->with($_SERVER['HOME'])->willReturn($homePath);

        $homePath
            ->expects(self::once())
            ->method('append')
            ->with('file.ext')
            ->willReturn($expandedPath);

        $this->assertEquals(
            $expandedPath,
            $path->expandUser()
        );
    }

    public function testExpandUserNothingToExpand(): void
    {
        $path = $this->getMock('file.ext', 'expandUser');
        $path->method('path')->willReturn('file.ext');

        $this->assertEquals(
            $path,
            $path->expandUser()
        );
    }

    public function testExpandVars(): void
    {
        $path = $this->getMock('~/file.ext', 'expandVars');
        $path->method('path')->willReturn('/foo/${test}/bar');

        $this->builtin
            ->expects(self::once())
            ->method('getenv')
            ->with('test')
            ->willReturn('abc');

        $expandedPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path
            ->expects(self::once())
            ->method('cast')
            ->with('/foo/abc/bar')
            ->willReturn($expandedPath);

        $this->assertEquals(
            $expandedPath,
            $path->expandVars()
        );
    }

    public function testExpandVarsVariant(): void
    {
        $path = $this->getMock('~/file.ext', 'expandVars');
        $path->method('path')->willReturn('/foo/$test/bar');

        $this->builtin
            ->expects(self::once())
            ->method('getenv')
            ->with('test')
            ->willReturn('abc');

        $expandedPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path
            ->expects(self::once())
            ->method('cast')
            ->with('/foo/abc/bar')
            ->willReturn($expandedPath);

        $this->assertEquals(
            $expandedPath,
            $path->expandVars()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testGlob(): void {
        $path = $this->getMock('/foo', 'glob');
        $path->method('isDir')->willReturn(True);

        $extended = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $extended->method('path')->willReturn('/foo/*');

        $child1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child1->method('path')->willReturn('/foo/a');

        $child2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child2->method('path')->willReturn('/foo/b');

        $child3 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $child3->method('path')->willReturn('/foo/c');

        $path
            ->method('append')
            ->willReturnMap([
                ['*', $extended],
                ['a', $child1],
                ['b', $child2],
                ['c', $child3]
            ]);

        $this->builtin
            ->expects(self::once())
            ->method('glob')
            ->with('/foo/*')
            ->willReturn(['a', 'b', 'c']);

        $result = $path->glob('*');

        $this->assertCount(
            3,
            $result
        );

        $this->assertEquals(
            '/foo/a',
            $result[0]->path()
        );

        $this->assertEquals(
            '/foo/b',
            $result[1]->path()
        );

        $this->assertEquals(
            '/foo/c',
            $result[2]->path()
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

        $extended = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $extended->method('path')->willReturn('/foo/*');

        $path->method('append')->with('*')->willReturn($extended);

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
        $path->method('path')->willReturn('/foo');

        $this->builtin
            ->expects(self::once())
            ->method('rmdir')
            ->with('/foo')
            ->willReturn(True);

        $path->rmdir();
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testRmDirNonEmptyAndRecursive(): void
    {
        $path = $this->getMock('/foo', 'rmdir');
        $path->method('isDir')->willReturn(True);
        $path->method('path')->willReturn('/foo');

        $file1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $file2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path->method('files')->willReturn([$file1, $file2]);
        $path->method('dirs')->willReturn([$dir1, $dir2]);

        $file1
            ->expects(self::once())
            ->method('remove');

        $file2
            ->expects(self::once())
            ->method('remove');

        $dir1
            ->expects(self::once())
            ->method('rmdir')
            ->with(True, False);

        $dir2
            ->expects(self::once())
            ->method('rmdir')
            ->with(True, False);

        $this->builtin
            ->expects(self::once())
            ->method('rmdir')
            ->with('/foo')
            ->willReturn(True);

        $path->rmdir(True);
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testRmDirNonEmptyAndNotRecursive(): void
    {
        $path = $this->getMock('/foo', 'rmdir');
        $path->method('isDir')->willReturn(True);
        $path->method('path')->willReturn('/foo');

        $file1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $file2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path->method('files')->willReturn([$file1, $file2]);
        $path->method('dirs')->willReturn([$dir1, $dir2]);

        $file1
            ->expects(self::never())
            ->method('remove');

        $file2
            ->expects(self::never())
            ->method('remove');

        $dir1
            ->expects(self::never())
            ->method('rmdir')
            ->with(True, False);

        $dir2
            ->expects(self::never())
            ->method('rmdir');

        $this->builtin
            ->expects(self::never())
            ->method('rmdir');

        $this->expectException(IOException::class);

        $path->rmdir(False);
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testRmDirDirDoesNotExistButPermissive(): void
    {
        $path = $this->getMock('/foo', 'rmdir');
        $path->method('isDir')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('rmdir');

        $path->rmdir(False, True);
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testRmDirDirDoesNotExistAndNotPermissive(): void
    {
        $path = $this->getMock('/foo', 'rmdir');
        $path->method('isDir')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('rmdir');

        $this->expectException(FileNotFoundException::class);

        $path->rmdir();
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    public function testRmDirDirWithError(): void
    {
        $path = $this->getMock('/foo', 'rmdir');
        $path->method('isDir')->willReturn(true);
        $path->method('path')->willReturn('/foo');

        $this->builtin
            ->expects(self::once())
            ->method('rmdir')
            ->with('/foo')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->rmdir();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testRename(): void
    {
        $path = $this->getMock('/foo', 'rename');

        $destination = "/bar";

        $path
            ->expects(self::once())
            ->method('move')
            ->with($destination)
            ->willReturn($path);

        $this->assertEquals(
            $path,
            $path->rename($destination)
        );
    }

    /**
     * @throws IOException
     */
    public function testReadHash(): void
    {
        $path = $this->getMock('/foo', 'readHash');

        $hash = "9f487a5b26dc5d66fb8d60e002355ebfa6a978fa9e2b63bbfdae609c8d2e65c9";

        $this->builtin
            ->expects(self::once())
            ->method('hash_file')
            ->with('sha256', '/foo', false)
            ->willReturn($hash);

        $this->assertEquals(
            $hash,
            $path->readHash('sha256')
        );
    }

    /**
     * @throws IOException
     */
    public function testReadHashAndIsBinary(): void
    {
        $path = $this->getMock('/foo', 'readHash');

        $hash = "9f487a5b26dc5d66fb8d60e002355ebfa6a978fa9e2b63bbfdae609c8d2e65c9";

        $this->builtin
            ->expects(self::once())
            ->method('hash_file')
            ->with('sha256', '/foo', true)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->readHash('sha256', true);
    }

    /**
     * @throws IOException
     */
    public function testReadHashWithError(): void
    {
        $path = $this->getMock('/foo', 'readHash');

        $hash = "9f487a5b26dc5d66fb8d60e002355ebfa6a978fa9e2b63bbfdae609c8d2e65c9";

        $this->builtin
            ->expects(self::once())
            ->method('hash_file')
            ->with('sha256', '/foo', false)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->readHash('sha256');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testReadLink(): void
    {
        $path = $this->getMock('/foo/file.ext', 'readLink');
        $path->method('isLink')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('readLink')
            ->with('/foo/file.ext')
            ->willReturn('/bar/file.ext');

        $linkPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path
            ->expects(self::once())
            ->method('cast')
            ->with('/bar/file.ext')
            ->willReturn($linkPath);

        $this->assertEquals(
            $linkPath,
            $path->readLink()
        );
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testReadLinkIsNotALink(): void
    {
        $path = $this->getMock('/foo/file.ext', 'readLink');
        $path->method('isLink')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('readLink');

        $path
            ->expects(self::never())
            ->method('cast');

        $this->expectException(FileNotFoundException::class);

        $path->readLink();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testReadLinkWithError(): void
    {
        $path = $this->getMock('/foo/file.ext', 'readLink');
        $path->method('isLink')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('readLink')
            ->with('/foo/file.ext')
            ->willReturn(False);

        $path
            ->expects(self::never())
            ->method('cast');

        $this->expectException(IOException::class);

        $path->readLink();
    }

    /**
     * @throws IOException
     */
    public function testRemove(): void
    {
        $path = $this->getMock('/foo', 'remove');
        $path->method('isFile')->willReturn(True);

        $this->builtin
                ->expects(self::once())
                ->method('unlink')
                ->with('/foo')
                ->willReturn(True);

        $path->remove();
    }

    /**
     * @throws IOException
     */
    public function testRemoveFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo', 'remove');
        $path->method('isFile')->willReturn(False);

        $this->builtin
                ->expects(self::never())
                ->method('unlink');

        $this->expectException(FileNotFoundException::class);

        $path->remove();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testRemoveWithError(): void
    {
        $path = $this->getMock('/foo', 'remove');
        $path->method('isFile')->willReturn(True);

        $this->builtin
            ->expects(self::once())
            ->method('unlink')
            ->with('/foo')
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->remove();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testUnlink(): void
    {
        $path = $this->getMock('/foo', 'unlink');

        $path
            ->expects(self::once())
            ->method('remove');

        $path->unlink();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testRemoveP(): void
    {
        $path = $this->getMock('/foo', 'remove_p');
        $path->method('isFile')->willReturn(True);
        $path->method('isDir')->willReturn(False);

        $path
            ->expects(self::once())
            ->method('remove');

        $path->remove_p();
    }

    /**
     * @throws IOException
     */
    public function testRemovePFileDoesNotExists(): void
    {
        $path = $this->getMock('/foo', 'remove_p');
        $path->method('isFile')->willReturn(False);
        $path->method('isDir')->willReturn(False);

        $path
            ->expects(self::never())
            ->method('remove');

        $path->remove_p();
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testRemovePTargetIsDir(): void
    {
        $path = $this->getMock('/foo', 'remove_p');
        $path->method('isFile')->willReturn(False);
        $path->method('isDir')->willReturn(True);

        $path
            ->expects(self::never())
            ->method('remove');

        $this->expectException(FileExistsException::class);

        $path->remove_p();
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
     * @throws FileNotFoundException
     */
    public function testWalkDirs(): void
    {
        $path = $this->getMock('/foo', 'walkDirs');
        $path->method('isDir')->willReturn(True);

        $iterator = $this
            ->getMockBuilder(\Iterator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->builtin
            ->expects(self::once())
            ->method('getRecursiveIterator')
            ->with('/foo')
            ->willReturn($iterator);

        $file1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $file2 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $dir1 = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();

        $path
            ->method('cast')
            ->willReturnMap([
                ['/foo/file1.ext', $file1],
                ['/foo/file2.ext', $file2],
                ['/foo/dir1.ext', $dir1]
            ]);

        $iterator
            ->method('valid')
            ->will($this->onConsecutiveCalls(true, true, true, false));

        $iterator
            ->method('current')
            ->will($this->onConsecutiveCalls('/foo/file1.ext', '/foo/file2.ext', '/foo/dir1.ext'));

        $iterator
            ->method('key')
            ->will($this->onConsecutiveCalls(0, 1, 2));

        $this->assertEquals(
            [$file1, $file2, $dir1],
            iterator_to_array($path->walkDirs())
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testWalkDirsDirDoesNotExist(): void
    {
        $path = $this->getMock('/foo', 'walkDirs');
        $path->method('isDir')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('getRecursiveIterator');

        $this->expectException(FileNotFoundException::class);

        iterator_to_array($path->walkDirs());
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
    public function testChownWithId(): void
    {
        $path = $this->getMock('foo/file.ext', 'chown');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('chown')
            ->with('foo/file.ext', 1000)
            ->willReturn(true);

        $path->chown(1000);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChownWithName(): void
    {
        $path = $this->getMock('foo/file.ext', 'chown');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('chown')
            ->with('foo/file.ext', 'user')
            ->willReturn(true);

        $path->chown('user');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChownWithClearCache(): void
    {
        $path = $this->getMock('foo/file.ext', 'chown');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('chown')
            ->with('foo/file.ext', 'user')
            ->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('clearstatcache');

        $path->chown('user', true);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChownFileDoesNotExist(): void
    {
        $path = $this->getMock('foo/file.ext', 'chown');
        $path->method('exists')->willReturn(false);

        $this->builtin
            ->expects(self::never())
            ->method('chown');

        $this->expectException(FileNotFoundException::class);

        $path->chown('user');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChownWithError(): void
    {
        $path = $this->getMock('foo/file.ext', 'chown');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('chown')
            ->with('foo/file.ext', 'user')
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->chown('user');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChgrpWithId(): void
    {
        $path = $this->getMock('foo/file.ext', 'chgrp');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('chgrp')
            ->with('foo/file.ext', 100)
            ->willReturn(true);

        $path->chgrp(100);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChgrpWithName(): void
    {
        $path = $this->getMock('foo/file.ext', 'chgrp');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('chgrp')
            ->with('foo/file.ext', 'group')
            ->willReturn(true);

        $path->chgrp('group');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChgrpWithClearcache(): void
    {
        $path = $this->getMock('foo/file.ext', 'chgrp');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('chgrp')
            ->with('foo/file.ext', 'group')
            ->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('clearstatcache');

        $path->chgrp('group', true);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChgrpWithIdFileDoesNotExist(): void
    {
        $path = $this->getMock('foo/file.ext', 'chgrp');
        $path->method('exists')->willReturn(false);

        $this->builtin
            ->expects(self::never())
            ->method('chgrp');

        $this->expectException(FileNotFoundException::class);

        $path->chgrp('group');
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     */
    public function testChgrpWithError(): void
    {
        $path = $this->getMock('foo/file.ext', 'chgrp');
        $path->method('exists')->willReturn(true);

        $this->builtin
            ->expects(self::once())
            ->method('chgrp')
            ->with('foo/file.ext', 'group')
            ->willReturn(false);

        $this->expectException(IOException::class);

        $path->chgrp('group');
    }

    /**
     * @throws IOException
     */
    public function testChRoot(): void
    {
        $path = $this->getMock('foo', 'chroot');
        $path->method('isDir')->willReturn(True);

        $absPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->method('absPath')->willReturn($absPath);

        $absPath->method('path')->willReturn('/foo');

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
    public function testChRootDirDoesNotExist(): void
    {
        $path = $this->getMock('/foo', 'chroot');
        $path->method('isDir')->willReturn(False);

        $this->builtin
            ->expects(self::never())
            ->method('chroot');

        $this->expectException(FileNotFoundException::class);

        $path->chroot();
    }

    /**
     * @throws IOException
     */
    public function testChRootWithError(): void
    {
        $path = $this->getMock('/foo', 'chroot');
        $path->method('isDir')->willReturn(True);

        $absPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $path->method('absPath')->willReturn($absPath);

        $absPath->method('path')->willReturn('/foo');

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

    public function testIsMountIsTrue(): void
    {
        $path = $this->getMock('/foo', 'isMount');

        $this->builtin
            ->expects(self::once())
            ->method('disk_free_space')
            ->with('/foo')
            ->willReturn(123.0);

        $this->assertTrue(
            $path->isMount()
        );
    }

    public function testIsMountIsFalse(): void
    {
        $path = $this->getMock('/foo', 'isMount');

        $this->builtin
            ->expects(self::once())
            ->method('disk_free_space')
            ->with('/foo')
            ->willReturn(False);

        $this->assertFalse(
            $path->isMount()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIsReadable(): void
    {
        $path = $this->getMock('/foo', 'isReadable');
        $path->method('exists')->willReturn(true);

        $this
            ->builtin
            ->expects(self::once())
            ->method('is_readable')
            ->willReturn(true);

        $this->assertTrue($path->isReadable());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIsReadableFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo', 'isReadable');
        $path->method('exists')->willReturn(false);

        $this
            ->builtin
            ->expects(self::never())
            ->method('is_readable');

        $this->expectException(FileNotFoundException::class);

        $this->assertTrue($path->isReadable());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIsWritable(): void
    {
        $path = $this->getMock('/foo', 'isWritable');
        $path->method('exists')->willReturn(true);

        $this
            ->builtin
            ->expects(self::once())
            ->method('is_writable')
            ->willReturn(true);

        $this->assertTrue($path->isWritable());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIsWritableFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo', 'isWritable');
        $path->method('exists')->willReturn(false);

        $this
            ->builtin
            ->expects(self::never())
            ->method('is_writable');

        $this->expectException(FileNotFoundException::class);

        $this->assertTrue($path->isWritable());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIsExecutable(): void
    {
        $path = $this->getMock('/foo', 'isExecutable');
        $path->method('exists')->willReturn(true);

        $this
            ->builtin
            ->expects(self::once())
            ->method('is_executable')
            ->willReturn(true);

        $this->assertTrue($path->isExecutable());
    }

    /**
     * @throws FileNotFoundException
     */
    public function testIsExecutableFileDoesNotExist(): void
    {
        $path = $this->getMock('/foo', 'isExecutable');
        $path->method('exists')->willReturn(false);

        $this
            ->builtin
            ->expects(self::never())
            ->method('is_executable');

        $this->expectException(FileNotFoundException::class);

        $this->assertTrue($path->isExecutable());
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testLink(): void
    {
        $path = $this->getMock('/foo/file.ext', 'link');
        $path->method('exists')->willReturn(True);

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
        $path->method('exists')->willReturn(True);

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
        $path->method('exists')->willReturn(True);

        $target = $this
            ->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();
        $target->method('exists')->willReturn(True);

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
        $path->method('exists')->willReturn(True);

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

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testSymLink(): void
    {
        $path = $this->getMock('foo/file.ext', 'symlink');
        $path->method('exists')->willReturn(True);

        $newLink = 'bar/file.ext';

        $newLinkPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newLinkPath->method('exists')->willReturn(False);
        $newLinkPath->method('__toString')->willReturn($newLink);

        $path
            ->expects(self::once())
            ->method('cast')
            ->with($newLink)
            ->willReturn($newLinkPath);

        $this->builtin
            ->expects(self::once())
            ->method('symlink')
            ->with('foo/file.ext', $newLink)
            ->willReturn(True);

        $path->symlink($newLink);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testSymLinkFileDoesNotExist(): void
    {
        $path = $this->getMock('foo/file.ext', 'symlink');
        $path->method('exists')->willReturn(False);

        $newLink = 'bar/file.ext';

        $this->builtin
            ->expects(self::never())
            ->method('symlink');

        $this->expectException(FileNotFoundException::class);

        $path->symlink($newLink);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testSymLinkTargetExists(): void
    {
        $path = $this->getMock('foo/file.ext', 'symlink');
        $path->method('exists')->willReturn(True);

        $newLink = 'bar/file.ext';

        $newLinkPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newLinkPath->method('exists')->willReturn(True);

        $path
            ->expects(self::once())
            ->method('cast')
            ->with($newLink)
            ->willReturn($newLinkPath);

        $this->builtin
            ->expects(self::never())
            ->method('symlink');

        $this->expectException(FileExistsException::class);

        $path->symlink($newLink);
    }

    /**
     * @throws IOException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    public function testSymLinkWithError(): void
    {
        $path = $this->getMock('foo/file.ext', 'symlink');
        $path->method('exists')->willReturn(True);

        $newLink = 'bar/file.ext';

        $newLinkPath = $this->getMockBuilder(TestablePath::class)->disableOriginalConstructor()->getMock();
        $newLinkPath->method('exists')->willReturn(False);
        $newLinkPath->method('__toString')->willReturn($newLink);

        $path
            ->expects(self::once())
            ->method('cast')
            ->with($newLink)
            ->willReturn($newLinkPath);

        $this->builtin
            ->expects(self::once())
            ->method('symlink')
            ->with('foo/file.ext', $newLink)
            ->willReturn(False);

        $this->expectException(IOException::class);

        $path->symlink($newLink);
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

        $result = $this
            ->getMockBuilder(TestablePath::class)
            ->disableOriginalConstructor()
            ->getMock();

        $path
            ->method('cast')
            ->with('../../foo/bar/file.ext')
            ->willReturn($result);

        $this->assertEquals(
            $result,
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