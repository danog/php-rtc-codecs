<?php

namespace Tests\Webrtc\Codecs\Video\Vp8;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\Codecs\Video\Vp8\Vp8PayloadDescriptor;
use PHPUnit\Framework\TestCase;
use Webrtc\Exception\InvalidArgumentException;

#[UsesClass(AudioFrame::class)]
#[UsesClass(Frame::class)]
#[CoversClass(Vp8PayloadDescriptor::class)]
#[CoversClass(Context::class)]
class Vp8PayloadDescriptorTest extends TestCase
{
    public function testNoPictureId() {
        list($descr, $rest) = Vp8PayloadDescriptor::decode("\x10");

        $this->assertEquals(1, $descr->getPartitionStart());
        $this->assertEquals(0, $descr->getPictureId());
        $this->assertNull($descr->getPictureId());
        $this->assertNull($descr->getTl0picIdx());
        $this->assertNull($descr->getTid());
        $this->assertNull($descr->getKeyIdx());
        $this->assertEquals("\x10", $descr->encode());
        $this->assertEquals("", $rest);
    }

    public function testShortPictureId17() {
        list($descr, $rest) = Vp8PayloadDescriptor::decode("\x90\x80\x11");

        $this->assertEquals(1, $descr->getPartitionStart());
        $this->assertEquals(0, $descr->getPartitionId());
        $this->assertEquals(17, $descr->getPictureId());
        $this->assertNull($descr->getTl0picIdx());
        $this->assertNull($descr->getTid());
        $this->assertNull($descr->getKeyIdx());
        $this->assertEquals("\x90\x80\x11", $descr->encode());
        $this->assertEquals("", $rest);
    }

    public function testShortPictureId127() {
        list($descr, $rest) = Vp8PayloadDescriptor::decode("\x90\x80\x7f");

        $this->assertEquals(1, $descr->getPartitionStart());
        $this->assertEquals(0, $descr->getPartitionId());
        $this->assertEquals(127, $descr->getPictureId());
        $this->assertNull($descr->getTl0picIdx());
        $this->assertNull($descr->getTid());
        $this->assertNull($descr->getKeyIdx());
        $this->assertEquals("\x90\x80\x7f", $descr->encode());
        $this->assertEquals("", $rest);
    }

    public function testLongPictureId128() {
        list($descr, $rest) = Vp8PayloadDescriptor::decode("\x90\x80\x80\x80");

        $this->assertEquals(1, $descr->getPartitionStart());
        $this->assertEquals(0, $descr->getPartitionId());
        $this->assertEquals(128, $descr->getPictureId());
        $this->assertNull($descr->getTl0picIdx());
        $this->assertNull($descr->getTid());
        $this->assertNull($descr->getKeyIdx());
        $this->assertEquals("\x90\x80\x80\x80", $descr->encode());
        $this->assertEquals("", $rest);
    }

    public function testLongPictureId4711() {
        // From RFC 7741 - 4.6.5
        list($descr, $rest) = Vp8PayloadDescriptor::decode("\x90\x80\x92\x67");

        $this->assertEquals(1, $descr->getPartitionStart());
        $this->assertEquals(0, $descr->getPartitionId());
        $this->assertEquals(4711, $descr->getPictureId());
        $this->assertNull($descr->getTl0picIdx());
        $this->assertNull($descr->getTid());
        $this->assertNull($descr->getKeyIdx());
        $this->assertEquals("\x90\x80\x92\x67", $descr->encode());
        $this->assertEquals("", $rest);
    }

    public function testTl0picidx() {
        list($descr, $rest) = Vp8PayloadDescriptor::decode("\x90\xc0\x92\x67\x81");

        $this->assertEquals(1, $descr->getPartitionStart());
        $this->assertEquals(0, $descr->getPartitionId());
        $this->assertEquals(4711, $descr->getPictureId());
        $this->assertEquals(129, $descr->getTl0picIdx());
        $this->assertNull($descr->getTid());
        $this->assertNull($descr->getKeyIdx());
        $this->assertEquals("\x90\xc0\x92\x67\x81", $descr->encode());
        $this->assertEquals("", $rest);
    }

    public function testTid() {
        list($descr, $rest) = Vp8PayloadDescriptor::decode("\x90\x20\xe0");

        $this->assertEquals(1, $descr->getPartitionStart());
        $this->assertEquals(0, $descr->getPictureId());
        $this->assertNull($descr->getPictureId());
        $this->assertNull($descr->getTl0picIdx());
        $this->assertEquals([3, 1], $descr->getTid());
        $this->assertNull($descr->getKeyIdx());
        $this->assertEquals("\x90\x20\xe0", $descr->encode());
        $this->assertEquals("", $rest);
    }

    public function testKeyidx() {
        list($descr, $rest) = Vp8PayloadDescriptor::decode("\x90\x10\x1f");

        $this->assertEquals(1, $descr->getPartitionStart());
        $this->assertEquals(0, $descr->getPictureId());
        $this->assertNull($descr->getPictureId());
        $this->assertNull($descr->getTl0picIdx());
        $this->assertNull($descr->getTid());
        $this->assertEquals(31, $descr->getKeyIdx());
        $this->assertEquals("\x90\x10\x1f", $descr->encode());
        $this->assertEquals("", $rest);
    }

    public function testTruncated() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("VPX descriptor is too short");
        Vp8PayloadDescriptor::decode("");
    }

    public function testTruncated2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("VPX descriptor has truncated extended bits");
        Vp8PayloadDescriptor::decode("\x80");
    }

    public function testTruncated3() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("VPX descriptor has truncated PictureID");
        Vp8PayloadDescriptor::decode("\x80\x80");
    }

    public function testTruncated4() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("VPX descriptor has truncated long PictureID");
        Vp8PayloadDescriptor::decode("\x80\x80\x80");
    }

    public function testTruncated5() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("VPX descriptor has truncated TL0PICIDX");
        Vp8PayloadDescriptor::decode("\x80\x40");
    }

    public function testTruncated6() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("VPX descriptor has truncated T/K");
        Vp8PayloadDescriptor::decode("\x80\x20");
    }

    public function testTruncated7() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("VPX descriptor has truncated T/K");
        Vp8PayloadDescriptor::decode("\x80\x10");
    }
}
