# WebRTC Codec Package

![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue)
![License](https://img.shields.io/badge/License-BSD-blue)

A comprehensive codec implementation package for WebRTC applications, providing audio/video encoding and decoding capabilities.

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

- **PHP ≥ 8.4** with FFI extension enabled
- Linux (Windows and macOS support planned for future releases)
- FFmpeg/libav shared libraries (libavcodec, libavfilter, etc.)
    - Compatible with FFmpeg **version 7.1.1**
- libopus development libraries
- libvpx development libraries 
  - Compatible with libvpx **version 1.15.0** 

## Documentation

This package is part of the PHP WebRTC library. For complete documentation, examples, and API reference, visit:

[PHP WebRTC Documentation](https://www.quasarstream.com/php-webrtc)

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
