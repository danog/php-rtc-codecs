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

use Webrtc\Codecs\PayloadDescriptorInterface;
use Webrtc\Exception\InvalidArgumentException;

/**
 * VP9 Payload Descriptor Class
 *
 * Handles the generation and parsing of VP9 payload descriptors for RTP packets.
 * Implements RFC 9628 (formerly draft-ietf-payload-vp9) for the VP9 video payload
 * header in RTP streams.
 *
 * The first octet is always present:
 *
 * ```
 *          0 1 2 3 4 5 6 7
 *         +-+-+-+-+-+-+-+-+
 *         |I|P|L|F|B|E|V|Z|
 *         +-+-+-+-+-+-+-+-+
 * ```
 *
 * I: PictureID present, P: inter-picture predicted, L: layer indices present,
 * F: flexible mode, B: start of frame, E: end of frame, V: scalability structure
 * present, Z: not a reference frame for upper spatial layers.
 *
 * Only the fields a single-layer stream needs are modelled: the scalability structure
 * (V) is parsed far enough to be skipped, since it only ever appears on keyframes of
 * streams that use spatial layers.
 *
 * @package Webrtc\Codecs\Video\Vp9
 */
class Vp9PayloadDescriptor implements PayloadDescriptorInterface
{
    /**
     * @var int $startOfFrame Start of a frame (B bit)
     */
    private int $startOfFrame;

    /**
     * @var int $endOfFrame End of a frame (E bit)
     */
    private int $endOfFrame;

    /**
     * @var bool $interPicturePredicted Inter-picture predicted frame (P bit)
     */
    private bool $interPicturePredicted;

    /**
     * @var int|null $pictureId Picture identifier (7 or 15-bit)
     */
    private ?int $pictureId;

    /**
     * @var bool $nonReference Not a reference frame for upper spatial layers (Z bit)
     */
    private bool $nonReference;

    /**
     * @var array|null $layerIndices [temporalId, switchingUp, spatialId, interLayer] when present
     */
    private ?array $layerIndices;

    /**
     * @var int|null $tl0picidx Temporal layer zero index, non-flexible mode only
     */
    private ?int $tl0picidx;

    /**
     * Constructor
     *
     * @param int $startOfFrame Whether this payload starts a frame
     * @param int $endOfFrame Whether this payload ends a frame
     * @param bool $interPicturePredicted Whether the frame is inter-picture predicted
     * @param int|null $pictureId Optional picture ID
     * @param bool $nonReference Whether upper spatial layers do not reference this frame
     * @param array|null $layerIndices Optional layer indices
     * @param int|null $tl0picidx Optional TL0PICIDX, non-flexible mode only
     */
    public function __construct(
        int    $startOfFrame,
        int    $endOfFrame,
        bool   $interPicturePredicted = false,
        ?int   $pictureId = null,
        bool   $nonReference = false,
        ?array $layerIndices = null,
        ?int   $tl0picidx = null
    )
    {
        $this->startOfFrame = $startOfFrame;
        $this->endOfFrame = $endOfFrame;
        $this->interPicturePredicted = $interPicturePredicted;
        $this->pictureId = $pictureId;
        $this->nonReference = $nonReference;
        $this->layerIndices = $layerIndices;
        $this->tl0picidx = $tl0picidx;
    }

    /**
     * Whether this payload starts a frame
     *
     * @return int Start of frame flag (0 or 1)
     */
    public function getStartOfFrame(): int
    {
        return $this->startOfFrame;
    }

    /**
     * Whether this payload ends a frame
     *
     * @return int End of frame flag (0 or 1)
     */
    public function getEndOfFrame(): int
    {
        return $this->endOfFrame;
    }

    /**
     * Whether the frame is inter-picture predicted
     *
     * A keyframe is exactly a frame with this flag cleared, which is how a receiver
     * detects one without parsing the bitstream.
     *
     * @return bool True when the frame depends on a previous one
     */
    public function isInterPicturePredicted(): bool
    {
        return $this->interPicturePredicted;
    }

    /**
     * Gets picture ID
     *
     * @return int|null Picture identifier or null
     */
    public function getPictureId(): ?int
    {
        return $this->pictureId;
    }

    /**
     * Whether upper spatial layers do not reference this frame
     *
     * @return bool True when the frame may be discarded by upper layers
     */
    public function isNonReference(): bool
    {
        return $this->nonReference;
    }

    /**
     * Gets layer indices
     *
     * @return array|null [temporalId, switchingUp, spatialId, interLayer] or null
     */
    public function getLayerIndices(): ?array
    {
        return $this->layerIndices;
    }

    /**
     * Gets TL0PICIDX
     *
     * @return int|null Temporal layer zero index or null
     */
    public function getTl0picidx(): ?int
    {
        return $this->tl0picidx;
    }

    /**
     * Sets the start of frame flag
     *
     * @param int $startOfFrame New start of frame value
     */
    public function setStartOfFrame(int $startOfFrame): void
    {
        $this->startOfFrame = $startOfFrame;
    }

    /**
     * Sets the end of frame flag
     *
     * @param int $endOfFrame New end of frame value
     */
    public function setEndOfFrame(int $endOfFrame): void
    {
        $this->endOfFrame = $endOfFrame;
    }

    /**
     * Sets whether the frame is inter-picture predicted
     *
     * @param bool $interPicturePredicted New inter-picture predicted value
     */
    public function setInterPicturePredicted(bool $interPicturePredicted): void
    {
        $this->interPicturePredicted = $interPicturePredicted;
    }

    /**
     * Sets picture ID
     *
     * @param int|null $pictureId New picture ID
     */
    public function setPictureId(?int $pictureId): void
    {
        $this->pictureId = $pictureId;
    }

    /**
     * Encodes descriptor to binary string
     *
     * @return string Binary payload descriptor
     */
    public function encode(): string
    {
        $octet = 0;
        if ($this->pictureId !== null) {
            $octet |= 1 << 7;
        }
        if ($this->interPicturePredicted) {
            $octet |= 1 << 6;
        }
        if ($this->layerIndices !== null) {
            $octet |= 1 << 5;
        }
        // The F bit stays clear: we never signal flexible mode, so a reference index
        // list is never appended and TL0PICIDX describes the temporal structure.
        if ($this->startOfFrame) {
            $octet |= 1 << 3;
        }
        if ($this->endOfFrame) {
            $octet |= 1 << 2;
        }
        if ($this->nonReference) {
            $octet |= 1;
        }

        $data = pack('C', $octet);
        if ($this->pictureId !== null) {
            $data .= $this->encodePictureId();
        }
        if ($this->layerIndices !== null) {
            $data .= $this->encodeLayerIndices();
        }

        return $data;
    }

    /**
     * Encodes the picture ID, using the long form above 127
     *
     * @return string Binary picture ID
     */
    private function encodePictureId(): string
    {
        return $this->pictureId < 128
            ? pack('C', $this->pictureId)
            : pack('n', (1 << 15) | $this->pictureId);
    }

    /**
     * Encodes the layer indices, plus TL0PICIDX in non-flexible mode
     *
     * @return string Binary layer indices
     */
    private function encodeLayerIndices(): string
    {
        [$temporalId, $switchingUp, $spatialId, $interLayer] = $this->layerIndices;
        $data = pack(
            'C',
            (($temporalId & 7) << 5) | (($switchingUp & 1) << 4) | (($spatialId & 7) << 1) | ($interLayer & 1)
        );

        // The F bit is always clear, so TL0PICIDX is mandatory whenever L is set.
        return $data . pack('C', $this->tl0picidx ?? 0);
    }

    /**
     * Decodes descriptor from binary data
     *
     * @param string $data Binary input data
     * @return array [Vp9PayloadDescriptor, remaining_data]
     * @throws InvalidArgumentException On malformed input
     */
    public static function decode(string $data): array
    {
        if (strlen($data) < 1) {
            throw new InvalidArgumentException("VP9 descriptor is too short");
        }

        $pos = 0;
        $octet = ord($data[$pos++]);
        $hasPictureId = (bool)($octet >> 7 & 1);
        $interPicturePredicted = (bool)($octet >> 6 & 1);
        $hasLayerIndices = (bool)($octet >> 5 & 1);
        $flexibleMode = (bool)($octet >> 4 & 1);
        $startOfFrame = $octet >> 3 & 1;
        $endOfFrame = $octet >> 2 & 1;
        $hasScalabilityStructure = (bool)($octet >> 1 & 1);
        $nonReference = (bool)($octet & 1);

        $pictureId = null;
        if ($hasPictureId) {
            [$pictureId, $pos] = self::decodePictureId($data, $pos);
        }

        $layerIndices = null;
        $tl0picidx = null;
        if ($hasLayerIndices) {
            [$layerIndices, $tl0picidx, $pos] = self::decodeLayerIndices($data, $pos, $flexibleMode);
        }

        if ($flexibleMode && $interPicturePredicted) {
            $pos = self::skipReferenceIndices($data, $pos);
        }

        if ($hasScalabilityStructure) {
            $pos = self::skipScalabilityStructure($data, $pos);
        }

        if (strlen($data) < $pos) {
            throw new InvalidArgumentException("VP9 descriptor is truncated");
        }

        return [
            new self(
                $startOfFrame,
                $endOfFrame,
                $interPicturePredicted,
                $pictureId,
                $nonReference,
                $layerIndices,
                $tl0picidx
            ),
            substr($data, $pos),
        ];
    }

    /**
     * Decodes the 7 or 15-bit picture ID
     *
     * @param string $data Binary input data
     * @param int $pos Current offset
     * @return array [pictureId, newPos]
     * @throws InvalidArgumentException On truncated input
     */
    private static function decodePictureId(string $data, int $pos): array
    {
        if (strlen($data) < $pos + 1) {
            throw new InvalidArgumentException("VP9 descriptor has truncated PictureID");
        }
        if (ord($data[$pos]) & 0x80) {
            if (strlen($data) < $pos + 2) {
                throw new InvalidArgumentException("VP9 descriptor has truncated long PictureID");
            }
            return [unpack('n', substr($data, $pos, 2))[1] & 0x7FFF, $pos + 2];
        }

        return [ord($data[$pos]), $pos + 1];
    }

    /**
     * Decodes the layer indices octet, and TL0PICIDX in non-flexible mode
     *
     * @param string $data Binary input data
     * @param int $pos Current offset
     * @param bool $flexibleMode Whether the F bit is set
     * @return array [layerIndices, tl0picidx, newPos]
     * @throws InvalidArgumentException On truncated input
     */
    private static function decodeLayerIndices(string $data, int $pos, bool $flexibleMode): array
    {
        if (strlen($data) < $pos + 1) {
            throw new InvalidArgumentException("VP9 descriptor has truncated layer indices");
        }
        $octet = ord($data[$pos++]);
        $layerIndices = [$octet >> 5 & 7, $octet >> 4 & 1, $octet >> 1 & 7, $octet & 1];

        $tl0picidx = null;
        if (!$flexibleMode) {
            if (strlen($data) < $pos + 1) {
                throw new InvalidArgumentException("VP9 descriptor has truncated TL0PICIDX");
            }
            $tl0picidx = ord($data[$pos++]);
        }

        return [$layerIndices, $tl0picidx, $pos];
    }

    /**
     * Skips the reference index list of a flexible mode payload
     *
     * @param string $data Binary input data
     * @param int $pos Current offset
     * @return int Offset past the list
     * @throws InvalidArgumentException On truncated input
     */
    private static function skipReferenceIndices(string $data, int $pos): int
    {
        // Up to three P_DIFF fields, each chained by its own least significant "more" bit.
        for ($i = 0; $i < 3; $i++) {
            if (strlen($data) < $pos + 1) {
                throw new InvalidArgumentException("VP9 descriptor has truncated reference indices");
            }
            $octet = ord($data[$pos++]);
            if (!($octet & 1)) {
                break;
            }
        }

        return $pos;
    }

    /**
     * Skips the scalability structure of a payload that carries one
     *
     * @param string $data Binary input data
     * @param int $pos Current offset
     * @return int Offset past the structure
     * @throws InvalidArgumentException On truncated input
     */
    private static function skipScalabilityStructure(string $data, int $pos): int
    {
        if (strlen($data) < $pos + 1) {
            throw new InvalidArgumentException("VP9 descriptor has truncated scalability structure");
        }
        $octet = ord($data[$pos++]);
        $spatialLayers = ($octet >> 5 & 7) + 1;
        $hasResolutions = (bool)($octet >> 4 & 1);
        $hasPictureGroup = (bool)($octet >> 3 & 1);

        if ($hasResolutions) {
            // Two 16-bit values, width and height, per spatial layer.
            $pos += 4 * $spatialLayers;
        }

        if ($hasPictureGroup) {
            if (strlen($data) < $pos + 1) {
                throw new InvalidArgumentException("VP9 descriptor has truncated picture group");
            }
            $pictureGroupSize = ord($data[$pos++]);
            for ($i = 0; $i < $pictureGroupSize; $i++) {
                if (strlen($data) < $pos + 1) {
                    throw new InvalidArgumentException("VP9 descriptor has truncated picture group entry");
                }
                $entry = ord($data[$pos++]);
                // Each entry is followed by one octet per reference it declares.
                $pos += $entry >> 2 & 3;
            }
        }

        return $pos;
    }

    /**
     * String representation of descriptor
     *
     * @return string Human-readable descriptor info
     */
    public function __toString(): string
    {
        return sprintf(
            "Vp9PayloadDescriptor(B=%d, E=%d, P=%d, pic_id=%s)",
            $this->startOfFrame,
            $this->endOfFrame,
            $this->interPicturePredicted ? 1 : 0,
            $this->pictureId !== null ? $this->pictureId : 'null'
        );
    }
}
