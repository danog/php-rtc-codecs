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
 * VP9 Video Decoder Class
 *
 * Implements real-time decoding of VP9-encoded video frames using FFmpeg's VP9
 * decoder (via danog/php-rtc-av). Differs from
 * {@see \Webrtc\Codecs\Video\Vp8\Vp8Decoder} only in the codec it opens.
 *
 * @package Webrtc\Codecs\Video\Vp9
 */
final class Vp9Decoder implements DecoderInterface
{
    /**
     * @var TransCoder $transcoder VP9 transcoder instance
     */
    private TransCoder $transcoder;

    /**
     * Constructor
     *
     * Initializes the AVCodec library and a VP9 codec context in read mode.
     * @throws AvCodecException
     */
    public function __construct()
    {
        AVCodec::init();
        $context = VideoContext::create(new Codec("vp9"));
        \assert($context instanceof VideoContext);
        $this->transcoder = new TransCoder($context);
    }

    /**
     * Decodes a VP9-encoded video frame
     *
     * @param JitterFrameInterface $frame Input frame containing VP9 payload and timestamp
     * @return array Array of decoded VideoFrame objects, empty on decoding failure
     */
    #[\Override]
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
