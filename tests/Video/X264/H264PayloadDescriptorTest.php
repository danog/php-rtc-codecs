<?php

namespace Tests\Webrtc\Codecs\Video\X264;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\Codecs\Video\X264\H264PayloadDescriptor;
use PHPUnit\Framework\TestCase;
use Webrtc\Exception\InvalidArgumentException;

#[UsesClass(Context::class)]
#[UsesClass(Dictionary::class)]
#[UsesClass(Frame::class)]
#[UsesClass(VideoFrame::class)]
#[CoversClass(H264PayloadDescriptor::class)]
class H264PayloadDescriptorTest extends TestCase
{

    public function testParseEmpty() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("NAL unit is too short");
        H264PayloadDescriptor::decode("");
    }

    public function testParseStapA() {
        $payload = $this->loadFile("h264_0000.bin");
        list($descr, $rest) = H264PayloadDescriptor::decode($payload);

        $this->assertTrue($descr);
        $this->assertEquals("\x00\x00\x00\x01", substr($rest, 0, 4));
        $this->assertEquals(26, strlen($rest));
    }

    public function testParseStapATruncated() {
        $payload = $this->loadFile("h264_0000.bin");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("NAL unit is too short");
        H264PayloadDescriptor::decode(substr($payload, 0, 1));
    }

    public function testParseStapATruncated2() {
        $payload = $this->loadFile("h264_0000.bin");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STAP-A length field is truncated");
        H264PayloadDescriptor::decode(substr($payload, 0, 2));
    }

    public function testParseStapATruncated3() {
        $payload = $this->loadFile("h264_0000.bin");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STAP-A data is truncated");
        H264PayloadDescriptor::decode(substr($payload, 0, 3));
    }

    public function testParseStapB() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("NAL unit type 25 is not supported");
        H264PayloadDescriptor::decode("\x19\x00");
    }

    public function testParseFuA1() {
        $payload = $this->loadFile("h264_0001.bin");
        list($descr, $rest) = H264PayloadDescriptor::decode($payload);

        $this->assertTrue($descr);
        $this->assertEquals("\x00\x00\x00\x01", substr($rest, 0, 4));
        $this->assertEquals(916, strlen($rest));
    }

    public function testParseFuA2() {
        $payload = $this->loadFile("h264_0002.bin");
        list($descr, $rest) = H264PayloadDescriptor::decode($payload);

        $this->assertFalse($descr);
        $this->assertNotEquals("\x00\x00\x00\x01", substr($rest, 0, 4));
        $this->assertEquals(912, strlen($rest));
    }

    public function testParseFuATruncated() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("NAL unit is too short");
        H264PayloadDescriptor::decode("\x7c");
    }

    public function testParseNalu() {
        $payload = $this->loadFile("h264_0003.bin");
        list($descr, $rest) = H264PayloadDescriptor::decode($payload);

        $this->assertTrue($descr);
        $this->assertEquals("\x00\x00\x00\x01", substr($rest, 0, 4));
        $this->assertEquals(substr($payload, 0), substr($rest, 4));
        $this->assertEquals(564, strlen($rest));
    }

    private function loadFile($filename): string
    {
        return file_get_contents(__DIR__ . "/../../fixture/$filename");
    }
}
