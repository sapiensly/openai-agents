# Tools Directory

This directory contains utility scripts and tools for testing and development of the OpenAI Agents package.

## Available Tools

### create_test_audio.py

A Python script that generates a test audio file for voice agent testing.

**Features:**
- Creates a 3-second 440 Hz sine wave audio file
- WAV format, Mono, 16-bit, 44.1 kHz
- Saves files to `storage/app/audio/` directory
- Useful for testing voice/audio functionality

**Usage:**
```bash
cd packages/sapiensly/openai-agents/tools
python3 create_test_audio.py
```

**Output:**
- `storage/app/audio/input.wav`: Test audio file (264 KB)
- Console output with file details and location

**Requirements:**
- Python 3.x
- Standard library modules: `wave`, `struct`, `math`, `os`

**File Locations:**
- Input files: `storage/app/audio/input.wav`
- Output files: `storage/app/audio/reply.mp3`
- Directory created automatically if it doesn't exist

## Purpose

These tools are designed to support development and testing of the Laravel OpenAI Agents package, particularly for features that require external resources like audio files.

## Contributing

When adding new tools to this directory:

1. Include proper documentation
2. Add error handling
3. Make the script self-contained
4. Include usage examples
5. Update this README
6. Use `storage/app/` for file outputs when appropriate 