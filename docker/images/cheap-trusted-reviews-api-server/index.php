<?php

use JetBrains\PhpStorm\NoReturn;

$WAIT_MS = $_ENV['WAIT_MS'] ?? 50;

$pdo = new PDO( "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4", $_ENV['DB_USER'], $_ENV['DB_PASS'] );

header( 'Content-Type: application/json' );

/**
 * Echoes an error message to the browser
 *
 * @param string $msg
 * @param int    $code
 *
 * @return void
 */
#[NoReturn] function showErrorMessage ( string $msg, int $code = 400 ) : void {
  http_response_code( $code );
  echo json_encode( [
    "code" => $code,
    "message" => $msg,
    "data" => [],
  ], JSON_PRETTY_PRINT );
  die();
}



$hotelId = intval($_GET['hotel_id'] ?? 0);
if ( $hotelId == 0 )
  showErrorMessage( "Vous devez saisir un hotel_id de type entier en paramètre d'URL. Exemple : http://cheap-trusted-reviews.fake/?hotel_id=1" );

try {
  $stmt = $pdo->prepare( "SELECT
	COUNT(meta_value) AS count,
	AVG(meta_value) AS rating
FROM
	wp_posts as post
	INNER JOIN tp.wp_postmeta as meta ON post.ID = meta.post_id
WHERE
	post_type = 'review'
	AND post_author = :hotelId" );
  
  $stmt->execute( [ 'hotelId' => $hotelId ] );
  $data = $stmt->fetch( PDO::FETCH_ASSOC );
  
  if ( $data === false || $data['count'] === null )
    showErrorMessage( "Il n'y a pas d'hôtel avec l'ID $hotelId", 404 );
  
  usleep( $WAIT_MS * 1000 );
  echo json_encode( [
    'hotel_id' => $hotelId,
    'data' => $data,
  ], JSON_PRETTY_PRINT );
  die();
} catch ( Throwable $e ) {
  showErrorMessage( "Une erreur inattendue est survenue", 500 );
}