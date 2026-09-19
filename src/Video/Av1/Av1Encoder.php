<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\Codecs\Video\Av1;

use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Frame\FrameInterface;
use Webrtc\Codecs\Encoder;
use Webrtc\Codecs\EncoderInterface;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\Exception\RuntimeException;

/**
 * AV1 RTP payloader.
 *
 * Packetizes an already-encoded AV1 temporal unit into RTP payloads following the AV1 RTP
 * specification (https://aomediacodec.github.io/av1-rtp-spec/). Nothing is decoded or re-encoded:
 * the OBUs a Matroska/WebM file stores are re-framed for RTP and put on the wire as-is, so AV1
 * playback needs no codec library (and therefore no FFI), exactly like VP8/VP9/H.264.
 *
 * Only packing (sending pre-encoded frames) is implemented; realtime AV1 encoding from raw frames
 * is not, because MadelineProto only ever transmits pre-encoded media.
 *
 * @package Webrtc\Codecs\Video\Av1
 */
final class Av1Encoder extends Encoder implements EncoderInterface
{
    /** Every video codec on the wire uses a 90kHz RTP clock. */
    private const VIDEO_CLOCK_RATE = 90000;

    /** Maximum RTP payload size, leaving room for the RTP header within a typical MTU. */
    private const PACKET_MAX = 1200;

    /** OBU types we care about (see the AV1 bitstream specification, section 6.2.2). */
    private const OBU_SEQUENCE_HEADER = 1;
    private const OBU_TEMPORAL_DELIMITER = 2;

    /** Bit in the OBU header byte marking the presence of a trailing leb128 obu_size field. */
    private const OBU_HAS_SIZE_FIELD = 0x02;

    protected int $bitrate = 1000000;

    /**
     * Realtime AV1 encoding is intentionally unsupported: only pre-encoded frames are transmitted.
     */
    #[\Override]
    public function encode(FrameInterface $frame, bool $useKeyframe = false): string|array
    {
        throw new RuntimeException('Realtime AV1 encoding is not supported; play a pre-encoded AV1 WebM/MKV file instead.');
    }

    /**
     * Packetize one pre-encoded AV1 temporal unit into RTP payloads.
     *
     * @return array{0: list<string>, 1: int} [payloads, timestamp]
     */
    #[\Override]
    public function pack(Packet|EncodedPacket $packet): string|array
    {
        $timestamp = $packet instanceof EncodedPacket
            ? $packet->getTimestamp()
            : $this->convertTimebase($packet->getPts() ?? 0, $this->getTimebaseArray($packet->getTimeBase()), [1, self::VIDEO_CLOCK_RATE]);

        [$elements, $newCodedVideoSequence] = $this->toRtpObus($packet->getData());

        return [$this->packetize($elements, $newCodedVideoSequence), $timestamp];
    }

    /**
     * Split a temporal unit into RTP-ready OBU elements.
     *
     * Each element keeps its OBU header (and extension byte, if any) with the `obu_has_size_field`
     * bit cleared, followed by the OBU payload — the RTP framing carries the length instead. Temporal
     * delimiter OBUs are dropped, as the RTP timestamp already delimits temporal units.
     *
     * @return array{0: list<string>, 1: bool} [obu elements, whether a new coded video sequence starts]
     */
    private function toRtpObus(string $tu): array
    {
        $elements = [];
        $newCodedVideoSequence = false;
        $offset = 0;
        $length = \strlen($tu);

        while ($offset < $length) {
            $header = \ord($tu[$offset]);
            $type = ($header >> 3) & 0x0F;
            $hasExtension = (bool) (($header >> 2) & 1);
            $hasSize = (bool) (($header >> 1) & 1);
            $headerLength = 1 + ($hasExtension ? 1 : 0);

            $cursor = $offset + $headerLength;
            if ($hasSize) {
                $size = self::readLeb128($tu, $cursor);
            } else {
                // Without an explicit size the OBU runs to the end of the temporal unit.
                $size = $length - $cursor;
            }
            $payloadEnd = $cursor + $size;
            if ($size < 0 || $payloadEnd > $length) {
                break; // Malformed: stop rather than emit garbage.
            }

            if ($type !== self::OBU_TEMPORAL_DELIMITER) {
                if ($type === self::OBU_SEQUENCE_HEADER) {
                    $newCodedVideoSequence = true;
                }
                $rtpHeader = \chr($header & ~self::OBU_HAS_SIZE_FIELD);
                if ($hasExtension) {
                    $rtpHeader .= $tu[$offset + 1];
                }
                $elements[] = $rtpHeader . substr($tu, $cursor, $size);
            }

            $offset = $payloadEnd;
        }

        return [$elements, $newCodedVideoSequence];
    }

    /**
     * Pack OBU elements into RTP payloads, fragmenting any element too large for one packet.
     *
     * The aggregation header uses W=0, so every element is prefixed by its leb128 length; the Z and
     * Y bits flag an element that continues from the previous packet or into the next one, and the N
     * bit marks the first packet of a new coded video sequence.
     *
     * @param list<string> $elements
     * @return list<string>
     */
    private function packetize(array $elements, bool $newCodedVideoSequence): array
    {
        /** @var list<array{z: bool, y: bool, elems: list<string>}> $packets */
        $packets = [];
        $current = ['z' => false, 'y' => false, 'elems' => []];
        $currentSize = 1; // The aggregation header.

        $flush = static function () use (&$packets, &$current, &$currentSize): void {
            if ($current['elems'] !== []) {
                $packets[] = $current;
            }
            $current = ['z' => false, 'y' => false, 'elems' => []];
            $currentSize = 1;
        };

        foreach ($elements as $obu) {
            $len = \strlen($obu);
            $pos = 0;
            $isStart = true;
            while ($pos < $len) {
                // Reserve up to 2 bytes for the element's leb128 length (enough for a full MTU).
                $available = self::PACKET_MAX - $currentSize - 2;
                if ($available < 1) {
                    $flush();
                    $available = self::PACKET_MAX - $currentSize - 2;
                }
                $chunk = min($available, $len - $pos);
                $fragment = substr($obu, $pos, $chunk);
                $pos += $chunk;

                if ($current['elems'] === []) {
                    // The first element of a packet that is a continuation sets the Z bit.
                    $current['z'] = !$isStart;
                }
                $current['elems'][] = $fragment;
                $currentSize += self::leb128Length(\strlen($fragment)) + \strlen($fragment);
                $isStart = false;

                if ($pos < $len) {
                    // The OBU continues in the next packet: this packet ends with a fragment.
                    $current['y'] = true;
                    $flush();
                }
            }
        }
        $flush();

        $payloads = [];
        $first = true;
        foreach ($packets as $packet) {
            $aggregationHeader = ($packet['z'] ? 0x80 : 0)
                | ($packet['y'] ? 0x40 : 0)
                // W = 0: each element is explicitly length-prefixed below.
                | (($newCodedVideoSequence && $first) ? 0x08 : 0);
            $buffer = \chr($aggregationHeader);
            foreach ($packet['elems'] as $element) {
                $buffer .= self::writeLeb128(\strlen($element)) . $element;
            }
            $payloads[] = $buffer;
            $first = false;
        }

        return $payloads;
    }

    /**
     * Read an unsigned leb128 value, advancing the cursor past it.
     */
    private static function readLeb128(string $data, int &$offset): int
    {
        $value = 0;
        $shift = 0;
        do {
            $byte = \ord($data[$offset++]);
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while (($byte & 0x80) && $shift < 56);
        return $value;
    }

    /**
     * Encode an unsigned integer as leb128.
     */
    private static function writeLeb128(int $value): string
    {
        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $out .= \chr($byte);
        } while ($value !== 0);
        return $out;
    }

    /**
     * Number of bytes the leb128 encoding of a value occupies.
     */
    private static function leb128Length(int $value): int
    {
        $n = 1;
        while ($value >= 0x80) {
            $value >>= 7;
            $n++;
        }
        return $n;
    }
}
