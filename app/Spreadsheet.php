<?php
/**
 * Dependency-free import parser for pasted text, CSV and XLSX files.
 *
 * Rows are returned as [content, author, source].
 */

declare(strict_types=1);

final class Spreadsheet
{
    private const MAX_FILE_BYTES = 5242880;   // 5 MB
    private const MAX_XML_BYTES = 20971520;  // 20 MB per XLSX entry

    /**
     * @param array<string, mixed> $file One entry from $_FILES.
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function parseUpload(array $file, int $maxRows = 2000): array
    {
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::uploadErrorMessage($error));
        }

        $tmpPath = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('上传文件无效，请重新选择文件。');
        }

        $size = @filesize($tmpPath);
        if ($size === false || $size < 1) {
            throw new RuntimeException('上传文件为空。');
        }
        if ($size > self::MAX_FILE_BYTES) {
            throw new RuntimeException('导入文件不能超过 5 MB，请拆分后再导入。');
        }

        $name = isset($file['name']) ? (string) $file['name'] : '';
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return self::parseCsv($tmpPath, $maxRows);
        }

        if ($extension === 'xlsx') {
            return self::parseXlsx($tmpPath, $maxRows);
        }

        throw new RuntimeException('仅支持 .xlsx 或 .csv 文件。如为旧版 .xls，请先在 Excel 中另存为 .xlsx 或 CSV。');
    }

    /**
     * Parse pasted text. One line is one quote; optional fields are separated by
     * "|" or a tab. Lines beginning with "#" are ignored.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function parseText(string $text, int $maxRows = 2000): array
    {
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $lines = preg_split('/\r\n|\r|\n/', $text);
        if (!is_array($lines)) {
            return array();
        }

        $rows = array();
        $headerChecked = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $cells = self::splitTextLine($line);

            if (!$headerChecked) {
                $headerChecked = true;
                if (self::isHeaderRow($cells)) {
                    continue;
                }
            }

            $row = self::normalizeRow($cells);
            if ($row[0] === '' && $row[1] === '' && $row[2] === '') {
                continue;
            }

            $rows[] = $row;
            if (count($rows) >= $maxRows) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private static function parseCsv(string $path, int $maxRows): array
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException('无法读取 CSV 文件。');
        }

        $raw = self::toUtf8($raw);
        $delimiter = self::detectDelimiter($raw);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('无法处理 CSV 文件。');
        }
        fwrite($stream, $raw);
        rewind($stream);

        $rows = array();
        $headerChecked = false;

        while (($cells = fgetcsv($stream, 0, $delimiter)) !== false) {
            $cells = array_map(function ($value): string {
                return trim((string) $value);
            }, $cells);

            if (!$headerChecked) {
                $headerChecked = true;
                if (self::isHeaderRow($cells)) {
                    continue;
                }
            }

            $row = self::normalizeRow($cells);
            if ($row[0] === '' && $row[1] === '' && $row[2] === '') {
                continue;
            }

            $rows[] = $row;
            if (count($rows) >= $maxRows) {
                break;
            }
        }

        fclose($stream);

        return $rows;
    }

    /**
     * Parse the first worksheet of an XLSX file using ZipArchive + SimpleXML.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private static function parseXlsx(string $path, int $maxRows): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('服务器未启用 ZipArchive 扩展，暂时无法导入 .xlsx。请将文件另存为 CSV 后导入。');
        }
        if (!class_exists('SimpleXMLElement')) {
            throw new RuntimeException('服务器未启用 SimpleXML 扩展，暂时无法导入 .xlsx。请将文件另存为 CSV 后导入。');
        }

        $zip = new ZipArchive();
        if (@$zip->open($path) !== true) {
            throw new RuntimeException('无法打开 Excel 文件，请确认它是有效的 .xlsx 文件。');
        }

        try {
            $sharedStrings = self::readSharedStrings($zip);
            $sheetPath = self::firstSheetPath($zip);
            $sheetXml = self::readZipEntry($zip, $sheetPath);

            if ($sheetXml === null) {
                $sheetXml = self::readZipEntry($zip, 'xl/worksheets/sheet1.xml');
            }
            if ($sheetXml === null) {
                throw new RuntimeException('Excel 文件中没有找到可读取的工作表。');
            }

            $sheet = @simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
            if ($sheet === false || !isset($sheet->sheetData)) {
                throw new RuntimeException('无法解析 Excel 工作表，请尝试另存为 CSV 后导入。');
            }

            $rows = array();
            $headerChecked = false;

            foreach ($sheet->sheetData->row as $rowNode) {
                $cells = array();

                foreach ($rowNode->c as $cell) {
                    $reference = (string) $cell['r'];
                    $column = self::columnIndex($reference);
                    if ($column === null) {
                        continue;
                    }
                    $cells[$column] = self::xlsxCellValue($cell, $sharedStrings);
                }

                if (empty($cells)) {
                    continue;
                }

                $maxColumn = max(array_keys($cells));
                $ordered = array();
                for ($i = 0; $i <= $maxColumn; $i++) {
                    $ordered[] = isset($cells[$i]) ? $cells[$i] : '';
                }

                if (!$headerChecked) {
                    $headerChecked = true;
                    if (self::isHeaderRow($ordered)) {
                        continue;
                    }
                }

                $row = self::normalizeRow($ordered);
                if ($row[0] === '' && $row[1] === '' && $row[2] === '') {
                    continue;
                }

                $rows[] = $row;
                if (count($rows) >= $maxRows) {
                    break;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = self::readZipEntry($zip, 'xl/sharedStrings.xml');
        if ($xml === null) {
            return array();
        }

        $shared = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        if ($shared === false) {
            return array();
        }

        $strings = array();
        foreach ($shared->si as $item) {
            $strings[] = self::xmlText($item);
        }

        return $strings;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbookXml = self::readZipEntry($zip, 'xl/workbook.xml');
        if ($workbookXml === null) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = @simplexml_load_string($workbookXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        if ($workbook === false || !isset($workbook->sheets->sheet[0])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheet = $workbook->sheets->sheet[0];
        $namespaces = $workbook->getDocNamespaces(true);
        $relationshipNamespace = isset($namespaces['r'])
            ? $namespaces['r']
            : 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $attributes = $sheet->attributes($relationshipNamespace);
        $relationshipId = isset($attributes['id']) ? (string) $attributes['id'] : '';

        if ($relationshipId === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        $relsXml = self::readZipEntry($zip, 'xl/_rels/workbook.xml.rels');
        if ($relsXml === null) {
            return 'xl/worksheets/sheet1.xml';
        }

        $rels = @simplexml_load_string($relsXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        if ($rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        foreach ($rels->Relationship as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = ltrim((string) $relationship['Target'], '/');
            if ($target === '') {
                break;
            }

            if (strpos($target, 'xl/') !== 0) {
                $target = 'xl/' . $target;
            }

            return $target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function readZipEntry(ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return null;
        }

        if (isset($stat['size']) && (int) $stat['size'] > self::MAX_XML_BYTES) {
            throw new RuntimeException('Excel 文件内部数据过大，请拆分后再导入。');
        }

        $data = $zip->getFromName($name);

        return $data === false ? null : $data;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private static function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = isset($cell['t']) ? (string) $cell['t'] : '';

        if ($type === 's') {
            $index = (int) $cell->v;

            return isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
        }

        if ($type === 'inlineStr') {
            return self::xmlText($cell->is);
        }

        return trim((string) $cell->v);
    }

    private static function xmlText(SimpleXMLElement $node): string
    {
        $parts = $node->xpath('.//*[local-name()="t"]');
        if (!is_array($parts)) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            $text .= (string) $part;
        }

        return $text;
    }

    /**
     * Convert A1 / AB12 style references to zero-based column numbers.
     */
    private static function columnIndex(string $reference): ?int
    {
        if (!preg_match('/^([A-Z]+)\d+$/i', $reference, $matches)) {
            return null;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        $length = strlen($letters);

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function normalizeRow(array $cells): array
    {
        return array(
            isset($cells[0]) ? trim((string) $cells[0]) : '',
            isset($cells[1]) ? trim((string) $cells[1]) : '',
            isset($cells[2]) ? trim((string) $cells[2]) : '',
        );
    }

    /**
     * @param array<int, string> $cells
     */
    private static function isHeaderRow(array $cells): bool
    {
        $first = isset($cells[0]) ? strtolower(trim((string) $cells[0])) : '';

        $headers = array('内容', '句子', '句子内容', '一言', '文本', '正文', 'content', 'quote', 'text', 'hitokoto');

        return in_array($first, $headers, true);
    }

    /**
     * @return array<int, string>
     */
    private static function splitTextLine(string $line): array
    {
        if (strpos($line, '|') !== false) {
            return array_map('trim', explode('|', $line));
        }

        if (strpos($line, "\t") !== false) {
            return array_map('trim', explode("\t", $line));
        }

        return array($line);
    }

    private static function toUtf8(string $content): string
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if ($content === '' || preg_match('//u', $content) === 1) {
            return $content;
        }

        $converted = false;
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'GB18030');
        } elseif (function_exists('iconv')) {
            $converted = @iconv('GB18030', 'UTF-8//IGNORE', $content);
        }

        if (!is_string($converted) || preg_match('//u', $converted) !== 1) {
            throw new RuntimeException('无法识别 CSV 编码，请使用 UTF-8 或 GBK 编码另存后重试。');
        }

        return $converted;
    }

    private static function detectDelimiter(string $content): string
    {
        $line = strtok($content, "\r\n");
        if ($line === false) {
            return ',';
        }

        $counts = array(
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        );
        arsort($counts);
        $delimiter = (string) array_key_first($counts);

        return $counts[$delimiter] > 0 ? $delimiter : ',';
    }

    private static function uploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return '上传文件超过服务器允许的大小，请拆分文件或联系主机商调整 upload_max_filesize。';
            case UPLOAD_ERR_PARTIAL:
                return '文件只上传了一部分，请重试。';
            case UPLOAD_ERR_NO_FILE:
                return '请选择要导入的 Excel 或 CSV 文件。';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '服务器缺少临时上传目录，无法接收文件。';
            case UPLOAD_ERR_CANT_WRITE:
                return '服务器无法写入上传文件，请检查目录权限。';
            case UPLOAD_ERR_EXTENSION:
                return '上传被服务器扩展中止，请更换文件后重试。';
            default:
                return '文件上传失败，请重试。';
        }
    }
}
