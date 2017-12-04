<?php

namespace Kibo\Phast\Filters\HTML;

use Kibo\Phast\Common\ObjectifiedFunctions;
use PHPUnit\Framework\TestCase;

class CompositeHTMLFilterTest extends TestCase {

    const MAX_BUFFER_SIZE_TO_APPLY = 1024;

    /**
     * @var ObjectifiedFunctions
     */
    private $functions;

    /**
     * @var CompositeHTMLFilter
     */
    private $filter;

    public function setUp() {
        parent::setUp();
        $this->functions = new ObjectifiedFunctions();
        $this->filter = new CompositeHTMLFilter(self::MAX_BUFFER_SIZE_TO_APPLY, $this->functions);
    }

    public function testShouldApplyOnHTML() {
        $this->shouldTransform();
        $buffer = "<!DOCTYPE html>\n<html>\n<body></body>\n</html>";
        $filtered = $this->filter->apply($buffer);
        $this->assertRegExp("~^<!doctype html><html>\s*<body></body>\s*</html>~", $filtered);
    }

    public function testShouldApplyOnXHTML() {
        $this->shouldTransform();
        $buffer = "<?xml version=\"1.0\"?\><!DOCTYPE html>\n<html>\n<body></body>\n</html>";
        $this->filter->apply($buffer);
    }

    public function testShouldApplyOnLowerDOCTYPE() {
        $this->shouldTransform();
        $buffer = "<!doctype html>\n<html>\n<body></body>\n</html>";
        $this->filter->apply($buffer);
    }

    public function testShouldApplyWithNoDOCTYPE() {
        $this->shouldTransform();
        $buffer = "<html>\n<body></body>\n</html>";
        $this->filter->apply($buffer);
    }

    public function testShouldApplyWithWhitespacesStart() {
        $this->shouldTransform();
        $buffer = "    \n<!doctype       html>\n<html>\n<body></body>\n</html>";
        $this->filter->apply($buffer);
    }

    public function testShouldNotApplyWithNoBodyEndTag() {
        $this->shouldNotTransform();
        $buffer = "<html>\n<body>";
        $this->filter->apply($buffer);
    }

    public function testShouldNotApplyIfNotHTML() {
        $this->shouldNotTransform();
        $buffer = '<?xml version="1.0"?\><tag>asd</tag>';
        $this->filter->apply($buffer);
    }

    public function testNotLoadingBadHTML() {
        $this->shouldNotTransform();
        $buffer = "\0<html><body></body></html>";
        $doc = new \DOMDocument();
        $loads = @$doc->loadHTML($buffer);
        $this->assertFalse($loads);
        $this->assertEquals($buffer, $this->filter->apply($buffer));
    }

    public function testShouldReturnApplied() {
        $this->shouldTransform();
        $buffer = "<html>\n<body></body>\n";
        $return = $this->filter->apply($buffer);
        $this->assertTrue(is_string($return));
        $this->assertNotEquals($buffer, $return);
    }

    public function testShouldReturnOriginal() {
        $this->shouldNotTransform();
        $buffer = 'yolo';
        $this->assertEquals($buffer, $this->filter->apply($buffer));
    }

    public function testShouldApplyAllFilters() {
        $this->shouldTransform();
        $this->shouldTransform();
        $buffer = '<html><body></body></html>';
        $this->filter->apply($buffer);
    }

    public function testShouldNotApplyIfBufferIsTooBig() {
        $this->shouldNotTransform();
        $buffer = sprintf('<html><body>%s</body></html>', str_pad('', self::MAX_BUFFER_SIZE_TO_APPLY, 's'));
        $filtered = $this->filter->apply($buffer);
        $this->assertEquals($buffer, $filtered);
    }

    public function testShouldOutputUTF8WithDeclaration() {
        $this->shouldTransform();
        $buffer = '<html><head><meta charset=utf8></head><body>ü</body></html>';
        $filtered = $this->filter->apply($buffer);
        $this->assertContains('ü', $filtered);
    }

    public function testShouldOutputUTF8WithoutDeclaration() {
        $this->shouldTransform();
        $buffer = '<html><body>ü</body></html>';
        $filtered = $this->filter->apply($buffer);
        $this->assertContains('ü', $filtered);
    }

    public function testHandleMixedUTF8AndWindows1252() {
        $this->shouldTransform();
        $buffer = "<html><body>ü\xfc</body></html>";
        $filtered = $this->filter->apply($buffer);
        $this->assertContains('üü', $filtered);
    }

    public function testHandleMixedUTF8AndWindows1252WithEuroSign() {
        $this->shouldTransform();
        $buffer = "<html><body>ü\x80</body></html>";
        $filtered = $this->filter->apply($buffer);
        $this->assertContains('ü€', $filtered);
    }

    public function testShouldHandleTagCloseInScript() {
        $this->shouldTransform();
        $script = "<script>document.write('<div></div>');</script>";
        $buffer = "<html><body>$script</body></html>";
        $filtered = $this->filter->apply($buffer);
        $this->assertContains($script, $filtered);
    }

    public function testShouldHandleExceptions() {
        $filter = $this->createMock(HTMLFilter::class);
        $filter->expects($this->once())
            ->method('transformHTMLDOM')
            ->willThrowException(new \Exception());
        $this->filter->addHTMLFilter($filter);
        $buffer = '<html><body></body></html>';

        $logMsg = null;
        $this->functions->error_log = function ($msg) use (&$logMsg) {
            $logMsg = $msg;
        };

        $actual = $this->filter->apply($buffer);
        $this->assertEquals($buffer, $actual);
        $this->assertStringStartsWith('Phast: CompositeHTMLFilter: ', $logMsg);
    }

    private function setExpectation($expectation) {
        $filterMock = $this->createMock(HTMLFilter::class);
        $filterMock->expects($expectation)->method('transformHTMLDOM');
        $this->filter->addHTMLFilter($filterMock);
        return $filterMock;
    }

    private function shouldTransform() {
        return $this->setExpectation($this->once());
    }

    private function shouldNotTransform() {
        return $this->setExpectation($this->never());
    }

}
