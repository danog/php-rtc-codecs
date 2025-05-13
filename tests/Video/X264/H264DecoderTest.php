<?php

namespace Tests\Webrtc\Codecs\Video\X264;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Codec as ACodec;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Context\VideoContext;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\Video\X264\H264Decoder;
use Webrtc\RTP\Jitter\JitterFrame;
use Webrtc\RTP\Parameters\RTCRtpCodecParameters;

#[UsesClass(AVCodec::class)]
#[UsesClass(ACodec::class)]
#[UsesClass(Context::class)]
#[UsesClass(Dictionary::class)]
#[UsesClass(VideoContext::class)]
#[UsesClass(Buffer::class)]
#[UsesClass(Packet::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(Frame::class)]
#[UsesClass(TransCoder::class)]
#[UsesClass(Codec::class)]
#[UsesClass(JitterFrame::class)]
#[UsesClass(RTCRtpCodecParameters::class)]
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
