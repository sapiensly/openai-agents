<?php
declare(strict_types=1);

namespace Sapiensly\OpenaiAgents\Handoff;

use Illuminate\Support\Facades\Log;
use Sapiensly\OpenaiAgents\Registry\AgentRegistry;

/**
 * Class ContextAnalyzer
 *
 * Analyzes user input and conversation context to determine the best agent
 * for handling requests. Uses pattern matching and intent analysis.
 */
class ContextAnalyzer
{
    /**
     * Create a new ContextAnalyzer instance.
     *
     * @param AgentRegistry $registry The agent registry
     * @param array $config The configuration array
     */
    public function __construct(
        private AgentRegistry $registry,
        private array $config
    ) {}

    /**
     * Analyze user input and suggest the best agent for handling the request.
     *
     * @param string $userInput The user's input
     * @param string $currentAgentId The current agent ID
     * @return HandoffSuggestion|null The suggested handoff, or null if no handoff needed
     */
    public function analyzeAndSuggestHandoff(string $userInput, string $currentAgentId): ?HandoffSuggestion
    {
        $startTime = microtime(true);
        
        // Log analysis start
        Log::info('context_analysis_started', [
            'user_input' => $userInput,
            'current_agent' => $currentAgentId,
            'input_length' => strlen($userInput),
            'timestamp' => now()->toISOString(),
            'analysis_id' => uniqid('analysis_', true)
        ]);

        // Analyze user intent
        $intent = $this->analyzeUserIntent($userInput);
        $requiredCapabilities = $this->extractRequiredCapabilities($userInput);
        $bestAgent = $this->findBestAgentForIntent($intent, $requiredCapabilities);

        // Detect multiple domains (ambigüedad)
        $domains = [];
        if ($this->detectMathIntent(strtolower($userInput))) {
            $domains[] = 'mathematics';
        }
        if ($this->detectHistoryIntent(strtolower($userInput))) {
            $domains[] = 'history';
        }
        if ($this->detectTechnicalIntent(strtolower($userInput))) {
            $domains[] = 'technical_support';
        }
        if ($this->detectSalesIntent(strtolower($userInput))) {
            $domains[] = 'sales';
        }
        $domains = array_unique($domains);

        // Log intent analysis
        Log::info('intent_analysis_completed', [
            'primary_domain' => $intent['primary_domain'],
            'confidence' => $intent['confidence'],
            'detected_domains' => $domains,
            'required_capabilities' => $requiredCapabilities,
            'best_agent_found' => $bestAgent ? $bestAgent->getId() : null,
            'analysis_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);

        // Suggest handoff if there is ambiguity (more than one domain)
        if (count($domains) > 1) {
            $suggestion = new HandoffSuggestion(
                targetAgentId: 'general_agent',
                confidence: 0.4,
                reason: 'Pregunta ambigua o de múltiples dominios',
                priority: 2,
                requiredCapabilities: $requiredCapabilities
            );
            
            Log::info('handoff_suggestion_generated', [
                'suggestion_type' => 'ambiguous_domains',
                'target_agent' => $suggestion->targetAgentId,
                'confidence' => $suggestion->confidence,
                'reason' => $suggestion->reason,
                'detected_domains' => $domains,
                'analysis_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            return $suggestion;
        }

        // Suggest handoff if the domain is general or confidence is low
        if ($intent['primary_domain'] === 'general' || ($intent['confidence'] ?? 0) < 0.5) {
            $suggestion = new HandoffSuggestion(
                targetAgentId: 'general_agent',
                confidence: $intent['confidence'] ?? 0.3,
                reason: 'Pregunta general o baja confianza en el dominio',
                priority: 2,
                requiredCapabilities: $requiredCapabilities
            );
            
            Log::info('handoff_suggestion_generated', [
                'suggestion_type' => 'low_confidence',
                'target_agent' => $suggestion->targetAgentId,
                'confidence' => $suggestion->confidence,
                'reason' => $suggestion->reason,
                'primary_domain' => $intent['primary_domain'],
                'analysis_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            return $suggestion;
        }

        // Original logic: suggest handoff if the best agent is not the current one
        if ($bestAgent && $bestAgent->getId() !== $currentAgentId) {
            $suggestion = new HandoffSuggestion(
                targetAgentId: $bestAgent->getId(),
                confidence: $this->calculateConfidence($intent, $bestAgent),
                reason: $this->generateHandoffReason($intent, $bestAgent),
                priority: $this->calculatePriority($intent),
                requiredCapabilities: $requiredCapabilities
            );
            
            Log::info('handoff_suggestion_generated', [
                'suggestion_type' => 'optimal_agent',
                'target_agent' => $suggestion->targetAgentId,
                'confidence' => $suggestion->confidence,
                'reason' => $suggestion->reason,
                'priority' => $suggestion->priority,
                'analysis_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            return $suggestion;
        }
        
        // New logic: if we detect specific patterns, suggest handoff even with low confidence
        if ($this->hasSpecificPatterns($userInput)) {
            $targetAgent = $this->getAgentForSpecificPattern($userInput);
            if ($targetAgent && $targetAgent !== $currentAgentId) {
                $suggestion = new HandoffSuggestion(
                    targetAgentId: $targetAgent,
                    confidence: 0.6, // Moderate confidence for specific patterns
                    reason: 'Specific pattern detected',
                    priority: 2,
                    requiredCapabilities: $requiredCapabilities
                );
                
                Log::info('handoff_suggestion_generated', [
                    'suggestion_type' => 'specific_pattern',
                    'target_agent' => $suggestion->targetAgentId,
                    'confidence' => $suggestion->confidence,
                    'reason' => $suggestion->reason,
                    'analysis_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
                ]);
                
                return $suggestion;
            }
        }
        
        // No suggestion generated
        Log::info('no_handoff_suggestion', [
            'reason' => 'No suitable handoff found',
            'current_agent' => $currentAgentId,
            'analysis_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);
        
        return null;
    }

    /**
     * Analyze user intent from input.
     *
     * @param string $userInput The user's input
     * @return array The analyzed intent
     */
    private function analyzeUserIntent(string $userInput): array
    {
        $intent = [
            'primary_domain' => 'general',
            'confidence' => 0.0,
            'keywords' => [],
            'entities' => []
        ];

        $input = strtolower($userInput);

        // Math intent detection
        if ($this->detectMathIntent($input)) {
            $intent['primary_domain'] = 'mathematics';
            $intent['confidence'] = $this->calculateMathConfidence($input);
            $intent['keywords'] = $this->extractMathKeywords($input);
        }

        // History intent detection
        if ($this->detectHistoryIntent($input)) {
            $intent['primary_domain'] = 'history';
            $intent['confidence'] = $this->calculateHistoryConfidence($input);
            $intent['keywords'] = $this->extractHistoryKeywords($input);
        }

        // Technical support intent detection
        if ($this->detectTechnicalIntent($input)) {
            $intent['primary_domain'] = 'technical_support';
            $intent['confidence'] = $this->calculateTechnicalConfidence($input);
            $intent['keywords'] = $this->extractTechnicalKeywords($input);
        }

        // Sales intent detection
        if ($this->detectSalesIntent($input)) {
            $intent['primary_domain'] = 'sales';
            $intent['confidence'] = $this->calculateSalesConfidence($input);
            $intent['keywords'] = $this->extractSalesKeywords($input);
        }

        return $intent;
    }

    /**
     * Detect math-related intent.
     *
     * @param string $input The lowercase input
     * @return bool True if math intent is detected
     */
    private function detectMathIntent(string $input): bool
    {
        $mathPatterns = [
            '/\b(?:calculate|solve|compute|add|subtract|multiply|divide|sum|total|equation|formula|math|mathematical|number|digit|percentage|fraction|decimal|algebra|geometry|calculus|statistics)\b/',
            '/\b(?:what is|how much is|what\'s|how do i|can you help me with)\s+(?:the\s+)?(?:math|calculation|problem|equation)\b/',
            '/\b\d+\s*[\+\-\*\/]\s*\d+\b/', // Basic arithmetic
            '/\b(?:plus|minus|times|divided by)\b/',
            '/\b(?:square root|power|exponent|logarithm)\b/',
            '/\b(?:derivative|integral|limit|function|variable)\b/',
            '/\b(?:calculate|solve|compute)\b/',
            '/\b(?:what is|how much is)\s+\d+\s*[\+\-\*\/]\s*\d+\b/',
            '/\b(?:calculate|solve)\s+(?:the\s+)?(?:derivative|integral)\b/'
        ];

        foreach ($mathPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect history-related intent.
     *
     * @param string $input The lowercase input
     * @return bool True if history intent is detected
     */
    private function detectHistoryIntent(string $input): bool
    {
        $historyPatterns = [
            '/\b(?:history|historical|ancient|medieval|renaissance|modern|war|battle|empire|kingdom|dynasty|civilization|archaeology|artifact|monument|temple|pyramid|castle|palace)\b/',
            '/\b(?:who was|what happened|when did|tell me about|explain the history of)\b/',
            '/\b(?:world war|civil war|revolution|independence|conquest|exploration)\b/',
            '/\b(?:egypt|rome|greece|china|india|mesopotamia|babylon|persia|ottoman|mongol|aztec|maya|inca)\b/',
            '/\b(?:pharaoh|emperor|king|queen|president|prime minister|dictator|general|admiral)\b/',
            '/\b(?:napoleon|caesar|alexander|cleopatra|queen victoria|king henry)\b/',
            '/\b(?:who was|tell me about)\s+(?:napoleon|caesar|alexander|cleopatra)\b/',
            '/\b(?:ancient|medieval|renaissance|modern)\s+(?:times|period|era)\b/'
        ];

        foreach ($historyPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect technical support intent.
     *
     * @param string $input The lowercase input
     * @return bool True if technical intent is detected
     */
    private function detectTechnicalIntent(string $input): bool
    {
        $technicalPatterns = [
            '/\b(?:technical|support|help|troubleshoot|error|bug|issue|problem|fix|repair|install|configure|setup|compatibility|driver|software|hardware|system|network|connection)\b/',
            '/\b(?:computer|laptop|desktop|server|router|printer|scanner|device|peripheral)\b/',
            '/\b(?:windows|mac|linux|android|ios|program|application|app|website|database)\b/',
            '/\b(?:doesn\'t work|not working|broken|crashed|frozen|slow|error message|blue screen)\b/'
        ];

        foreach ($technicalPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect sales intent.
     *
     * @param string $input The lowercase input
     * @return bool True if sales intent is detected
     */
    private function detectSalesIntent(string $input): bool
    {
        $salesPatterns = [
            '/\b(?:buy|purchase|order|price|cost|discount|sale|deal|offer|promotion|coupon|quote|estimate|invoice|payment|billing|subscription)\b/',
            '/\b(?:product|service|item|package|plan|tier|premium|basic|standard|enterprise)\b/',
            '/\b(?:how much|what\'s the price|cost|pricing|rates|fees|charges)\b/',
            '/\b(?:sales|salesperson|representative|account manager|customer service)\b/'
        ];

        foreach ($salesPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate confidence for math intent.
     *
     * @param string $input The lowercase input
     * @return float Confidence score (0.0 to 1.0)
     */
    private function calculateMathConfidence(string $input): float
    {
        $confidence = 0.0;
        
        // Basic math operations
        if (preg_match('/\d+\s*[\+\-\*\/]\s*\d+/', $input)) {
            $confidence += 0.8;
        }
        
        // Math keywords
        $mathKeywords = ['calculate', 'solve', 'compute', 'equation', 'formula', 'math'];
        foreach ($mathKeywords as $keyword) {
            if (str_contains($input, $keyword)) {
                $confidence += 0.3;
            }
        }
        
        return min(1.0, $confidence);
    }

    /**
     * Calculate confidence for history intent.
     *
     * @param string $input The lowercase input
     * @return float Confidence score (0.0 to 1.0)
     */
    private function calculateHistoryConfidence(string $input): float
    {
        $confidence = 0.0;
        
        // History keywords
        $historyKeywords = ['history', 'historical', 'ancient', 'war', 'battle', 'empire'];
        foreach ($historyKeywords as $keyword) {
            if (str_contains($input, $keyword)) {
                $confidence += 0.4;
            }
        }
        
        // Historical figures/events
        $historicalEntities = ['egypt', 'rome', 'greece', 'world war', 'civil war'];
        foreach ($historicalEntities as $entity) {
            if (str_contains($input, $entity)) {
                $confidence += 0.5;
            }
        }
        
        return min(1.0, $confidence);
    }

    /**
     * Calculate confidence for technical intent.
     *
     * @param string $input The lowercase input
     * @return float Confidence score (0.0 to 1.0)
     */
    private function calculateTechnicalConfidence(string $input): float
    {
        $confidence = 0.0;
        
        // Technical keywords
        $technicalKeywords = ['technical', 'support', 'help', 'troubleshoot', 'error', 'bug'];
        foreach ($technicalKeywords as $keyword) {
            if (str_contains($input, $keyword)) {
                $confidence += 0.3;
            }
        }
        
        // Technical entities
        $technicalEntities = ['computer', 'software', 'hardware', 'system', 'network'];
        foreach ($technicalEntities as $entity) {
            if (str_contains($input, $entity)) {
                $confidence += 0.4;
            }
        }
        
        return min(1.0, $confidence);
    }

    /**
     * Calculate confidence for sales intent.
     *
     * @param string $input The lowercase input
     * @return float Confidence score (0.0 to 1.0)
     */
    private function calculateSalesConfidence(string $input): float
    {
        $confidence = 0.0;
        
        // Sales keywords
        $salesKeywords = ['buy', 'purchase', 'price', 'cost', 'discount', 'sale'];
        foreach ($salesKeywords as $keyword) {
            if (str_contains($input, $keyword)) {
                $confidence += 0.3;
            }
        }
        
        // Sales entities
        $salesEntities = ['product', 'service', 'order', 'payment', 'billing'];
        foreach ($salesEntities as $entity) {
            if (str_contains($input, $entity)) {
                $confidence += 0.4;
            }
        }
        
        return min(1.0, $confidence);
    }

    /**
     * Extract math-related keywords.
     *
     * @param string $input The lowercase input
     * @return array Array of math keywords
     */
    private function extractMathKeywords(string $input): array
    {
        $keywords = [];
        $mathTerms = ['calculate', 'solve', 'compute', 'add', 'subtract', 'multiply', 'divide', 'equation', 'formula'];
        
        foreach ($mathTerms as $term) {
            if (str_contains($input, $term)) {
                $keywords[] = $term;
            }
        }
        
        return $keywords;
    }

    /**
     * Extract history-related keywords.
     *
     * @param string $input The lowercase input
     * @return array Array of history keywords
     */
    private function extractHistoryKeywords(string $input): array
    {
        $keywords = [];
        $historyTerms = ['history', 'historical', 'ancient', 'war', 'battle', 'empire', 'kingdom'];
        
        foreach ($historyTerms as $term) {
            if (str_contains($input, $term)) {
                $keywords[] = $term;
            }
        }
        
        return $keywords;
    }

    /**
     * Extract technical keywords.
     *
     * @param string $input The lowercase input
     * @return array Array of technical keywords
     */
    private function extractTechnicalKeywords(string $input): array
    {
        $keywords = [];
        $technicalTerms = ['technical', 'support', 'help', 'troubleshoot', 'error', 'bug', 'problem'];
        
        foreach ($technicalTerms as $term) {
            if (str_contains($input, $term)) {
                $keywords[] = $term;
            }
        }
        
        return $keywords;
    }

    /**
     * Extract sales keywords.
     *
     * @param string $input The lowercase input
     * @return array Array of sales keywords
     */
    private function extractSalesKeywords(string $input): array
    {
        $keywords = [];
        $salesTerms = ['buy', 'purchase', 'price', 'cost', 'discount', 'sale', 'order'];
        
        foreach ($salesTerms as $term) {
            if (str_contains($input, $term)) {
                $keywords[] = $term;
            }
        }
        
        return $keywords;
    }

    /**
     * Extract required capabilities from user input.
     *
     * @param string $userInput The user's input
     * @return array Array of required capabilities
     */
    private function extractRequiredCapabilities(string $userInput): array
    {
        $capabilities = [];
        $input = strtolower($userInput);

        // Map domains to capabilities
        $domainCapabilities = [
            'mathematics' => ['mathematics', 'calculations', 'problem_solving'],
            'history' => ['history', 'historical_research', 'timeline'],
            'technical_support' => ['troubleshooting', 'installation_help', 'bug_reporting'],
            'sales' => ['product_information', 'pricing', 'discounts']
        ];

        if ($this->detectMathIntent($input)) {
            $capabilities = array_merge($capabilities, $domainCapabilities['mathematics']);
        }

        if ($this->detectHistoryIntent($input)) {
            $capabilities = array_merge($capabilities, $domainCapabilities['history']);
        }

        if ($this->detectTechnicalIntent($input)) {
            $capabilities = array_merge($capabilities, $domainCapabilities['technical_support']);
        }

        if ($this->detectSalesIntent($input)) {
            $capabilities = array_merge($capabilities, $domainCapabilities['sales']);
        }

        return array_unique($capabilities);
    }

    /**
     * Find the best agent for the given intent and capabilities.
     *
     * @param array $intent The analyzed intent
     * @param array $requiredCapabilities The required capabilities
     * @return \Sapiensly\OpenaiAgents\Agent|null The best agent, or null if none found
     */
    private function findBestAgentForIntent(array $intent, array $requiredCapabilities): ?\Sapiensly\OpenaiAgents\Agent
    {
        if (empty($requiredCapabilities)) {
            return null;
        }

        // Find agents with required capabilities
        $candidates = $this->registry->findAgentsByCapabilities($requiredCapabilities);

        if (empty($candidates)) {
            return null;
        }

        // Score candidates based on intent match
        $scoredCandidates = [];
        foreach ($candidates as $agent) {
            $score = $this->calculateAgentScore($agent, $intent);
            $scoredCandidates[] = ['agent' => $agent, 'score' => $score];
        }

        // Sort by score (highest first)
        usort($scoredCandidates, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scoredCandidates[0]['agent'] ?? null;
    }

    /**
     * Calculate a score for an agent based on intent match.
     *
     * @param \Sapiensly\OpenaiAgents\Agent $agent The agent to score
     * @param array $intent The analyzed intent
     * @return float The agent score
     */
    private function calculateAgentScore(\Sapiensly\OpenaiAgents\Agent $agent, array $intent): float
    {
        $score = 0.0;
        $agentId = $agent->getId();

        // Base score from intent confidence
        $score += $intent['confidence'];

        // Bonus for domain-specific agents
        $domainAgentMap = [
            'mathematics' => 'math_agent',
            'history' => 'history_agent',
            'technical_support' => 'technical_support',
            'sales' => 'sales_agent'
        ];

        if (isset($domainAgentMap[$intent['primary_domain']]) && 
            $agentId === $domainAgentMap[$intent['primary_domain']]) {
            $score += 0.5;
        }

        return $score;
    }

    /**
     * Calculate confidence for the handoff suggestion.
     *
     * @param array $intent The analyzed intent
     * @param \Sapiensly\OpenaiAgents\Agent $agent The target agent
     * @return float Confidence score (0.0 to 1.0)
     */
    private function calculateConfidence(array $intent, \Sapiensly\OpenaiAgents\Agent $agent): float
    {
        return min(1.0, $intent['confidence'] + 0.2); // Add small bonus for agent match
    }

    /**
     * Generate a reason for the handoff.
     *
     * @param array $intent The analyzed intent
     * @param \Sapiensly\OpenaiAgents\Agent $agent The target agent
     * @return string The handoff reason
     */
    private function generateHandoffReason(array $intent, \Sapiensly\OpenaiAgents\Agent $agent): string
    {
        $domain = $intent['primary_domain'];
        $agentId = $agent->getId();

        return "User intent detected as '{$domain}', transferring to specialized agent '{$agentId}'";
    }

    /**
     * Calculate priority for the handoff.
     *
     * @param array $intent The analyzed intent
     * @return int The priority (higher values = higher priority)
     */
    private function calculatePriority(array $intent): int
    {
        $priority = 1;

        // Higher priority for high confidence
        if ($intent['confidence'] > 0.8) {
            $priority = 3;
        } elseif ($intent['confidence'] > 0.6) {
            $priority = 2;
        }

        // Higher priority for specific domains
        if (in_array($intent['primary_domain'], ['mathematics', 'history'])) {
            $priority += 1;
        }

        return $priority;
    }

    /**
     * Check if the input has specific patterns that should trigger handoff.
     *
     * @param string $userInput The user input
     * @return bool True if specific patterns are found
     */
    private function hasSpecificPatterns(string $userInput): bool
    {
        $input = strtolower($userInput);
        
        // Patrones específicos que deberían activar handoff
        $specificPatterns = [
            '/\b(?:calculate|solve|compute)\b/',
            '/\b(?:derivative|integral|limit)\b/',
            '/\b(?:who was|tell me about)\s+(?:napoleon|caesar|alexander)\b/',
            '/\b(?:world war|civil war)\b/',
            '/\b(?:egypt|rome|greece)\b/'
        ];
        
        foreach ($specificPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the target agent for specific patterns.
     *
     * @param string $userInput The user input
     * @return string|null The target agent ID or null if no match
     */
    private function getAgentForSpecificPattern(string $userInput): ?string
    {
        $input = strtolower($userInput);
        
        // Patrones matemáticos
        if (preg_match('/\b(?:calculate|solve|compute|derivative|integral|limit)\b/', $input)) {
            return 'math_agent';
        }
        
        // Patrones históricos
        if (preg_match('/\b(?:who was|tell me about|napoleon|caesar|alexander|world war|civil war|egypt|rome|greece)\b/', $input)) {
            return 'history_agent';
        }
        
        return null;
    }
} 