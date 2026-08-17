<?php

namespace Tests\Webrtc\Codecs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Codecs\CodecUtility;
use Webrtc\RTPParameter\RTCRtpCodecCapability;
use Webrtc\RTPParameter\RTCRtpCodecParameters;

#[CoversClass(CodecUtility::class)]
class CodecUtilityTest extends TestCase
{
    private static function rtx(mixed $apt): RTCRtpCodecParameters
    {
        return new RTCRtpCodecParameters(
            mimeType: 'video/rtx',
            clockRate: 90000,
            payloadType: 101,
            parameters: $apt === null ? [] : ['apt' => $apt]
        );
    }

    public function testIsRtx()
    {
        $this->assertTrue(CodecUtility::isRtx(self::rtx(100)));
        $this->assertFalse(CodecUtility::isRtx(
            new RTCRtpCodecParameters(mimeType: 'video/VP8', clockRate: 90000, payloadType: 100)
        ));
    }

    /**
     * A codec built from the local capability list carries an int.
     */
    public function testAptFromAnIntegerParameter()
    {
        $this->assertSame(100, CodecUtility::apt(self::rtx(100)));
    }

    /**
     * A codec parsed out of an `a=fmtp` line carries the same value as a string, and the two must
     * compare equal against a payload type or retransmission is silently never enabled.
     */
    public function testAptFromAStringParameter()
    {
        $this->assertSame(100, CodecUtility::apt(self::rtx('100')));
    }

    public function testAptOfACapabilityIsRead()
    {
        $capability = new RTCRtpCodecCapability('video/rtx', 90000, null, ['apt' => '104']);

        $this->assertSame(104, CodecUtility::apt($capability));
    }

    /**
     * Null must never coincide with a real payload type, zero included.
     */
    public function testAptIsNullWhenAbsentOrUnusable()
    {
        $this->assertNull(CodecUtility::apt(self::rtx(null)));
        $this->assertNull(CodecUtility::apt(self::rtx('')));
        $this->assertNull(CodecUtility::apt(self::rtx('abc')));
        $this->assertNull(CodecUtility::apt(self::rtx('-1')));
    }

    public function testAptOfZeroIsDistinctFromAbsent()
    {
        $this->assertSame(0, CodecUtility::apt(self::rtx('0')));
        $this->assertNull(CodecUtility::apt(self::rtx(null)));
    }
}
