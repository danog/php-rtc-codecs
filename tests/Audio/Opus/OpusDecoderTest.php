<?php

namespace Tests\Webrtc\Codecs\Audio\Opus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\Fraction;
use Tests\Webrtc\Codecs\JitterFrame;
use Webrtc\Codecs\Audio\Opus\OpusDecoder;
use Webrtc\Codecs\Codec;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(Codec::class)]
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
