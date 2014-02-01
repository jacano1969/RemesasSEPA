<?php

include "bics.php";

// Set $path variable with file name that you want to convert to SEPA format
$path = "";

main();

function main() {
	
	global $path;

	$file = utf8_encode( file_get_contents( $path ) );
	$lines = explode(PHP_EOL, $file);
	$count = 0;
	$output = "";
	$output_mistakes = "";
	$ccc_total = 0;
	$ccc_mistakes = 0;

	foreach ($lines as $line) {
		$cif = substr($line, 4, 9);
		$ref_code = substr($line, 16, 12);
		$ccc = str_replace( ' ', '', substr($line, 68, 20) );
		
		if ( strlen( $ccc ) === 20 ) {
			$ccc_sepa = convertToSEPA( $ccc );
			$ccc_total++;

			if ( !$ccc_sepa ) {
				$output_mistakes .= $line . PHP_EOL;
				$ccc_mistakes++;
			} else {
				$line = str_replace( $ccc, $ccc_sepa, $line );
		
				$line = str_replace($cif, convertToCreditorId( $cif ), $line);
				$line = str_replace($ref_code, $ref_code . "                       ", $line);

				echo $line . PHP_EOL;
				$output .= $line . PHP_EOL;
			}
		} else {
				$line = str_replace($cif, convertToCreditorId( $cif ), $line);
				$line = str_replace($ref_code, $ref_code . "                       ", $line);

				echo $line . PHP_EOL;
				$output .= $line . PHP_EOL;
		}
	}

	write_file( $output );
	if ( $output_mistakes !== "" )
		write_file( $output_mistakes, "MISTAKES_" );

	if ( $ccc_mistakes > 0 )
		echo "El " . round( $ccc_mistakes * 100 / $ccc_total, 2 ) . "% ($ccc_mistakes cuentas de $ccc_total) están incorrectas" . PHP_EOL;
}

function write_file( $data, $prefix = "SEPA_" ) {

	global $path;

	file_put_contents( $prefix . $path, $data );
	// If you run this file on a OS which is not Linux you must remove the two lines following
	if ( $data !== "" )
		system( "dos2unix " . $prefix . $path );
}

function getBIC( $ccc ) {

	global $bic;

	$ce = (int)substr( $ccc, 0, 4 );
	if ( isset( $bic[ $ce ] ) )
		return $bic[ $ce ];

	return false;
}

function convertToSEPA( $ccc ) {

	$bic = getBIC( $ccc );

	if ( !$bic )
		return false;

	return convertToIBAN( $ccc ) . $bic;
}

function convertToIBAN( $ccc ) {

	$iban = model9710( $ccc );

	return "ES" . $iban . $ccc;
}

function convertToCreditorId( $cif ) {

	$letters = array( 'A' => '10', 'B' => '11', 'C' => '12', 'D' => '13', 'E' => '14', 'F' => '15', 'G' => '16', 'H' => '17',
					'I' => '18', 'J' => '19', 'K' => '20', 'L' => '21', 'M' => '22', 'N' => '23', 'O' => '24', 'P' => '25',
					'Q' => '26', 'R' => '27', 'S' => '28', 'T' => '29', 'U' => '30', 'V' => '31', 'W' => '32', 'X' => '33',
					'Y' => '34', 'Z' => '35' );
	$aux = "";
	$cif = strtoupper( $cif );

	for ($i=0; $i < strlen( $cif ); $i++) {
		if ( isset( $letters[ $cif[$i] ] ) )
			$aux .= $letters[ $cif[$i] ];
		else
			$aux .= $cif[$i];
	}

	$result = model9710( $aux );

	return "ES" . $result . "000" . $cif;
}

function model9710( $original_data ) {

	$data = $original_data . "142800";

	$result = 0;

	do {
		$count = 0;

		if( $result === 0 )
			$count = 8;
		elseif( $result < 10 )
			$count = 7;
		else
			$count = 6;

		$result = (int)( $result . substr($data, 0, $count) ) % 97;
		$data = substr($data, $count);

	} while( strlen( $data ) > 0 );

	$result = 98 - $result;

	return $result < 10 ? ("0" . $result) : $result;
}
