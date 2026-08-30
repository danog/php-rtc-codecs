<?php

namespace Tests\Webrtc\Codecs\Audio\Opus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Webrtc\Codecs\Audio\AudioEncoderTest;
use Webrtc\Codecs\Audio\Opus\OpusDecoder;
use Webrtc\Codecs\Audio\Opus\OpusEncoder;
use Webrtc\Codecs\Codec;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(Codec::class)]
#[UsesClass(OpusDecoder::class)]
#[CoversClass(OpusEncoder::class)]
class OpusEncoderTest extends AudioEncoderTest
{
    private const OPUS_PAYLOAD = "\xfc\xff\xfe";
    protected $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new RTCRtpCodecParameters(mimeType: 'audio/opus', clockRate: 48000, channels: 2, payloadType: 100);
    }

    public function testEncoderMono8khz()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(OpusEncoder::class, $encoder);

        $output = array_map(fn($frame) => $encoder->encode($frame), $this->createAudioFrames(layout: "mono", sampleRate: 8000, count: 3));

        $expected = [
            [[], null],  // No output due to buffering
            [[self::OPUS_PAYLOAD], 0],
            [[self::OPUS_PAYLOAD], 960],
        ];

        $this->assertEquals($expected, $output);
    }

    public function testEncoderStereo8khz()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(OpusEncoder::class, $encoder);

        $output = array_map(fn($frame) => $encoder->encode($frame), $this->createAudioFrames(layout: "stereo", sampleRate: 8000, count: 3));

        $expected = [
            [[], null],  // No output due to buffering
            [[self::OPUS_PAYLOAD], 0],
            [[self::OPUS_PAYLOAD], 960],
        ];

        $this->assertEquals($expected, $output);
    }

    public function testEncoderStereo48khz()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(OpusEncoder::class, $encoder);

        $frames = $this->createAudioFrames(layout: "stereo", sampleRate: 48000, count: 2);

        // first frame
        list($payloads, $timestamp) = $encoder->encode($frames[0]);
        $this->assertEquals([self::OPUS_PAYLOAD], $payloads);
        $this->assertEquals(0, $timestamp);

        // second frame
        list($payloads, $timestamp) = $encoder->encode($frames[1]);
        $this->assertEquals(960, $timestamp);
    }

    public function testEncoderPack()
    {
        $encoder = $this->getEncoder();
        $this->assertInstanceOf(OpusEncoder::class, $encoder);

        $packet = $this->createPacket(payload: self::OPUS_PAYLOAD, pts: 1);
        list($payloads, $timestamp) = $encoder->pack($packet);
        $this->assertEquals([self::OPUS_PAYLOAD], $payloads);
        $this->assertEquals(48, $timestamp);
    }

    public function testRoundTrip()
    {
        $this->roundTripAudio(outputLayout: "stereo", outputSampleRate: 48000, inputLayout: "stereo", inputSampleRate: 48000);
    }

    public function testRoundTripWithLoss()
    {
        $this->roundTripAudio("stereo", outputSampleRate: 48000, inputLayout: "stereo", inputSampleRate: 48000, drop: [1]);
    }
}