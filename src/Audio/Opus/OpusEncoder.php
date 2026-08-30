<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\Codecs\Audio\Opus;

use Webrtc\AVCodec\Audio\AudioResampler;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\AVFilter;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\AudioContext;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\AVCodec\Exception\AvCodecException;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\FrameInterface;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Codecs\Encoder as BEncoder;
use Webrtc\Codecs\EncoderInterface;
use Webrtc\Exception\RuntimeException;

/**
 * Opus Audio Encoder Class
 *
 * Provides encoding of audio frames to Opus format, backed by FFmpeg's libopus
 * encoder (via danog/php-rtc-av). Input frames are resampled to Opus-compatible
 * parameters (48 kHz, stereo, 960 samples per frame) before encoding.
 *
 * @package Webrtc\Codecs\Audio\Opus
 */
final class OpusEncoder extends BEncoder implements EncoderInterface
{    /**
     * @var int SAMPLE_RATE Opus required sample rate (48kHz)
     */
    private const SAMPLE_RATE = 48000;

    /**
     * @var int SAMPLES_PER_FRAME Opus frame size (960 samples)
     */
    private const SAMPLES_PER_FRAME = 960;

    /**
     * @var TransCoder|null $transcoder Opus encoder transcoder instance
     */
    private ?TransCoder $transcoder = null;

    /**
     * @var AudioResampler|null $resampler Audio resampler instance
     */
    private ?AudioResampler $resampler = null;

    /**
     * Constructor
     *
     * The native libraries are only loaded on the first actual encode: packing an
     * already-encoded OPUS frame for RTP needs no codec at all, so FFI stays optional.
     */
    public function __construct()
    {
    }

    /**
     * Load libav/libopus on demand.
     *
     * @throws AvCodecException
     */
    private function ensureEncoder(): void
    {
        if (isset($this->transcoder)) {
            return;
        }
        AVCodec::init();
        AVFilter::init();

        $context = AudioContext::create(new Codec("libopus", "w"));
        \assert($context instanceof AudioContext);
        $context->setFormat("s16");
        $context->setLayout("stereo");
        $context->setSampleRate(self::SAMPLE_RATE);

        $this->transcoder = new TransCoder($context);
        $this->resampler = new AudioResampler("s16", "stereo", self::SAMPLE_RATE, self::SAMPLES_PER_FRAME);
    }

    /**
     * Encode an audio frame to Opus format
     *
     * @param FrameInterface|AudioFrame $frame Audio frame to encode
     * @param bool $useKeyframe Ignored for audio (kept for interface compatibility)
     * @return array|string [packets, pts] Array of encoded packets and presentation timestamp
     * @throws RuntimeException If frame validation fails
     * @throws AvCodecException
     */
    #[\Override]
    public function encode(FrameInterface|AudioFrame $frame, bool $useKeyframe = false): string|array
    {
        $this->ensureEncoder();
        $this->validateFrame($frame);
        \assert($frame instanceof AudioFrame);
        \assert($this->resampler instanceof AudioResampler);
        $frames = $this->resampler->resample($frame);

        $packets = [];
        foreach ($frames as $frame) {
            \assert($this->transcoder instanceof TransCoder);
            /** @var Packet[] $encoded */
            $encoded = $this->transcoder->encode($frame);
            foreach ($encoded as $packet) {
                $packets[] = $packet->getData();
            }
        }

        return [$packets, $frame->getPts()];
    }

    /**
     * Package encoded data into transport format
     *
     * @param Packet|EncodedPacket $packet Encoded audio packet
     * @return array|string [packets, pts] Array containing packet data and converted timestamp
     */
    #[\Override]
    public function pack(Packet|EncodedPacket $packet): string|array
    {
        if ($packet instanceof EncodedPacket) {
            // Already timed in the 48kHz OPUS clock: pass it straight through, no codec needed.
            return [[$packet->getData()], $packet->getTimestamp()];
        }
        return [[$packet->getData()], $this->convertTimebase($packet->getPts() ?? 0, $this->getTimebaseArray($packet->getTimeBase()), [1, 48000])];
    }

    /**
     * Validate audio frame meets Opus requirements
     *
     * @param FrameInterface $frame Frame to validate
     * @throws RuntimeException If the frame is invalid
     */
    private function validateFrame(FrameInterface $frame): void
    {
        if (!$frame instanceof AudioFrame) {
            throw new RuntimeException("frame is not audio");
        }
        if ($frame->getFormat()->getName() != "s16") {
            throw new RuntimeException("format is not valid");
        }
        if (!in_array($frame->getLayout()->getName(), ["mono", "stereo"])) {
            throw new RuntimeException("Audio frame should be either mono or stereo");
        }
    }
}
