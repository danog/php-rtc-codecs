<?php

namespace Tests\Webrtc\Codecs\Audio;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\Codecs\Fraction;
use Tests\Webrtc\Codecs\JitterFrame;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\Codecs\Codec;
use Webrtc\Codecs\DecoderInterface;
use Webrtc\Codecs\Encoder;
use Webrtc\Codecs\EncoderInterface;

#[CoversClass(Encoder::class)]
class AudioEncoderTest extends TestCase
{
    const AUDIO_PTIME = 0.02; // 20ms

    protected function createPacket(string $payload, int $pts): Packet
    {
        $packet = new Packet();
        $packet->putData($payload);
        $packet->setPts($pts);
        $packet->setTimeBase(1, 1000);

        return $packet;
    }

    protected function createAudioFrame(int $samples, int $pts, string $layout = "mono", int $sampleRate = 48000): AudioFrame
    {
        $frame = new AudioFrame(format: "s16", layout: $layout, samples: $samples);
        foreach ($frame->getPlanes() as $plane) {
            $plane->putData(str_repeat("\0", $plane->getSize()));
        }
        $frame->setPts($pts);
        $frame->setSampleRate($sampleRate);
        $frame->setTimeBase(1, $sampleRate);

        return $frame;
    }

    protected function createAudioFrames(string $layout, int $sampleRate, int $count): array
    {
        $frames = [];
        $timestamp = 0;
        $samplesPerFrame = (int)(self::AUDIO_PTIME * $sampleRate);

        for ($i = 0; $i < $count; $i++) {
            $frames[] = $this->createAudioFrame(samples: $samplesPerFrame, pts: $timestamp, layout: $layout, sampleRate: $sampleRate);
            $timestamp += $samplesPerFrame;
        }

        return $frames;
    }

    protected function roundTripAudio(string $outputLayout, int $outputSampleRate, string $inputLayout = "mono", int $inputSampleRate = 8000, array $drop = []): void
    {
        $encoder = $this->getEncoder();
        $decoder = $this->getDecoder();

        $inputFrames = $this->createAudioFrames(
            layout: $inputLayout,
            sampleRate: $inputSampleRate,
            count: 10
        );

        $outputSampleCount = (int)($outputSampleRate * self::AUDIO_PTIME);

        foreach ($inputFrames as $i => $frame) {
            // encode
            list($packages, $timestamp) = $encoder->encode($frame);

            if (!in_array($i, $drop)) {
                // depacketize
                $data = "";
                foreach ($packages as $package) {
                    $data .= Codec::depayload($this->codec, $package)[1];
                }

                // decode
                $frames = $decoder->decode(new JitterFrame(data: $data, timestamp: $timestamp));
                $this->assertCount(1, $frames);
                $this->assertEquals("s16", $frames[0]->getFormat()->getName());
                $this->assertEquals($outputLayout, $frames[0]->getLayout()->getName());
                $this->assertEquals($outputSampleRate * self::AUDIO_PTIME, $frames[0]->getSamples());
                $this->assertEquals($outputSampleRate, $frames[0]->getSampleRate());
                $this->assertEquals($i * $outputSampleCount, $frames[0]->getPts());
                $this->assertEquals(
                    new Fraction(1, $outputSampleRate)(),
                    $frames[0]->getTimeBase()
                );
            }
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