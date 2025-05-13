<?php

namespace Tests\Webrtc\Codecs\Video\Vp8;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\Video\Vp8\Vp8Decoder;
use Webrtc\RTP\Parameters\RTCRtpCodecParameters;
use Webrtc\VPX\Context;
use Webrtc\VPX\Decoder;
use Webrtc\VPX\Vpx;

#[UsesClass(Codec::class)]
#[UsesClass(RTCRtpCodecParameters::class)]
#[UsesClass(Context::class)]
#[UsesClass(Decoder::class)]
#[UsesClass(Vpx::class)]
#[CoversClass(Vp8Decoder::class)]
class Vp8DecoderTest extends TestCase
{
    public function testDecoder()
    {
        $decoder = Codec::getDecoder(new RTCRtpCodecParameters(mimeType: 'video/VP8', clockRate: 90000, payloadType: 100));
        $this->assertInstanceOf(Vp8Decoder::class, $decoder);
    }
}
