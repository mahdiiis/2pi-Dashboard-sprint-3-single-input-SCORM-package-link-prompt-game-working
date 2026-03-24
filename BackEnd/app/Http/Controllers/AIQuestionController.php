<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Exception;

class AIQuestionController extends Controller
{
    public function generateQuestions(Request $request)
    {
        \Log::info('🚀 [AIQuestion] Bulk generation request received');
        \Log::info('📋 Request data (meta):', [
            'course'      => $request->input('course'),
            'topic'       => $request->input('topic'),
            'gameNumber'  => $request->input('gameNumber'),
            'numLevels'   => $request->input('numLevels'),
            'level_types' => $request->input('level_types'),
            'ai_prompt'   => $request->input('ai_prompt'),
            'source_text_length' => strlen($request->input('source_text', '')),
        ]);
        // Log the actual source text so we can verify PDF/URL content was received
        if ($request->input('source_text')) {
            \Log::info('📄 [AIQuestion] SOURCE TEXT RECEIVED (first 2000 chars):\n' . substr($request->input('source_text'), 0, 2000));
        } else {
            \Log::info('⚠️ [AIQuestion] No source_text provided — AI will use prompt only.');
        }

        $validated = $request->validate([
            'course'        => 'required|string|max:100',
            'topic'         => 'required|string|max:100',
            'gameNumber'    => 'required|integer|min:1',
            'numLevels'     => 'required|integer|min:1|max:6',
            'level_types'   => 'required|array',
            'level_types.*' => 'required|in:box,balloon',
            'ai_prompt'     => 'required|string|max:1000',
            'source_text'   => 'nullable|string|max:16000', // Optional extracted text from file/URL(s)
        ]);

        if (count($validated['level_types']) !== $validated['numLevels']) {
            return response()->json([
                'success' => false,
                'message' => 'Le nombre de types doit correspondre au nombre de niveaux'
            ], 422);
        }

        $numLevels = $validated['numLevels'];
        $levelTypes = $validated['level_types'];

        $systemPrompt = "Tu es un professeur de mathématiques expert du système scolaire MAROCAIN pour l'école primaire. Langage simple pour enfants 8-12 ans. Exemples concrets du quotidien marocain. TU RÉPONDS TOUJOURS EN JSON VALIDE UNIQUEMENT.";

        $levelsDescription = "";
        for ($i = 0; $i < $numLevels; $i++) {
            $levelNum = $i + 1;
            $type = $levelTypes[$i];

            if ($type === 'box') {
                $levelsDescription .= "- Niveau {$levelNum} : TYPE BOX → EXACTEMENT 5 questions avec UNE réponse courte chacune\n";
            } else {
                $levelsDescription .= "- Niveau {$levelNum} : TYPE BALLOON → 1 question avec EXACTEMENT 10 réponses (vrai/faux)\n";
            }
        }

       // Build the source text injection (if provided)
        $sourceTextSection = '';
        if (!empty($validated['source_text'])) {
            $sourceTextSection = "
=== TEXTES SOURCE REÇUS ===
{$validated['source_text']}
=== FIN DES TEXTES SOURCE ===

🚀 DIRECTIVES POUR L'UTILISATION DES SOURCES :

1. ANALYSE DES SOURCES
   - Les sources sont séparées par des indicateurs (ex: === SOURCE 1).
   - Identifie le sujet et le contenu éducatif de chaque source.

2. RÉPARTITION ÉQUILIBRÉE (TRÈS IMPORTANT)
   - Si tu reçois PLUSIEURS sources, tes questions DOIVENT impérativement utiliser les informations de TOUTES les sources.
   - Sélectionne des concepts variés venant de chaque document pour que le quiz reflète l'ensemble des sources fournies.

3. PRIORITÉ AUX INSTRUCTIONS DU PROFESSEUR
   - Si le professeur demande explicitement d'utiliser une source par niveau (ex: \"Niveau 1 = URL, Niveau 2 = PDF\"), applique cette règle de répartition spécifique.

4. RÉDACTION DES QUESTIONS
   - Écris les questions naturellement sur le sujet (ex: \"Comment calcule-t-on l'aire d'un triangle ?\").
   - N'ajoute AUCUN label indiquant l'origine de la question (PAS de mentions type \"(Source 1)\", \"Selon le PDF\", etc.).
   - Concentre-toi sur le savoir, ignore les métadonnées (titres de pages web, numéros de page, interface utilisateur).

5. SOURCES NON ÉDUCATIVES (TRÈS IMPORTANT)
   - Si une source ne contient AUCUN contenu éducatif (ex: juste \"Partagez vos vidéos avec vos amis\", des liens, du spam, ou aucune information pertinente sur le sujet), IGNORE son contenu.
   - Si TOUTES les sources sont non-éducatives, génère simplement des questions de qualité basées sur tes propres connaissances du COURS et du SUJET.
";
        }

       $userPrompt = "
Le professeur demande : \"{$validated['ai_prompt']}\"

COURS : {$validated['course']}
SUJET : {$validated['topic']}

{$sourceTextSection}

NOTE : Si l'instruction du professeur spécifie \"level [N] from [SOURCE]\", respectez STRICTEMENT cette directive.

GÉNÈRE EXACTEMENT {$numLevels} NIVEAUX :
{$levelsDescription}

DIFFICULTÉ PROGRESSIVE :
- Niveau 1 : facile
- Niveau {$numLevels} : difficile

IMPORTANT : Tu DOIS répondre avec UNIQUEMENT ce JSON, rien d'autre. Pas de texte avant, pas de texte après. Juste le JSON.

Voici le format JSON EXACT que tu dois respecter (exemple pour 2 niveaux box et balloon) :

{
    \"levels\": [
        {
            \"level_number\": 1,
            \"level_type\": \"box\",
            \"level_stats\": {
                \"coins\": 0,
                \"lifes\": 5,
                \"mistakes\": 0,
                \"stars\": 1,
                \"time_spent\": 0
            },
            \"questions\": [
                {
                    \"text\": \"Combien font 2 + 2 ?\",
                    \"answer\": \"4\"
                },
                {
                    \"text\": \"5 × 3 = ?\",
                    \"answer\": \"15\"
                },
                {
                    \"text\": \"10 ÷ 2 = ?\",
                    \"answer\": \"5\"
                },
                {
                    \"text\": \"20 - 5 = ?\",
                    \"answer\": \"15\"
                },
                {
                    \"text\": \"7 + 8 = ?\",
                    \"answer\": \"15\"
                }
            ]
        },
        {
            \"level_number\": 2,
            \"level_type\": \"balloon\",
            \"level_stats\": {
                \"coins\": 0,
                \"lifes\": 5,
                \"mistakes\": 0,
                \"stars\": 1,
                \"time_spent\": 0
            },
            \"question\": \"Quelle fraction représente la moitié ?\",
            \"answers\": [
                { \"text\": \"1/2\", \"is_true\": true },
                { \"text\": \"1/3\", \"is_true\": false },
                { \"text\": \"2/4\", \"is_true\": true },
                { \"text\": \"1/5\", \"is_true\": false },
                { \"text\": \"3/6\", \"is_true\": true },
                { \"text\": \"1/4\", \"is_true\": false },
                { \"text\": \"5/10\", \"is_true\": true },
                { \"text\": \"1/6\", \"is_true\": false },
                { \"text\": \"4/8\", \"is_true\": true },
                { \"text\": \"1/8\", \"is_true\": false }
            ]
        }
    ]
}

Génère maintenant les {$numLevels} niveaux demandés en suivant EXACTEMENT ce format.
        ";

        // ── Log the FULL assembled prompt sent to the AI ──────────────────────
        \Log::info("📝 [AIQuestion] FULL SYSTEM PROMPT:\n" . $systemPrompt);
        \Log::info("📝 [AIQuestion] FULL USER PROMPT SENT TO AI:\n" . $userPrompt);

        try {
            $apiKey = config('services.groq.api_key');

            if (!$apiKey) {
                \Log::warning('⚠️ GROQ_API_KEY is missing. Using fallback mock data.');
                return $this->getMockBulkData($validated);
            }

            \Log::info('🌐 [AIQuestion] Calling Groq API matching user provided script...');

            $modelsToTry = [
                'llama-3.1-8b-instant',
                'llama-3.3-70b-versatile',
                'mixtral-8x7b-32768'
            ];

            $response = null;
            $usedModel = '';

            foreach ($modelsToTry as $model) {
                \Log::info("🔄 Trying Groq API with model: {$model}");

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => $model,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt]
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 3000,
                ]);

                if ($response->successful()) {
                    $usedModel = $model;
                    break;
                } else {
                    $status = $response->status();
                    $body = $response->body();
                    \Log::warning("⚠️ Groq Model {$model} failed. Status: {$status}. Body: {$body}");
                }
            }

            if (!$response || !$response->successful()) {
                throw new \Exception('All Groq models failed or rate-limited.');
            }

            $result  = $response->json();
            $content = $result['choices'][0]['message']['content'];

            \Log::info("RAW AI CONTENT ($usedModel):\n" . $content);

            $content = preg_replace('/```json\s*|\s*```/', '', trim($content));
            $aiData = json_decode($content, true);

            if (!$aiData || !isset($aiData['levels'])) {
                \Log::error("❌ Invalid JSON received: " . $content);
                return response()->json([
                    'success' => false,
                    'message' => 'Format JSON invalide reçu de l\'IA. Réessaie.'
                ], 500);
            }

            if (count($aiData['levels']) !== $numLevels) {
                \Log::warning("⚠️ L'IA a généré " . count($aiData['levels']) . " niveaux au lieu de {$numLevels}.");
                return response()->json([
                    'success' => false,
                    'message' => "L'IA a généré " . count($aiData['levels']) . " niveaux au lieu de {$numLevels}."
                ], 500);
            }

            // Ajustement strict backend : forcer le respect de 5 questions (box) et 10 réponses (balloon)
            foreach ($aiData['levels'] as &$level) {
                if ($level['level_type'] === 'box' && isset($level['questions']) && is_array($level['questions'])) {
                    $level['questions'] = array_slice($level['questions'], 0, 5);
                } elseif ($level['level_type'] === 'balloon' && isset($level['answers']) && is_array($level['answers'])) {
                    $level['answers'] = array_slice($level['answers'], 0, 10);
                }
            }
            unset($level);

            \Log::info("✅ [AIQuestion] Successfully generated all levels.");

            return response()->json([
                'success' => true,
                'data'    => [
                    'course'      => $validated['course'],
                    'topic'       => $validated['topic'],
                    'gameNumber'  => $validated['gameNumber'],
                    'numLevels'   => $validated['numLevels'],
                    'levels'      => $aiData['levels'],
                    'player_info' => [
                        'current_level' => 1,
                        'lives'         => 3,
                        'score'         => 0
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('❌ [AIQuestion] Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage()
            ], 500);
        }
    }

    private function getMockBulkData(array $validated): \Illuminate\Http\JsonResponse
    {
        $promptSnippet = substr($validated['ai_prompt'] ?? '', 0, 50);
        \Log::info("🔧 [AIQuestion] using mock data with prompt snippet: {$promptSnippet}");

        $levels = [];
        for ($i = 0; $i < $validated['numLevels']; $i++) {
            $type = $validated['level_types'][$i];
            if ($type === 'box') {
                // create questions that incorporate the prompt snippet
                $questions = [];
                for ($q = 1; $q <= 5; $q++) {
                    $questions[] = [
                        'text' => "MOCK Q{$q} about '{$promptSnippet}'",
                        'answer' => (string)($q * 2),
                    ];
                }
            } else {
                $questions = null;
            }

            $balloonQuestion = $type === 'balloon' ? "MOCK balloon question about '{$promptSnippet}'" : null;
            $balloonAnswers = $type === 'balloon' ? [
                ['text' => 'Option A', 'is_true' => true],
                ['text' => 'Option B', 'is_true' => false],
                ['text' => 'Option C', 'is_true' => false],
                ['text' => 'Option D', 'is_true' => false],
                ['text' => 'Option E', 'is_true' => false],
                ['text' => 'Option F', 'is_true' => false],
                ['text' => 'Option G', 'is_true' => false],
                ['text' => 'Option H', 'is_true' => false],
                ['text' => 'Option I', 'is_true' => false],
                ['text' => 'Option J', 'is_true' => false],
            ] : null;

            $levels[] = [
                'level_number' => $i + 1,
                'level_type' => $type,
                'level_stats' => [
                    'coins' => 0,
                    'lifes' => 5,
                    'mistakes' => 0,
                    'stars' => 1,
                    'time_spent' => 0
                ],
                'questions' => $questions,
                'question' => $balloonQuestion,
                'answers' => $balloonAnswers,
            ];

            // Cleanup nulls so json is clean
            $levels[$i] = array_filter($levels[$i], function($value) { return $value !== null; });
        }

        return response()->json([
            'success' => true,
            'message' => 'Mocked data (No API Key)',
            'data' => [
                'course' => $validated['course'],
                'topic' => $validated['topic'],
                'gameNumber' => $validated['gameNumber'],
                'numLevels' => $validated['numLevels'],
                'levels' => $levels,
                'player_info' => [
                    'current_level' => 1,
                    'lives' => 3,
                    'score' => 0
                ]
            ]
        ], 200);
    }
}
