<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\Codecs\Video\Vp8;

use Throwable;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\VideoContext;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Exception\AvCodecException;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Codecs\DecoderInterface;
use Webrtc\Codecs\JitterFrameInterface;

/**
 * VP8 Video Decoder Class
 *
 * Implements real-time decoding of VP8-encoded video frames using FFmpeg's VP8
 * decoder (via danog/php-rtc-av). Provides efficient frame-by-frame decoding
 * suitable for WebRTC applications.
 *
 * @package Webrtc\Codecs\Video\Vp8
 */
class Vp8Decoder implements DecoderInterface
{
    /**
     * @var TransCoder $transcoder VP8 transcoder instance
     */
    private TransCoder $transcoder;

    /**
     * Constructor
     *
     * Initializes the AVCodec library and a VP8 codec context in read mode.
     * @throws AvCodecException
     */
    public function __construct()
    {
        AVCodec::init();
        $this->transcoder = new TransCoder(VideoContext::create(new Codec("vp8")));
    }

    /**
     * Decodes a VP8-encoded video frame
     *
     * @param JitterFrameInterface $frame Input frame containing VP8 payload and timestamp
     * @return array Array of decoded VideoFrame objects, empty on decoding failure
     */
    public function decode(JitterFrameInterface $frame): array
    {
        try {
            $packet = new Packet();
            $packet->putData($frame->getData());
            $packet->setPts($frame->getTimestamp());
            $packet->setTimeBase(1, 90000);

            return $this->transcoder->decode($packet);
        } catch (Throwable) {
            return [];
        }
    }
}
