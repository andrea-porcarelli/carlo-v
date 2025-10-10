<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateRepositoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create-repo {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Repository';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $model = $this->argument('model');
        if (!isset($model)) {
            $this->error('Devi indicare il model per la creazione del repository');
            die();
        }

        $interface = File::get(resource_path() . '/make-repository/BaseInterface.php');
        $new_interface = str_replace('Base', $model, $interface);

        $repository = File::get(resource_path() . '/make-repository/BaseRepository.php');
        $new_repository = str_ireplace('BaseModel', $model, $repository);
        $new_repository = str_replace('Base', $model, $new_repository);
        $new_repository = str_ireplace('App\Models\Model', 'App\Models\\' . $model, $new_repository);

        File::put(__DIR__ . '/../../Interfaces/' . $model . 'Interface.php', $new_interface);
        File::put(__DIR__ . '/../../Repositories/' . $model . 'Repository.php', $new_repository);

        $service_provider = File::get(__DIR__ . '/../../Providers/RepositoryServiceProvider.php');


        $content_before_string = strstr($service_provider, '#namespace here', true);
        $line1 = 0;
        if (false !== $content_before_string) {
            $line1 = count(explode(PHP_EOL, $content_before_string));
        }
        $content_before_string = strstr($service_provider, '#register here', true);
        $line2 = 0;
        if (false !== $content_before_string) {
            $line2 = count(explode(PHP_EOL, $content_before_string));
        }

        $lines = [];
        $fc = fopen(__DIR__ . '/../../Providers/RepositoryServiceProvider.php', "r");
        while (!feof($fc)) {
            $buffer = fgets($fc, 4096);
            $lines[] = $buffer;
        }
        fclose($fc);


        $f = fopen(__DIR__ . '/../../Providers/RepositoryServiceProvider.php', "w") or die("couldn't open $file");

        $lineCount = count($lines);
        $row = 0;
        for ($i = 0; $i < ($lineCount + $row) - 1; $i++) {
            fwrite($f, $lines[$i]);
            if ($i == ($line1 - 1)) {
                fwrite($f, "\nuse App\Interfaces\\" . $model . "Interface;\nuse App\Repositories\\" . $model . "Repository;\n");
            }
            if ($i == ($line2 - 1)) {
                fwrite($f, "        \$this->app->bind(" . $model . "Interface::class, " . $model . "Repository::class);\n");
            }
        }

        fwrite($f, $lines[$lineCount-1]);
        fclose($f);

    }


}
