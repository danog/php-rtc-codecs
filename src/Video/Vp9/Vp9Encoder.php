<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\Codecs\Video\Vp9;

use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Frame\FrameInterface;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\Codecs\Encoder;
use Webrtc\Exception\RuntimeException;

/**
 * VP9 Video Encoder Class
 *
 * Packetizes VP9 frames for RTP transport, following RFC 9628.
 *
 * Unlike {@see \Webrtc\Codecs\Video\Vp8\Vp8Encoder} this class does not drive libvpx:
 * it exists so that VP9 that is *already* encoded — the frames a WebM or MKV file
 * stores — can be sent without decoding and re-encoding it, which is by far the
 * common case and needs no native library at all. {@see self::encode()} is therefore
 * the only unsupported operation.
 *
 * @package Webrtc\Codecs\Video\Vp9
 */
class Vp9Encoder extends Encoder
{
    /**
     * @var int Maximum RTP payload size, chosen to stay inside a typical MTU
     */
    private const PACKET_MAX = 1300;

    /**
     * @var int VIDEO_CLOCK_RATE Standard video clock rate (90kHz)
     */
    private const VIDEO_CLOCK_RATE = 90000;

    /**
     * @var int $pictureId Current picture identifier (15-bit)
     */
    private int $pictureId;

    /**
     * Constructor
     *
     * Picks a random initial picture ID, as recommended for a fresh RTP stream.
     */
    public function __construct()
    {
        $this->pictureId = rand(0, (1 << 15) - 1);
    }

    /**
     * Encodes a raw video frame to VP9
     *
     * Not implemented: nothing in the stack transcodes to VP9, and a half-configured
     * libvpx VP9 encoder would produce a stream that silently degrades rather than
     * failing, so this reports the limitation instead of guessing at settings.
     *
     * @param FrameInterface $frame Input video frame
     * @param bool $useKeyframe Force keyframe generation
     * @throws RuntimeException Always
     */
    public function encode(FrameInterface $frame, bool $useKeyframe = false): array
    {
        throw new RuntimeException(
            'Realtime VP9 encoding is not implemented; VP9 that is already encoded is packetized '
            . 'by pack() without any native library.'
        );
    }

    /**
     * Packages an already-encoded VP9 frame for RTP transport
     *
     * @param Packet|EncodedPacket $packet Encoded video packet
     * @return array [payloads, timestamp] Packets and converted timestamp
     */
    public function pack(Packet|EncodedPacket $packet): array
    {
        $keyframe = $packet instanceof EncodedPacket && $packet->isKeyframe();
        $payloads = $this->packetize($packet->getData(), $this->pictureId, $keyframe);
        $timestamp = $packet instanceof EncodedPacket
            ? $packet->getTimestamp()
            : $this->convertTimebase($packet->getPts(), (array)$packet->getTimeBase(), [1, self::VIDEO_CLOCK_RATE]);
        $this->pictureId = ($this->pictureId + 1) % (1 << 15);

        return [$payloads, $timestamp];
    }

    /**
     * Packetizes encoded data with VP9 payload descriptors
     *
     * A VP9 frame is an opaque byte string as far as RTP is concerned, so it is simply
     * split across packets; the B and E bits mark the first and last fragment, which is
     * what lets a receiver reassemble the frame.
     *
     * @param string $buffer Encoded frame data
     * @param int $pictureId Picture identifier
     * @param bool $keyframe Whether the frame is a keyframe
     * @return array Array of RTP payload packets
     */
    public function packetize(string $buffer, int $pictureId, bool $keyframe = false): array
    {
        $payloads = [];
        // The P bit means "inter-picture predicted", so it is precisely the inverse of
        // a keyframe: a receiver uses it to find a decodable starting point.
        $descriptor = new Vp9PayloadDescriptor(1, 0, !$keyframe, $pictureId);

        $length = strlen($buffer);
        $pos = 0;

        // An empty frame still deserves one packet, so that B and E are both signalled.
        do {
            $descriptorBytes = $descriptor->encode();
            $size = min($length - $pos, self::PACKET_MAX - strlen($descriptorBytes));
            $fragment = substr($buffer, $pos, $size);
            $pos += $size;

            $descriptor->setEndOfFrame($pos >= $length ? 1 : 0);
            // Re-encode once the end bit is known: the descriptor length never changes,
            // since the fields it carries are fixed for the whole frame.
            $payloads[] = $descriptor->encode() . $fragment;
            $descriptor->setStartOfFrame(0);
        } while ($pos < $length);

        return $payloads;
    }
}
