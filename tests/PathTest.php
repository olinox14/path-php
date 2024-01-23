<?php

namespace Path\Tests;

use Path\Path;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    protected Path $pathClass;

    protected function setUp(): void
    {
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
}