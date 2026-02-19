<?php

namespace App\Http\Controllers\Backoffice;

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
     * POST /webhook/deploy
     *
     * Triggers a "git pull" in the application root.
     * The caller must pass the deploy key either:
     *   - Header:          X-Deploy-Key: <key>
     *   - Query parameter: ?key=<key>
     *
     * Example (curl):
     *   curl -X POST https://<host>/webhook/deploy \
     *        -H "X-Deploy-Key: cv-deploy-7Xm2pN9qR4wL8jE3tK"
     */
    public function trigger(Request $request): JsonResponse
    {
        // ── Key validation (constant-time comparison) ──────────────────────────
        $provided = $request->header('X-Deploy-Key') ?? $request->query('key', '');

        if (!hash_equals(self::DEPLOY_KEY, (string) $provided)) {
            Log::warning('Deploy webhook: invalid key attempt', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // ── Run git pull ───────────────────────────────────────────────────────
        $repoPath = base_path();

        $command = sprintf(
            'git -C %s pull 2>&1',
            escapeshellarg($repoPath)
        );

        exec($command, $outputLines, $exitCode);

        $output = implode("\n", $outputLines);

        Log::info('Deploy webhook: git pull executed', [
            'exit_code' => $exitCode,
            'output'    => $output,
        ]);

        return response()->json([
            'success'   => $exitCode === 0,
            'exit_code' => $exitCode,
            'output'    => $output,
        ], $exitCode === 0 ? 200 : 500);
    }
}
