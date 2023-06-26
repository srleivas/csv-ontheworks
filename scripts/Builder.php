<?php

class Builder
{
    private $separator;
    private $fromEncoding;
    private $toEncoding;
    private $file = [];
    private $sql;
    private $tableName;
    private $schema;
    private $writeToFile;
    private $headers;

    public function __construct()
    {
        $this->separator = !empty($_POST['separator']) ? $_POST['separator'] : ',';
        $this->fromEncoding = !empty($_POST['sourceEncoding']) ? $_POST['sourceEncoding'] : false;
        $this->toEncoding = !empty($_POST['targetEncoding']) ? $_POST['targetEncoding'] : false;
        $this->schema = !empty($_POST['targetSchema']) ? $_POST['targetSchema'] : 'migracao_automatica';
    }

    /**
     * @param $file array
     * @return void
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * @return void
     */
    public function build()
    {
        $file = fopen($this->file['tmp_name'], 'r');
        if ($file === false) {
            header("HTTP/1.1 500 Internal Server Error");
        }

        $this->createTable($file);
        $this->importData($file);
//        $this->addTableData($file);

        fclose($file);

//        if ($this->writeToFile) {
//            return $this->writeSqlToFile();
//        }
//
//        $this->saveSqlToDatabase();
    }

    /**
     * @param $file
     * @return void
     */
    private function createTable($file)
    {
        $this->tableName = $this->getFileName();
        $fileHeaders = $this->buildHeaders($file);

        $temporaryTable = $this->writeToFile ? 'CREATE TEMP' : 'CREATE';
        $createTableSql = "
            DROP TABLE IF EXISTS {$this->schema}.{$this->tableName};
            {$temporaryTable} TABLE {$this->schema}.{$this->tableName} ($fileHeaders);
        ";

        $this->sql = preg_replace("/\n$|\s{2,}/", '', $createTableSql);
    }

    public function getFileName()
    {
        return mb_strtolower(preg_replace('/\s|\./', '_', $this->file['name']));
    }

    private function buildHeaders($file)
    {
        $headers = $this->prepareHeaders(fgets($file));
        $headers = explode($this->separator, $headers);

        $headers = array_map(function ($header) {
            return substr($header, 0, 62) . " TEXT";
        }, $headers);

        $this->headers = implode(', ', $headers);

        return $this->headers;
    }

    private function prepareHeaders($string)
    {
        list($fromEncoding, $toEncoding) = $this->getStringEncodings($string);

        $string = mb_convert_encoding($string, $fromEncoding, $toEncoding);
        $string = preg_replace(
            [
                "/[áàãâä]/u", "/[ÁÀÃÂÄ]/u", "/[éèêë]/u",
                "/[ÉÈÊË]/u", "/[íìîï]/u", "/[ÍÌÎÏ]/u",
                "/[óòõôö]/u", "/[ÓÒÕÔÖ]/u", "/[úùûü]/u",
                "/[ÚÙÛÜ]/u", "/ñ/u", "/Ñ/u", "/ç/u"
            ],
            explode(" ", "a A e E i I o O u U n N c"),
            $string);

        $string = preg_replace('/\"| $|\n$|\.|\(|\)/', '', $string);
        $string = preg_replace("/\s|\/|-/", '_', $string);
        return mb_strtolower($string);
    }

    /**
     * @param $string
     * @return array
     */
    private function getStringEncodings($string)
    {
        $fromEncoding = $this->fromEncoding ?: mb_detect_encoding($string);
        $fromEncoding = $fromEncoding ?: 'UTF-8';
        $toEncoding = $this->toEncoding ?: 'UTF-8';

        $encodings = [];
        $encodings[] = $fromEncoding;
        $encodings[] = $toEncoding;

        return $encodings;
    }

    public function importData($file)
    {
        $migrationSchema = $this->schema ?: "migracao_automatica";

        pg_connect("host=localhost port=5432 dbname=import_csv user=postgres");
        $sqlSchema = "CREATE SCHEMA IF NOT EXISTS {$migrationSchema}; ";

        pg_query($sqlSchema);
        $result = pg_query($this->sql);

        if ($result === false) {
            throw new Exception("Houve um problema ao importar para a tabela {$this->tableName}");
        }

        $filePath = stream_get_meta_data($file)['uri'];

        $headers = str_replace('TEXT', '', $this->headers);
        $sqlCsvImport = "\"\COPY {$migrationSchema}.{$this->tableName} ({$headers}) ";
        $sqlCsvImport .= "FROM '{$filePath}' (DELIMITER '|', QUOTE '\254', FORMAT 'csv', HEADER)\"";

        $conn = "-h localhost -p 5432 -d import_csv -U postgres";
        $command = "psql {$conn} -c {$sqlCsvImport} 2>&1";
        exec($command, $execResult, $execCode);

        if ($execCode > 0) {
            dump($command);
            $mensagemErro = json_encode($execResult);
            if ($mensagemErro) {
                throw new \Exception("$mensagemErro");
            }

            throw new Exception("Houve um problema ao importar para a tabela {$this->tableName}");
        }
    }

    public function setWriteToFile()
    {
        $this->writeToFile = true;
    }

    /**
     * @return string
     */
    private function writeSqlToFile()
    {
        $timestamp = time();
        $folderPath = __DIR__ . '/../tmp';
        $fileName = "dump_{$this->tableName}_{$timestamp}.sql";

        if (!file_exists($folderPath)) {
            mkdir($folderPath);
            @chmod($folderPath, 0777);
        }

        $file = fopen("{$folderPath}/{$fileName}", 'w');
        @chmod("$folderPath/$fileName", 0777);

        fwrite($file, $this->sql);
        fclose($file);

        return "{$folderPath}/{$fileName}";
    }

    private function saveSqlToDatabase()
    {
        pg_connect("host=localhost port=5432 dbname=import_csv user=postgres");

        $migrationSchema = $this->schema ?: "migracao_automatica";
        $sqlSchema = "CREATE SCHEMA IF NOT EXISTS {$migrationSchema}; ";

        $createSchemaResult = pg_query($sqlSchema);
        $saveDataResult = pg_query($this->sql);

        if (pg_last_error() !== '') {
            $timestamp = time();
            $folderPath = __DIR__ . '/../log';
            $fileName = "{$this->tableName}_{$timestamp}.txt";

            if (!file_exists($folderPath)) {
                mkdir($folderPath);
                @chmod($folderPath, 0777);
            }

            $file = fopen("{$folderPath}/{$fileName}", 'w');
            @chmod("$folderPath/$fileName", 0777);

            $beautySign = str_repeat('=', 25);
            $logMessage = "{$beautySign} {$this->tableName} {$beautySign}\n";
            $logMessage .= pg_last_error();

            fwrite($file, $logMessage);
            fclose($file);
        }
    }

    /**
     * @param $file
     * @return void
     */
    private function addTableData($file)
    {
        $data = [];
        while (!feof($file)) {
            $fileData = fgets($file);
            if ($fileData === false) break;

            $columnData = $this->prepareColumnData($fileData);
            $columnData = explode($this->separator, $columnData);

            foreach ($columnData as $key => $column) {
                $columnData[$key] = "'{$column}'";
            }

            $columnData = implode(',', $columnData);
            $fromEncoding = $this->fromEncoding ?: mb_detect_encoding($columnData);
            $toEncoding = $this->toEncoding ?: 'UTF-8';

            $columnData = mb_convert_encoding($columnData, $fromEncoding, $toEncoding);
            $data[] = "({$columnData})";
        }

        if (empty($data)) {
            return;
        }

        $dataSql = implode(',', $data);
        $sql = "INSERT INTO {$this->schema}.{$this->tableName} VALUES {$dataSql}";

        $this->sql .= $sql;
    }

    private function prepareColumnData($string)
    {
        return preg_replace("/\n$|\"|'|\\\/", '', $string);
    }
}