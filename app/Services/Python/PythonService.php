<?php

namespace App\Services\Python;

use App\Models\Brand;
use App\Models\AiAction;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class PythonService
{
    protected string $pythonPath;
    protected string $scriptsPath;

    public function __construct()
    {
        $this->pythonPath = env('PYTHON_PATH', 'python3');
        $this->scriptsPath = base_path('app/Python');
    }

    /**
     * Run a Python script synchronously.
     */
    public function runScript(string $script, array $params = []): array
    {
        $scriptPath = $this->scriptsPath . '/' . $script . '.py';
        $jsonParams = json_encode($params);

        $command = [
            $this->pythonPath,
            $scriptPath,
            $jsonParams,
        ];

        $process = Process::run(implode(' ', $command));

        if (!$process->successful()) {
            Log::error('Python script failed', [
                'script' => $script,
                'error' => $process->errorOutput(),
            ]);
            return ['error' => $process->errorOutput()];
        }

        $output = $process->output();
        return json_decode($output, true) ?? ['error' => 'Invalid JSON output'];
    }
}
