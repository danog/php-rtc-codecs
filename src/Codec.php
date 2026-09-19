<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\Codecs;

use Exception;
use Webrtc\Codecs\Audio\Opus\OpusDecoder;
use Webrtc\Codecs\Audio\Opus\OpusEncoder;
use Webrtc\Codecs\Audio\PCM\PCMaDecoder;
use Webrtc\Codecs\Audio\PCM\PCMaEncoder;
use Webrtc\Codecs\Audio\PCM\PCMuDecoder;
use Webrtc\Codecs\Audio\PCM\PCMuEncoder;
use Webrtc\Codecs\Video\Av1\Av1Encoder;
use Webrtc\Codecs\Video\Vp8\Vp8Decoder;
use Webrtc\Codecs\Video\Vp8\Vp8Encoder;
use Webrtc\Codecs\Video\Vp8\Vp8PayloadDescriptor;
use Webrtc\Codecs\Video\Vp9\Vp9Decoder;
use Webrtc\Codecs\Video\Vp9\Vp9Encoder;
use Webrtc\Codecs\Video\Vp9\Vp9PayloadDescriptor;
use Webrtc\Codecs\Video\X264\H264Decoder;
use Webrtc\Codecs\Video\X264\H264Encoder;
use Webrtc\Codecs\Video\X264\H264PayloadDescriptor;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\RTPParameter\RTCRtcpFeedback;
use Webrtc\RTPParameter\RTCRtpCapabilities;
use Webrtc\RTPParameter\RTCRtpCodecCapability;
use Webrtc\RTPParameter\RTCRtpCodecParameters;
use Webrtc\RTPParameter\RTCRtpHeaderExtensionCapability;
use Webrtc\RTPParameter\RTCRtpHeaderExtensionParameters;

/**
 * WebRTC Codec Management Class
 *
 * Provides centralized codec and RTP capabilities management for WebRTC applications.
 * Handles audio/video codec registration, capability negotiation, and encoder/decoder
 * instantiation.
 *
 * @package Webrtc\Codecs
 */
final class Codec
{
    /**
     * @var RTCRtpCodecParameters[][] Registered codecs by media type
     */
    private array $codecs;

    /**
     * @var array{audio: RTCRtpHeaderExtensionParameters[], video: RTCRtpHeaderExtensionParameters[]} Header extensions by media type
     */
    private array $headerExtensions;

    /**
     * @var int Dynamic payload type counter
     */
    private int $dynamicPt = 97;

    /**
     * Constructor - initializes default codecs and header extensions
     */
    public function __construct()
    {
        $this->initializeDefaultCodecs();
        $this->initializeHeaderExtensions();
        $this->initCodecs();
    }

    /**
     * Initializes default audio codecs
     */
    private function initializeDefaultCodecs(): void
    {
        $this->codecs = [
            'audio' => [
                new RTCRtpCodecParameters('audio/opus', 48000, 2, 96),
                new RTCRtpCodecParameters('audio/PCMU', 8000, 1, 0),
                new RTCRtpCodecParameters('audio/PCMA', 8000, 1, 8),
            ],
            'video' => [],
        ];
    }

    /**
     * Initializes default header extensions
     */
    private function initializeHeaderExtensions(): void
    {
        $this->headerExtensions = [
            'audio' => [
                new RTCRtpHeaderExtensionParameters(1, 'urn:ietf:params:rtp-hdrext:sdes:mid'),
                new RTCRtpHeaderExtensionParameters(2, 'urn:ietf:params:rtp-hdrext:ssrc-audio-level'),
            ],
            'video' => [
                new RTCRtpHeaderExtensionParameters(1, 'urn:ietf:params:rtp-hdrext:sdes:mid'),
                new RTCRtpHeaderExtensionParameters(3, 'http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time'),
            ],
        ];
    }

    /**
     * Adds video codec with RTX support
     *
     * @param string $mimeType Codec MIME type
     * @param array<string, int|string|null> $parameters Codec-specific parameters
     */
    private function addVideoCodec(string $mimeType, array $parameters = []): void
    {
        $clockRate = 90000;
        $this->codecs['video'][] = new RTCRtpCodecParameters(
            $mimeType,
            $clockRate,
            null,
            $this->dynamicPt,
            [
                new RTCRtcpFeedback('nack'),
                new RTCRtcpFeedback('nack', 'pli'),
                new RTCRtcpFeedback('goog-remb'),
            ],
            $parameters
        );

        $this->codecs['video'][] = new RTCRtpCodecParameters(
            'video/rtx',
            $clockRate,
            null,
            $this->dynamicPt + 1,
            [],
            ['apt' => $this->dynamicPt]
        );

        $this->dynamicPt += 2;
    }

    /**
     * Initializes all supported codecs
     */
    private function initCodecs(): void
    {
        // These fmtp parameters are the generic *fallback* capability set advertised before any
        // specific bitstream is known. When a pre-encoded file is transmitted, the caller should
        // derive the real parameters from the bitstream with {@see self::fmtpFromBitstream()} and
        // override these, so what is advertised matches what is actually sent.
        $this->addVideoCodec('video/VP8');
        // Profile 0 is the 8-bit 4:2:0 profile, the only one every VP9 decoder must support.
        $this->addVideoCodec('video/VP9', ['profile-id' => '0']);
        foreach (['42001f', '42e01f'] as $profileLevelId) {
            $this->addVideoCodec('video/H264', [
                'level-asymmetry-allowed' => '1',
                'packetization-mode' => '1',
                'profile-level-id' => $profileLevelId,
            ]);
        }
        // AV1 fallback: profile 0 (8-bit 4:2:0 Main), Main tier; level-idx 5 (=level 3.1) is a mid
        // default only used when the bitstream's real level is unknown.
        $this->addVideoCodec('video/AV1', ['profile' => '0', 'level-idx' => '5', 'tier' => '0']);
    }

    /**
     * Derive the SDP `a=fmtp` parameters that describe an encoded video bitstream, so an endpoint can
     * advertise what it actually transmits instead of a hardcoded guess.
     *
     * The parameters are read from the codec's configuration record (the Matroska/MP4 CodecPrivate:
     * `av1C` for AV1, `avcC` for H.264, the WebM VP9 feature metadata for VP9). VP9 in WebM usually
     * stores no configuration record — its profile lives in every frame — so a keyframe may be passed
     * as a fallback source.
     *
     * @param string      $mimeType     Codec MIME type, e.g. `video/AV1`, `video/H264`, `video/VP9` (case-insensitive).
     * @param string      $codecPrivate The track configuration record, or `''` if none was stored.
     * @param string|null $keyframe     A keyframe of the stream, used when $codecPrivate is absent/insufficient.
     * @return array<string, string> fmtp overrides to merge onto the advertised parameters; `[]` if none could be derived.
     */
    public static function fmtpFromBitstream(string $mimeType, string $codecPrivate, ?string $keyframe = null): array
    {
        return match (strtolower($mimeType)) {
            'video/av1'  => self::av1FmtpFromBitstream($codecPrivate),
            'video/h264' => self::h264FmtpFromBitstream($codecPrivate),
            'video/vp9'  => self::vp9FmtpFromBitstream($codecPrivate, $keyframe),
            default      => [],
        };
    }

    /**
     * Read `profile`/`level-idx`/`tier` from an AV1CodecConfigurationRecord (`av1C`).
     *
     * Layout (https://aomediacodec.github.io/av1-isobmff/#av1codecconfigurationrecord-syntax):
     * byte 0 = marker(1) | version(7); byte 1 = seq_profile(3) | seq_level_idx_0(5);
     * byte 2 = seq_tier_0(1) | high_bitdepth(1) | ...
     *
     * @return array<string, string>
     */
    private static function av1FmtpFromBitstream(string $av1c): array
    {
        if (\strlen($av1c) < 3 || (\ord($av1c[0]) & 0x80) === 0) {
            return [];
        }
        $b1 = \ord($av1c[1]);
        $b2 = \ord($av1c[2]);
        return [
            'profile'   => (string) (($b1 >> 5) & 0x07),
            'level-idx' => (string) ($b1 & 0x1F),
            'tier'      => (string) (($b2 >> 7) & 0x01),
        ];
    }

    /**
     * Read `profile-level-id` from an AVCDecoderConfigurationRecord (`avcC`): bytes 1-3 are
     * AVCProfileIndication, profile_compatibility and AVCLevelIndication — exactly the three bytes
     * of the SDP profile-level-id.
     *
     * @return array<string, string>
     */
    private static function h264FmtpFromBitstream(string $avcc): array
    {
        if (\strlen($avcc) < 4) {
            return [];
        }
        return [
            'level-asymmetry-allowed' => '1',
            'packetization-mode'      => '1',
            'profile-level-id'        => bin2hex($avcc[1].$avcc[2].$avcc[3]),
        ];
    }

    /**
     * Read the VP9 `profile-id`, from the WebM VP9 feature metadata (CodecPrivate) if present, else
     * from the uncompressed header of a keyframe.
     *
     * @return array<string, string>
     */
    private static function vp9FmtpFromBitstream(string $vpcc, ?string $keyframe): array
    {
        $profile = self::vp9ProfileFromMetadata($vpcc);
        if ($profile === null && $keyframe !== null) {
            $profile = self::vp9ProfileFromFrame($keyframe);
        }
        return $profile === null ? [] : ['profile-id' => (string) $profile];
    }

    /**
     * The WebM VP9 CodecPrivate is a sequence of {id byte, length byte, value} features; feature
     * id 1 is the profile.
     */
    private static function vp9ProfileFromMetadata(string $vpcc): ?int
    {
        $offset = 0;
        $length = \strlen($vpcc);
        while ($offset + 2 <= $length) {
            $id  = \ord($vpcc[$offset]);
            $len = \ord($vpcc[$offset + 1]);
            $offset += 2;
            if ($offset + $len > $length) {
                break;
            }
            if ($id === 1 && $len >= 1) {
                return \ord($vpcc[$offset]);
            }
            $offset += $len;
        }
        return null;
    }

    /**
     * The VP9 uncompressed header starts with frame_marker (2 bits = 0b10) then profile_low_bit and
     * profile_high_bit; profile = (high << 1) | low.
     */
    private static function vp9ProfileFromFrame(string $frame): ?int
    {
        if ($frame === '') {
            return null;
        }
        $b = \ord($frame[0]);
        if (($b >> 6) !== 0b10) {
            return null;
        }
        return ((($b >> 4) & 1) << 1) | (($b >> 5) & 1);
    }

    /**
     * Gets capabilities for specified media type
     *
     * @param string $kind Media type ('audio' or 'video')
     * @return RTCRtpCapabilities Codec and header extension capabilities
     * @throws InvalidArgumentException For unknown media types
     */
    public function getCapabilities(string $kind): RTCRtpCapabilities
    {
        if (!isset($this->codecs[$kind])) {
            throw new InvalidArgumentException("Cannot get capabilities for unknown media $kind");
        }

        $codecs = [];
        $headerExtensions = [];
        $rtxAdded = false;

        foreach ($this->codecs[$kind] as $codec) {
            if (CodecUtility::isRtx($codec)) {
                if (!$rtxAdded) {
                    $codecs[] = new RTCRtpCodecCapability(
                        $codec->mimeType,
                        $codec->clockRate
                    );
                    $rtxAdded = true;
                }
            } else {
                $codecs[] = new RTCRtpCodecCapability(
                    $codec->mimeType,
                    $codec->clockRate,
                    $codec->channels,
                    $codec->parameters
                );
            }
        }

foreach ($this->headerExtensions[$kind] as $extension) {
            $headerExtensions[] = new RTCRtpHeaderExtensionCapability($extension->uri);
        }

        return new RTCRtpCapabilities($codecs, $headerExtensions);
    }

    /**
     * Gets decoder instance for specified codec
     *
     * @param RTCRtpCodecParameters $codec Codec parameters
     * @return DecoderInterface Appropriate decoder instance
     * @throws InvalidArgumentException For unsupported codecs
     */
    public static function getDecoder(RTCRtpCodecParameters $codec): DecoderInterface
    {
        return match (strtolower($codec->mimeType)) {
            'audio/opus' => new OpusDecoder,
            'audio/pcma' => new PCMaDecoder,
            'audio/pcmu' => new PCMuDecoder,
            'video/h264' => new H264Decoder,
            'video/vp8'  => new Vp8Decoder,
            'video/vp9'  => new Vp9Decoder,
            default => throw new InvalidArgumentException("No decoder found for MIME type `$codec->mimeType`"),
        };
    }

    /**
     * Gets encoder instance for specified codec
     *
     * @param RTCRtpCodecParameters $codec Codec parameters
     * @return EncoderInterface Appropriate encoder instance
     * @throws InvalidArgumentException For unsupported codecs
     */
    public static function getEncoder(RTCRtpCodecParameters $codec): EncoderInterface
    {
        return match (strtolower($codec->mimeType)) {
            'audio/opus' => new OpusEncoder,
            'audio/pcma' => new PCMaEncoder,
            'audio/pcmu' => new PCMuEncoder,
            'video/h264' => new H264Encoder,
            'video/vp8'  => new Vp8Encoder,
            'video/vp9'  => new Vp9Encoder,
            'video/av1'  => new Av1Encoder,
            default => throw new InvalidArgumentException("No encoder found for MIME type `$codec->mimeType`"),
        };
    }

    /**
     * Processes RTP payload according to codec requirements
     *
     * @param RTCRtpCodecParameters $codec Codec parameters
     * @param string $payload RTP payload data
     * @return array [bool, string] Processed payload data
     * @throws Exception For processing failures
     */
    public static function depayload(RTCRtpCodecParameters $codec, string $payload): array
    {
        return match (strtolower($codec->mimeType)) {
            "video/vp8" => Vp8PayloadDescriptor::decode($payload),
            "video/vp9" => Vp9PayloadDescriptor::decode($payload),
            "video/h264" => H264PayloadDescriptor::decode($payload),
            default => [true, $payload],
        };
    }

    /**
     * Gets registered codecs
     *
     * @param string|null $kind Optional media type filter
     * @return array Codecs array
     */
    public function getCodecs(?string $kind = null): array
    {
        return $kind !== null ? $this->codecs[$kind] : $this->codecs;
    }

    /**
     * Gets registered header extensions
     *
     * @param string|null $kind Optional media type filter
     * @return RTCRtpHeaderExtensionParameters[] Header extensions array
     */
    public function getHeaderExtensions(?string $kind = null): array
    {
        if ($kind !== null) {
            return $this->headerExtensions[$kind];
        }

        $headerExtensions = [];
        foreach ($this->headerExtensions as $extensions) {
            foreach ($extensions as $extension) {
                $headerExtensions[] = $extension;
            }
        }
        return $headerExtensions;
    }
}