<?php

namespace Tests\Webrtc\Codecs\Audio\PCM;

use Webrtc\AVCodec\AVCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\Fraction;
use Tests\Webrtc\Codecs\JitterFrame;
use Webrtc\Codecs\Audio\PCM\PCMuDecoder;
use Webrtc\Codecs\Codec;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(\Webrtc\AVCodec\AVCodec::class)]
#[UsesClass(\Webrtc\AVCodec\AVFilter::class)]
#[UsesClass(\Webrtc\AVCodec\AVFormat::class)]
#[UsesClass(\Webrtc\AVCodec\Audio\AudioLayout::class)]
#[UsesClass(\Webrtc\AVCodec\Codec::class)]
#[UsesClass(\Webrtc\AVCodec\Context\AudioContext::class)]
#[UsesClass(\Webrtc\AVCodec\Context\Context::class)]
#[UsesClass(\Webrtc\AVCodec\Context\Dictionary::class)]
#[UsesClass(\Webrtc\AVCodec\Data\AudioPlane::class)]
#[UsesClass(\Webrtc\AVCodec\Data\Buffer::class)]
#[UsesClass(\Webrtc\AVCodec\Data\Packet::class)]
#[UsesClass(\Webrtc\AVCodec\Format\AudioFormat::class)]
#[UsesClass(\Webrtc\AVCodec\Frame\AudioFrame::class)]
#[UsesClass(\Webrtc\AVCodec\Frame\Frame::class)]
#[UsesClass(\Webrtc\AVCodec\TransCoder::class)]
#[UsesClass(Codec::class)]
#[UsesClass(\Webrtc\RTPParameter\RTCRtpCodecParameters::class)]
#[CoversClass(PCMuDecoder::class)]
class PCMuDecoderTest extends TestCase
{
    private string $payload;
    private $codec;

    protected function setUp(): void
    {
        if (!AVCodec::isAvailable()) {
            self::markTestSkipped(
                'Transcoding needs the FFI extension and an FFmpeg build matching the bundled headers.'
            );
        }

        parent::setUp();
        $this->codec = new RTCRtpCodecParameters(mimeType: 'audio/PCMU', clockRate: 8000, channels: 1, payloadType: 0);
        $this->payload = str_repeat("\xff", 160);
    }

    public function testDecoder()
    {
        $decoder = Codec::getDecoder($this->codec);
        $this->assertInstanceOf(PcmuDecoder::class, $decoder);

        $frames = $decoder->decode(new JitterFrame(data: $this->payload, timestamp: 0));
        $this->assertCount(1, $frames);

        $frame = $frames[0];
        $this->assertEquals("s16", $frame->getFormat()->getName());
        $this->assertEquals("mono", $frame->getLayout()->getName());
        $this->assertEquals(str_repeat("\x00\x00", 160), $frame->getPlanes()[0]->getData());
        $this->assertEquals(0, $frame->getPts());
        $this->assertEquals(160, $frame->getSamples());
        $this->assertEquals(8000, $frame->getSampleRate());
        $this->assertEquals((new Fraction(1, 8000))(), $frame->getTimeBase());
    }
}
