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

use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\AVFilter;
use Webrtc\AVCodec\Audio\AudioResampler;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\AudioContext;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Exception\AvCodecException;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Codecs\DecoderInterface;
use Webrtc\Codecs\JitterFrameInterface;

/**
 * Opus Audio Decoder Class
 *
 * Provides decoding of Opus audio packets to raw PCM audio frames, backed by
 * FFmpeg's native Opus decoder (via danog/php-rtc-av) rather than libopus directly.
 * The decoder emits planar float; this class resamples it to the standard Opus
 * output format (interleaved s16, 48 kHz stereo, 960 samples per frame).
 *
 * @package Webrtc\Codecs\Audio\Opus
 */
class OpusDecoder implements DecoderInterface
{
    /**
     * @var int SAMPLE_RATE Opus required sample rate (48kHz)
     */
    private const SAMPLE_RATE = 48000;

    /**
     * @var int CHANNELS Opus output channel count (stereo)
     */
    private const CHANNELS = 2;

    /**
     * @var int SAMPLE_BYTES Bytes per sample for the s16 output format
     */
    private const SAMPLE_BYTES = 2;

    /**
     * @var TransCoder $transcoder Opus transcoder instance
     */
    private TransCoder $transcoder;

    /**
     * @var AudioResampler $resampler Converts the decoder's float output to s16
     */
    private AudioResampler $resampler;

    /**
     * Constructor
     *
     * Initializes required libraries:
     * - AVCodec for the Opus decoder
     * - AVFilter for the resampler used to convert to s16
     * @throws AvCodecException
     */
    public function __construct()
    {
        AVCodec::init();
        AVFilter::init();

        $context = AudioContext::create(new Codec("opus"));
        $context->setFormat("fltp");
        $context->setLayout("stereo");
        $context->setSampleRate(self::SAMPLE_RATE);

        $this->transcoder = new TransCoder($context);
        $this->resampler = new AudioResampler("s16", "stereo", self::SAMPLE_RATE, 960);
    }

    /**
     * Decode an Opus packet to audio frame(s)
     *
     * @param JitterFrameInterface $frame Input frame containing Opus packet and timestamp
     * @return AudioFrame[] Array containing one decoded AudioFrame (Opus uses fixed frame sizes)
     * @throws AvCodecException
     */
    public function decode(JitterFrameInterface $frame): array
    {
        $packet = new Packet();
        $packet->putData($frame->getData());
        $packet->setPts($frame->getTimestamp());
        $packet->setTimeBase(1, self::SAMPLE_RATE);

        $frames = [];
        foreach ($this->transcoder->decode($packet) as $decoded) {
            foreach ($this->resampler->resample($decoded) as $resampled) {
                $frames[] = $this->buildFrame($resampled, $frame->getTimestamp());
            }
        }

        return $frames;
    }

    /**
     * Repackages a resampled frame into a tightly-allocated s16 AudioFrame.
     *
     * The resampler pads its output buffer for alignment, so its plane reports more
     * bytes than the frame logically holds. Copying the logical samples into a fresh
     * frame yields the exact 4 * samples byte layout callers expect from Opus.
     *
     * @param AudioFrame $frame Resampled s16 frame from the filter graph
     * @param int $pts Presentation timestamp to stamp on the output
     * @return AudioFrame Clean s16 stereo frame
     */
    private function buildFrame(AudioFrame $frame, int $pts): AudioFrame
    {
        $samples = $frame->getSamples();
        $length = $samples * self::CHANNELS * self::SAMPLE_BYTES;
        $data = substr($frame->getPlanes()[0]->getData(), 0, $length);

        $audioFrame = new AudioFrame("s16", "stereo", $samples);
        $audioFrame->getPlanes()[0]->putData($data);
        $audioFrame->setSampleRate(self::SAMPLE_RATE);
        $audioFrame->setPts($pts);
        $audioFrame->setTimeBase(1, self::SAMPLE_RATE);

        return $audioFrame;
    }
}
