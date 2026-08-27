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

use Exception;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\VideoContext;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Enum\PictureType;
use Webrtc\AVCodec\Exception\AvCodecException;
use Webrtc\AVCodec\Format\VideoFormat;
use Webrtc\AVCodec\Frame\FrameInterface;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\AVCodec\SWScale;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Codecs\CodecUtility;
use Webrtc\Codecs\EncodedPacket;
use Webrtc\Codecs\Encoder;
use Webrtc\Codecs\EncoderInterface;

/**
 * VP8 Video Encoder Class
 *
 * Implements real-time VP8 video encoding optimized for WebRTC applications,
 * backed by FFmpeg's libvpx encoder (via danog/php-rtc-av). Provides adaptive
 * bitrate control and RFC 7741 payload packetization for RTP transport.
 *
 * @package Webrtc\Codecs\Video\Vp8
 */
class Vp8Encoder extends Encoder implements EncoderInterface
{
    /**
     * @var int VIDEO_CLOCK_RATE Standard video clock rate (90kHz)
     */
    private const VIDEO_CLOCK_RATE = 90000;

    /**
     * @var int MAX_FRAME_RATE Maximum supported frame rate (30fps)
     */
    private const MAX_FRAME_RATE = 30;

    /**
     * Maximum RTP payload size, chosen to stay inside a typical MTU.
     */
    private const PACKET_MAX = 1300;

    /**
     * @var int $pictureId Current picture identifier (15-bit)
     */
    private int $pictureId;

    /**
     * @var VideoContext|null $encoderContext Active encoder context
     */
    private ?VideoContext $encoderContext = null;

    /**
     * @var int $bitrate Current target bitrate (500kbps default)
     */
    protected int $bitrate = 500000;

    /**
     * Constructor
     *
     * Only the picture ID is needed to packetize already-encoded VP8 frames; libav is
     * loaded lazily, on the first real encode.
     */
    public function __construct()
    {
        $this->pictureId = rand(0, (1 << 15) - 1);
    }

    /**
     * Load libav on demand.
     *
     * @throws AvCodecException
     */
    private function ensureEncoder(): void
    {
        AVCodec::init();
        SWScale::init();
    }

    /**
     * Encodes a video frame to VP8 format
     *
     * @param FrameInterface|VideoFrame $frame Input video frame
     * @param bool $useKeyframe Force keyframe generation
     * @return array [payloads, timestamp] Encoded packets and presentation timestamp
     * @throws AvCodecException
     */
    public function encode(FrameInterface|VideoFrame $frame, bool $useKeyframe = false): array
    {
        $this->ensureEncoder();

        if ($frame->getVideoFormat()->getName() !== "yuv420p") {
            $frame = $frame->reformat(format: "yuv420p");
        }

        if ($this->encoderContext && (
                $frame->getVideoFormat()->getWidth() !== $this->encoderContext->getWidth() ||
                $frame->getVideoFormat()->getHeight() !== $this->encoderContext->getHeight() ||
                $this->bitrate !== $this->encoderContext->getBitrate()
            )) {
            $this->encoderContext = null;
        }

        $frame->setPictureType($useKeyframe ? PictureType::I : PictureType::NONE);

        if ($this->encoderContext === null) {
            $this->encoderContext = $this->createContext($frame->getVideoFormat());
        }

        $transCoder = new TransCoder($this->encoderContext);

        // Capture the timestamp before encoding: the transcoder rebases the frame's
        // time base to the context's during encode, which would corrupt the conversion.
        $timestamp = $this->getTimeBase($frame);

        $buffer = "";
        foreach ($transCoder->encode($frame) as $packet) {
            $buffer .= $packet->getData();
        }

        $payloads = $this->packetize($buffer, $this->pictureId);
        $this->pictureId = ($this->pictureId + 1) % (1 << 15);

        return [$payloads, $timestamp];
    }

    /**
     * Creates a configured VP8 encoder context
     *
     * @param VideoFormat $format Video format
     * @return VideoContext Encoder context
     * @throws AvCodecException
     */
    private function createContext(VideoFormat $format): VideoContext
    {
        $context = VideoContext::create(new Codec("libvpx", "w"));
        $context->setFormat($format);
        $context->setBitRate($this->bitrate);
        $context->setFramerate(self::MAX_FRAME_RATE, 1);
        $context->setTimeBase(1, self::VIDEO_CLOCK_RATE);
        $context->setOptions([
            "deadline" => "realtime",
            "cpu-used" => "5",
            "threads" => (string)$this->calcCpuCore($format),
        ]);
        $context->open();

        return $context;
    }

    /**
     * Packages encoded data for RTP transport
     *
     * @param Packet|EncodedPacket $packet Encoded video packet
     * @return array [payloads, timestamp] Packets and converted timestamp
     */
    public function pack(Packet|EncodedPacket $packet): array
    {
        $payloads = $this->packetize($packet->getData(), $this->pictureId);
        $timestamp = $packet instanceof EncodedPacket
            ? $packet->getTimestamp()
            : $this->convertTimebase($packet->getPts(), (array)$packet->getTimeBase(), [1, self::VIDEO_CLOCK_RATE]);
        $this->pictureId = ($this->pictureId + 1) % (1 << 15);
        return [$payloads, $timestamp];
    }

    /**
     * Packetizes encoded data with VP8 payload descriptors
     *
     * @param string $buffer Encoded frame data
     * @param int $pictureId Picture identifier
     * @return array Array of RTP payload packets
     */
    public function packetize(string $buffer, int $pictureId): array
    {
        $payloads = [];
        $descr = new Vp8PayloadDescriptor(1, 0);
        $descr->setPictureId($pictureId);

        $length = strlen($buffer);
        $pos = 0;

        while ($pos < $length) {
            $descrBytes = $descr->encode();
            $size = min($length - $pos, self::PACKET_MAX - strlen($descrBytes));
            $payloads[] = $descrBytes . substr($buffer, $pos, $size);
            $descr->setPartitionStart(0); // Update the descriptor's partition start flag
            $pos += $size;
        }

        return $payloads;
    }

    /**
     * Converts frame timestamp to 90kHz clock base
     *
     * @param VideoFrame $frame Input video frame
     * @return int Timestamp in 90kHz units
     */
    public function getTimeBase(VideoFrame $frame): int
    {
        return $this->convertTimebase($frame->getPts(), (array)$frame->getTimeBase(), [1, self::VIDEO_CLOCK_RATE]);
    }

    /**
     * Calculates optimal thread count for encoding
     *
     * @param VideoFormat $format Input video format
     * @return int Number of threads to use
     */
    private function calcCpuCore(VideoFormat $format): int
    {
        $totalPixel = $format->getWidth() * $format->getHeight();
        $cpuCores = CodecUtility::getNumberOfCPUCores();

        return self::numberOfThreads($totalPixel, $cpuCores);
    }

    /**
     * Determines thread count based on resolution and CPU cores
     *
     * @param int $pixels Frame resolution (width × height)
     * @param int $cpus Available CPU cores
     * @return int Recommended thread count
     */
    public static function numberOfThreads(int $pixels, int $cpus): int
    {
        if ($pixels >= 1920 * 1080 && $cpus > 8) {
            return 8;
        } elseif ($pixels > 1280 * 960 && $cpus >= 6) {
            return 3;
        } elseif ($pixels > 640 * 480 && $cpus >= 3) {
            return 2;
        } else {
            return 1;
        }
    }

    /**
     * Sets current picture identifier
     *
     * @param int $pictureId New picture ID (15-bit)
     */
    public function setPictureId(int $pictureId): void
    {
        $this->pictureId = $pictureId;
    }

    /**
     * Updates target bitrate
     *
     * @param int $bitrate New bitrate in bits per second
     */
    public function setBitrate(int $bitrate): void
    {
        parent::setBitrate($bitrate);
    }
}
