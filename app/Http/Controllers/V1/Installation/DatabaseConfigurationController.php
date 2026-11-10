<?php

namespace Crater\Http\Controllers\V1\Installation;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\DatabaseEnvironmentRequest;
use Crater\Space\EnvironmentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DatabaseConfigurationController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $environmentManager;

    /**
     * @param EnvironmentManager $environmentManager
     */
    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->environmentManager = $environmentManager;
    }

    /**
     *
     * @param DatabaseEnvironmentRequest $request
     */
    public function saveDatabaseEnvironment(DatabaseEnvironmentRequest $request)
    {
        Artisan::call('config:clear');

        $results = $this->environmentManager->saveDatabaseVariables($request);

        if (! empty($results['success'])) {
            \Storage::disk('local')->delete('database_created');

            try {
                if (! config('app.key') && Artisan::call('key:generate', ['--force' => true]) !== 0) {
                    throw new \RuntimeException('Unable to generate the application key.');
                }

                Artisan::call('config:clear');
                Artisan::call('storage:link');

                if (Artisan::call('migrate', ['--seed' => true, '--force' => true]) !== 0) {
                    \Log::error('Installation migrations failed.', ['output' => Artisan::output()]);
                    throw new \RuntimeException('Database migrations failed.');
                }

                if (! \Storage::disk('local')->put('database_created', 'database_created')) {
                    throw new \RuntimeException('Unable to save the installation marker.');
                }
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'error_message' => 'Database installation failed. Check storage/logs/laravel.log before retrying.',
                ]);
            }
        }

        return response()->json($results);
    }

    public function getDatabaseEnvironment(Request $request)
    {
        $databaseData = [];

        switch ($request->connection) {
            case 'sqlite':
                $databaseData = [
                    'database_connection' => 'sqlite',
                    'database_name' => database_path('database.sqlite'),
                ];

                break;

            case 'pgsql':
                $databaseData = [
                    'database_connection' => 'pgsql',
                    'database_host' => '127.0.0.1',
                    'database_port' => 5432,
                ];

                break;

            case 'mysql':
                $databaseData = [
                    'database_connection' => 'mysql',
                    'database_host' => '127.0.0.1',
                    'database_port' => 3306,
                ];

                break;

        }


        return response()->json([
            'config' => $databaseData,
            'success' => true,
        ]);
    }
}
