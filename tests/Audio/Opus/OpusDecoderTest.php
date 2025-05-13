<?php

namespace Tests\Webrtc\Codecs\Audio\Opus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\Fraction;
use Webrtc\AVCodec\Audio\AudioLayout;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\AVFilter;
use Webrtc\AVCodec\Data\AudioPlane;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\Codecs\Audio\Opus\OpusDecoder;
use Webrtc\Codecs\Codec;
use Webrtc\Opus\Decoder;
use Webrtc\Opus\Opus;
use Webrtc\RTP\Jitter\JitterFrame;
use Webrtc\RTP\Parameters\RTCRtpCodecParameters;

/*
 *- Webrtc\AVCodec\AVCodec
- Webrtc\AVCodec\AVFilter
- Webrtc\AVCodec\Audio\AudioLayout
- Webrtc\AVCodec\Data\AudioPlane
- Webrtc\AVCodec\Data\Buffer
- Webrtc\AVCodec\Format\AudioFormat
- Webrtc\AVCodec\Frame\AudioFrame
- Webrtc\AVCodec\Frame\Frame
- Webrtc\Codecs\Codec
- Webrtc\Opus\Decoder
- Webrtc\Opus\Opus
- Webrtc\RTP\Jitter\JitterFrame
- Webrtc\RTP\Parameters\RTCRtpCodecParameters

 * */

#[UsesClass(AVCodec::class)]
#[UsesClass(AVFilter::class)]
#[UsesClass(AudioLayout::class)]
#[UsesClass(AudioPlane::class)]
#[UsesClass(Buffer::class)]
#[UsesClass(AudioFormat::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(Frame::class)]
#[UsesClass(Codec::class)]
#[UsesClass(Decoder::class)]
#[UsesClass(Opus::class)]
#[UsesClass(JitterFrame::class)]
#[UsesClass(RTCRtpCodecParameters::class)]
#[CoversClass(OpusDecoder::class)]
class OpusDecoderTest extends TestCase
{
    private const OPUS_PAYLOAD = "\xfc\xff\xfe";
    private $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new RTCRtpCodecParameters(mimeType: 'audio/opus', clockRate: 48000, channels: 2, payloadType: 100);
    }

    public function testDecoder()
    {
        $decoder = Codec::getDecoder($this->codec);
        $this->assertInstanceOf(OpusDecoder::class, $decoder);

        $frames = $decoder->decode(new JitterFrame(data: self::OPUS_PAYLOAD, timestamp: 0));
        $this->assertCount(1, $frames);

        $frame = $frames[0];
        $this->assertEquals("s16", $frame->getFormat()->getName());
        $this->assertEquals("stereo", $frame->getLayout()->getName());
        $this->assertEquals(str_repeat("\x00", 4 * 960), $frame->getPlanes()[0]->getData());
        $this->assertEquals(48000, $frame->getSampleRate());
        $this->assertEquals(0, $frame->getPts());
        $this->assertEquals(new Fraction(1, 48000)(), $frame->getTimeBase());
    }
}
