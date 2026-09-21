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

use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Frame\FrameInterface;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\Codecs\Encoder;
use Webrtc\Codecs\EncoderInterface;
use Webrtc\Codecs\Video\X264\H264Encoder;
use Webrtc\Exception\RuntimeException;

/**
 * H.265 (HEVC) RTP packetizer, per RFC 7798.
 *
 * Only pre-encoded bitstreams are supported: {@see self::pack()} splits an Annex B access unit into
 * NAL units and emits single NAL unit packets, aggregation packets (AP) for runs of small NAL units
 * (parameter sets and the like) and fragmentation units (FU) for NAL units larger than the MTU.
 * There is no realtime HEVC encoder.
 *
 * @package Webrtc\Codecs\Video\H265
 */
final class H265Encoder extends Encoder implements EncoderInterface
{
    private const VIDEO_CLOCK_RATE = 90000;
    /** Maximum RTP payload size. */
    private const PACKET_MAX = 1200;
    /** Payload header (2) + FU header (1). */
    private const FU_HEADER_SIZE = 3;
    private const LENGTH_FIELD_SIZE = 2;
    /** RFC 7798 recommends at most this many NAL units per AP. */
    private const AP_MAX_UNITS = 9;

    protected int $bitrate = 1000000;

    #[\Override]
    public function encode(FrameInterface $frame, bool $useKeyframe = false): string|array
    {
        throw new RuntimeException('Realtime H.265 encoding is not supported; play a pre-encoded HEVC MKV file instead.');
    }

    #[\Override]
    public function pack(Packet|EncodedPacket $packet): string|array
    {
        $timestamp = $packet instanceof EncodedPacket
            ? $packet->getTimestamp()
            : $this->convertTimebase($packet->getPts() ?? 0, $this->getTimebaseArray($packet->getTimeBase()), [1, self::VIDEO_CLOCK_RATE]);
        $nalUnits = [];
        foreach (H264Encoder::splitBitstream($packet->getData()) as $nalUnit) {
            if (strlen($nalUnit) >= H265PayloadDescriptor::NAL_HEADER_SIZE) {
                $nalUnits[] = $nalUnit;
            }
        }
        return [self::packetize($nalUnits), $timestamp];
    }

    /**
     * Packetize the NAL units of one access unit.
     *
     * @param list<string> $nalUnits NAL units without start codes, each with its 2-byte header.
     * @return list<string> RTP payloads.
     */
    public static function packetize(array $nalUnits): array
    {
        $payloads = [];
        $count = count($nalUnits);
        $i = 0;
        while ($i < $count) {
            $nalUnit = $nalUnits[$i];
            if (strlen($nalUnit) > self::PACKET_MAX) {
                $payloads = array_merge($payloads, self::fragment($nalUnit));
                $i++;
                continue;
            }
            // Aggregate a run of small NAL units into one AP, as long as they fit.
            $aggregated = [];
            $size = H265PayloadDescriptor::NAL_HEADER_SIZE;
            while ($i < $count
                && count($aggregated) < self::AP_MAX_UNITS
                && $size + self::LENGTH_FIELD_SIZE + strlen($nalUnits[$i]) <= self::PACKET_MAX
            ) {
                $aggregated[] = $nalUnits[$i];
                $size += self::LENGTH_FIELD_SIZE + strlen($nalUnits[$i]);
                $i++;
            }
            if (count($aggregated) === 1) {
                $payloads[] = $aggregated[0];
                continue;
            }
            $payloads[] = self::aggregate($aggregated);
        }
        return $payloads;
    }

    /**
     * Build an aggregation packet (type 48) out of several NAL units.
     *
     * @param list<string> $nalUnits
     */
    private static function aggregate(array $nalUnits): string
    {
        $forbidden = 0;
        $layerAndTid = ord($nalUnits[0][1]);
        foreach ($nalUnits as $nalUnit) {
            $forbidden |= ord($nalUnit[0]) & 0x80;
            // The AP carries the lowest TID of its units (the LayerId is 0 in a WebRTC stream).
            $layerAndTid = min($layerAndTid, ord($nalUnit[1]));
        }
        $payload = chr($forbidden | (H265PayloadDescriptor::NAL_TYPE_AP << 1)) . chr($layerAndTid);
        foreach ($nalUnits as $nalUnit) {
            $payload .= pack("n", strlen($nalUnit)) . $nalUnit;
        }
        return $payload;
    }

    /**
     * Split one NAL unit into fragmentation units (type 49).
     *
     * @return list<string>
     */
    private static function fragment(string $nalUnit): array
    {
        $header0 = ord($nalUnit[0]);
        $type = ($header0 >> 1) & 0x3F;
        $payloadHeader = chr(($header0 & 0x81) | (H265PayloadDescriptor::NAL_TYPE_FU << 1)) . $nalUnit[1];
        $data = substr($nalUnit, H265PayloadDescriptor::NAL_HEADER_SIZE);
        $length = strlen($data);
        $chunk = self::PACKET_MAX - self::FU_HEADER_SIZE;
        $packets = [];
        for ($offset = 0; $offset < $length; $offset += $chunk) {
            $fuHeader = $type;
            if ($offset === 0) {
                $fuHeader |= 0x80;
            }
            if ($offset + $chunk >= $length) {
                $fuHeader |= 0x40;
            }
            $packets[] = $payloadHeader . chr($fuHeader) . substr($data, $offset, $chunk);
        }
        return $packets;
    }
}
