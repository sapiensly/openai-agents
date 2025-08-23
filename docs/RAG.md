### RAG implementation

This document explains how Retrieval-Augmented Generation (RAG) is wired inside the package, from the Agent integration to the helper tools for vector stores and files, plus important edge cases we can see in the code.

---

### How RAG is wired into an Agent

- Entry point: Agent::useRAG(array|string $vector_identifiers, ?int $max_num_results)
  - Accepts a single identifier or an array. Each identifier can be:
    - an OpenAI vector store ID (starts with "vs_"), or
    - a vector store name (the code treats anything that doesn’t start with "vs_" as a name).
  - For each identifier, the Agent resolves to an actual vector store ID by calling:
    - findVectorStoreById($id) if it starts with "vs_"
    - findVectorStoreByName($name) otherwise
  - Both methods call runTool('vector_store', 'list') and search the returned list for a match.
  - If a vector store cannot be found, useRAG throws: "Vector store 'X' not found." If none resolve, it throws: "No valid vector store IDs found for the provided identifiers."

- After collecting vector_store_ids:
  - The method composes ragConfig = [
    'type' => 'file_search',
    'vector_store_ids' => [...],
    'max_num_results' => $max_num_results (or default),
  ]
  - It then calls registerRAG().

- registerRAG():
  - Verifies ragConfig is set and vector_store_ids is not empty.
  - Merges a new tool entry of type file_search into the Agent’s tools (Agent options):
    - [
      'type' => 'file_search',
      'vector_store_ids' => ragConfig['vector_store_ids'],
      'max_num_results' => ragConfig['max_num_results'] ?? config('agent.rag.max_num_results', 5)
    ]

- Chat flow impact:
  - When you later call $agent->chat('...'), the Agent’s Responses API payload includes the tools definitions already set in options (including file_search with vector_store_ids). This lets the model retrieve relevant content from those vector stores.

Key takeaway: In this package, Agent-level RAG is implemented as OpenAI Responses API "file_search" tool config with a concrete list of vector_store_ids. The discovery of those IDs is done by listing vector stores via VectorStoreTool under the hood.

---

### Vector store discovery and the internal tool runner

- Agent::runTool(string $toolName, string $action, array $args = []): string
  - Resolves a callable from getDefaultTool($toolName)
  - Injects 'action' => $action into $args
  - Invokes the tool, returning a JSON string.

- getDefaultTool() mappings relevant to RAG:
  - 'vector_store' => new Tools\VectorStoreTool($client)
  - 'file_upload' => new Tools\FileUploadTool($client)
  - 'rag' => new Tools\RAGTool($client)

- findVectorStoreByName/Id:
  - Call runTool('vector_store', 'list')
  - Parse the returned JSON and match by name or ID.

Notes:
- The VectorStoreTool::list method uses the official client $this->client->vectorStores()->list([...]) and returns a normalized array with [id, name, status, created_at] for each store. The Agent relies on this shape.

---

### RAG-related tools included in the package

The package provides three Tool classes under Sapiensly\OpenaiAgents\Tools relevant to RAG workflows:

1) VectorStoreTool
- Purpose: manage vector stores in your OpenAI account.
- Key methods and their OpenAI calls:
  - create($args)
    - Calls $client->vectorStores()->create([...])
    - Supports optional 'expires_after' and 'metadata'.
  - list($args)
    - Calls $client->vectorStores()->list(['limit', 'order'])
    - Returns an array of vector stores with basic fields.
  - get($args)
    - Calls $client->vectorStores()->retrieve($vectorStoreId)
  - delete($args)
    - Calls $client->vectorStores()->delete($vectorStoreId)
  - addFiles($args)
    - Iterates file_ids; for each: $client->vectorStores()->files()->create($vectorStoreId, ['file_id' => $fid])
  - list_files($args)
    - Calls $client->vectorStores()->files()->list($vectorStoreId, ['limit' => ...])
  - search($args)
    - First tries the Chat API with tools: [ { type: 'retrieval', retrieval: { vector_store_id, k, r } } ]
    - If that fails with certain error hints, it attempts $client->vectorStores()->search($vectorStoreId, ['query' => $query]) as a fallback, and maps results to an array of { text: ... }.

2) FileUploadTool
- Purpose: upload files to your OpenAI account and manage them.
- Key methods and their OpenAI calls:
  - upload($args)
    - Calls $client->files()->upload(['file' => fopen($path,'r'), 'purpose' => 'assistants' (default)])
  - list($args)
    - Calls $client->files()->list(['purpose' => ?, 'limit' => ?])
  - get($args)
    - Calls $client->files()->retrieve($fileId)
  - delete($args)
    - Calls $client->files()->delete($fileId)
  - download($args)
    - Calls $client->files()->download($fileId)

3) RAGTool
- Purpose: a self-contained retrieval tool that performs a retrieval-oriented Chat API call when invoked.
- Behavior:
  - __invoke($args) -> performRetrieval()
  - Calls $client->chat()->create([...]) with tools: [ { type: 'retrieval', retrieval: { vector_store_id, k, r } } ]
  - Returns the message content from the first choice as plain text.

When to use which:
- Agent::useRAG(...) is the primary, integrated way to enable RAG in chats, adding a "file_search" tool configuration to the Agent’s tools. This is the recommended path for Level 2 Tools usage inside the Responses API chat flow.
- VectorStoreTool and FileUploadTool are utilities to manage vector stores and files programmatically from the same Agent account context (create stores, upload files, add files to stores, list files, etc.).
- RAGTool is a standalone callable tool that issues a retrieval-augmented chat request by itself. It’s not used by Agent::useRAG; rather, it can be exposed as a general tool callable (e.g., through getDefaultTool('rag')).

---

### Configuration touchpoints and discovered mismatch

- The Agent reads several defaults through config('...'), but the code segments we have show:
  - registerRAG() uses config('agent.rag.max_num_results', 5)
  - useRAG() uses config('agent.rag.max_num_results', 5) to default max_num_results

- The provided configuration file is config/sapiensly-openai-agents.php and defines:
  - 'tools' => [
    'rag' => [
      'enabled' => env('AGENTS_RAG_ENABLED', true),
      'default_k' => env('AGENTS_RAG_DEFAULT_K', 5),
      'default_r' => env('AGENTS_RAG_DEFAULT_R', 0.7),
      'auto_setup' => env('AGENTS_RAG_AUTO_SETUP', true),
    ]
  ]
  - There is no agent.rag.max_num_results key here.

Implication:
- Based on the code we see, if you rely on config to set max_num_results, calls to config('agent.rag.max_num_results') will fall back to 5 unless another config file with that key exists. The visible config only exposes tools.rag.default_k and default_r (used by the tools, not by Agent::useRAG directly). This is a mismatch worth noting when configuring defaults.

What is safe to rely on from the shown code:
- If you want to control the "k" and "r" defaults used in VectorStoreTool::search or RAGTool, you can use the 'tools.rag.default_k' and 'tools.rag.default_r' through env (they’re not directly read in the shown code paths, but they are the intended knobs in config). The methods currently take k, r from input args with hardcoded defaults inside the tool classes (k=5, r=0.7), not from config—so you should explicitly pass k and r where those tools are used, or adapt code if needed.
- For Agent::useRAG max_num_results, you should pass it explicitly in useRAG($ids, $maxNumResults) based on current code. Otherwise it defaults to 5 as per the config('agent.rag.max_num_results', 5) fallback.

---

### Practical workflows and recipes (from the code)

Below are end-to-end flows that match exactly what the code supports.

1) Use an existing vector store for RAG in a chat
- You already have a vector store (by ID or name) with relevant files added.
- Enable RAG in the Agent:
  - $agent = Agent::agent();
  - $agent->useRAG('vs_123...');
  - $response = $agent->chat('What is our refund policy?');
- Under the hood: the Agent sets tools => [ { type: 'file_search', vector_store_ids: ['vs_123...'], max_num_results: 5 } ]. The Responses API includes this tool in the request, enabling retrieval.

2) Use multiple vector stores at once
- $agent->useRAG(['vs_abc...', 'Policies Knowledge Base']);
- The Agent resolves each identifier to an ID, and adds them to vector_store_ids.

3) Create a vector store, upload files, and attach them to the store
- Use the helper tools. You can either call them via Agent’s internal runTool mechanism or directly instantiate and call the tool classes.

Example using the exposed default tools via Agent (structure based on code):
- Create a vector store
  - $result = $agent->getVectorStoreTool()->create(['name' => 'KB Docs']);
  - $data = json_decode($result, true);
  - $vsId = $data['vector_store_id'] ?? null;
- Upload a file
  - $fileJson = (new FileUploadTool($agent->getClient()))->upload([
      'file_path' => storage_path('app/docs/refund_policy.pdf'),
      'purpose' => 'assistants',
    ]);
  - $file = json_decode($fileJson, true);
  - $fileId = $file['file_id'] ?? null;
- Add file to vector store
  - (new VectorStoreTool($agent->getClient()))->addFiles([
      'vector_store_id' => $vsId,
      'file_ids' => [$fileId],
    ]);
- Now enable RAG and chat
  - $agent->useRAG($vsId);
  - $agent->chat('What is our refund policy?');

4) Search a vector store directly (outside of chat)
- Using VectorStoreTool::search:
  - $json = (new VectorStoreTool($agent->getClient()))->search([
      'query' => 'refund policy',
      'vector_store_id' => $vsId,
      'k' => 5,
      'r' => 0.7,
    ]);
  - Returns a JSON object with 'results' => array of { text: ... }.

5) RAGTool direct retrieval call
- If you want to call an ad-hoc retrieval via Chat API outside the Agent’s Responses flow:
  - $text = (new RAGTool($agent->getClient()))([
      'query' => 'refund policy',
      'vector_store_id' => $vsId,
      'k' => 5,
      'r' => 0.7,
    ]);
- This returns message content (string) from the Chat API response.

---

### Error handling visible in the code

- Agent::useRAG throws when:
  - vector_identifiers is empty
  - a provided store identifier cannot be resolved
  - no valid vector store IDs are found

- VectorStoreTool methods return JSON with {'success' => false, 'error' => '...'} on failures and Log::error details.
- FileUploadTool likewise returns structured JSON on errors and logs them.
- RAGTool catches exceptions and returns error strings, logging the details.

---

### Limits, defaults, and model choices (based on code)

- Models:
  - VectorStoreTool::search and RAGTool use 'gpt-4o' in the Chat API calls.
  - The Agent for regular chat defaults to model from config 'default_options.model' => 'gpt-4o' (config path: sapiensly-openai-agents.default_options.model).

- Defaults for retrieval parameters:
  - RAGTool and VectorStoreTool::search hardcode defaults k=5 and r=0.7 unless passed explicitly in args.
  - Agent::registerRAG uses max_num_results => ragConfig['max_num_results'] or config('agent.rag.max_num_results', 5) as fallback.

- Message/history constraints and token usage are handled by the Agent class broadly, but RAG tooling itself is about the tool configuration in the request. File search/retrieval occurs on OpenAI side based on your vector stores.

---

### Caveats and things to watch

- Config key mismatch:
  - The Agent looks for config('agent.rag.max_num_results'), but the provided config file does not define that key. As-is, the default will be 5 via the fallback. If you need a configurable default, pass it into useRAG(...) explicitly or align the code/config keys.

- Tool selection/APIs:
  - Agent-level RAG leverages the Responses API "file_search" tool with vector_store_ids (this is what registerRAG sets).
  - RAGTool and VectorStoreTool::search attempt a Chat Completions style retrieval with type 'retrieval'. If the client or API version doesn’t support that structure, VectorStoreTool::search falls back to a direct vectorStores()->search call (only 'query'), which the code anticipates may or may not be available depending on the OpenAI PHP SDK and API features.

- Vector store and file preparation is external to the Agent:
  - The Agent won’t create or upload files for you. Use VectorStoreTool and FileUploadTool to prepare the data first, then enable RAG via useRAG().

- Name lookups:
  - findVectorStoreByName matches exact name equality from the list endpoint’s returned name.

---

### Minimal, correct examples (aligned with current code)

- Get the VectorStore list from the Client or Agent:
```php
use Sapiensly\OpenaiAgents\Facades\Agent;
use Sapiensly\OpenaiAgents\Tools\VectorStoreTool;
use OpenAI;
// 1a. Directly via client
$client = OpenAI::client(config('sapiensly-openai-agents.api_key'));
$vsTool = new VectorStoreTool($client);
// 1b. Get client from Agent
$agent = Agent::agent();
$vsTool = $agent->getVectorStoreTool();
// 2. Use VectorStoreTool to list
$listJson = $vsTool->list(['limit' => 10, 'order' => 'desc']); // JSON string
/* $listJson's value example:
{
  "success": true,
  "vector_stores": [
    {
      "id": "vs_abc123def456ghi789jkl012mno345pq",
      "name": "Base de Conocimiento Empresarial",
      "status": "completed",
      "created_at": 1751990850
    },
    ...
  ],
  "count": 12
}
*/
```

- List files from a vector store:
```php
$vsId = 'vs_abc123def456ghi789jkl012mno345pq';
$filesJson = $vsTool->list_files(['vector_store_id' => $vsId, 'limit' => 10]);
/* $filesJson's value example:
{
  "success": true,
  "files": [
    {
      "id": "file_1234567890abcdef1234567890abcdef",
      "name": "refund_policy.pdf",
      "size": 123456,
      "created_at": 1751990850
    },
    ...
    ],
    "count": 5
}
```

- Get detail for a specific file:
```php
use Sapiensly\OpenaiAgents\Tools\FileUploadTool;

$fileTool = new FileUploadTool($client);

// Get file info by ID
$fileInfo = $fileTool->get(['file_id' => 'file_id_especifico']);
$fileData = json_decode($fileInfo, true);

if ($fileData['success']) {
    $file = $fileData['file'];
    echo "Original name: {$file['filename']}\n";
    echo "Size: {$file['bytes']} bytes\n";
    echo "Purpose: {$file['purpose']}\n";
    echo "Size: {$file['object']}\n";
}

```

- Enable RAG with a known vector store ID and ask a question:
```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$agent = Agent::agent();
$agent->useRAG('vs_123456789');
$response = $agent->chat('What is our refund policy?');
```

- Enable RAG with multiple stores, specifying max results per retrieval:
```php
$agent->useRAG(['vs_abc', 'Team Handbook'], 8);
$response = $agent->chat('Summarize our holiday policy.');
```

- Create a store, upload a file, attach, then use in chat:
```php
$agent = Agent::agent();
$vsTool = $agent->getVectorStoreTool();
$fuTool = new \Sapiensly\OpenaiAgents\Tools\FileUploadTool($agent->getClient());

$vs = json_decode($vsTool->create(['name' => 'KB Docs']), true);
$vsId = $vs['vector_store_id'] ?? null;

$file = json_decode($fuTool->upload([
  'file_path' => storage_path('app/docs/refund_policy.pdf'),
  'purpose' => 'assistants',
]), true);
$fileId = $file['file_id'] ?? null;

$vsTool->addFiles(['vector_store_id' => $vsId, 'file_ids' => [$fileId]]);

$agent->useRAG($vsId);
$response = $agent->chat('What is our refund policy?');
```

- Direct vector store search (outside of chat):
```php
$vsTool = new \Sapiensly\OpenaiAgents\Tools\VectorStoreTool($agent->getClient());
$resultsJson = $vsTool->search([
  'query' => 'refund policy',
  'vector_store_id' => $vsId,
  'k' => 5,
  'r' => 0.7,
]);
$results = json_decode($resultsJson, true);
```

---

### Summary

- Agent::useRAG integrates RAG by configuring a file_search tool with concrete vector_store_ids. This is how the Responses API call gets retrieval context.
- VectorStoreTool and FileUploadTool provide the CRUD and attachment flows for vector stores and files.
- RAGTool is a standalone retrieval caller via the Chat API. It is not used by useRAG, but is available as a tool mapping.
- There’s a config key mismatch for max_num_results default; pass the value explicitly to useRAG for now, given the visible code.

This completes the deeper, code-accurate continuation of the RAG implementation review without introducing functionality not present in the repository snippets you shared.

### ANNEX 1: CRUD Operations for Vector Stores and Files
```php
use Sapiensly\OpenaiAgents\Facades\Agent;

$agent = Agent::agent();
$client = $agent->getClient();
```

- Tools you will use:
    - VectorStoreTool: Sapiensly\OpenaiAgents\Tools\VectorStoreTool
    - FileUploadTool: Sapiensly\OpenaiAgents\Tools\FileUploadTool

Note: These tool methods return JSON strings. Use json_decode($json, true) to work with arrays.

---

### Vector Stores — CRUD and Attachments

Get the VectorStoreTool (either via Agent or directly):

```php
$vsTool = $agent->getVectorStoreTool(); // or: new \Sapiensly\OpenaiAgents\Tools\VectorStoreTool($client)
```

#### Create (C)

```php
use Sapiensly\OpenaiAgents\Tools\VectorStoreTool;

$vsTool = new VectorStoreTool($client);

$json = $vsTool->create([
    'name' => 'KB Docs',
    // Optional fields supported by the tool
    // 'expires_after' => ['anchor' => 'last_active_at', 'days' => 30],
    // 'metadata' => ['team' => 'support', 'source' => 'policy-pdfs'],
]);
$data = json_decode($json, true);

if ($data['success']) {
    $vectorStoreId = $data['vector_store_id']; // e.g., vs_abc123
}
```

#### List (R)

```php
$json = $vsTool->list([
    'limit' => 20,
    'order' => 'desc', // default
]);
$data = json_decode($json, true);
// $data['vector_stores'] => array of [id, name, status, created_at]
```

#### Get/Retrieve (R)

```php
$json = $vsTool->get(['vector_store_id' => $vectorStoreId]);
$data = json_decode($json, true);
// $data['vector_store'] => [id, name, status, created_at, expires_after?, metadata?]
```

#### Delete (D)

```php
$json = $vsTool->delete(['vector_store_id' => $vectorStoreId]);
$data = json_decode($json, true);
// success boolean and message
```

#### Add Files to a Vector Store

```php
$json = $vsTool->addFiles([
    'vector_store_id' => $vectorStoreId,
    'file_ids' => ['file_abc123', 'file_def456'],
]);
$data = json_decode($json, true);
// $data['added_files'] contains the created vector-store-file link IDs
```

#### List Files in a Vector Store

```php
$json = $vsTool->list_files([
    'vector_store_id' => $vectorStoreId,
    'limit' => 20,
]);
$data = json_decode($json, true);
// $data['files'] => array of [id, name, size, created_at]
```

Note: The tool does not implement an "update" method for vector stores. If you need to modify metadata/expiration, you’ll need to extend the tool.

---

### Files — CRUD

Create the FileUploadTool:

```php
use Sapiensly\OpenaiAgents\Tools\FileUploadTool;

$fileTool = new FileUploadTool($client);
```

#### Upload (C)

```php
$json = $fileTool->upload([
    'file_path' => storage_path('app/docs/refund_policy.pdf'),
    'purpose'   => 'assistants', // default
]);
$data = json_decode($json, true);

if ($data['success']) {
    $fileId = $data['file_id'];
}
```

#### List (R)

```php
$json = $fileTool->list([
    'purpose' => 'assistants', // optional
    'limit'   => 20,           // optional
]);
$data = json_decode($json, true);
// $data['files'] => [id, filename, purpose, bytes, created_at]
```

#### Get/Retrieve (R)

```php
$json = $fileTool->get(['file_id' => $fileId]);
$data = json_decode($json, true);
// $data['file'] => [id, filename, purpose, bytes, created_at, status?]
```

#### Download (R)

```php
$json = $fileTool->download(['file_id' => $fileId]);
$data = json_decode($json, true);

if ($data['success']) {
    $content = $data['content']; // raw file content
    // file_put_contents('/path/to/save.pdf', $content);
}
```

#### Delete (D)

```php
$json = $fileTool->delete(['file_id' => $fileId]);
$data = json_decode($json, true);
// success boolean and message
```

---

### End-to-End Example: Upload, Create Store, Attach, Use in Chat

```php
use Sapiensly\OpenaiAgents\Facades\Agent;
use Sapiensly\OpenaiAgents\Tools\FileUploadTool;

$agent = Agent::agent();

$vsTool = $agent->getVectorStoreTool();
$fileTool = new FileUploadTool($agent->getClient());

// 1) Create vector store
$vs = json_decode($vsTool->create(['name' => 'KB Docs']), true);
if (!$vs['success']) { throw new \RuntimeException($vs['error']); }
$vsId = $vs['vector_store_id'];

// 2) Upload a file
$up = json_decode($fileTool->upload([
    'file_path' => storage_path('app/docs/refund_policy.pdf'),
    'purpose' => 'assistants',
]), true);
if (!$up['success']) { throw new \RuntimeException($up['error']); }
$fileId = $up['file_id'];

// 3) Attach file to vector store
$add = json_decode($vsTool->addFiles([
    'vector_store_id' => $vsId,
    'file_ids' => [$fileId],
]), true);
if (!$add['success']) { throw new \RuntimeException($add['error']); }

// 4) Use RAG in chat (wires file_search with this vector store)
$agent->useRAG($vsId); // accepts ID or name; optional max_num_results as 2nd arg
$response = $agent->chat('What is our refund policy?');
// echo $response;

// 5) List files in the vector store (optional)
$listFiles = json_decode($vsTool->list_files(['vector_store_id' => $vsId, 'limit' => 10]), true);

// 6) Cleanup (optional)
// json_decode($fileTool->delete(['file_id' => $fileId]), true);
// json_decode($vsTool->delete(['vector_store_id' => $vsId]), true);
```

---

### Optional: Direct Vector Store Search (outside chat)

```php
$resultsJson = $vsTool->search([
  'query' => 'refund policy',
  'vector_store_id' => $vsId,
  'k' => 5,
  'r' => 0.7,
]);
$results = json_decode($resultsJson, true);
```

---

### Notes and Limitations

- Methods return JSON strings; decode with json_decode($json, true).
- VectorStoreTool methods implemented: create, list, get, delete, addFiles, list_files, search. No update method is implemented.
- FileUploadTool methods implemented: upload, list, get, download, delete.
- Agent::useRAG integrates a file_search tool in the Responses API call using concrete vector_store_ids and optional max_num_results; ensure your store(s) and file(s) are created and attached first.

If you want, I can adapt these snippets to your exact paths, error handling style, or integrate them into a service class.

### What command is this?

The repository registers a Laravel Artisan command named:
- agent:vector-store

It’s defined in Sapiensly\OpenaiAgents\Console\Commands\VectorStoreCommand and registered in CommandServiceProvider when the app is running in the console.

This command manages OpenAI Vector Stores that your agents use for RAG (Retrieval‑Augmented Generation).

---

### ANNEX 2: VectorStoreCommand

Actions supported:
- create: Create a new vector store
- list: List existing vector stores
- get: Retrieve detailed information for a vector store
- delete: Delete a vector store (with confirmation)
- add-files: Attach uploaded OpenAI File IDs to a vector store
- list-files: List files currently attached to a vector store, with an optional “details” mode

Global options:
- --name= (for create)
- --id= (for get, delete, add-files, list-files)
- --files=* (multiple values; for add-files)
- --limit= (int; for list and list-files; default 20)
- --details or -d (flag; enrich list-files output with real filename and size via Files API)

Exit codes:
- 0 on success (and also on user-cancel for delete)
- 1 on validation or API failure, or unknown action

---

### Command Signature

php artisan agent:vector-store {action} [options]

Where action ∈ { create | list | get | delete | add-files | list-files }.

---

### Action-by-Action Behavior

#### create
- Purpose: Create a new vector store in OpenAI.
- Usage: php artisan agent:vector-store create --name="KB Docs"
- Requires: --name
- What it does:
    - Validates that --name is provided.
    - Calls VectorStoreTool->create(['name' => $name]).
    - On success, prints a table with columns: [ID, Name, Status].
    - Exit codes: 0 on success, 1 on failure.
- Notes:
    - The underlying tool supports expires_after and metadata, but the command does not expose these flags. Use the tool in code if you need those.

#### list
- Purpose: List vector stores.
- Usage:
    - php artisan agent:vector-store list
    - php artisan agent:vector-store list --limit=50
- Options: --limit (default 20)
- What it does:
    - Calls VectorStoreTool->list(['limit' => $limit]).
    - Prints total count and a table [ID, Name, Status, Created].
    - If none found, prints a friendly message and exits 0.
- Exit codes: 0 on success, 1 on failure.

#### get
- Purpose: Show details for a vector store by ID.
- Usage: php artisan agent:vector-store get --id=vs_12345
- Requires: --id
- What it does:
    - Calls VectorStoreTool->get(['vector_store_id' => $id]).
    - Prints a property table with: ID, Name, Status, Created, Expires After (or “Never”).
- Exit codes: 0 on success, 1 on failure.

#### delete
- Purpose: Delete a vector store by ID.
- Usage: php artisan agent:vector-store delete --id=vs_12345
- Requires: --id
- What it does:
    - Prompts for confirmation. If you decline, it prints “Operation cancelled.” and returns 0.
    - If confirmed, calls VectorStoreTool->delete(['vector_store_id' => $id]).
    - Prints success or error message.
- Exit codes: 0 on success or user-cancel, 1 on failure.

#### add-files
- Purpose: Attach existing OpenAI File IDs to a vector store.
- Usage: php artisan agent:vector-store add-files --id=vs_12345 --files=file_abc --files=file_def
- Requires: --id and at least one --files value.
- What it does:
    - Calls VectorStoreTool->addFiles(['vector_store_id' => $id, 'file_ids' => $files]).
    - Prints the created vector-store-file link IDs for the attachments.
- Exit codes: 0 on success, 1 on failure.
- Notes:
    - This does not upload files. Files must already exist in OpenAI (upload using FileUploadTool or other means).

#### list-files
- Purpose: List files attached to a vector store.
- Usage:
    - php artisan agent:vector-store list-files --id=vs_12345
    - php artisan agent:vector-store list-files --id=vs_12345 --limit=50
    - php artisan agent:vector-store list-files --id=vs_12345 --details
- Requires: --id
- Options: --limit (default 20), --details/-d
- What it does:
    - Calls VectorStoreTool->list_files(['vector_store_id' => $id, 'limit' => $limit]).
    - If no files, prints a message and exits 0.
    - Default table columns: [ID, Name, Size, Created] based on the vector store file listing.
    - With --details, it additionally calls FileUploadTool->get(['file_id' => $fileId]) for each file to fetch the real filename and bytes from OpenAI Files API, then prints those values (slower due to extra API calls).
- Exit codes: 0 on success, 1 on failure.

---

### Inputs, Outputs, and Validation

- Required options are strictly validated per action; missing ones cause an error and exit code 1.
- delete uses an interactive confirmation.
- Output is human-friendly with Artisan table formatting and status lines.
- Underlying tool calls return JSON; the command decodes it and renders tables/messages.

---

### Dependencies Under the Hood

- Creates an OpenAI client via the package’s Agent: Agent::create(['model' => 'gpt-4o'])
- Uses:
    - Sapiensly\OpenaiAgents\Tools\VectorStoreTool for all vector-store operations.
    - Sapiensly\OpenaiAgents\Tools\FileUploadTool only when --details is used in list-files to fetch filenames and sizes.
- Requires OPENAI_API_KEY configured in your environment.

---

### Things This Command Does Not Do (per current code)

- No update operation for vector stores.
- No direct vector store "search" action (though VectorStoreTool has search; the command doesn’t expose it).
- No alternative output formats (e.g., JSON/CSV) exposed by flags; it prints tables and console lines only.
- No file upload capability; uploading must be done separately (e.g., FileUploadTool in code).
- No pagination beyond a simple --limit value.

---

### Practical Examples

- Create a store:
    - php artisan agent:vector-store create --name="KB Docs"

- List stores (default 20):
    - php artisan agent:vector-store list

- List more stores:
    - php artisan agent:vector-store list --limit=50

- Get details for a store:
    - php artisan agent:vector-store get --id=vs_123456

- Delete a store (with confirmation):
    - php artisan agent:vector-store delete --id=vs_123456

- Add files to a store:
    - php artisan agent:vector-store add-files --id=vs_123456 --files=file_abc123 --files=file_def456

- List files in a store:
    - php artisan agent:vector-store list-files --id=vs_123456

- List files with real filenames and bytes (slower):
    - php artisan agent:vector-store list-files --id=vs_123456 --details

---

### Troubleshooting

- Missing required option errors:
    - Provide --name for create; --id for get/delete/add-files/list-files; and at least one --files for add-files.

- API failures (create/list/get/delete/add-files/list-files):
    - Ensure OPENAI_API_KEY is set and valid; verify network access and account access to vector store features.

- Slow list-files with --details:
    - Expected due to an extra Files API request per file to get real names/sizes.

---

### How It Fits RAG in This Package

- The command manages the vector stores and file attachments used by RAG.
- After creating a store and attaching files, enable RAG in code:
    - $agent->useRAG($vectorStoreId[, $maxNumResults]);
    - Then $agent->chat('...') will include a file_search tool referencing your vector_store_ids, allowing retrieval-augmented responses.
