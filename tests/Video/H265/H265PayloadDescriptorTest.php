<?php

namespace Tests\Webrtc\Codecs\Video\H265;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\Codecs\Video\H265\H265Encoder;
use Webrtc\Codecs\Video\H265\H265PayloadDescriptor;
use Webrtc\Exception\InvalidArgumentException;

#[CoversClass(H265PayloadDescriptor::class)]
#[CoversClass(H265Encoder::class)]
class H265PayloadDescriptorTest extends TestCase
{
    /** A NAL unit of the given type with a synthetic body. */
    private static function nal(int $type, int $bodyLength): string
    {
        return chr($type << 1) . chr(0x01) . str_repeat(chr($type), $bodyLength);
    }

    public function testParseEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("NAL unit is too short");
        H265PayloadDescriptor::decode("\x40\x01");
    }

    public function testParsePaciIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("NAL unit type 50 is not supported");
        H265PayloadDescriptor::decode(chr(50 << 1) . "\x01\x00");
    }

    public function testSingleNalUnit(): void
    {
        $nal = self::nal(19, 10); // IDR_W_RADL
        [$start, $out] = H265PayloadDescriptor::decode($nal);
        $this->assertTrue($start);
        $this->assertSame("\x00\x00\x00\x01" . $nal, $out);
    }

    public function testAggregationPacketRoundTrip(): void
    {
        $nals = [self::nal(32, 20), self::nal(33, 40), self::nal(34, 6)]; // VPS, SPS, PPS
        $payloads = H265Encoder::packetize($nals);
        $this->assertCount(1, $payloads);
        $this->assertSame(H265PayloadDescriptor::NAL_TYPE_AP, H265PayloadDescriptor::nalType($payloads[0]));

        [$start, $out] = H265PayloadDescriptor::decode($payloads[0]);
        $this->assertTrue($start);
        $expected = '';
        foreach ($nals as $nal) {
            $expected .= "\x00\x00\x00\x01" . $nal;
        }
        $this->assertSame($expected, $out);
    }

    public function testAggregationPacketTruncated(): void
    {
        $payload = H265Encoder::packetize([self::nal(32, 20), self::nal(33, 40)])[0];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("AP data is truncated");
        H265PayloadDescriptor::decode(substr($payload, 0, 10));
    }

    public function testFragmentationUnitRoundTrip(): void
    {
        $nal = self::nal(19, 5000);
        $payloads = H265Encoder::packetize([$nal]);
        $this->assertGreaterThan(1, count($payloads));

        $out = '';
        foreach ($payloads as $i => $payload) {
            $this->assertSame(H265PayloadDescriptor::NAL_TYPE_FU, H265PayloadDescriptor::nalType($payload));
            $this->assertLessThanOrEqual(1200, strlen($payload));
            [$start, $chunk] = H265PayloadDescriptor::decode($payload);
            $this->assertSame($i === 0, $start);
            $out .= $chunk;
        }
        $this->assertSame("\x00\x00\x00\x01" . $nal, $out);
    }

    public function testPackSplitsAnnexBAccessUnit(): void
    {
        $nals = [self::nal(32, 20), self::nal(33, 40), self::nal(34, 6), self::nal(19, 3000)];
        $annexB = '';
        foreach ($nals as $nal) {
            $annexB .= "\x00\x00\x00\x01" . $nal;
        }
        [$payloads, $timestamp] = (new H265Encoder())->pack(new EncodedPacket($annexB, 4500));
        $this->assertSame(4500, $timestamp);

        $out = '';
        foreach ($payloads as $payload) {
            [, $chunk] = H265PayloadDescriptor::decode($payload);
            $out .= $chunk;
        }
        $this->assertSame($annexB, $out);
    }
}
