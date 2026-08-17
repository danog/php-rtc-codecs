<?php

namespace Tests\Webrtc\Codecs\Video\Vp9;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Codecs\Video\Vp9\Vp9PayloadDescriptor;
use Webrtc\Exception\InvalidArgumentException;

#[CoversClass(Vp9PayloadDescriptor::class)]
class Vp9PayloadDescriptorTest extends TestCase
{
    public function testMinimalDescriptorRoundTrips()
    {
        $descriptor = new Vp9PayloadDescriptor(1, 1);

        $encoded = $descriptor->encode();
        $this->assertSame(1, strlen($encoded), 'a descriptor with no optional field is one octet');

        [$decoded, $payload] = Vp9PayloadDescriptor::decode($encoded . 'frame');

        $this->assertSame(1, $decoded->getStartOfFrame());
        $this->assertSame(1, $decoded->getEndOfFrame());
        $this->assertFalse($decoded->isInterPicturePredicted());
        $this->assertNull($decoded->getPictureId());
        $this->assertSame('frame', $payload);
    }

    /**
     * The P bit is the inverse of "keyframe", which is how a receiver finds a decodable
     * starting point without parsing the bitstream.
     */
    public function testInterPicturePredictedFlagRoundTrips()
    {
        $descriptor = new Vp9PayloadDescriptor(1, 0, true);

        [$decoded] = Vp9PayloadDescriptor::decode($descriptor->encode());

        $this->assertTrue($decoded->isInterPicturePredicted());
        $this->assertSame(0, $decoded->getEndOfFrame());
    }

    public function testShortPictureIdRoundTrips()
    {
        $descriptor = new Vp9PayloadDescriptor(1, 1, false, 0x42);

        $encoded = $descriptor->encode();
        $this->assertSame(2, strlen($encoded), 'a picture ID below 128 takes a single octet');

        [$decoded, $payload] = Vp9PayloadDescriptor::decode($encoded . 'abc');

        $this->assertSame(0x42, $decoded->getPictureId());
        $this->assertSame('abc', $payload);
    }

    public function testLongPictureIdRoundTrips()
    {
        $descriptor = new Vp9PayloadDescriptor(1, 1, false, 30000);

        $encoded = $descriptor->encode();
        $this->assertSame(3, strlen($encoded), 'a picture ID above 127 takes two octets');

        [$decoded, $payload] = Vp9PayloadDescriptor::decode($encoded . 'abc');

        $this->assertSame(30000, $decoded->getPictureId());
        $this->assertSame('abc', $payload);
    }

    public function testLayerIndicesRoundTrip()
    {
        $descriptor = new Vp9PayloadDescriptor(1, 1, true, 5, true, [2, 1, 3, 1], 77);

        [$decoded, $payload] = Vp9PayloadDescriptor::decode($descriptor->encode() . 'xyz');

        $this->assertSame([2, 1, 3, 1], $decoded->getLayerIndices());
        $this->assertSame(77, $decoded->getTl0picidx());
        $this->assertTrue($decoded->isNonReference());
        $this->assertSame('xyz', $payload);
    }

    /**
     * A scalability structure only ever appears on keyframes of layered streams, but it
     * must still be skipped correctly to reach the payload behind it.
     */
    public function testScalabilityStructureIsSkipped()
    {
        // I=0 P=0 L=0 F=0 B=1 E=1 V=1 Z=0, then N_S=1 (two layers), Y=1, G=0.
        $header = pack('C', 0b00001110) . pack('C', 0b00110000)
            . pack('n', 640) . pack('n', 360)
            . pack('n', 1280) . pack('n', 720);

        [$decoded, $payload] = Vp9PayloadDescriptor::decode($header . 'frame');

        $this->assertSame(1, $decoded->getStartOfFrame());
        $this->assertSame('frame', $payload);
    }

    public function testEmptyDataIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);

        Vp9PayloadDescriptor::decode('');
    }

    public function testTruncatedPictureIdIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);

        // I bit set, but the picture ID octet is missing.
        Vp9PayloadDescriptor::decode(pack('C', 0b10001100));
    }

    public function testTruncatedLongPictureIdIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);

        // I bit set and the first picture ID octet announces a second one that never arrives.
        Vp9PayloadDescriptor::decode(pack('C', 0b10001100) . pack('C', 0x80));
    }
}
