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

    public function __construct()
    {
        $this->separator = !empty($_POST['separator']) ? $_POST['separator'] : ',';
        $this->fromEncoding = !empty($_POST['sourceEncoding']) ? $_POST['sourceEncoding'] : 'UTF-8';
        $this->toEncoding = !empty($_POST['targetEncoding']) ? $_POST['targetEncoding'] : false;
        $this->schema = !empty($_POST['targetSchema']) ? $_POST['targetSchema'] . '.' : '';
    }

    /**
     * @param $file
     * @return void
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    public function build()
    {
        $file = fopen($this->file['tmp_name'], 'r');

        $this->createTable($file);
        $this->addTableData($file);

        fclose($file);
        return $this->writeSqlToFile();
    }

    /**
     * @param $file
     * @return void
     */
    protected function createTable($file)
    {
        $columns = fgets($file);
        $columns = explode($this->separator, $columns);

        $fileName = preg_replace('/\s|\./', '_', $this->file['name']);
        $fileName = mb_strtolower($fileName);
        $this->tableName = $fileName;

        $sqlColumns = [];
        foreach ($columns as $column) {
            $fromEncoding = $this->fromEncoding ?: mb_detect_encoding($column);
            $toEncoding = $this->toEncoding ?: 'UTF-8';

            $column = preg_replace("/\s/", '_', $column);
            $column = preg_replace('/\"/', '', $column);
            $column = mb_strtolower(mb_convert_encoding($column, $fromEncoding, $toEncoding));
            $sqlColumns[] = "$column TEXT";
        }

        $sqlColumns = implode(', ', $sqlColumns);
        $sql = "
            DROP TABLE IF EXISTS {$this->schema}$fileName;
            CREATE TEMP TABLE {$this->schema}{$this->tableName} ($sqlColumns);
        ";

        $this->sql = preg_replace("/\n$|\s{2,}/", '', $sql);
    }

    /**
     * @param $file
     * @return void
     */
    protected function addTableData($file)
    {
        $data = [];
        while (!feof($file)) {
            $fileData = fgets($file);

            if ($fileData === false) {
                continue;
            }

            $columnData = preg_replace("/\n$/", '', $fileData);
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

        $dataSql = implode(',', $data);
        $sql = "INSERT INTO {$this->schema}{$this->tableName} VALUES {$dataSql}";

        $this->sql .= $sql;
    }

    /**
     * @return string
     */
    protected function writeSqlToFile()
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
}