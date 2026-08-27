<?php

namespace Tests\Webrtc\Codecs\Video\Vp8;

use Webrtc\AVCodec\AVCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\Video\Vp8\Vp8Decoder;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(\Webrtc\AVCodec\AVCodec::class)]
#[UsesClass(\Webrtc\AVCodec\AVFilter::class)]
#[UsesClass(\Webrtc\AVCodec\AVFormat::class)]
#[UsesClass(\Webrtc\AVCodec\Codec::class)]
#[UsesClass(\Webrtc\AVCodec\Context\Context::class)]
#[UsesClass(\Webrtc\AVCodec\Context\VideoContext::class)]
#[UsesClass(\Webrtc\AVCodec\TransCoder::class)]
#[UsesClass(Codec::class)]
#[UsesClass(\Webrtc\RTPParameter\RTCRtpCodecParameters::class)]
#[CoversClass(Vp8Decoder::class)]
class Vp8DecoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!AVCodec::isAvailable()) {
            self::markTestSkipped(
                'Transcoding needs the FFI extension and an FFmpeg build matching the bundled headers.'
            );
        }
    }

    public function testDecoder()
    {
        $decoder = Codec::getDecoder(new RTCRtpCodecParameters(mimeType: 'video/VP8', clockRate: 90000, payloadType: 100));
        $this->assertInstanceOf(Vp8Decoder::class, $decoder);
    }
}
