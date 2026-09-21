<?php

namespace Tests\Webrtc\Codecs\Video\Av1;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\Codecs\Video\Av1\Av1Encoder;
use Webrtc\Codecs\Video\Av1\Av1PayloadDescriptor;
use Webrtc\Exception\InvalidArgumentException;

#[CoversClass(Av1PayloadDescriptor::class)]
class Av1PayloadDescriptorTest extends TestCase
{
    /** An OBU of the given type with its size field and a synthetic payload. */
    private static function obu(int $type, int $payloadLength): string
    {
        $payload = str_repeat(chr($type), $payloadLength);
        return chr(($type << 3) | 0x02) . self::leb128($payloadLength) . $payload;
    }

    private static function leb128(int $value): string
    {
        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $out .= chr($value !== 0 ? $byte | 0x80 : $byte);
        } while ($value !== 0);
        return $out;
    }

    public function testParseEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Av1PayloadDescriptor::decode("");
    }

    public function testParseTruncatedElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("truncated");
        // W = 0, first element claims 100 bytes, only 2 follow.
        Av1PayloadDescriptor::decode("\x00\x64\x0a\x0b");
    }

    public function testTemporalUnitRoundTrip(): void
    {
        // Temporal delimiter (dropped on the wire), sequence header, a frame larger than an RTP
        // packet (fragmented) and a small frame: a keyframe temporal unit.
        $tu = self::obu(2, 0) . self::obu(1, 12) . self::obu(6, 3000) . self::obu(6, 40);
        [$payloads, $timestamp] = (new Av1Encoder())->pack(new EncodedPacket($tu, 9000));
        $this->assertSame(9000, $timestamp);
        $this->assertGreaterThan(1, count($payloads));
        // N bit: the first packet of a new coded video sequence.
        $this->assertSame(0x08, ord($payloads[0][0]) & 0x08);

        $elements = '';
        foreach ($payloads as $i => $payload) {
            [$startsObu, $chunk] = Av1PayloadDescriptor::decode($payload);
            $this->assertSame($i === 0 || (ord($payload[0]) & 0x80) === 0, $startsObu);
            $elements .= $chunk;
        }
        // Reassembled: every OBU with its size field, minus the temporal delimiter.
        $expected = self::obu(1, 12) . self::obu(6, 3000) . self::obu(6, 40);
        $this->assertSame($expected, Av1PayloadDescriptor::assemble($elements));
    }

    public function testAssembleKeepsSizeFieldsThatWereSent(): void
    {
        // A sender may leave obu_has_size_field set: W = 1 (one element, no length prefix).
        $obu = self::obu(6, 5);
        [$start, $elements] = Av1PayloadDescriptor::decode("\x10" . $obu);
        $this->assertTrue($start);
        $this->assertSame($obu, Av1PayloadDescriptor::assemble($elements));
    }

    public function testElementCountMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("announces 2 OBU elements but carries 1");
        // W = 2 but the only element runs... no: W=2 means the first has a length, the second runs to the end.
        Av1PayloadDescriptor::decode("\x20\x02\x30\x00");
    }
}
