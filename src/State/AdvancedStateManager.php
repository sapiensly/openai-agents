<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\State;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Class AdvancedStateManager
 *
 * Advanced implementation of the ConversationStateManager interface.
 * Provides compression, encryption, backup, and metrics for conversation state.
 */
class AdvancedStateManager implements ConversationStateManager
{
    /**
     * The underlying state manager.
     *
     * @var ConversationStateManager
     */
    private ConversationStateManager $stateManager;

    /**
     * The encryption key for sensitive data.
     *
     * @var string
     */
    private string $encryptionKey;

    /**
     * Whether compression is enabled.
     *
     * @var bool
     */
    private bool $compressionEnabled;

    /**
     * Whether encryption is enabled.
     *
     * @var bool
     */
    private bool $encryptionEnabled;

    /**
     * Whether backup is enabled.
     *
     * @var bool
     */
    private bool $backupEnabled;

    /**
     * The backup state manager.
     *
     * @var ConversationStateManager|null
     */
    private ?ConversationStateManager $backupManager;

    /**
     * Metrics for state operations.
     *
     * @var array
     */
    private array $metrics = [
        'saves' => 0,
        'loads' => 0,
        'compressions' => 0,
        'encryptions' => 0,
        'backups' => 0,
        'errors' => 0
    ];

    /**
     * Create a new AdvancedStateManager instance.
     *
     * @param ConversationStateManager $stateManager The underlying state manager
     * @param array $config The configuration array
     */
    public function __construct(ConversationStateManager $stateManager, array $config = [])
    {
        $this->stateManager = $stateManager;
        $this->encryptionKey = $config['encryption_key'] ?? 'default-key-change-in-production';
        $this->compressionEnabled = $config['compression_enabled'] ?? true;
        $this->encryptionEnabled = $config['encryption_enabled'] ?? false;
        $this->backupEnabled = $config['backup_enabled'] ?? false;
        $this->backupManager = $config['backup_manager'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function saveContext(string $conversationId, array $context): void
    {
        try {
            $this->metrics['saves']++;
            
            // Process context data
            $processedContext = $this->processContextForSave($context);
            
            // Save to primary storage
            $this->stateManager->saveContext($conversationId, $processedContext);
            
            // Backup if enabled
            if ($this->backupEnabled && $this->backupManager) {
                $this->backupManager->saveContext($conversationId, $processedContext);
                $this->metrics['backups']++;
            }
            
            Log::info('Advanced state manager: Context saved', [
                'conversation_id' => $conversationId,
                'context_size' => count($context),
                'compressed' => $this->compressionEnabled,
                'encrypted' => $this->encryptionEnabled
            ]);
            
        } catch (\Exception $e) {
            $this->metrics['errors']++;
            Log::error('Advanced state manager: Failed to save context', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function loadContext(string $conversationId): array
    {
        try {
            $this->metrics['loads']++;
            
            // Try to load from primary storage
            $context = $this->stateManager->loadContext($conversationId);
            
            if (empty($context)) {
                // Try backup if available
                if ($this->backupEnabled && $this->backupManager) {
                    $context = $this->backupManager->loadContext($conversationId);
                    Log::info('Advanced state manager: Loaded from backup', [
                        'conversation_id' => $conversationId
                    ]);
                }
            }
            
            // Process context data for load
            $processedContext = $this->processContextForLoad($context);
            
            Log::info('Advanced state manager: Context loaded', [
                'conversation_id' => $conversationId,
                'context_size' => count($processedContext)
            ]);
            
            return $processedContext;
            
        } catch (\Exception $e) {
            $this->metrics['errors']++;
            Log::error('Advanced state manager: Failed to load context', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveHandoffState(
        string $conversationId,
        string $sourceAgentId,
        string $targetAgentId,
        array $context
    ): void {
        try {
            $this->metrics['saves']++;
            
            // Process context data
            $processedContext = $this->processContextForSave($context);
            
            // Save to primary storage
            $this->stateManager->saveHandoffState($conversationId, $sourceAgentId, $targetAgentId, $processedContext);
            
            // Backup if enabled
            if ($this->backupEnabled && $this->backupManager) {
                $this->backupManager->saveHandoffState($conversationId, $sourceAgentId, $targetAgentId, $processedContext);
                $this->metrics['backups']++;
            }
            
            Log::info('Advanced state manager: Handoff state saved', [
                'conversation_id' => $conversationId,
                'source_agent' => $sourceAgentId,
                'target_agent' => $targetAgentId
            ]);
            
        } catch (\Exception $e) {
            $this->metrics['errors']++;
            Log::error('Advanced state manager: Failed to save handoff state', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConversationHistory(string $conversationId): array
    {
        return $this->stateManager->getConversationHistory($conversationId);
    }

    /**
     * {@inheritdoc}
     */
    public function getHandoffHistory(string $conversationId): array
    {
        return $this->stateManager->getHandoffHistory($conversationId);
    }

    /**
     * Process context data for saving (compression and encryption).
     *
     * @param array $context The context data
     * @return array The processed context data
     */
    private function processContextForSave(array $context): array
    {
        $processed = $context;
        
        // Compress if enabled
        if ($this->compressionEnabled) {
            $processed = $this->compressData($processed);
            $this->metrics['compressions']++;
        }
        
        // Encrypt if enabled
        if ($this->encryptionEnabled) {
            $processed = $this->encryptData($processed);
            $this->metrics['encryptions']++;
        }
        
        return $processed;
    }

    /**
     * Process context data for loading (decryption and decompression).
     *
     * @param array $context The context data
     * @return array The processed context data
     */
    private function processContextForLoad(array $context): array
    {
        $processed = $context;
        
        // Decrypt if encrypted
        if ($this->encryptionEnabled && $this->isEncrypted($processed)) {
            $processed = $this->decryptData($processed);
        }
        
        // Decompress if compressed
        if ($this->compressionEnabled && $this->isCompressed($processed)) {
            $processed = $this->decompressData($processed);
        }
        
        return $processed;
    }

    /**
     * Compress data using gzip.
     *
     * @param array $data The data to compress
     * @return array The compressed data
     */
    private function compressData(array $data): array
    {
        $json = json_encode($data);
        $compressed = gzencode($json);
        return [
            '_compressed' => true,
            'data' => base64_encode($compressed)
        ];
    }

    /**
     * Decompress data using gzip.
     *
     * @param array $data The compressed data
     * @return array The decompressed data
     */
    private function decompressData(array $data): array
    {
        if (!isset($data['_compressed']) || !$data['_compressed']) {
            return $data;
        }
        
        $compressed = base64_decode($data['data']);
        $json = gzdecode($compressed);
        return json_decode($json, true);
    }

    /**
     * Encrypt data using AES-256-CBC.
     *
     * @param array $data The data to encrypt
     * @return array The encrypted data
     */
    private function encryptData(array $data): array
    {
        $json = json_encode($data);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($json, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        return [
            '_encrypted' => true,
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }

    /**
     * Decrypt data using AES-256-CBC.
     *
     * @param array $data The encrypted data
     * @return array The decrypted data
     */
    private function decryptData(array $data): array
    {
        if (!isset($data['_encrypted']) || !$data['_encrypted']) {
            return $data;
        }
        
        $encrypted = base64_decode($data['data']);
        $iv = base64_decode($data['iv']);
        $json = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        return json_decode($json, true);
    }

    /**
     * Check if data is compressed.
     *
     * @param array $data The data to check
     * @return bool True if compressed
     */
    private function isCompressed(array $data): bool
    {
        return isset($data['_compressed']) && $data['_compressed'];
    }

    /**
     * Check if data is encrypted.
     *
     * @param array $data The data to check
     * @return bool True if encrypted
     */
    private function isEncrypted(array $data): bool
    {
        return isset($data['_encrypted']) && $data['_encrypted'];
    }

    /**
     * Get metrics for state operations.
     *
     * @return array The metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Reset metrics.
     *
     * @return void
     */
    public function resetMetrics(): void
    {
        $this->metrics = [
            'saves' => 0,
            'loads' => 0,
            'compressions' => 0,
            'encryptions' => 0,
            'backups' => 0,
            'errors' => 0
        ];
    }

    /**
     * Sync data between primary and backup storage.
     *
     * @param string $conversationId The conversation ID
     * @return bool True if sync was successful
     */
    public function syncWithBackup(string $conversationId): bool
    {
        if (!$this->backupEnabled || !$this->backupManager) {
            return false;
        }
        
        try {
            $context = $this->stateManager->loadContext($conversationId);
            $this->backupManager->saveContext($conversationId, $context);
            
            Log::info('Advanced state manager: Synced with backup', [
                'conversation_id' => $conversationId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Advanced state manager: Failed to sync with backup', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Recover data from backup.
     *
     * @param string $conversationId The conversation ID
     * @return bool True if recovery was successful
     */
    public function recoverFromBackup(string $conversationId): bool
    {
        if (!$this->backupEnabled || !$this->backupManager) {
            return false;
        }
        
        try {
            $context = $this->backupManager->loadContext($conversationId);
            if (!empty($context)) {
                $this->stateManager->saveContext($conversationId, $context);
                
                Log::info('Advanced state manager: Recovered from backup', [
                    'conversation_id' => $conversationId
                ]);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Advanced state manager: Failed to recover from backup', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 