<?php

namespace Tests\Webrtc\Codecs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\CodecUtility;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\Opus\Decoder;
use Webrtc\Opus\Encoder;
use Webrtc\RTP\Parameters\RTCRtcpFeedback;
use Webrtc\RTP\Parameters\RTCRtpCapabilities;
use Webrtc\RTP\Parameters\RTCRtpCodecCapability;
use Webrtc\RTP\Parameters\RTCRtpCodecParameters;
use Webrtc\RTP\Parameters\RTCRtpHeaderExtensionCapability;
use Webrtc\RTP\Parameters\RTCRtpHeaderExtensionParameters;

#[UsesClass(AudioFrame::class)]
#[UsesClass(Frame::class)]
#[UsesClass(Decoder::class)]
#[UsesClass(Encoder::class)]
#[UsesClass(RTCRtpCodecParameters::class)]
#[UsesClass(CodecUtility::class)]
#[UsesClass(RTCRtpCapabilities::class)]
#[UsesClass(RTCRtpCodecCapability::class)]
#[UsesClass(RTCRtpHeaderExtensionCapability::class)]
#[UsesClass(RTCRtpHeaderExtensionParameters::class)]
#[UsesClass(RTCRtcpFeedback::class)]
#[CoversClass(Codec::class)]
class CodecTest extends TestCase
{

    public function testCapabilities() {
        // audio
        $codec = new Codec();
        $capabilities = $codec->getCapabilities('audio');
        $this->assertInstanceOf(RTCRtpCapabilities::class, $capabilities);

        $expectedAudioCodecs = [
            new RTCRtpCodecCapability('audio/opus', 48000, 2),
            new RTCRtpCodecCapability('audio/PCMU', 8000, 1),
            new RTCRtpCodecCapability('audio/PCMA', 8000, 1),
        ];
        $this->assertEquals($expectedAudioCodecs, $capabilities->codecs);

        $expectedAudioExtensions = [
            new RTCRtpHeaderExtensionCapability('urn:ietf:params:rtp-hdrext:sdes:mid'),
            new RTCRtpHeaderExtensionCapability('urn:ietf:params:rtp-hdrext:ssrc-audio-level'),
        ];
        $this->assertEquals($expectedAudioExtensions, $capabilities->headerExtensions);

        // video
        $capabilities = $codec->getCapabilities('video');
        $this->assertInstanceOf(RTCRtpCapabilities::class, $capabilities);

        $expectedVideoCodecs = [
            new RTCRtpCodecCapability('video/VP8', 90000),
            new RTCRtpCodecCapability('video/rtx', 90000),
            new RTCRtpCodecCapability('video/H264', 90000, null, [
                'level-asymmetry-allowed' => '1',
                'packetization-mode' => '1',
                'profile-level-id' => '42001f',
            ]),
            new RTCRtpCodecCapability('video/H264', 90000, null, [
                'level-asymmetry-allowed' => '1',
                'packetization-mode' => '1',
                'profile-level-id' => '42e01f',
            ]),
        ];
        $this->assertEquals($expectedVideoCodecs, $capabilities->codecs);

        $expectedVideoExtensions = [
            new RTCRtpHeaderExtensionCapability('urn:ietf:params:rtp-hdrext:sdes:mid'),
            new RTCRtpHeaderExtensionCapability('http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time'),
        ];
        $this->assertEquals($expectedVideoExtensions, $capabilities->headerExtensions);

        // Invalid
        $this->expectException(InvalidArgumentException::class);
        $codec->getCapabilities('invalid');
    }

    public function testGetDecoder()
    {
        $invalidCodec = $this->getInvalidCodec();
        $this->expectException(InvalidArgumentException::class);
        Codec::getDecoder($invalidCodec);
    }

    public function testGetEncoder()
    {
        $invalidCodec = $this->getInvalidCodec();
        $this->expectException(InvalidArgumentException::class);
        Codec::getEncoder($invalidCodec);
    }

    private function getInvalidCodec(): RTCRtpCodecParameters
    {
        return new RTCRtpCodecParameters(mimeType: "audio/bogus", clockRate: 8000, channels: 1, payloadType: 0);
    }
}