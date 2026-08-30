<?php

namespace Tests\Webrtc\Codecs\Video\Vp8;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Webrtc\Codecs\Video\VideoEncoderTest;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\CodecUtility;
use Webrtc\Codecs\Video\Vp8\Vp8Decoder;
use Webrtc\Codecs\Video\Vp8\Vp8Encoder;
use Webrtc\Codecs\Video\Vp8\Vp8PayloadDescriptor;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(Codec::class)]
#[UsesClass(CodecUtility::class)]
#[UsesClass(Vp8Decoder::class)]
#[UsesClass(Vp8PayloadDescriptor::class)]
#[CoversClass(Vp8Encoder::class)]
class Vp8EncoderTest extends VideoEncoderTest
{
    protected $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new RTCRtpCodecParameters(mimeType: 'video/VP8', clockRate: 90000, payloadType: 100);
    }

    public function testEncoder()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(Vp8Encoder::class, $encoder);

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 0);
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertCount(1, $payloads);
        $this->assertLessThan(1300, strlen($payloads[0]));
        $this->assertEquals(0, $timestamp);

        // change resolution
        $frame = $this->createVideoFrame(width: 320, height: 240, pts: 3000);
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertCount(1, $payloads);
        $this->assertLessThan(1300, strlen($payloads[0]));
        $this->assertEquals(3000, $timestamp);
    }

    public function testEncoderRgb()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(Vp8Encoder::class, $encoder);

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 0, format: "rgb24");
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertCount(1, $payloads);
        $this->assertLessThan(1300, strlen($payloads[0]));
        $this->assertEquals(0, $timestamp);
    }

    public function testEncoderPack()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(Vp8Encoder::class, $encoder);
        $encoder->setPictureId(0);

        $packet = $this->createPacket(payload: "\x00", pts: 1);
        list($payloads, $timestamp) = $encoder->pack($packet);
        $this->assertEquals(["\x90\x80\x00\x00"], $payloads);
        $this->assertEquals(90, $timestamp);
    }

    public function testEncoderLarge()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(Vp8Encoder::class, $encoder);

        // first keyframe
        $frame = $this->createVideoFrame(width: 2560, height: 1920, pts: 0);
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertCount(7, $payloads);
        $this->assertEquals(1300, strlen($payloads[0]));
        $this->assertEquals(0, $timestamp);

        // delta frame
        $frame = $this->createVideoFrame(width: 2560, height: 1920, pts: 3000);
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertCount(1, $payloads);
        $this->assertLessThan(1300, strlen($payloads[0]));
        $this->assertEquals(3000, $timestamp);

        // force keyframe
        $frame = $this->createVideoFrame(width: 2560, height: 1920, pts: 6000);
        list($payloads, $timestamp) = $encoder->encode($frame, useKeyframe: true);
        $this->assertCount(7, $payloads);
        $this->assertEquals(1300, strlen($payloads[0]));
        $this->assertEquals(6000, $timestamp);
    }

    public function testEncoderTargetBitrate()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(Vp8Encoder::class, $encoder);
        $this->assertEquals(500000, $encoder->getBitrate());

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 0);
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertCount(1, $payloads);
        $this->assertLessThan(1300, strlen($payloads[0]));
        $this->assertEquals(0, $timestamp);

        // change target bitrate
        $encoder->setBitrate(600000);
        $this->assertEquals(600000, $encoder->getBitrate());

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 3000);
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertCount(1, $payloads);
        $this->assertLessThan(1300, strlen($payloads[0]));
        $this->assertEquals(3000, $timestamp);
    }

    public function testNumberOfThreads()
    {
        $this->assertEquals(8, Vp8Encoder::numberOfThreads(1920 * 1080, 16));
        $this->assertEquals(3, Vp8Encoder::numberOfThreads(1920 * 1080, 8));
        $this->assertEquals(2, Vp8Encoder::numberOfThreads(1920 * 1080, 4));
        $this->assertEquals(1, Vp8Encoder::numberOfThreads(1920 * 1080, 2));
    }

    public function testRoundTrip1280x720()
    {
        $this->roundtripVideo(1280, 720);
    }

    public function testRoundTrip960x540()
    {
        $this->roundtripVideo(960, 540);
    }

    public function testRoundTrip640x480()
    {
        $this->roundtripVideo(640, 480);
    }

    public function testRoundTrip640x480TimeBase()
    {
        $this->roundtripVideo(640, 480, [1, 9000]);
    }

    public function testRoundTrip320x240()
    {
        $this->roundtripVideo(320, 240);
    }
}
