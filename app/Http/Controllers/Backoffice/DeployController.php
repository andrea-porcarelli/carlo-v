<?php

namespace App\Http\Controllers\Backoffice;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployController extends BaseController
{
    /**
     * Hardcoded deploy key — keep this secret.
     * Change it if it is ever exposed.
     */
    private const DEPLOY_KEY = 'cv-deploy-7Xm2pN9qR4wL8jE3tK';

    /**
     * GET|POST /webhook/deploy?key=<key>
     *
     * Triggers a "git pull" in the application root.
     * Key can be passed as:
     *   - Query parameter: ?key=<key>
     *   - Header:          X-Deploy-Key: <key>
     *
     * If the setting "deploy_git_user" is configured, the command runs as:
     *   sudo -u <deploy_git_user> -n git pull
     * (requires a matching passwordless sudoers rule on the server — see below)
     */
    public function trigger(Request $request): JsonResponse
    {
        // ── Key validation (constant-time comparison) ──────────────────────────
        $provided = $request->query('key') ?? $request->header('X-Deploy-Key', '');

        if (!hash_equals(self::DEPLOY_KEY, (string) $provided)) {
            Log::warning('Deploy webhook: invalid key attempt', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $projectPath = base_path();

            // Array per catturare l'output del comando
            $output = [];
            $returnVar = 0;

            // Esegui git pull nella directory del progetto
            exec("git -C " . escapeshellarg($projectPath) . " -c safe.directory=" . escapeshellarg($projectPath) . " pull 2>&1", $output, $returnVar);

            $result = implode("\n", $output);

            Log::info("Git pull executed", [
                'output' => $result,
                'return_code' => $returnVar
            ]);

            if ($returnVar === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Git pull completato con successo',
                    'output' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore durante git pull',
                    'output' => $result
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Git pull error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage()
            ], 500);
        }
    }
}
