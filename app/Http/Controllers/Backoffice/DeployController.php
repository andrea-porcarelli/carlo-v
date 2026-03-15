<?php

namespace App\Http\Controllers\Backoffice;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeployController extends BaseController
{
    /**
     * Deploy key — read from env DEPLOY_KEY, fallback to hardcoded value.
     * Set DEPLOY_KEY in .env to override without touching code.
     */
    private static function deployKey(): string
    {
        return env('DEPLOY_KEY');
    }

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

        if (!hash_equals(self::deployKey(), (string) $provided)) {
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

            Log::info("Git pull executed", [
                'output' => implode("\n", $output),
                'return_code' => $returnVar,
            ]);

            if ($returnVar === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Git pull completato con successo',
                    'lines'   => array_values(array_filter($output, fn($l) => trim($l) !== '')),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore durante git pull',
                    'lines'   => array_values(array_filter($output, fn($l) => trim($l) !== '')),
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

    /**
     * GET|POST /webhook/migrate?key=<key>
     *
     * Runs "php artisan migrate --force" regardless of environment.
     */
    public function migrate(Request $request): JsonResponse
    {
        $provided = $request->query('key') ?? $request->header('X-Deploy-Key', '');

        if (!hash_equals(self::deployKey(), (string) $provided)) {
            Log::warning('Deploy webhook: invalid key attempt on migrate', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $cwd        = getcwd();
            $artisanPath = base_path('artisan');
            $artisanExists = file_exists($artisanPath);

            $diagnostics = [
                '--- diagnostics ---',
                'getcwd()   : ' . $cwd,
                'base_path(): ' . base_path(),
                'artisan    : ' . $artisanPath . ($artisanExists ? ' [OK]' : ' [NOT FOUND]'),
                'PHP_BINARY : ' . PHP_BINARY . ' (non usato)',
                'php CLI    : /usr/local/bin/php',
                '-------------------',
            ];

            if (!$artisanExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'artisan non trovato in ' . $artisanPath,
                    'lines'   => $diagnostics,
                ], 500);
            }

            $php     = '/usr/local/bin/php';
            $artisan = escapeshellarg($artisanPath);
            $command = "{$php} {$artisan} migrate --force 2>&1";

            $output    = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            Log::info('Artisan migrate executed', [
                'cwd'         => $cwd,
                'artisan'     => $artisanPath,
                'output'      => implode("\n", $output),
                'return_code' => $returnVar,
            ]);

            $lines = array_merge($diagnostics, array_values(array_filter($output, fn($l) => trim($l) !== '')));

            if ($returnVar === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Migrate completato con successo',
                    'lines'   => $lines,
                    'command' => $command,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore durante migrate',
                    'lines'   => $lines,
                    'command' => $command,
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Artisan migrate error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /backoffice/remote-deploy
     *
     * Proxy: chiama il webhook deploy sul peer carlov (carlov_url).
     * Usato solo dall'istanza web (role=web).
     */
    public function remoteTrigger(): JsonResponse
    {
        $carlovUrl = rtrim(Setting::get('carlov_url', ''), '/');

        if (!$carlovUrl) {
            return response()->json(['success' => false, 'message' => 'Impostazione carlov_url non configurata'], 422);
        }

        try {
            $response = Http::timeout(60)
                ->post("{$carlovUrl}/webhook/deploy?key=" . self::deployKey());

            $json = $response->json();
            if ($json === null) {
                Log::error('Remote deploy: risposta non JSON', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Risposta non JSON da carlov (HTTP ' . $response->status() . ')',
                    'lines'   => array_filter(explode("\n", $response->body())),
                ], 502);
            }

            return response()->json($json, $response->status());
        } catch (\Throwable $e) {
            Log::error('Remote deploy error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /backoffice/remote-migrate
     *
     * Proxy: chiama il webhook migrate sul peer carlov (carlov_url).
     * Usato solo dall'istanza web (role=web).
     */
    public function remoteMigrate(): JsonResponse
    {
        $carlovUrl = rtrim(Setting::get('carlov_url', ''), '/');

        if (!$carlovUrl) {
            return response()->json(['success' => false, 'message' => 'Impostazione carlov_url non configurata'], 422);
        }

        try {
            $response = Http::timeout(120)
                ->post("{$carlovUrl}/webhook/migrate?key=" . self::deployKey());

            $json = $response->json();
            if ($json === null) {
                Log::error('Remote migrate: risposta non JSON', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Risposta non JSON da carlov (HTTP ' . $response->status() . ')',
                    'lines'   => array_filter(explode("\n", $response->body())),
                ], 502);
            }

            return response()->json($json, $response->status());
        } catch (\Throwable $e) {
            Log::error('Remote migrate error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
