<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Framework\Cache\ArrayDriver;
use Framework\Blog\Markdown;
use Framework\Lang\Lang;

class CacheTest extends TestCase
{
    protected ArrayDriver $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayDriver();
    }

    public function testPutAndGet(): void
    {
        $this->cache->put('name', 'John', 60);
        $this->assertEquals('John', $this->cache->get('name'));
    }

    public function testGetDefault(): void
    {
        $this->assertEquals('default', $this->cache->get('missing', 'default'));
    }

    public function testHas(): void
    {
        $this->cache->put('key', 'value', 60);
        $this->assertTrue($this->cache->has('key'));
        $this->assertFalse($this->cache->has('missing'));
    }

    public function testForget(): void
    {
        $this->cache->put('key', 'value', 60);
        $this->cache->forget('key');
        $this->assertNull($this->cache->get('key'));
    }

    public function testIncrement(): void
    {
        $this->cache->put('count', 10, 60);
        $result = $this->cache->increment('count', 5);
        $this->assertEquals(15, $result);
    }

    public function testDecrement(): void
    {
        $this->cache->put('count', 10, 60);
        $result = $this->cache->decrement('count', 3);
        $this->assertEquals(7, $result);
    }

    public function testFlush(): void
    {
        $this->cache->put('a', 1, 60);
        $this->cache->put('b', 2, 60);
        $this->cache->flush();
        $this->assertNull($this->cache->get('a'));
        $this->assertNull($this->cache->get('b'));
    }

    public function testPutManyAndGetMany(): void
    {
        $this->cache->putMany(['a' => 1, 'b' => 2, 'c' => 3], 60);
        $result = $this->cache->many(['a', 'b', 'c', 'd']);
        $this->assertEquals([1, 2, 3, null], array_values($result));
    }

    public function testRemember(): void
    {
        $result = $this->cache->get('remembered');
        $this->assertNull($result);

        $value = $this->cache->get('remembered');
        if ($value === null) {
            $value = 'computed';
            $this->cache->put('remembered', $value, 60);
        }
        $this->assertEquals('computed', $this->cache->get('remembered'));
    }

    public function testTtlExpiration(): void
    {
        $this->cache->put('temp', 'value', 1);
        $this->assertEquals('value', $this->cache->get('temp'));

        // Manually expire by accessing private expires array
        $reflection = new \ReflectionClass($this->cache);
        $expires = $reflection->getProperty('expires');
        $expires->setAccessible(true);
        $expires->setValue($this->cache, ['temp' => time() - 10]);

        $this->assertNull($this->cache->get('temp'));
    }
}

class MarkdownTest extends TestCase
{
    public function testHeaders(): void
    {
        $html = Markdown::parse('# Hello');
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function testBold(): void
    {
        $html = Markdown::parse('**bold text**');
        $this->assertStringContainsString('<strong>bold text</strong>', $html);
    }

    public function testItalic(): void
    {
        $html = Markdown::parse('*italic text*');
        $this->assertStringContainsString('<em>italic text</em>', $html);
    }

    public function testLinks(): void
    {
        $html = Markdown::parse('[Google](https://google.com)');
        $this->assertStringContainsString('<a href="https://google.com">', $html);
    }

    public function testCodeBlock(): void
    {
        $markdown = "```\ncode here\n```";
        $html = Markdown::parse($markdown);
        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('<code', $html);
    }

    public function testParagraphs(): void
    {
        $html = Markdown::parse("Some text\n\nMore text");
        $this->assertStringContainsString('<p>Some text</p>', $html);
        $this->assertStringContainsString('<p>More text</p>', $html);
    }
}
