<?php
defined( 'ABSPATH' ) || exit;

function tk_col_idx( $letters ) {
	$n = 0;
	for ( $i = 0; $i < strlen( $letters ); $i++ ) { $n = $n * 26 + ord( strtoupper( $letters[ $i ] ) ) - 64; }
	return $n - 1;
}

function tk_xlsx_sheets( $file ) {
	$zip = new ZipArchive();
	if ( $zip->open( $file ) !== true ) { return array(); }
	$wb = $zip->getFromName( 'xl/workbook.xml' ); $rels = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
	if ( ! $wb || ! $rels ) { return array(); }
	$wx = simplexml_load_string( $wb ); $rx = simplexml_load_string( $rels );
	$map = array();
	foreach ( $rx->Relationship as $r ) { $map[ (string) $r['Id'] ] = (string) $r['Target']; }
	$out = array();
	foreach ( $wx->sheets->sheet as $s ) {
		$rid = (string) $s->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )['id'];
		$t = isset( $map[ $rid ] ) ? $map[ $rid ] : '';
		$t = ltrim( $t, '/' );
		if ( strpos( $t, 'xl/' ) !== 0 ) { $t = 'xl/' . $t; }
		$out[] = array( 'name' => (string) $s['name'], 'file' => $t );
	}
	return $out;
}

function tk_xlsx_rows( $file, $sheet_file, $max = 30000 ) {
	$zip = new ZipArchive();
	if ( $zip->open( $file ) !== true ) { return array(); }
	$shared = array();
	$ss = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( $ss ) {
		$sx = simplexml_load_string( $ss );
		foreach ( $sx->si as $si ) {
			$txt = '';
			if ( isset( $si->t ) ) { $txt = (string) $si->t; }
			elseif ( isset( $si->r ) ) { foreach ( $si->r as $r ) { $txt .= (string) $r->t; } }
			$shared[] = $txt;
		}
	}
	$data = $zip->getFromName( $sheet_file );
	if ( ! $data ) { return array(); }
	$dx = simplexml_load_string( $data );
	$rows = array();
	foreach ( $dx->sheetData->row as $row ) {
		$cells = array();
		foreach ( $row->c as $c ) {
			$ref = (string) $c['r']; $col = preg_replace( '/[0-9]/', '', $ref );
			$t = (string) $c['t'];
			$v = isset( $c->v ) ? (string) $c->v : ( isset( $c->is->t ) ? (string) $c->is->t : '' );
			if ( 's' === $t ) { $v = isset( $shared[ (int) $v ] ) ? $shared[ (int) $v ] : ''; }
			$cells[ tk_col_idx( $col ) ] = $v;
		}
		ksort( $cells );
		$rows[] = array_values( $cells );
		if ( count( $rows ) >= $max ) { break; }
	}
	return $rows;
}

function tk_csv_rows( $file, $max = 30000 ) {
	$rows = array(); $h = fopen( $file, 'r' );
	if ( ! $h ) { return $rows; }
	while ( ( $r = fgetcsv( $h ) ) !== false ) { $rows[] = $r; if ( count( $rows ) >= $max ) { break; } }
	fclose( $h );
	return $rows;
}

function tk_find_or_create_brand( $name ) {
	global $wpdb; $p = $wpdb->prefix;
	if ( '' === trim( (string) $name ) ) { return array( 0, false ); }
	$key = strtolower( trim( (string) $name ) );
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_brands WHERE LOWER(name_en)=%s OR slug=%s OR LOWER(name_fa)=%s", $key, sanitize_title( $name ), $key ) );
	if ( $id ) { return array( (int) $id, false ); }
	$slug = sanitize_title( $name );
	if ( ! $slug ) { $slug = 'brand-' . time(); }
	$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_brands (slug,name_fa,name_en,sort,active) VALUES (%s,%s,%s,999,1)", $slug, $name, $name ) );
	return array( (int) $wpdb->insert_id, true );
}

function tk_run_import( $rows, $map ) {
	global $wpdb; $p = $wpdb->prefix;
	$now = current_time( 'mysql' );
	$rep = array( 'ok' => 0, 'review' => array(), 'brands_new' => 0 );
	$col = array(
		'model'    => array_search( 'model', $map, true ),
		'brand'    => array_search( 'brand', $map, true ),
		'qty'      => array_search( 'qty', $map, true ),
		'location' => array_search( 'location', $map, true ),
	);
	foreach ( $rows as $i => $r ) {
		if ( 0 === $i ) { continue; } // ردیف سرستون
		$get = function ( $k ) use ( $r, $col ) {
			return ( false !== $col[ $k ] && isset( $r[ $col[ $k ] ] ) ) ? trim( (string) $r[ $col[ $k ] ] ) : '';
		};
		$model = $get( 'model' ); $brand = $get( 'brand' ); $qty = $get( 'qty' ); $loc = $get( 'location' );
		if ( '' === $model ) { continue; }

		list( $bid, $created ) = tk_find_or_create_brand( $brand );
		if ( $created ) { $rep['brands_new']++; }
		if ( ! $bid ) { $rep['review'][] = array( $model, $brand, $qty, 'برند نامشخص' ); continue; }

		$parsed = null;
		foreach ( preg_split( '/[\/\\\\]+/', $model ) as $part ) {
			$part = trim( $part );
			if ( '' === $part ) { continue; }
			$parsed = TK_Engine::parse_sku( $part );
			if ( $parsed ) { break; }
		}
		if ( ! $parsed ) { $rep['review'][] = array( $model, $brand, $qty, 'مدل ناشناخته' ); continue; }

		$sec = TK_Engine::section_by_slug( $parsed['section'] );
		if ( ! $sec ) { $rep['review'][] = array( $model, $brand, $qty, 'بخش نامشخص' ); continue; }

		$q = '' === $qty ? 0 : (int) $qty;
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$p}tk_stock (brand_id,section_id,size,qty,location,sku_raw,updated_at) VALUES (%d,%d,%d,%d,%s,%s,%s) ON DUPLICATE KEY UPDATE qty=VALUES(qty), location=VALUES(location), sku_raw=VALUES(sku_raw), updated_at=VALUES(updated_at)",
			$bid, (int) $sec->id, $parsed['size'], $q, $loc, $model, $now ) );
		$rep['ok']++;
	}
	return $rep;
}