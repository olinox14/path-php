<?php

namespace Path\Tests\unit;

use Path\BuiltinProxy;
use Path\Path;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TestablePath extends Path {
    public function setBuiltin(BuiltinProxy $builtinProxy): void
    {
        $this->builtin = $builtinProxy;
    }
}

class PathTest extends TestCase
{
    private BuiltinProxy | MockObject $builtin;

    public function setUp(): void
    {
        $this->builtin = $this->getMockBuilder(BuiltinProxy::class)->getMock();
    }

    public function getPathMockForMethod(string $path, string $methodName): TestablePath | MockObject
    {
        $mock = $this
            ->getMockBuilder(TestablePath::class)
            ->setConstructorArgs([$path])
            ->setMethodsExcept(['setBuiltin', $methodName])
            ->getMock();
        $mock->setBuiltin($this->builtin);
        return $mock;
    }

    public function testAbsPath(): void {
        $path = $this->getPathMockForMethod('bar', 'absPath');

        $this->builtin
            ->expects(self::once())
            ->method('realpath')
            ->with('bar')
            ->willReturn('/foo/bar');

        $this->assertEquals('/foo/bar', $path->absPath());
    }
}