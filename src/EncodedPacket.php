<?php

/**
 * This file is part of the PHP WebRTC package, vendored and modified for MadelineProto.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\Codecs;

/**
 * An already-encoded media unit, carried through the stack without ever touching a codec.
 *
 * The upstream stack represents encoded media with `AVCodec\Data\Packet`, which is a thin wrapper
 * around an FFI `AVPacket` and therefore requires libavcodec. When media is read from a file that
 * already stores the exact codec and framing the peer expects (OGG OPUS, WebM VP8, MP4 H.264), no
 * transcoding is needed at all, and this pure-PHP class is used instead so that the FFI extension
 * stays entirely optional.
 */
final class EncodedPacket
{
    /**
     * @param string $data      The encoded payload (one full frame).
     * @param int    $timestamp Presentation timestamp, already expressed in the codec's RTP clock.
     * @param bool   $keyframe  Whether this frame can be decoded without any previous frame.
     * @param ?int   $audioLevel Audio level in -dBov (0..127), if known.
     */
    public function __construct(
        private readonly string $data,
        private readonly int $timestamp,
        private readonly bool $keyframe = true,
        private readonly ?int $audioLevel = null,
    ) {
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function isKeyframe(): bool
    {
        return $this->keyframe;
    }

    public function getAudioLevel(): ?int
    {
        return $this->audioLevel;
    }

    public function getSize(): int
    {
        return \strlen($this->data);
    }
}
