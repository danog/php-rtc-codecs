<?php

namespace Tests\Webrtc\Codecs\Audio\PCM;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\Fraction;
use Tests\Webrtc\Codecs\JitterFrame;
use Webrtc\AVCodec\Audio\AudioLayout;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Codec as ACodec;
use Webrtc\AVCodec\Context\AudioContext;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\AudioPlane;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Codecs\Audio\PCM\PCMuDecoder;
use Webrtc\Codecs\Codec;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[CoversClass(PCMuDecoder::class)]
class PCMuDecoderTest extends TestCase
{
    private string $payload;
    private $codec;

    protected function setUp(): void
    {
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
        $this->assertEquals(new Fraction(1, 8000)(), $frame->getTimeBase());
    }
}
