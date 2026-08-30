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

use Webrtc\RTPParameter\RTCRtpCodecCapability;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

/**
 * Codec Utility Class
 *
 * Provides helper methods and utilities for codec-related operations.
 * Contains platform-agnostic functionality needed by various codec implementations.
 *
 * @package Webrtc\Codecs
 */
final class CodecUtility
{
    /**
     * Gets the number of available CPU cores
     *
     * Cross-platform method to determine available processing cores.
     * Uses different system commands based on the operating system.
     *
     * @return int Number of CPU cores
     */
    public static function getNumberOfCPUCores(): int
    {
        $cores = PHP_OS_FAMILY === 'Windows'
            ? (string) getenv('NUMBER_OF_PROCESSORS')
            : (string) @exec('nproc');

        $cores = trim($cores);
        return $cores !== '' ? (int) $cores : 1;
    }

    /**
     * Checks if a codec is an RTX (Retransmission) codec
     *
     * @param RTCRtpCodecParameters|RTCRtpCodecCapability $codec Codec to check
     * @return bool True if the codec is RTX, false otherwise
     */
    public static function isRtx(RTCRtpCodecParameters|RTCRtpCodecCapability $codec): bool
    {
        return strtolower(explode("/", $codec->mimeType)[1]) === 'rtx';
    }

    /**
     * Gets the payload type an RTX codec retransmits, as declared by its `apt` parameter
     *
     * The value arrives as an int when the codec was built from the local capability list, and as
     * a string when it was parsed out of an `a=fmtp` line, so it has to be normalised before it can
     * be compared against a payload type. A codec with no usable `apt` yields null, which never
     * matches a real payload type.
     *
     * @param RTCRtpCodecParameters|RTCRtpCodecCapability $codec Codec to read
     * @return int|null The associated payload type, or null if the codec declares none
     */
    public static function apt(RTCRtpCodecParameters|RTCRtpCodecCapability $codec): ?int
    {
        $apt = $codec->parameters['apt'] ?? null;

        return is_int($apt) || (is_string($apt) && ctype_digit($apt)) ? (int) $apt : null;
    }
}