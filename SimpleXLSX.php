<?php
/**
 * SimpleXLSX php class
 * MS Excel 2007 workbooks reader
 *
 * Copyright (c) 2012 - 2021 SimpleXLSX
 *
 * @category   SimpleXLSX
 * @package    SimpleXLSX
 * @copyright  Copyright (c) 2012 - 2021 SimpleXLSX (https://github.com/shuchkin/simplexlsx/)
 * @license    MIT
 * @version    0.9.1
 */

class SimpleXLSX {
    // Don't remove this string! It's used by script.
    const VERSION = '0.9.1';

    public $workbook;
    public $sheets;
    public $sheetNames;
    public $sheetFiles = [];
    public $styles;
    public $hyperlinks;
    public $package;
    public $sharedstrings;
    public $date_formats;

    private $sheet_info;

    public function __construct( $filename = null, $is_data = false, $debug = false ) {
        if ( $debug ) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting( E_ALL );
        }
        if ( $filename ) {
            $this->parse( $filename, $is_data );
        }
    }
    public static function parse( $filename = null, $is_data = false, $debug = false ) {
        $xlsx = new self();
        $xlsx->parse( $filename, $is_data );
        return $xlsx;
    }
    public static function parseData( $data, $debug = false ) {
        return self::parse( $data, true, $debug );
    }
    public function sheets() {
        return $this->sheets;
    }
    public function sheetsCount() {
        return count($this->sheets);
    }
    public function sheetName( $idx ) {
        if (isset($this->sheetNames[$idx])) {
            return $this->sheetNames[$idx];
        }
        return false;
    }
    public function sheetNames() {
        return $this->sheetNames;
    }
    public function sheet( $idx ) {
        if (isset($this->sheets[$idx])) {
            return $this->sheets[$idx];
        }
        return false;
    }
    public function rows( $idx ) {
        if (isset($this->sheets[$idx])) {
            return $this->sheets[$idx];
        }
        return false;
    }
    public function toHTML( $idx = false ) {
        $s = '';
        if ( $idx === false ) {
            foreach( $this->sheetNames as $i => $name ) {
                $s .= '<h3>'.$name.'</h3>'.$this->toHTML( $i );
            }
            return $s;
        }
        $table = '<table>';
        foreach( $this->rows( $idx ) as $r ) {
            $table .= '<tr><td>'.implode('</td><td>', $r ).'</td></tr>';
        }
        $table .= '</table>';
        return $table;
    }
    public function __get( $name ) {
        if ($name === 'sheetnames') {
            return $this->sheetNames;
        }
        return null;
    }
    private function parse( $filename, $is_data = false ) {
        $this->package = [
            'filename' => $filename,
            'is_data' => $is_data,
            'files' => [],
            'rels' => []
        ];

        $this->rels_read();
        $this->read_shared_strings();
        $this->read_styles();
        $this->read_workbook();

        foreach( $this->sheet_info as $sheet_id => $sheet ) {
            $this->sheets[ $sheet_id ] = $this->read_sheet( $sheet['path'] );
            $this->sheetNames[ $sheet_id ] = $sheet['name'];
        }
        return $this;
    }
    private function rels_read() {
        $rels_file = '_rels/.rels';
        if ( $this->get_file_data( $rels_file ) ) {
            $xml = new SimpleXMLElement( $this->get_file_data( $rels_file ) );
            foreach ($xml->Relationship as $rel) {
                $this->package['rels'][(string) $rel['Id']] = [
                    'target' => (string) $rel['Target'],
                    'type' => (string) $rel['Type']
                ];
            }
        }
    }
    private function read_shared_strings() {
        $f = 'xl/sharedStrings.xml';
        if ( $this->get_file_data( $f ) ) {
            $xml = new SimpleXMLElement( $this->get_file_data( $f ) );
            $this->sharedstrings = [];
            foreach ( $xml->si as $si ) {
                $this->sharedstrings[] = (string) $si->t;
            }
        }
    }
    private function read_styles() {
        $f = 'xl/styles.xml';
        if ( $this->get_file_data( $f ) ) {
            $xml = new SimpleXMLElement( $this->get_file_data( $f ) );
            $this->styles = [];
            if ($xml->numFmts && $xml->numFmts->numFmt) {
                foreach ($xml->numFmts->numFmt as $numFmt) {
                    $this->styles[(int)$numFmt['numFmtId']] = (string)$numFmt['formatCode'];
                }
            }
        }
    }
    private function read_workbook() {
        $f = 'xl/workbook.xml';
        if ( $this->get_file_data( $f ) ) {
            $xml = new SimpleXMLElement( $this->get_file_data( $f ) );
            $this->sheet_info = [];
            foreach ($xml->sheets->sheet as $sheet) {
                $r = $sheet->attributes('r', true);
                $this->sheet_info[(int)$sheet['sheetId']] = [
                    'name' => (string)$sheet['name'],
                    'path' => 'xl/'.basename($r['id']).'.xml'
                ];
            }
        }
    }
    private function read_sheet( $path ) {
        if ( $this->get_file_data( $path ) ) {
            $xml = new SimpleXMLElement( $this->get_file_data( $path ) );
            $rows = [];
            foreach ($xml->sheetData->row as $row) {
                $r = [];
                foreach ($row->c as $c) {
                    $idx = $this->get_col_index((string)$c['r']);
                    $val = '';
                    if (isset($c->v)) {
                        $val = (string)$c->v;
                        if (isset($c['t']) && $c['t'] == 's') {
                            $val = $this->sharedstrings[$val];
                        }
                    }
                    $r[$idx] = $val;
                }
                $rows[] = $r;
            }
            return $rows;
        }
        return [];
    }
    private function get_col_index( $cell ) {
        if (preg_match('/([A-Z]+)(\d+)/', $cell, $matches)) {
            $col = $matches[1];
            $ord = 0;
            for ($i = strlen($col) - 1, $j = 0; $i >= 0; $i--, $j++) {
                $ord += (ord($col[$i]) - 64) * pow(26, $j);
            }
            return $ord - 1;
        }
        return -1;
    }
    private function get_file_data( $path ) {
        if (isset($this->package['files'][$path])) {
            return $this->package['files'][$path];
        }
        if ($this->package['is_data']) {
            $zip = new ZipArchive();
            $tmp_file = tempnam(sys_get_temp_dir(), 'xlsx');
            file_put_contents($tmp_file, $this->package['filename']);
            if ($zip->open($tmp_file) === TRUE) {
                $this->package['files'][$path] = $zip->getFromName($path);
                $zip->close();
                unlink($tmp_file);
                return $this->package['files'][$path];
            }
        } else {
            $zip = new ZipArchive();
            if ($zip->open($this->package['filename']) === TRUE) {
                $this->package['files'][$path] = $zip->getFromName($path);
                $zip->close();
                return $this->package['files'][$path];
            }
        }
        return false;
    }
}
