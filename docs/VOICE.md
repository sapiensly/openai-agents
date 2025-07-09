# Voice Pipeline (Speech-to-Text & Text-to-Speech)

Este paquete incluye un pipeline de voz completo que permite:
- Transcribir audio a texto (Speech-to-Text, STT)
- Generar audio a partir de texto (Text-to-Speech, TTS)
- Orquestar un flujo completo de audio → texto → agente → audio

## Características
- Utiliza OpenAI Whisper para transcripción
- Utiliza OpenAI TTS para síntesis de voz
- Integración con agentes y prompts personalizados
- Expuesto como API REST y comando Artisan
- Interfaz web para TTS y STT
- Archivos de audio almacenados en `storage/app/audio/`

---

## Uso Programático

```php
use Sapiensly\OpenaiAgents\VoicePipeline;
use Sapiensly\OpenaiAgents\AgentManager;
use OpenAI\Factory;

$client = (new Factory())->withApiKey(config('agents.api_key'))->make();
$manager = app(AgentManager::class);
$agent = $manager->agent(null, 'You are a helpful assistant.');
$pipeline = new VoicePipeline($client, $agent);

// Transcribir audio
$text = $pipeline->transcribe('storage/app/audio/input.wav');

// Generar audio
$audio = $pipeline->speak('Hello world');
file_put_contents('storage/app/audio/output.mp3', $audio);

// Pipeline completo: audio → texto → respuesta → audio
$audioReply = $pipeline->run('storage/app/audio/input.wav');
file_put_contents('storage/app/audio/reply.mp3', $audioReply);
```

---

## Comando Artisan: Prueba de Pipeline de Voz

Puedes probar el flujo completo con:

```bash
# Crear archivo de audio de prueba
cd packages/sapiensly/openai-agents/tools
python3 create_test_audio.py

# Probar pipeline de voz
php artisan agent:test-voice-pipeline --input=storage/app/audio/input.wav --output=storage/app/audio/reply.mp3 --system="You are a helpful assistant."
```

- `--input`  Input audio file (default: `storage/app/audio/input.wav`)
- `--output` Output audio file (default: `storage/app/audio/reply.mp3`)
- `--system` Prompt del sistema para el agente

**Example output:**
```
🎤 Testing Voice Pipeline: STT → Agent → TTS
🤖 Creating agent and voice pipeline...
📝 Transcribing audio to text...
✅ Transcribed text: "Hello, this is a test audio file."
💬 Sending text to agent...
✅ Agent response: "Hello! How can I assist you today?"
🔊 Converting response to speech...
💾 Saving audio file...
✅ Audio saved to: storage/app/audio/reply.mp3
```

---

## API REST

- **POST** `/agents/speak` — Text-to-Speech
- **POST** `/agents/transcribe` — Speech-to-Text

### Example: Transcribe audio
```bash
curl -F "audio=@storage/app/audio/input.wav" http://localhost:8000/agents/transcribe
```

### Example: Generate audio
```bash
curl -X POST -d "text=Hello world" http://localhost:8000/agents/speak --output storage/app/audio/output.mp3
```

---

## Interfaz Web

- Pestaña "Text to Speech": convierte texto a audio y lo reproduce.
- Pestaña "Speech to Text": sube un archivo de audio y muestra el texto transcrito.

---

## Requisitos
- Tener configurada la API Key de OpenAI (`OPENAI_API_KEY`)
- El archivo de entrada debe ser un audio válido (WAV, MP3, M4A, OGG, WEBM)
- El directorio `storage/app/audio/` se crea automáticamente

---

## Troubleshooting
- If the transcription is empty, verify that the audio has clear voice and supported format.
- If the output file is not generated, check write permissions and the API key.
- Audio files are stored in `storage/app/audio/` by default.

---

## Referencias
- [OpenAI Whisper (Speech-to-Text)](https://platform.openai.com/docs/guides/speech-to-text)
- [OpenAI Text-to-Speech](https://platform.openai.com/docs/guides/text-to-speech) 