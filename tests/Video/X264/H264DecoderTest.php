<?php

namespace Tests\Webrtc\Codecs\Video\X264;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\JitterFrame;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\Video\X264\H264Decoder;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(Codec::class)]
#[CoversClass(H264Decoder::class)]
class H264DecoderTest extends TestCase
{

    public function testDecoder()
    {
        $decoder = Codec::getDecoder(new RTCRtpCodecParameters(mimeType: "video/H264", clockRate: 90000, payloadType: 100));
        $this->assertInstanceOf(H264Decoder::class, $decoder);

        // decode junk
        $frames = $decoder->decode(new JitterFrame(data: "123", timestamp: 0));
        $this->assertEquals([], $frames);
    }
}
