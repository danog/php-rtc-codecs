<?php

namespace Tests\Webrtc\Codecs\Video;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\Fraction;
use Tests\Webrtc\Codecs\JitterFrame;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Exception\AvCodecException;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\DecoderInterface;
use Webrtc\Codecs\Encoder;
use Webrtc\Codecs\EncoderInterface;

#[CoversClass(Encoder::class)]
class VideoEncoderTest extends TestCase
{
    protected function createPacket(string $payload, int $pts): Packet
    {
        $packet = new Packet();
        $packet->putData($payload);
        $packet->setPts($pts);
        $packet->setTimeBase(1, 1000);

        return $packet;
    }

    /**
     * @throws AvCodecException
     * @throws \Exception
     */
    protected function createVideoFrame(int $width, int $height, int $pts, string $format = "yuv420p", ?array $timeBase = null): VideoFrame
    {
        $frame = new VideoFrame(width: $width, height: $height, format: $format);
        $frame->setPts($pts);
        $frame->setTimeBase($timeBase[0] ?? 1, $timeBase[1] ?? 90000);
        foreach ($frame->planes() as $plane) {
            $plane->putData(str_repeat("\0", $plane->getSize()));
        }

        return $frame;
    }

    /**
     * @param int $width
     * @param int $height
     * @param int $count
     * @param array|null $timeBase
     * @return array<VideoFrame>
     * @throws AvCodecException
     */
    protected function createVideoFrames(int $width, int $height, int $count, ?array $timeBase = null): array
    {
        $frames = [];

        for ($i = 0; $i < $count; $i++) {
            $pts = intval($i / ($timeBase ? $timeBase[0] / $timeBase[1] : 1 / 90000) / 30);
            $frames[] = $this->createVideoFrame($width, $height, $pts, "yuv420p", $timeBase);
        }

        return $frames;
    }

    protected function roundTripVideo(int $width, int $height, ?array $timeBase = null): void
    {
        $encoder = $this->getEncoder();
        $decoder = $this->getDecoder();

        $inputFrames = $this->createVideoFrames(width: $width, height: $height, count: 30, timeBase: $timeBase);

        foreach ($inputFrames as $i => $frame) {
            // encode
            list($packets, $timestamp) = $encoder->encode($frame);

            // depacketize
            $data = "";
            foreach ($packets as $packet) {
                $data .= Codec::depayload($this->codec, $packet)[1];
            }

            // decode
            $frames = $decoder->decode(new JitterFrame(data: $data, timestamp: $timestamp));
            $this->assertCount(1, $frames);
            $this->assertEquals($frame->getVideoFormat()->getWidth(), $frames[0]->getVideoFormat()->getWidth());
            $this->assertEquals($frame->getVideoFormat()->getHeight(), $frames[0]->getVideoFormat()->getHeight());
            // there is a bug in FFMpeg library (Rounding and Time Base Issues) - (in some version of ffmpeg it fixed)
            $this->assertEquals($i * 3000, $frames[0]->getPts() % 3000 === 0 ? $frames[0]->getPts() : $frames[0]->getPts() + 1);
            $this->assertEquals((new Fraction(1, 90000))(), $frames[0]->getTimeBase());
        }
    }

    protected function getEncoder(): EncoderInterface
    {
        return Codec::getEncoder($this->codec);
    }

    private function getDecoder(): DecoderInterface
    {
        return Codec::getDecoder($this->codec);
    }

    public function testEncode()
    {
        $this->assertTrue(true);
        // TODO: Write other common Encoder tests here
    }
}