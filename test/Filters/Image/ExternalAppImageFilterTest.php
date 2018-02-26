<?php

namespace Kibo\Phast\Filters\Image;


use Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException;
use Kibo\Phast\Filters\Image\ImageImplementations\DummyImage;
use PHPUnit\Framework\TestCase;

class ExternalAppImageFilterTest extends TestCase {

    /**
     * @var bool
     */
    private $shouldApply;

    /**
     * @var string
     */
    private $binPath;

    /**
     * @var string
     */
    private $defaultBin;

    /**
     * @var string
     */
    private $cmdArgs;

    /**
     * @var string
     */
    private $imageContent;

    /**
     * @var string
     */
    private $originalPathEnv;

    public function setUp() {
        parent::setUp();
        $this->shouldApply = true;
        $this->binPath = '/usr/local/bin/php';
        $this->defaultBin = 'php';
        $this->cmdArgs = ' -r "echo \"called\";"';
        $this->imageContent = 'test-image';
        $this->originalPathEnv = getenv('PATH');
    }

    public function tearDown() {
        parent::tearDown();
        putenv('PATH="' . addslashes($this->originalPathEnv) . '"');
    }

    public function testExecution() {
        $this->assertEquals('called', $this->performTest()->getAsString());
    }

    public function testNotCallingWhenShouldNotApply() {
        $this->shouldApply = false;
        $image = $this->performTest();
        $this->assertEquals($this->imageContent, $image->getAsString());
    }

    public function testExceptionOnNonExistingExecutable() {
        $this->binPath = 'does-not-exist';
        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessageRegExp('/^Executable not found/');
        $this->performTest();
    }

    public function testExceptionOnBadExitValue() {
        $this->cmdArgs = ' -r "exit(-1);"';
        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessageRegExp('/^External image processing command failed with status/');
        $this->performTest();
    }

    public function testExceptionOnNoResult() {
        $this->cmdArgs = ' -r "echo \"\";"';
        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessageRegExp('/^External image processing command did not output anything/');
        $this->performTest();
    }

    public function testFindingExecutableFromEnv() {
        $this->binPath = false;
        $this->assertEquals('called', $this->performTest()->getAsString());
    }

    public function testAddingPathsToPath() {
        putenv('PATH=');
        $this->binPath = false;
        $this->assertEquals('called', $this->performTest()->getAsString());
        $this->assertEmpty(getenv('PATH'));
    }

    /**
     * @return DummyImage
     */
    private function performTest() {
        $config = $this->binPath ? ['binpath' => $this->binPath] : [];
        $filter = $this->getMockBuilder(ExternalAppImageFilter::class)
            ->setConstructorArgs([$config])
            ->setMethods(['shouldApply', 'getDefaultBinName', 'getCmdArgs'])
            ->getMockForAbstractClass();
        $filter->method('shouldApply')
            ->willReturn($this->shouldApply);
        $filter->method('getDefaultBinName')
            ->willReturn($this->defaultBin);
        $filter->method('getCmdArgs')
            ->willReturn($this->cmdArgs);
        $image = new DummyImage();
        $image->setImageString($this->imageContent);
        return $filter->transformImage($image, []);
    }



}
