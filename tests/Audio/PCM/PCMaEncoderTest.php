<?php

namespace Tests\Webrtc\Codecs\Audio\PCM;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Webrtc\Codecs\Audio\AudioEncoderTest;
use Webrtc\Codecs\Audio\PCM\PCMaDecoder;
use Webrtc\Codecs\Audio\PCM\PCMaEncoder;
use Webrtc\Codecs\Audio\PCM\PCMDecoder;
use Webrtc\Codecs\Codec;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(PCMDecoder::class)]
#[UsesClass(PCMaDecoder::class)]
#[UsesClass(Codec::class)]
#[CoversClass(PCMaEncoder::class)]
class PCMaEncoderTest extends AudioEncoderTest
{
    private string $payload;
    protected $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new RTCRtpCodecParameters(mimeType: 'audio/PCMA', clockRate: 8000, channels: 1, payloadType: 8);
        $this->payload = str_repeat("\xd5", 160);
    }

    public function testEncoderMono8hz()
    {
        $encoder = $this->getEncoder();;
        $this->assertInstanceOf(PcmaEncoder::class, $encoder);

        foreach ($this->createAudioFrames(layout: "mono", sampleRate: 8000, count: 10) as $frame) {
            list($payloads, $timestamp) = $encoder->encode($frame);
            $this->assertEquals([$this->payload], $payloads);
            $this->assertEquals($frame->getPts(), $timestamp);
        }
    }

    public function testEncoderStereo8khz()
    {
        $encoder = $this->getEncoder();;
        $this->assertInstanceOf(PcmaEncoder::class, $encoder);

        foreach ($this->createAudioFrames(layout: "stereo", sampleRate: 8000, count: 10) as $frame) {
            list($payloads, $timestamp) = $encoder->encode($frame);
            $this->assertEquals([$this->payload], $payloads);
            $this->assertEquals($frame->getPts(), $timestamp);
        }
    }

    public function testEncoderStereo48khz()
    {
        $encoder = $this->getEncoder();;
        $this->assertInstanceOf(PcmaEncoder::class, $encoder);

        $output = array_map(fn($frame) => $encoder->encode($frame), $this->createAudioFrames("stereo", 48000, 10));

        $expected = [
            [[substr($this->payload, 0, 144)], 0],
            [[$this->payload], 144],
            [[$this->payload], 304],
            [[$this->payload], 464],
            [[$this->payload], 624],
            [[$this->payload], 784],
            [[$this->payload], 944],
            [[$this->payload], 1104],
            [[$this->payload], 1264],
            [[$this->payload], 1424],
        ];

        $this->assertEquals($expected, $output);
    }

    public function testEncoderPack()
    {
        $encoder = $this->getEncoder();;
        $this->assertInstanceOf(PcmaEncoder::class, $encoder);

        $packet = $this->createPacket(payload: $this->payload, pts: 1);
        list($payloads, $timestamp) = $encoder->pack($packet);
        $this->assertEquals([$this->payload], $payloads);
        $this->assertEquals(8, $timestamp);
    }

    public function testRoundTrip()
    {
        $this->roundTripAudio(outputLayout: "mono", outputSampleRate: 8000);
    }

    public function testRoundTripWithLoss()
    {
        $this->roundTripAudio(outputLayout: "mono", outputSampleRate: 8000, drop: [1]);
    }
}
