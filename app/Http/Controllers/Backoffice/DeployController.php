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

        // ── Build git pull command ─────────────────────────────────────────────
        $repoPath  = base_path();
        $gitUser   = (string) Setting::get('deploy_git_user', '');

        /*
         * If deploy_git_user is set, run via sudo so the correct user
         * (the one who owns the .git directory) executes the pull.
         *
         * Required sudoers rule on the server (run "sudo visudo"):
         *   www-data ALL=(<deploy_git_user>) NOPASSWD: /usr/bin/git
         */
        if ($gitUser !== '') {
            $command = sprintf(
                'sudo -u %s -n git -C %s -c safe.directory=%s pull 2>&1',
                escapeshellarg($gitUser),
                escapeshellarg($repoPath),
                escapeshellarg($repoPath)
            );
        } else {
            $command = sprintf(
                'git -C %s -c safe.directory=%s pull 2>&1',
                escapeshellarg($repoPath),
                escapeshellarg($repoPath)
            );
        }

        exec($command, $outputLines, $exitCode);

        $output = implode("\n", $outputLines);

        Log::info('Deploy webhook: git pull executed', [
            'git_user'  => $gitUser ?: 'www-data',
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
