<?php

namespace Tests\Webrtc\Codecs\Audio\PCM;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\Fraction;
use Tests\Webrtc\Codecs\JitterFrame;
use Webrtc\AVCodec\Audio\AudioLayout;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\AudioContext;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\AudioPlane;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Codecs\Audio\PCM\PCMaDecoder;
use Webrtc\Codecs\Codec as BaseCodec;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[CoversClass(PCMaDecoder::class)]
class PCMaDecoderTest extends TestCase
{
    private string $payload;
    private RTCRtpCodecParameters $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new RTCRtpCodecParameters(mimeType: 'audio/PCMA', clockRate: 8000, channels: 1, payloadType: 8);
        $this->payload = str_repeat("\xd5", 160);
    }

    public function testDecoder()
    {
        $decoder = BaseCodec::getDecoder($this->codec);
        $this->assertInstanceOf(PcmaDecoder::class, $decoder);

        $frames = $decoder->decode(new JitterFrame(data: $this->payload, timestamp: 0));
        $this->assertCount(1, $frames);

        $frame = $frames[0];
        $this->assertEquals("s16", $frame->getFormat()->getName());
        $this->assertEquals("mono", $frame->getLayout()->getName());

        $expectedBytes = str_repeat((PHP_INT_SIZE === 8 ? "\x08\x00" : "\x00\x08"), 160);

        $this->assertEquals($expectedBytes, $frame->getPlanes()[0]->getData());

        $this->assertEquals(0, $frame->getPts());
        $this->assertEquals(160, $frame->getSamples());
        $this->assertEquals(8000, $frame->getSampleRate());
        $this->assertEquals(new Fraction(1, 8000)(), $frame->getTimeBase());
    }
}
