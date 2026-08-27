<?php

namespace Tests\Webrtc\Codecs\Video\Vp9;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\Codecs\Video\Vp9\Vp9Encoder;
use Webrtc\Codecs\Video\Vp9\Vp9PayloadDescriptor;
use Webrtc\Exception\RuntimeException;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[UsesClass(Codec::class)]
#[UsesClass(Vp9PayloadDescriptor::class)]
#[UsesClass(EncodedPacket::class)]
#[UsesClass(\Webrtc\RTPParameter\RTCRtpCodecParameters::class)]
#[CoversClass(Vp9Encoder::class)]
class Vp9EncoderTest extends TestCase
{
    /** Largest RTP payload the packetizer may emit, to stay inside a typical MTU. */
    private const MAX_PAYLOAD = 1300;

    private function encoder(): Vp9Encoder
    {
        $encoder = Codec::getEncoder(
            new RTCRtpCodecParameters(mimeType: 'video/VP9', clockRate: 90000, payloadType: 102)
        );
        $this->assertInstanceOf(Vp9Encoder::class, $encoder);

        return $encoder;
    }

    /**
     * Packetizing an already-encoded frame must work without any native library, which is
     * what lets VP9 files be played on a plain PHP installation.
     */
    public function testEncoderIsResolvedAndNeedsNoNativeLibrary()
    {
        [$payloads, $timestamp] = $this->encoder()->pack(new EncodedPacket('frame data', 4500, true));

        $this->assertCount(1, $payloads);
        $this->assertSame(4500, $timestamp);
    }

    public function testLargeFrameIsFragmentedAndReassembles()
    {
        $frame = random_bytes(4000);

        [$payloads] = $this->encoder()->pack(new EncodedPacket($frame, 9000, true));

        $this->assertGreaterThan(1, count($payloads), 'a 4000 byte frame cannot fit in one packet');

        $recovered = '';
        $last = count($payloads) - 1;
        foreach ($payloads as $index => $payload) {
            $this->assertLessThanOrEqual(self::MAX_PAYLOAD, strlen($payload));

            [$descriptor, $fragment] = Vp9PayloadDescriptor::decode($payload);
            $this->assertSame($index === 0 ? 1 : 0, $descriptor->getStartOfFrame(), "B bit of fragment $index");
            $this->assertSame($index === $last ? 1 : 0, $descriptor->getEndOfFrame(), "E bit of fragment $index");
            $recovered .= $fragment;
        }

        $this->assertSame($frame, $recovered, 'reassembling the fragments must yield the frame');
    }

    public function testKeyframesClearTheInterPicturePredictedBit()
    {
        $encoder = $this->encoder();

        [$keyPayloads] = $encoder->pack(new EncodedPacket('key', 0, true));
        [$deltaPayloads] = $encoder->pack(new EncodedPacket('delta', 3000, false));

        [$key] = Vp9PayloadDescriptor::decode($keyPayloads[0]);
        [$delta] = Vp9PayloadDescriptor::decode($deltaPayloads[0]);

        $this->assertFalse($key->isInterPicturePredicted());
        $this->assertTrue($delta->isInterPicturePredicted());
    }

    public function testPictureIdAdvancesPerFrame()
    {
        $encoder = $this->encoder();

        [$first] = $encoder->pack(new EncodedPacket('a', 0, true));
        [$second] = $encoder->pack(new EncodedPacket('b', 3000, false));

        [$one] = Vp9PayloadDescriptor::decode($first[0]);
        [$two] = Vp9PayloadDescriptor::decode($second[0]);

        $this->assertSame(($one->getPictureId() + 1) % (1 << 15), $two->getPictureId());
    }

    /**
     * Every fragment of one frame must carry the same picture ID, or a receiver cannot
     * tell which fragments belong together.
     */
    public function testAllFragmentsShareOnePictureId()
    {
        [$payloads] = $this->encoder()->pack(new EncodedPacket(random_bytes(4000), 0, true));

        $pictureIds = [];
        foreach ($payloads as $payload) {
            [$descriptor] = Vp9PayloadDescriptor::decode($payload);
            $pictureIds[] = $descriptor->getPictureId();
        }

        $this->assertCount(1, array_unique($pictureIds));
    }

    public function testRealtimeEncodingReportsThatItIsUnavailable()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not implemented/');

        $this->encoder()->encode(
            $this->createStub(\Webrtc\AVCodec\Frame\FrameInterface::class)
        );
    }
}
