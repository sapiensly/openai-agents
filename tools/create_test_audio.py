#!/usr/bin/env python3
"""
Test Audio Generator for OpenAI Agents Package

This script creates a simple test audio file (440 Hz sine wave, 3 seconds)
for testing voice/audio functionality in the Laravel OpenAI Agents package.

Usage:
    python create_test_audio.py

Output:
    - storage/app/audio/input.wav: 3-second 440 Hz sine wave audio file
"""

import wave
import struct
import math
import os

def create_test_audio():
    """Create a test audio file for voice agent testing"""
    
    # Audio configuration
    sample_rate = 44100
    duration = 3  # seconds
    frequency = 440  # Hz (A4 note)
    
    # Get the Laravel project root (3 levels up from tools directory)
    current_dir = os.path.dirname(os.path.abspath(__file__))
    laravel_root = os.path.join(current_dir, '..', '..', '..', '..')
    
    # Create storage directory if it doesn't exist
    storage_dir = os.path.join(laravel_root, 'storage', 'app', 'audio')
    os.makedirs(storage_dir, exist_ok=True)
    
    # Create WAV file in storage directory
    output_file = os.path.join(storage_dir, 'input.wav')
    with wave.open(output_file, 'w') as wav_file:
        wav_file.setnchannels(1)  # Mono
        wav_file.setsampwidth(2)  # 16-bit
        wav_file.setframerate(sample_rate)
        
        # Generate audio samples
        samples = []
        for i in range(int(sample_rate * duration)):
            sample = math.sin(2 * math.pi * frequency * i / sample_rate)
            samples.append(int(sample * 32767))
        
        wav_file.writeframes(struct.pack('<%dh' % len(samples), *samples))
    
    # Get file size
    file_size = os.path.getsize(output_file)
    
    print(f"✅ Test audio file created: {output_file}")
    print(f"📊 File size: {file_size} bytes")
    print(f"🎵 Audio: {frequency} Hz sine wave, {duration} seconds")
    print(f"🔧 Format: WAV, Mono, 16-bit, {sample_rate} Hz")
    print(f"📁 Location: {os.path.abspath(output_file)}")
    
    return output_file

if __name__ == "__main__":
    create_test_audio() 