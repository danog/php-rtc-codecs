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

/**
 * Abstract Encoder Base Class
 *
 * Provides common functionality and base implementation for all encoder classes.
 * Handles bitrate management and timestamp conversion operations shared across
 * different codec implementations.
 *
 * @package Webrtc\Codecs
 */
abstract class Encoder implements EncoderInterface
{
    /**
     * @var int $bitrate Current target bitrate in bits per second (default: 1 Mbps)
     */
    protected int $bitrate = 1000000;

    /**
     * Gets the current encoder bitrate
     *
     * @return int Current bitrate in bits per second
     */
    public function getBitrate(): int
    {
        return $this->bitrate;
    }

    /**
     * Sets the target encoder bitrate
     *
     * @param int $bitrate New bitrate in bits per second
     */
    #[\Override]
    public function setBitrate(int $bitrate): void
    {
        $this->bitrate = $bitrate;
    }

    /**
     * Converts timestamps between different timebases
     *
     * @param int $pts Presentation timestamp to convert
     * @param array{num: int, den: int} $fromBase Source timebase as ['num' => numerator, 'den' => denominator]
     * @param array{0: int, 1: int} $toBase Target timebase as [numerator, denominator]
     * @return int Converted timestamp
     */
    protected function convertTimebase(int $pts, array $fromBase, array $toBase): int
    {
        if ($fromBase['num'] != $toBase[0] || $fromBase['den'] != $toBase[1]) {
            $pts = (int)((float)$pts * ((float)$fromBase['num'] / (float)$fromBase['den']) / ((float)$toBase[0] / (float)$toBase[1]));
        }
        return $pts;
    }

    /**
     * Normalizes a time base object ({num, den}) into the array shape convertTimebase() expects
     *
     * @param \stdClass $timeBase Time base object as returned by frames and packets
     * @return array{num: int, den: int}
     */
    protected function getTimebaseArray(\stdClass $timeBase): array
    {
        return ['num' => (int)$timeBase->num, 'den' => (int)$timeBase->den];
    }
}