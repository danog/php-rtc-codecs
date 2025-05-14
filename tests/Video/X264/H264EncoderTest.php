<?php

namespace Tests\Webrtc\Codecs\Video\X264;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Webrtc\Codecs\Video\VideoEncoderTest;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\Video\X264\H264Decoder;
use Webrtc\Codecs\Video\X264\H264Encoder;
use Webrtc\Codecs\Video\X264\H264PayloadDescriptor;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(Codec::class)]
#[UsesClass(H264Decoder::class)]
#[UsesClass(H264PayloadDescriptor::class)]
#[CoversClass(H264Encoder::class)]
class H264EncoderTest extends VideoEncoderTest
{
    protected RTCRtpCodecParameters $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new RTCRtpCodecParameters(mimeType: "video/H264", clockRate: 90000, payloadType: 100);
    }

    public function testEncoder()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(H264Encoder::class, $encoder);

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 0);
        list($packages, $timestamp) = $encoder->encode($frame);
        $this->assertGreaterThanOrEqual(1, count($packages));
        $this->assertEquals(0, $timestamp);
    }

    public function testEncoderLarge()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(H264Encoder::class, $encoder);

        // first keyframe
        $frame = $this->createVideoFrame(width: 1280, height: 720, pts: 0);
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertGreaterThanOrEqual(3, count($payloads));
        $this->assertEquals(0, $timestamp);

        // delta frame
        $frame = $this->createVideoFrame(width: 1280, height: 720, pts: 3000);
        list($payloads, $timestamp) = $encoder->encode($frame);
        $this->assertGreaterThanOrEqual(1, count($payloads));
        $this->assertEquals(3000, $timestamp);

        // force keyframe
        $frame = $this->createVideoFrame(width: 1280, height: 720, pts: 6000);
        list($payloads, $timestamp) = $encoder->encode($frame, useKeyframe: true);
        $this->assertGreaterThanOrEqual(3, count($payloads));
        $this->assertEquals(6000, $timestamp);
    }

    public function testEncoderPack()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(H264Encoder::class, $encoder);

        $packet = $this->createPacket(payload: "\x00\x00\x01\x00", pts: 1);
        list($payloads, $timestamp) = $encoder->pack($packet);
        $this->assertEquals(["\x00"], $payloads);
        $this->assertEquals(90, $timestamp);
    }

    public function testEncoderTargetBitrate()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(H264Encoder::class, $encoder);
        $this->assertEquals(1000000, $encoder->getBitrate());

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 0);
        list($packages, $timestamp) = $encoder->encode($frame);
        $this->assertGreaterThanOrEqual(1, count($packages));
        $this->assertLessThan(1300, strlen($packages[0]));
        $this->assertEquals(0, $timestamp);

        // change target bitrate
        $encoder->setBitrate(1200000);
        $this->assertEquals(1200000, $encoder->getBitrate());

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 3000);
        list($packages, $timestamp) = $encoder->encode($frame);
        $this->assertGreaterThanOrEqual(1, count($packages));
        $this->assertLessThan(1300, strlen($packages[0]));
        $this->assertEquals(3000, $timestamp);
    }

    public function testRoundTrip1280x720()
    {
        $this->roundTripVideo(1280, 720);
    }

    public function testRoundTrip960x540()
    {
        $this->roundTripVideo(960, 540);
    }

    public function testRoundTrip640x480()
    {
        $this->roundTripVideo(640, 480);
    }

    public function testRoundTrip640x480TimeBase()
    {
        $this->roundTripVideo(640, 480, [1, 9000]);
    }

    public function testRoundTrip320x240()
    {
        $this->roundTripVideo(320, 240);
    }

    public function testSplitBitstream()
    {
        // No start code
        $packages = iterator_to_array(H264Encoder::splitBitstream("\x00\x00\x00\x00"));
        $this->assertEquals([], $packages);

        // 3-byte start code
        $packages = iterator_to_array(
            H264Encoder::splitBitstream("\x00\x00\x01\xff\x00\x00\x01\xfb")
        );
        $this->assertEquals(["\xff", "\xfb"], $packages);

        // 4-byte start code
        $packages = iterator_to_array(
            H264Encoder::splitBitstream("\x00\x00\x00\x01\xff\x00\x00\x00\x01\xfb")
        );
        $this->assertEquals(["\xff", "\xfb"], $packages);

        // Multiple bytes in a packet
        $packages = iterator_to_array(
            H264Encoder::splitBitstream("\x00\x00\x00\x01\xff\xab\xcd\x00\x00\x00\x01\xfb")
        );
        $this->assertEquals(["\xff\xab\xcd", "\xfb"], $packages);

        // Skip leading 0s
        $packages = iterator_to_array(H264Encoder::splitBitstream("\x00\x00\x00\x01\xff"));
        $this->assertEquals(["\xff"], $packages);

        // Both leading and trailing 0s
        $packages = iterator_to_array(
            H264Encoder::splitBitstream("\x00\x00\x00\x00\x00\x00\x01\xff\x00\x00\x00\x00\x00")
        );
        $this->assertEquals(["\xff\x00\x00\x00\x00\x00"], $packages);
    }

    public function testPacketizeOneSmall()
    {
        $packages = ["\xFF\xFF"];
        $packetizePackages = H264Encoder::packetize($packages);
        $this->assertEquals($packages, $packetizePackages);

        $packages = [str_repeat("\xFF", 1300)];
        $packetizePackages = H264Encoder::packetize($packages);
        $this->assertEquals($packages, $packetizePackages);
    }

    public function testPacketizeOneBig()
    {
        $packages = [str_repeat("\xFF\xFF", 1000)];
        $packetizePackages = H264Encoder::packetize($packages);
        $this->assertCount(2, $packetizePackages);
        $this->assertEquals(28, ord($packetizePackages[0][0]) & 0x1F);
        $this->assertEquals(28, ord($packetizePackages[1][0]) & 0x1F);
    }

    public function testPacketizeTwoSmall()
    {
        $packages = ["\x01\xFF", "\xFF\xFF"];
        $packetizePackages = H264Encoder::packetize($packages);
        $this->assertCount(1, $packetizePackages);
        $this->assertEquals(24, ord($packetizePackages[0][0]) & 0x1F);
    }

    public function testPacketizeMultipleSmall()
    {
        $packages = array_fill(0, 9, "\x01\xFF");
        $packetizePackages = H264Encoder::packetize($packages);
        $this->assertCount(1, $packetizePackages);
        $this->assertEquals(24, ord($packetizePackages[0][0]) & 0x1F);

        $packages = array_fill(0, 10, "\x01\xFF");
        $packetizePackages = H264Encoder::packetize($packages);
        $this->assertCount(2, $packetizePackages);
        $this->assertEquals(24, ord($packetizePackages[0][0]) & 0x1F);
        $this->assertEquals($packages[9], $packetizePackages[1]);
    }

    public function testFrameEncoder()
    {
        $encoder = $this->getEncoder();

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 0);
        $packages = iterator_to_array($encoder->encodeFrame($frame, false));

        $this->assertGreaterThanOrEqual(3, count($packages));
        $nalTypes = array_map(fn ($p) => ord($p[0]) & 0x1F, $packages);
        $this->assertNotEmpty(array_intersect([8, 7, 5], $nalTypes));

        $frame = $this->createVideoFrame(width: 640, height: 480, pts: 3000);
        $packages = iterator_to_array($encoder->encodeFrame($frame, false));
        $this->assertGreaterThanOrEqual(1, count($packages));

        // change resolution
        $frame = $this->createVideoFrame(width: 320, height: 240, pts: 6000);
        $packages = iterator_to_array($encoder->encodeFrame($frame, false));
        $this->assertGreaterThanOrEqual(1, count($packages));
    }
}
