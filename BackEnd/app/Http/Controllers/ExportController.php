<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use ZipArchive;
 
class ExportController extends Controller
{
    public function exportQuizAsZip(Request $request)
    {
        try {
            \Log::info('📦 [Export] Starting SCORM export');
 
            $validated = $request->validate([
                'course'     => 'required|string',
                'topic'      => 'required|string',
                'gameNumber' => 'required|integer',
                'numLevels'  => 'required|integer',
                'levels'     => 'required|array',
            ]);
 
            $quizData    = $validated;
            $scormTitle  = "{$quizData['course']} - {$quizData['topic']}";
            $timestamp   = now()->format('Y-m-d_H-i-s');
            $zipFilename = "scorm_quiz_{$timestamp}.zip";
            $zipPath     = storage_path("app/temp/{$zipFilename}");
 
            $gamePath = storage_path("app/games/game_{$quizData['gameNumber']}");
 
            // If exact game folder doesn't exist, fall back to first available
            if (!file_exists($gamePath)) {
                $available = glob(storage_path('app/games/game_*'), GLOB_ONLYDIR);
                if (empty($available)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Aucun dossier de jeu disponible.'
                    ], 404);
                }
                $gamePath = $available[0];
            }

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
 
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create ZIP'
                ], 500);
            }
 
            // ── imsmanifest.xml ──────────────────────────────────────────────
            $zip->addFromString('imsmanifest.xml', $this->getManifest($scormTitle));

            // ── scorm.js — use the real file from disk if present ────────────
            $scormJsPath = "{$gamePath}/scorm.js";
            if (file_exists($scormJsPath)) {
                $zip->addFile($scormJsPath, 'scorm.js');
            } else {
                $zip->addFromString('scorm.js', $this->getScormJs());
            }

            // ── index.html — use as-is (already has <script src="scorm.js">) ─
            $indexHtmlPath = "{$gamePath}/index.html";
            if (!file_exists($indexHtmlPath)) {
                $zip->close();
                return response()->json([
                    'success' => false,
                    'message' => 'index.html introuvable dans le dossier du jeu'
                ], 404);
            }
            $zip->addFile($indexHtmlPath, 'index.html');
 
            // ── Godot game files ─────────────────────────────────────────────
            $gameFiles = [
                'index.js',
                'index.wasm',
                'index.pck',
                'index.png',
                'index.audio.worklet.js',
                'index.apple-touch-icon.png',
                'index.icon.png',
            ];
 
            foreach ($gameFiles as $filename) {
                $filePath = "{$gamePath}/{$filename}";
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $filename);
                } else {
                    \Log::warning("Fichier Godot manquant : {$filename}");
                }
            }
 
            // ── data/levels_data.json — generated from quiz data ─────────────
            $zip->addFromString('data/levels_data.json', json_encode([
                'levels'      => $quizData['levels'],
                'player_info' => [
                    'current_level' => 1,
                    'lives'         => 3,
                    'score'         => 0
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
 
            $zip->close();
 
            \Log::info("✅ [Export] SCORM package ready: {$zipFilename}");
 
            // Encode as base64 and return JSON
            $zipContent = file_get_contents($zipPath);
            @unlink($zipPath);
 
            return response()->json([
                'success'  => true,
                'filename' => $zipFilename,
                'data'     => base64_encode($zipContent),
            ]);
 
        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export quiz: ' . $e->getMessage()
            ], 500);
        }
    }
 
    /**
     * Fallback scorm.js if not present on disk — full debug version.
     */
    private function getScormJs()
    {
        return <<<'JS'
// ─── SCORM DEBUG BRIDGE ───────────────────────────────────────────────────────
function scorm_get_api() {
    if (window.API)                              { return window.API; }
    if (window.parent && window.parent.API)      { return window.parent.API; }
    if (window.top && window.top.API)            { return window.top.API; }
    var win = window;
    var attempts = 0;
    while (win.parent && win.parent !== win && attempts < 10) {
        win = win.parent;
        if (win.API) { return win.API; }
        attempts++;
    }
    return null;
}

function scorm_initialize() {
    var api = scorm_get_api();
    if (!api) { return false; }
    var result = api.LMSInitialize("");
    return result === "true" || result === true;
}

function scorm_set_score(score, min, max) {
    var api = scorm_get_api();
    if (!api) { return; }
    api.LMSSetValue("cmi.core.score.raw", String(score));
    api.LMSSetValue("cmi.core.score.min", String(min));
    api.LMSSetValue("cmi.core.score.max", String(max));
    api.LMSCommit("");
}

function scorm_set_completion(passed) {
    var api = scorm_get_api();
    if (!api) { return; }
    api.LMSSetValue("cmi.core.lesson_status", passed ? "passed" : "failed");
    api.LMSCommit("");
}

function scorm_finish() {
    var api = scorm_get_api();
    if (!api) { return; }
    api.LMSFinish("");
}

window.addEventListener("load", function () {
    setTimeout(function () { scorm_initialize(); }, 500);
});
JS;
    }
 
    private function getManifest($title)
    {
        $id  = 'quiz_' . md5($title);
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<manifest identifier="' . $id . '" version="1.0"' . "\n";
        $xml .= '    xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"' . "\n";
        $xml .= '    xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"' . "\n";
        $xml .= '    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n";
        $xml .= '    <metadata>' . "\n";
        $xml .= '        <schema>ADL SCORM</schema>' . "\n";
        $xml .= '        <schemaversion>1.2</schemaversion>' . "\n";
        $xml .= '    </metadata>' . "\n";
        $xml .= '    <organizations default="org1">' . "\n";
        $xml .= '        <organization identifier="org1">' . "\n";
        $xml .= '            <title>' . htmlspecialchars($title) . '</title>' . "\n";
        $xml .= '            <item identifier="item1" identifierref="resource1">' . "\n";
        $xml .= '                <title>' . htmlspecialchars($title) . '</title>' . "\n";
        $xml .= '            </item>' . "\n";
        $xml .= '        </organization>' . "\n";
        $xml .= '    </organizations>' . "\n";
        $xml .= '    <resources>' . "\n";
        $xml .= '        <resource identifier="resource1"' . "\n";
        $xml .= '            type="webcontent"' . "\n";
        $xml .= '            adlcp:scormtype="sco"' . "\n";
        $xml .= '            href="index.html">' . "\n";
        $xml .= '            <file href="index.html"/>' . "\n";
        $xml .= '            <file href="scorm.js"/>' . "\n";
        $xml .= '            <file href="index.js"/>' . "\n";
        $xml .= '            <file href="index.wasm"/>' . "\n";
        $xml .= '            <file href="index.pck"/>' . "\n";
        $xml .= '            <file href="index.png"/>' . "\n";
        $xml .= '            <file href="index.audio.worklet.js"/>' . "\n";
        $xml .= '            <file href="index.apple-touch-icon.png"/>' . "\n";
        $xml .= '            <file href="index.icon.png"/>' . "\n";
        $xml .= '            <file href="data/levels_data.json"/>' . "\n";
        $xml .= '        </resource>' . "\n";
        $xml .= '    </resources>' . "\n";
        $xml .= '</manifest>' . "\n";
        return $xml;
    }
}