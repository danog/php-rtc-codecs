<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\Codecs\Video\H265;

use Webrtc\Codecs\PayloadDescriptorInterface;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\Exception\NotImplementedException;

/**
 * H.265 (HEVC) payload descriptor.
 *
 * Parses the RTP payload formats of RFC 7798 back into Annex B NAL units: single NAL unit packets,
 * aggregation packets (AP, type 48) and fragmentation units (FU, type 49), without the optional
 * DONL/DOND fields (`sprop-max-don-diff` is 0, the only mode WebRTC uses). PACI packets (type 50)
 * are not supported.
 *
 * The output of consecutive packets of one access unit concatenates into the Annex B access unit.
 *
 * @package Webrtc\Codecs\Video\H265
 */
final class H265PayloadDescriptor implements PayloadDescriptorInterface
{
    /** Size of the HEVC NAL unit header: F(1) Type(6) LayerId(6) TID(3). */
    public const NAL_HEADER_SIZE = 2;
    /** Aggregation packet NAL unit type. */
    public const NAL_TYPE_AP = 48;
    /** Fragmentation unit NAL unit type. */
    public const NAL_TYPE_FU = 49;
    /** PACI packet NAL unit type (not supported). */
    public const NAL_TYPE_PACI = 50;
    /** Size of the NAL unit size field in an AP. */
    private const LENGTH_FIELD_SIZE = 2;
    private const START_CODE = "\x00\x00\x00\x01";

    /**
     * Parse an H.265 RTP payload into Annex B NAL unit(s).
     *
     * @param string $data RTP payload data
     * @return array{bool, string} Whether this is the start of a NAL unit (always true except for
     *                             a non-first fragment), and the reconstructed Annex B bytes.
     * @throws InvalidArgumentException For malformed packets
     */
    #[\Override]
    public static function decode(string $data): array
    {
        if (strlen($data) < self::NAL_HEADER_SIZE + 1) {
            throw new InvalidArgumentException("NAL unit is too short");
        }

        $nalType = self::nalType($data);

        return match (true) {
            $nalType < self::NAL_TYPE_AP => [true, self::START_CODE . $data],
            $nalType === self::NAL_TYPE_AP => self::handleAggregationPacket($data),
            $nalType === self::NAL_TYPE_FU => self::handleFragmentationUnit($data),
            default => throw new InvalidArgumentException("NAL unit type $nalType is not supported"),
        };
    }

    /**
     * The type of a NAL unit, from its two-byte header.
     */
    public static function nalType(string $nal): int
    {
        return (ord($nal[0]) >> 1) & 0x3F;
    }

    /**
     * @return array{bool, string}
     */
    private static function handleAggregationPacket(string $data): array
    {
        $pos = self::NAL_HEADER_SIZE;
        $length = strlen($data);
        $output = "";
        while ($pos < $length) {
            if ($length < $pos + self::LENGTH_FIELD_SIZE) {
                throw new InvalidArgumentException("AP length field is truncated");
            }
            $naluSize = unpack("n", substr($data, $pos, self::LENGTH_FIELD_SIZE));
            \assert($naluSize !== false);
            $naluSize = (int)$naluSize[1];
            $pos += self::LENGTH_FIELD_SIZE;
            if ($naluSize < self::NAL_HEADER_SIZE || $length < $pos + $naluSize) {
                throw new InvalidArgumentException("AP data is truncated");
            }
            $output .= self::START_CODE . substr($data, $pos, $naluSize);
            $pos += $naluSize;
        }
        return [true, $output];
    }

    /**
     * @return array{bool, string}
     */
    private static function handleFragmentationUnit(string $data): array
    {
        $fuHeader = ord($data[self::NAL_HEADER_SIZE]);
        $start = (bool)($fuHeader & 0x80);
        $fuType = $fuHeader & 0x3F;
        $payload = substr($data, self::NAL_HEADER_SIZE + 1);
        if (!$start) {
            return [false, $payload];
        }
        // Rebuild the original NAL unit header: keep the F bit and the LayerId/TID of the payload
        // header, replace the type.
        $header = chr((ord($data[0]) & 0x81) | ($fuType << 1)) . $data[1];
        return [true, self::START_CODE . $header . $payload];
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
