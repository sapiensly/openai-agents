# Changelog

All notable changes to the Laravel OpenAI Agents package will be documented in this file.

## [Unreleased] - 2024-12-19

### Added
- **RAG with Streaming Support**: New command `agent:test-rag-streaming` for testing RAG functionality with real-time streaming output
- **Enhanced RAG Streaming**: Improved `chatStreamedWithRetrieval` method to support proper streaming with vector stores
- **Streaming Configuration**: Added configurable options for RAG streaming including delay, timeout, and max-length
- **Documentation Updates**: Updated RAG_GUIDE.md, STREAMING.md, TEST_COMMANDS.md, and README.md with RAG streaming examples

### Fixed
- **RAG Streaming Issue**: Fixed issue where RAG streaming was returning single chunks instead of gradual text output
- **Array to String Conversion**: Fixed error when displaying RAG responses that contained arrays of objects
- **Streaming Performance**: Improved streaming performance for RAG queries with proper chunk handling

### Changed
- **RAG Streaming Implementation**: Modified `chatStreamedWithRetrieval` to use streaming directly instead of calling non-streaming methods
- **Command Registration**: Added `TestRAGStreaming` command to the command service provider

### Technical Details
- **Streaming Chunks**: RAG streaming now properly yields individual text chunks (200+ chunks typical) instead of single responses
- **Fallback Support**: Maintains fallback to traditional RAG when OpenAI retrieval tool is not available
- **Error Handling**: Improved error handling for RAG streaming with proper exception management

## [Previous Versions]

### Features
- Progressive enhancement architecture (4 levels)
- Multi-agent handoff system
- OpenAI Official Tools integration
- Voice pipeline support
- MCP (Model Context Protocol) support
- Comprehensive testing commands
- Streaming support for real-time responses
- RAG (Retrieval-Augmented Generation) functionality
- Vector store management
- File upload and management

### Commands Available
- `agent:test-level1` - Level 1 conversational agent testing
- `agent:test-level2` - Level 2 agent with tools testing
- `agent:test-level3` - Level 3 multi-agent testing
- `agent:test-level4` - Level 4 autonomous agent testing
- `agent:test-all-levels` - Test all progressive enhancement levels
- `agent:test-streaming` - Streaming functionality testing
- `agent:test-rag` - RAG functionality testing
- `agent:test-rag-streaming` - **NEW**: RAG with streaming testing
- `agent:vector-store` - Vector store management
- `agent:list-files` - File management from OpenAI account 