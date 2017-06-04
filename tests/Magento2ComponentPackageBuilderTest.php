<?php

class Magento2ComponentPackageBuilderTest extends \PHPUnit\Framework\TestCase
{
    private $destinationZipPath;
    /**
     * @var Magento2ComponentPackageBuilder
     */
    private $builder;

    protected function setUp()
    {
        parent::setUp();
        $this->destinationZipPath = __DIR__ . '/fixtures/destination';
        $this->builder = new Magento2ComponentPackageBuilder(new DevNullOutput());
    }

    protected function tearDown()
    {
        parent::tearDown();
        @array_map('unlink', glob($this->destinationZipPath . '/*'));
    }

    public function testBuildMarketplacePackage()
    {
        $this->builder->build(
            __DIR__ . '/fixtures/awesome-module-repo/src',
            __DIR__ . '/fixtures/awesome-module-repo/composer.json',
            $this->destinationZipPath
        );
        $packageZipPath = $this->destinationZipPath . '/awesome-module-1.1.2.zip';
        $this->assertFileExists($packageZipPath);
        $zip = new \ZipArchive();
        $zip->open($packageZipPath);
        $zip->extractTo($this->destinationZipPath);
        $this->assertFileExists($this->destinationZipPath . '/composer.json');
        $this->assertFileExists($this->destinationZipPath . '/registration.php');
        $composerData = json_decode($this->destinationZipPath . '/composer.json', true);
        $this->assertEmpty($composerData['autoload']['psr-4']['Awesome\\Module\\']);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Source path "/invalid/path" is not a directory or is not readable.
     */
    public function testBuildMarkeplacePackageShouldFailIfSourcePathDoesNotExists()
    {
        $this->builder->build(
            '/invalid/path',
            __DIR__ . '/fixtures/awesome-module-repo/composer.json',
            $this->destinationZipPath
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp  /Cannot find Magento2 component registration file at path/
     */
    public function testBuildMarketplacePackageShouldFailIfSourcePathDoesNotContainRegistrationFile()
    {
        $this->builder->build(
            __DIR__ . '/fixtures/dir-without-registration-file',
            __DIR__ . '/fixtures/awesome-module-repo/composer.json',
            $this->destinationZipPath
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp  /Cannot decode source Composer file at path/
     */
    public function testBuildMarketplacePackageShouldFailIfSourceComposerIsNotValidJson()
    {
        $this->builder->build(
            __DIR__ . '/fixtures/awesome-module-repo/src',
            __DIR__ . '/fixtures/dir-with-invalid-composer-file/composer.json',
            $this->destinationZipPath
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp  /Cannot find required property "version" in source Composer file at path /
     */
    public function testBuildMarketplacePackageShouldFailIfSourceComposerDoesNotContainRequiredProperty()
    {
        $this->builder->build(
            __DIR__ . '/fixtures/awesome-module-repo/src',
            __DIR__ . '/fixtures/dir-with-incomplete-composer-file/composer.json',
            $this->destinationZipPath
        );
    }
}
