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

use Webrtc\Codecs\PayloadDescriptorInterface;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\Exception\NotImplementedException;

/**
 * AV1 payload descriptor, per the AV1 RTP payload format specification
 * (https://aomediacodec.github.io/av1-rtp-spec/).
 *
 * An RTP packet carries an aggregation header (Z|Y|W|N) followed by OBU elements, each either
 * prefixed with its leb128 size or, for the last one when W is set, running to the end of the
 * packet. An OBU may be fragmented over several packets (the Z and Y bits), so a single packet
 * cannot be turned back into whole OBUs on its own.
 *
 * {@see self::decode()} therefore emits an intermediate representation — each element as a
 * continuation flag byte, its leb128 size and its bytes — which concatenates across the packets of
 * a temporal unit, and {@see self::assemble()} turns that back into a plain temporal unit of OBUs
 * in the low-overhead format (every OBU carrying its size field), as Matroska/MP4 and decoders
 * expect.
 *
 * @package Webrtc\Codecs\Video\Av1
 */
final class Av1PayloadDescriptor implements PayloadDescriptorInterface
{
    private const OBU_TEMPORAL_DELIMITER = 2;
    private const OBU_HAS_EXTENSION = 0x04;
    private const OBU_HAS_SIZE_FIELD = 0x02;
    /** Marker of an element that starts a new OBU in the intermediate representation. */
    private const ELEMENT_START = "\x00";
    /** Marker of an element that continues the previous OBU. */
    private const ELEMENT_CONTINUATION = "\x01";

    /**
     * Parse one AV1 RTP payload into the intermediate element list.
     *
     * @param string $data RTP payload data
     * @return array{bool, string} Whether the packet starts a new OBU (Z bit clear), and the
     *                             elements in the intermediate representation.
     * @throws InvalidArgumentException For malformed packets
     */
    #[\Override]
    public static function decode(string $data): array
    {
        $length = strlen($data);
        if ($length < 1) {
            throw new InvalidArgumentException("AV1 payload is too short");
        }
        $aggregationHeader = ord($data[0]);
        $continues = (bool)($aggregationHeader & 0x80); // Z: the first element continues an OBU.
        $count = ($aggregationHeader >> 4) & 0x03; // W: the number of elements, 0 = sized.

        $output = "";
        $pos = 1;
        $index = 0;
        while ($pos < $length) {
            if ($count !== 0 && $index === $count - 1) {
                $size = $length - $pos;
            } else {
                $size = self::readLeb128($data, $pos);
            }
            if ($size < 0 || $pos + $size > $length) {
                throw new InvalidArgumentException("AV1 OBU element is truncated");
            }
            $output .= ($index === 0 && $continues ? self::ELEMENT_CONTINUATION : self::ELEMENT_START)
                . self::writeLeb128($size)
                . substr($data, $pos, $size);
            $pos += $size;
            $index++;
        }
        if ($count !== 0 && $index !== $count) {
            throw new InvalidArgumentException("AV1 payload announces $count OBU elements but carries $index");
        }
        return [!$continues, $output];
    }

    /**
     * Turn the concatenated intermediate elements of a whole temporal unit back into OBUs with
     * size fields. Fragments are merged, and temporal delimiters (which the RTP format omits and
     * container formats do not store) are dropped should a sender include one.
     *
     * @param string $elements The concatenated output of {@see self::decode()} for every packet.
     * @return string The temporal unit in the low-overhead bitstream format.
     * @throws InvalidArgumentException For a malformed element list
     */
    public static function assemble(string $elements): string
    {
        $length = strlen($elements);
        $pos = 0;
        $obus = [];
        $current = null;
        while ($pos < $length) {
            $marker = $elements[$pos++];
            if ($pos >= $length) {
                throw new InvalidArgumentException("AV1 element list is truncated");
            }
            $size = self::readLeb128($elements, $pos);
            if ($pos + $size > $length) {
                throw new InvalidArgumentException("AV1 element list is truncated");
            }
            $fragment = substr($elements, $pos, $size);
            $pos += $size;
            if ($marker === self::ELEMENT_CONTINUATION && $current !== null) {
                $current .= $fragment;
                continue;
            }
            if ($current !== null) {
                $obus[] = $current;
            }
            $current = $fragment;
        }
        if ($current !== null) {
            $obus[] = $current;
        }

        $output = "";
        foreach ($obus as $obu) {
            $withSize = self::withSizeField($obu);
            if ($withSize !== null) {
                $output .= $withSize;
            }
        }
        return $output;
    }

    /**
     * Return an OBU with its size field set (adding it if the sender stripped it), or null for a
     * temporal delimiter or an OBU too short to carry a header.
     */
    private static function withSizeField(string $obu): ?string
    {
        if ($obu === '') {
            return null;
        }
        $header = ord($obu[0]);
        $type = ($header >> 3) & 0x0F;
        if ($type === self::OBU_TEMPORAL_DELIMITER) {
            return null;
        }
        $headerLength = 1 + (($header & self::OBU_HAS_EXTENSION) ? 1 : 0);
        if (strlen($obu) < $headerLength) {
            return null;
        }
        if ($header & self::OBU_HAS_SIZE_FIELD) {
            return $obu;
        }
        return chr($header | self::OBU_HAS_SIZE_FIELD)
            . substr($obu, 1, $headerLength - 1)
            . self::writeLeb128(strlen($obu) - $headerLength)
            . substr($obu, $headerLength);
    }

    private static function readLeb128(string $data, int &$offset): int
    {
        $value = 0;
        $shift = 0;
        $length = strlen($data);
        do {
            if ($offset >= $length) {
                throw new InvalidArgumentException("AV1 leb128 size is truncated");
            }
            $byte = ord($data[$offset++]);
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while (($byte & 0x80) && $shift < 56);
        return $value;
    }

    private static function writeLeb128(int $value): string
    {
        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $out .= chr($byte);
        } while ($value !== 0);
        return $out;
    }

    /**
     * Not implemented - encoding not supported
     *
     * @throws NotImplementedException Always throws
     */
    #[\Override]
    public function encode(): string
    {
        throw new NotImplementedException("encoding not supported!");
    }
}
