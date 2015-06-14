<?php
namespace Push_API;

class MIME_Builder {

	private $boundary;

	function __construct() {
		$this->boundary = md5( microtime() );
	}

	public function boundary() {
		return $this->boundary;
	}

	public function add_json_string( $name, $filename, $content ) {
		return $this->build_attachment( $name, $filename, $content, 'application/json' );
	}

	public function add_content_from_file( $filepath, $name = 'a_file' ) {
		$filename		 = basename( $filepath );
		$filecontent = file_get_contents( $filepath );
		$filemime    = $this->get_mime_type_for( $filepath );

		return $this->build_attachment( $name, $filename, $filecontent, $filemime );
	}

	public function close() {
		return '--' . $this->boundary . '--';
	}

	private function build_attachment( $name, $filename, $content, $mime_type ) {
		$eol  = "\r\n";
		$size = strlen( $content );

		$attachment  = '--' . $this->boundary . $eol;
		$attachment .= 'Content-Type: ' . $mime_type . $eol;
		$attachment .= 'Content-Disposition: form-data; name=' . $name . '; filename=' . $filename . '; size=' . $size . $eol . $eol;
		$attachment .= $content . $eol;

		return $attachment;
	}


	private function get_mime_type_for( $filepath ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$type  = finfo_file( $finfo, $filepath );

		if( $this->is_valid_mime_type( $type ) ) {
			return $type;
		}

		return 'application/octet-stream';
	}

	private function is_valid_mime_type( $type ) {
		return in_array( $type, array (
			'image/jpeg',
			'image/png',
			'image/gif',
			'application/font-sfnt',
			'application/x-font-truetype',
			'application/font-truetype',
			'application/vnd.ms-opentype',
			'application/x-font-opentype',
			'application/font-opentype',
			'application/octet-stream',
		) );
	}

}