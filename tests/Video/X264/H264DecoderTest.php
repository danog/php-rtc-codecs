<?php

namespace Tests\Webrtc\Codecs\Video\X264;

use Webrtc\AVCodec\AVCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\JitterFrame;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\Video\X264\H264Decoder;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(\Webrtc\AVCodec\AVCodec::class)]
#[UsesClass(\Webrtc\AVCodec\AVFilter::class)]
#[UsesClass(\Webrtc\AVCodec\AVFormat::class)]
#[UsesClass(\Webrtc\AVCodec\Codec::class)]
#[UsesClass(\Webrtc\AVCodec\Context\Context::class)]
#[UsesClass(\Webrtc\AVCodec\Context\Dictionary::class)]
#[UsesClass(\Webrtc\AVCodec\Context\VideoContext::class)]
#[UsesClass(\Webrtc\AVCodec\Data\Buffer::class)]
#[UsesClass(\Webrtc\AVCodec\Data\Packet::class)]
#[UsesClass(\Webrtc\AVCodec\TransCoder::class)]
#[UsesClass(Codec::class)]
#[UsesClass(\Webrtc\RTPParameter\RTCRtpCodecParameters::class)]
#[CoversClass(H264Decoder::class)]
class H264DecoderTest extends TestCase
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
        $decoder = Codec::getDecoder(new RTCRtpCodecParameters(mimeType: "video/H264", clockRate: 90000, payloadType: 100));
        $this->assertInstanceOf(H264Decoder::class, $decoder);

        // decode junk
        $frames = $decoder->decode(new JitterFrame(data: "123", timestamp: 0));
        $this->assertEquals([], $frames);
    }
}
