<?php
/**
 * Unit tests for Resource class
 */

namespace Tests\Unit\Lib;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Lib\Resource;

class ResourceTest extends TestCase
{
    /**
     * @var Resource
     */
    private $resource;

    /**
     * Set up fresh Resource instance before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resource = Resource::instance();

        // Reset the instance to default state using reflection
        $reflection = new \ReflectionClass($this->resource);

        // Reset basePath
        $property = $reflection->getProperty('basePath');
        $property->setAccessible(true);
        $property->setValue($this->resource, '');

        // Reset filePath
        $property = $reflection->getProperty('filePath');
        $property->setAccessible(true);
        $property->setValue($this->resource, [
            'style'         => '',
            'script'        => '',
            'font'          => '',
            'document'      => '',
            'image'         => '',
            'favicon'       => '',
            'url'           => ''
        ]);

        // Reset output
        $property = $reflection->getProperty('output');
        $property->setAccessible(true);
        $property->setValue($this->resource, 'inline');

        // Clear resources array
        $property = $reflection->getProperty('resources');
        $property->setAccessible(true);
        $property->setValue($this->resource, []);
    }

    // setOption / getOption Tests

    public function testSetOptionSimple(): void
    {
        $this->resource->setOption('basePath', '/public');

        $result = $this->resource->getOption('basePath');

        $this->assertEquals('/public', $result);
    }

    public function testSetOptionArray(): void
    {
        $filePaths = [
            'style' => '/css',
            'script' => '/js'
        ];

        $this->resource->setOption('filePath', $filePaths);

        $result = $this->resource->getOption('filePath');

        $this->assertEquals($filePaths, $result);
    }

    public function testSetOptionExtend(): void
    {
        $this->resource->setOption('filePath', ['style' => '/css']);
        $this->resource->setOption('filePath', ['script' => '/js'], true);

        $result = $this->resource->getOption('filePath');

        $this->assertArrayHasKey('style', $result);
        $this->assertArrayHasKey('script', $result);
        $this->assertEquals('/css', $result['style']);
        $this->assertEquals('/js', $result['script']);
    }

    public function testSetOptionOverwrite(): void
    {
        $this->resource->setOption('filePath', ['style' => '/css']);
        $this->resource->setOption('filePath', ['script' => '/js'], false);

        $result = $this->resource->getOption('filePath');

        $this->assertArrayNotHasKey('style', $result);
        $this->assertArrayHasKey('script', $result);
    }

    public function testGetOptionNonExistent(): void
    {
        $result = $this->resource->getOption('nonExistentOption');

        $this->assertNull($result);
    }

    // register Tests

    public function testRegisterDefaultRel(): void
    {
        $this->resource->register('style', 'app.css');

        // Access via reflection since resources is private
        $reflection = new \ReflectionClass($this->resource);
        $property = $reflection->getProperty('resources');
        $property->setAccessible(true);
        $resources = $property->getValue($this->resource);

        $this->assertArrayHasKey('style', $resources);
        $this->assertArrayHasKey('app.css', $resources['style']);
        $this->assertEquals('preload', $resources['style']['app.css']['options']['rel']);
    }

    public function testRegisterCustomRel(): void
    {
        $this->resource->register('script', 'app.js', 'prerender');

        $reflection = new \ReflectionClass($this->resource);
        $property = $reflection->getProperty('resources');
        $property->setAccessible(true);
        $resources = $property->getValue($this->resource);

        $this->assertEquals('prerender', $resources['script']['app.js']['options']['rel']);
    }

    public function testRegisterMultipleResources(): void
    {
        $this->resource->register('style', 'app.css');
        $this->resource->register('style', 'theme.css');
        $this->resource->register('script', 'app.js');

        $reflection = new \ReflectionClass($this->resource);
        $property = $reflection->getProperty('resources');
        $property->setAccessible(true);
        $resources = $property->getValue($this->resource);

        $this->assertCount(2, $resources['style']);
        $this->assertCount(1, $resources['script']);
    }

    // getLink Tests

    public function testGetLinkWithPath(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['style' => '/css']);

        $result = $this->resource->getLink('style', 'app.css');

        $this->assertEquals('/public/css/app.css', $result);
    }

    public function testGetLinkWithoutExtension(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['style' => '/css']);

        $result = $this->resource->getLink('style', 'app');

        $this->assertEquals('/public/css/app.css', $result);
    }

    public function testGetLinkUrl(): void
    {
        $result = $this->resource->getLink('url', 'https://example.com/resource.js');

        $this->assertEquals('https://example.com/resource.js', $result);
    }

    public function testGetLinkScript(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['script' => '/js']);

        $result = $this->resource->getLink('script', 'main');

        $this->assertEquals('/public/js/main.js', $result);
    }

    public function testGetLinkFont(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['font' => '/fonts']);

        $result = $this->resource->getLink('font', 'roboto-regular');

        $this->assertEquals('/public/fonts/roboto-regular.woff2', $result);
    }

    // getPath Tests

    public function testGetPath(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['style' => '/css']);

        $result = $this->resource->getPath('style');

        $this->assertEquals('/public/css', $result);
    }

    public function testGetPathTrailingSlash(): void
    {
        $this->resource->setOption('basePath', '/public/');
        $this->resource->setOption('filePath', ['style' => '/css']);

        $result = $this->resource->getPath('style');

        $this->assertEquals('/public/css', $result);
    }

    public function testGetPathBackslash(): void
    {
        $this->resource->setOption('basePath', '/public\\');
        $this->resource->setOption('filePath', ['style' => '/css']);

        $result = $this->resource->getPath('style');

        $this->assertEquals('/public/css', $result);
    }

    // buildLinks Tests

    public function testBuildLinksEmpty(): void
    {
        $result = $this->resource->buildLinks();

        $this->assertEquals("\n\t", $result);
    }

    public function testBuildLinksSingle(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['style' => '/css']);
        $this->resource->register('style', 'app.css');

        $result = $this->resource->buildLinks();

        $this->assertStringContainsString('<link', $result);
        $this->assertStringContainsString('rel="preload"', $result);
        $this->assertStringContainsString('href="/public/css/app.css"', $result);
        $this->assertStringContainsString('as="style"', $result);
    }

    public function testBuildLinksMultiple(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['style' => '/css', 'script' => '/js']);
        $this->resource->register('style', 'app.css');
        $this->resource->register('script', 'app.js');

        $result = $this->resource->buildLinks();

        $this->assertStringContainsString('/public/css/app.css', $result);
        $this->assertStringContainsString('/public/js/app.js', $result);
        $this->assertStringContainsString('as="style"', $result);
        $this->assertStringContainsString('as="script"', $result);
    }

    public function testBuildLinksFont(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['font' => '/fonts']);
        $this->resource->register('font', 'roboto-regular.woff2');

        $result = $this->resource->buildLinks();

        $this->assertStringContainsString('as="font"', $result);
        $this->assertStringContainsString('type="font/woff2"', $result);
        $this->assertStringContainsString('crossorigin="anonymous"', $result);
    }

    public function testBuildLinksCustomRel(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['document' => '/pages']);
        $this->resource->register('document', 'about.html', 'prerender');

        $result = $this->resource->buildLinks();

        $this->assertStringContainsString('rel="prerender"', $result);
    }

    // buildHeader Tests

    public function testBuildHeaderEmpty(): void
    {
        $result = $this->resource->buildHeader();

        $this->assertEquals('Link: ', $result);
    }

    public function testBuildHeaderSingle(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['style' => '/css']);
        $this->resource->register('style', 'app.css');

        $result = $this->resource->buildHeader();

        $this->assertStringStartsWith('Link: ', $result);
        $this->assertStringContainsString('</public/css/app.css>', $result);
        $this->assertStringContainsString('rel="preload"', $result);
        $this->assertStringContainsString('as="style"', $result);
    }

    public function testBuildHeaderMultiple(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['style' => '/css', 'script' => '/js']);
        $this->resource->register('style', 'app.css');
        $this->resource->register('script', 'app.js');

        $result = $this->resource->buildHeader();

        $this->assertStringContainsString('</public/css/app.css>', $result);
        $this->assertStringContainsString('</public/js/app.js>', $result);
        $this->assertStringContainsString(',', $result); // Multiple resources separated by comma
    }

    public function testBuildHeaderFont(): void
    {
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', ['font' => '/fonts']);
        $this->resource->register('font', 'roboto-regular.woff2');

        $result = $this->resource->buildHeader();

        $this->assertStringContainsString('as="font"', $result);
        $this->assertStringContainsString('type="font/woff2"', $result);
        $this->assertStringContainsString('crossorigin="anonymous"', $result);
    }

    // Protected methods test via reflection

    public function testGetLinkAttrAsStyle(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getLinkAttrAs');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'style');

        $this->assertEquals('style', $result);
    }

    public function testGetLinkAttrAsScript(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getLinkAttrAs');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'script');

        $this->assertEquals('script', $result);
    }

    public function testGetLinkAttrAsFont(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getLinkAttrAs');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'font');

        $this->assertEquals('font', $result);
    }

    public function testGetLinkAttrAsUrl(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getLinkAttrAs');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'url');

        $this->assertEquals('', $result);
    }

    public function testGetLinkAttrAsUnknown(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getLinkAttrAs');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'unknown');

        $this->assertEquals('', $result);
    }

    public function testGetLinkAttrTypeFont(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getLinkAttrType');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'font');

        $this->assertEquals('font/woff2', $result);
    }

    public function testGetLinkAttrTypeStyle(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getLinkAttrType');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'style');

        $this->assertEquals('', $result);
    }

    public function testGetAdditionalAttrsFont(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getAdditionalAttrs');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'font');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('crossorigin', $result);
        $this->assertEquals('anonymous', $result['crossorigin']);
    }

    public function testGetAdditionalAttrsStyle(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getAdditionalAttrs');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'style');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetFileExtensionStyle(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'style');

        $this->assertEquals('css', $result);
    }

    public function testGetFileExtensionScript(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'script');

        $this->assertEquals('js', $result);
    }

    public function testGetFileExtensionFont(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'font');

        $this->assertEquals('woff2', $result);
    }

    public function testGetFileExtensionDocument(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'document');

        $this->assertEquals('html', $result);
    }

    public function testGetFileExtensionUnknown(): void
    {
        $reflection = new \ReflectionClass($this->resource);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $result = $method->invoke($this->resource, 'unknown');

        $this->assertEquals('', $result);
    }

    // Integration Tests

    public function testCompleteWorkflow(): void
    {
        // Setup
        $this->resource->setOption('basePath', '/public');
        $this->resource->setOption('filePath', [
            'style' => '/css',
            'script' => '/js',
            'font' => '/fonts'
        ]);

        // Register resources
        $this->resource->register('style', 'main.css');
        $this->resource->register('script', 'app.js');
        $this->resource->register('font', 'roboto.woff2');

        // Build links
        $links = $this->resource->buildLinks();

        // Verify all resources are included
        $this->assertStringContainsString('/public/css/main.css', $links);
        $this->assertStringContainsString('/public/js/app.js', $links);
        $this->assertStringContainsString('/public/fonts/roboto.woff2', $links);

        // Build header
        $header = $this->resource->buildHeader();

        // Verify header format
        $this->assertStringStartsWith('Link: ', $header);
        $this->assertStringContainsString('</public/css/main.css>', $header);
        $this->assertStringContainsString('</public/js/app.js>', $header);
        $this->assertStringContainsString('</public/fonts/roboto.woff2>', $header);
    }
}
