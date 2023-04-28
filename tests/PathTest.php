<?php

namespace Tests;

use Cli\Services\Paths;

class PathTest extends TestCase
{

    public function test_resolve()
    {
        $cwd = getcwd();
        $this->assertEquals('/my/path', Paths::resolve('/my/path/'));
        $this->assertEquals('/my/path', Paths::resolve('\\my\\path'));
        $this->assertEquals('/my/path', Paths::resolve('/my/path'));
        $this->assertEquals('/my/path', Paths::resolve('/my/cats/../path'));
        $this->assertEquals('/my/path', Paths::resolve('/my/cats/.././path'));
        $this->assertEquals('/my/path', Paths::resolve('/my/path', '/root'));
        $this->assertEquals('/root/my/path', Paths::resolve('my/path', '/root'));
        $this->assertEquals('/my/path', Paths::resolve('../my/path', '/root'));
        $this->assertEquals("{$cwd}/my/path", Paths::resolve('my/path'));
        $this->assertEquals("{$cwd}", Paths::resolve(''));
    }

    public function test_join()
    {
        $this->assertEquals('/my/path', Paths::join('/my/', 'path'));
        $this->assertEquals('/my/path', Paths::join('/my/', '/path'));
        $this->assertEquals('/my/path', Paths::join('/my', 'path'));
        $this->assertEquals('/my/path', Paths::join('/my', 'path/'));
        $this->assertEquals('my/path/to/here', Paths::join('my', 'path', 'to', 'here'));
        $this->assertEquals('my', Paths::join('my'));
        $this->assertEquals('my/path', Paths::join('my//', '//path//'));
        $this->assertEquals('/my/path', Paths::join('/my//', '\\path\\'));
    }
}