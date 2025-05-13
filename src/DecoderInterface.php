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

use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\FrameInterface;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\RTP\Jitter\JitterFrame;

interface DecoderInterface
{
    /**
     * @param JitterFrame $frame
     * @return FrameInterface[]|VideoFrame[]|AudioFrame[]
     */
    public function decode(JitterFrame $frame): array;
}