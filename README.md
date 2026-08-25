# WebRTC Codec Package

![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)
![License](https://img.shields.io/badge/License-BSD-blue)

A comprehensive codec implementation package for WebRTC applications, providing audio/video encoding and decoding capabilities.

## About this fork

This is the `danog/php-rtc-codecs` fork used by MadelineProto. It targets PHP 8.2+, can packetize already-encoded media without FFI, and adds VP9 RTP payload parsing and packetization alongside the existing Opus, VP8, and H.264 support.

All internal Composer dependencies use their `danog/php-rtc-*` package names directly, so installing a component selects the maintained danog packages throughout the dependency graph.

## Features

### 🎧 Audio Codecs
- **Opus** (encode/decode)
- **PCM A-law** (encode/decode)
- **PCM μ-law** (encode/decode)

### 📹 Video Codecs
- **VP8** (encode/decode)
- **H.264** (encode/decode)

### 🛠 Core Functionality
- RTP payload packetization/depacketization(H.264 and VP8)
- Dynamic bitrate control
- Frame timestamp management
- Hardware acceleration support
- Thread-optimized encoding/decoding

## Requirements

## Requirements

- **PHP ≥ 8.2**
- FFI and matching native codec libraries only when encoding, decoding, or transcoding media
- Linux (Windows and macOS support planned for future releases)
- FFmpeg/libav shared libraries (libavcodec, libavfilter, etc.)
    - Compatible with FFmpeg **version 7.1.1**
- libopus development libraries
- libvpx development libraries 
  - Compatible with libvpx **version 1.15.0** 

## Documentation

This package is part of the PHP WebRTC library. For complete documentation, examples, and API reference, visit:

[PHP WebRTC Documentation](https://github.com/danog/php-rtc-codecs)

## Credits

### Authors

- **Amin Yazdanpanah**  
  - Website: [aminyazdanpanah.com](https://www.aminyazdanpanah.com)
  - Email: [github@aminyazdanpanah.com](mailto:github@aminyazdanpanah.com)

- **Sana Moniri**  
  - GtiHub: [sanamoniri](https://github.com/sanamoniri)

## Reporting Issues

Found a bug? Please report it on our [issues](https://github.com/php-webrtc/Codecs/issues).

## License

BSD 3-Clause License. See [LICENSE](LICENSE) for details.
