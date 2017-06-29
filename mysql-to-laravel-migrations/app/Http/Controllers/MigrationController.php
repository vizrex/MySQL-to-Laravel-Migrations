<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MigrationController extends Controller
{
    /*
     * Upload MySQL Dump file in a separate directory
     */

    public function upload(Request $request)
    {
        $isSucceeded = false;
        $params = [];
        $response = null;

        $fileName = "inputFile";
        if ($request->hasFile($fileName))
        {
            if ($request->file($fileName)->isValid())
            {
                $localFileName = self::generateRandomFileName(Auth::user()->id);
                $request->file($fileName)->move(storage_path('uploads'), $localFileName);
                $params["localFileName"] = $localFileName;
                $isSucceeded = true;
            }
        }


        if ($isSucceeded)
        {
            $response = view('migrations.processing')->withSuccess("File has been uploaded successfully! Please wait while it is being processed!")->with($params);
        }
        else
        {
            /*
             * TODO: Return the exact error cause rather than a generic message as follow
             */

            $response = redirect()->back()->withError("There was an error uploading the file! Please try again!");
        }

        return $response;
    }

    public function processUploadedFile(Request $request)
    {
        /*
         * TODO: Add Saftey Checks:
         * 1. Parameter 'localFileName' doesn't exist in request
         * 2. Given filename is invalid or doesn't exist on server
         */

        /*
         * Courtesy: http://stackoverflow.com/questions/19751354/how-to-import-sql-file-in-mysql-database-using-php
         */

        $localFileName = $request->input('localFileName');

        $isSucceeded = false;
        $errorMsg = null;
        $response = null;

        // Read in entire file
        $lines = file(storage_path("uploads") . '/' . $localFileName);

        try
        {
            // Clear existing database
            $this->clearTempDB();

            DB::connection(env("TEMP_DB_CONNECTION", "mysql_temp"))->transaction(function($lines) use ($lines)
            {

                $tempQuery = '';

                // Loop through each line
                foreach ($lines as $line)
                {
                    // Skip it if it's a comment
                    if (substr($line, 0, 2) == '--' || $line == '')
                        continue;

                    // Add this line to the current segment
                    $tempQuery .= $line;

                    // If it has a semicolon at the end, it's the end of the query
                    if (substr(trim($line), -1, 1) == ';')
                    {
                        // Perform the query
                        DB::connection(env("TEMP_DB_CONNECTION", "mysql_temp"))->statement($tempQuery);

                        // Reset temp variable to empty
                        $tempQuery = '';
                    }
                }
            });

            $isSucceeded = true;
        }
        catch (\Exception $ex)
        {
            $errorMsg = $ex->getMessage();
            $isSucceeded = false;
        }

        if (!$isSucceeded)
        {
            if ($errorMsg == null)
            {
                $errorMsg = "Unable to process given dump file!";
            }

            $response = response(json_encode(['errorMsg' => $errorMsg]), 400);
        }
        else
        {
            $importedTableNames = $this->getTablesNamesOfTempDB();
            $response = json_encode($importedTableNames);
        }

        return $response;
    }

    /*
     * Get list of tables of temporary database.
     */

    private function getTablesNamesOfTempDB()
    {
        $tableNamesResult = DB::connection(env("TEMP_DB_CONNECTION", "mysql_temp"))
                ->select("SHOW TABLES FROM " . env("TEMP_DB_DATABASE", "db_dev_migen_temp"));

        $tableNames = $this->traverseTableNamesResult($tableNamesResult);
        return $tableNames;
    }

    /*
     * Generate Migrations
     */

    public function generateMigrations(Request $request)
    {
        /*
         * TODO: Implement saftey checks:
         * 1. `tables` should exist in request
         * 2. `tables` shouldn't be empty
         */


        /*
         * Get list of tables for which we need to generate migrations
         */
        $tableNames = $request->input("tables");

        /*
         * Generate a random direcotry name where to store generated migrations
         */
        $destDirectory = storage_path("migrations") . '/' . self::generateRandomDirectoryName(Auth::user()->id);
        /*
         * Create this directory so that Artisan command can use it to write output files
         */
        mkdir($destDirectory, 0777, true);

        /*
         * Prepare parameters for migration:generate command
         */
        $connection = env("TEMP_DB_CONNECTION", "mysql_temp");
        $params = [];
        $params["--defaultFKNames"] = true;       // this is a switch only so skipping the value part
        $params["--defaultIndexNames"] = true;    // this is also a switch only
        $params["-n"] = true;    // this is also a switch only
        $params["-t"] = implode(",", $tableNames);  // table names should be passed as comma separated array
        $params["-p"] = $destDirectory;
        $params["-c"] = $connection;
        $params["-q"] = true;

        \Illuminate\Support\Facades\Log::debug("Params: " . json_encode($params));
        // Execute the php artisan command
        \Illuminate\Support\Facades\Artisan::call("migrate:generate", $params);

        \Illuminate\Support\Facades\Log::debug("Migration DONE!");
        // Omitt connection specifier from migration
        $this->removeConnectionNameFromMigrations($destDirectory, $connection);
        // Generate a zip file name, to be downloaded later
        $zipFileName = storage_path("migrations") . '/' . self::generateRandomFileName(Auth::user()->id, "migration_", ".zip");
        $zipFile = self::zip($destDirectory, $zipFileName);

        return response()->download($zipFileName);
    }

    /*
     * We need to traverse the resultset of `SHOW TABLES FROM <DB>` query
     * The resultset is an array, each element containing an object
     * The first property of object is a key (usually of format TABLES_in_<DB>)
     * while its value is the actual table name.
     */

    private function traverseTableNamesResult($tableNamesResult)
    {
        $tableNames = [];
        foreach ($tableNamesResult as $index => $resultObj)
        {
            foreach ($resultObj as $key => $tableName)
            {
                array_push($tableNames, $tableName);
            }
        }

        return $tableNames;
    }

    private static function generateRandomFileName($userId, $prefix = "", $extension = ".sql")
    {
        $date = new \DateTime();
        $fileName = $prefix;
        $fileName = $fileName . date_format($date, 'dmyhis');
        $fileName = $fileName . $userId;
        $fileName = $fileName . $extension;

        return $fileName;
    }

    private static function generateRandomDirectoryName($userId, $prefix = "")
    {
        $date = new \DateTime();
        $dirName = $prefix;
        $dirName = $dirName . $userId;
        $dirName = $dirName . date_format($date, 'dmyhis');

        return $dirName;
    }

    private static function zip($dir, $zipFileName)
    {
        /*
         * Courtesy: http://stackoverflow.com/questions/4914750/how-to-zip-a-whole-folder-using-php
         */

        // Get real path for our folder
        $rootPath = realpath($dir);

        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open($zipFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath), \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file)
        {
            // Skip directories (they would be added automatically)
            if (!$file->isDir())
            {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();
    }

    private function clearTempDB()
    {
        $tables = $this->getTablesNamesOfTempDB();
        // Disable foreign key checks before drop tables
        DB::connection(env("TEMP_DB_CONNECTION", "mysql_temp"))->statement("SET foreign_key_checks = 0");
        foreach ($tables as $table)
        {
            DB::connection(env("TEMP_DB_CONNECTION", "mysql_temp"))
                    ->statement("DROP TABLE `". $table."`");
        }
        // Re-enable foreign key checks
        DB::connection(env("TEMP_DB_CONNECTION", "mysql_temp"))->statement("SET foreign_key_checks = 1");
    }

    /*
     * migrate:generate command adds a connection name in each migration
     * it is required for this tool to work but would be unnecessary or
     * even undesired for users. So we need to omitt it manually once
     * migrations have been generated.
     *
     * Currenly migrate:generate command has no mechanism to do so,
     * therefore, we need to open migrations one by one and update each
     * file manually!
     */

    private function removeConnectionNameFromMigrations($migrationsDirectory, $usedConnectionName)
    {
        // Get all files in migrations directory
        $fileNames = preg_grep('~\.(php)$~', scandir($migrationsDirectory));
        // Update files one by one
        foreach ($fileNames as $fileName)
        {
            // Create absolute path of file
            $filePath = $migrationsDirectory . "/" . $fileName;
            // Read all contents of file
            $fileContent = file_get_contents($filePath);
            // Omitt the connection specifier
            $fileContent = str_replace("::connection('" . $usedConnectionName . "')->", "::", $fileContent);
            // Write back the updated content of file
            file_put_contents($filePath, $fileContent);
        }
    }

}
