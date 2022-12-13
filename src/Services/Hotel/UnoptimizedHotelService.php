<?php

namespace App\Services\Hotel;

use App\Common\FilterException;
use App\Common\SingletonTrait;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use App\Common\Timers;
use App\Common\PDOSingleton;
use Exception;
use PDO;

/**
 * Une classe utilitaire pour récupérer les données des magasins stockés en base de données
 */
class UnoptimizedHotelService extends AbstractHotelService {
  
  use SingletonTrait;
  
  
  protected function __construct () {
    parent::__construct( new RoomService() );
  }
  
  
  /**
   * Récupère une nouvelle instance de connexion à la base de donnée
   *
   * @return PDO
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDB () : PDO {
    $timer = Timers::getInstance();
    $idtimerdb = $timer->startTimer("DbTimer");
    $pdo = PDOSingleton::get();
    $timer->endTimer("DbTimer", $idtimerdb);
    return $pdo;
  }
  
  
  /**
   * Récupère une méta-donnée de l'instance donnée
   *
   * @param int    $userId
   * @param string $key
   *
   * @return string|null
   */
  protected function getMeta ( int $userId, string $key ) : ?string {
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_usermeta" );
    $stmt->execute();
    
    $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
    $output = null;
    foreach ( $result as $row ) {
      if ( $row['user_id'] === $userId && $row['meta_key'] === $key )
        $output = $row['meta_value'];
    }
    
    return $output;
  }
  
  
  /**
   * Récupère toutes les meta données de l'instance donnée
   *
   * @param HotelEntity $hotel
   *
   * @return array
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getMetas ( HotelEntity $hotel ) : array {
    $metaDatas = [
      'address' => [
        'address_1' => $this->getMeta( $hotel->getId(), 'address_1' ),
        'address_2' => $this->getMeta( $hotel->getId(), 'address_2' ),
        'address_city' => $this->getMeta( $hotel->getId(), 'address_city' ),
        'address_zip' => $this->getMeta( $hotel->getId(), 'address_zip' ),
        'address_country' => $this->getMeta( $hotel->getId(), 'address_country' ),
      ],
      'geo_lat' =>  $this->getMeta( $hotel->getId(), 'geo_lat' ),
      'geo_lng' =>  $this->getMeta( $hotel->getId(), 'geo_lng' ),
      'coverImage' =>  $this->getMeta( $hotel->getId(), 'coverImage' ),
      'phone' =>  $this->getMeta( $hotel->getId(), 'phone' ),
    ];
    
    return $metaDatas;
  }
  
  
  /**
   * Récupère les données liées aux évaluations des hotels (nombre d'avis et moyenne des avis)
   *
   * @param HotelEntity $hotel
   *
   * @return array{rating: int, count: int}
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getReviews ( HotelEntity $hotel ) : array {
    // Récupère tous les avis d'un hotel
    $stmt = $this->getDB()->prepare( "SELECT AVG(ratingData.meta_value) as average,
      Count(ratingData.meta_value) as counted
    FROM wp_posts AS post
        INNER JOIN wp_postmeta AS ratingData
            ON post.ID = ratingData.post_id AND ratingData.meta_key = 'rating'
            
      WHERE post.post_author=:hotelId AND post.ID = ratingData.post_id AND meta_key = 'rating' AND post.post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetchAll( PDO::FETCH_ASSOC );
    
    $output = [
      'rating' => round( $reviews[0]['average'] ),
      'count' => $reviews[0]['counted'],
    ];
    
    return $output;
  }
  
  
  /**
   * Récupère les données liées à la chambre la moins chère des hotels
   *
   * @param HotelEntity $hotel
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   rooms: int | null,
   *   bathRooms: int | null,
   *   types: string[]
   * }                  $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws FilterException
   * @return RoomEntity
   */
  protected function getCheapestRoom ( HotelEntity $hotel, array $args = [] ) : RoomEntity {
    // On charge toutes les chambres de l'hôtel
    $command = "SELECT post.ID as ID,
      post.post_title as title,
      MIN(priceData.meta_value) AS price,
      imageData.meta_value as coverimage,
      bedData.meta_value as bedrooms,
      bathData.meta_value as bathrooms,
      typeData.meta_value as type,
      surfaceData.meta_value as surface
     
      FROM wp_posts AS post
  
        INNER JOIN wp_postmeta AS surfaceData 
          ON post.ID = surfaceData.post_id AND surfaceData.meta_key = 'surface'

        INNER JOIN wp_postmeta AS typeData 
          ON post.ID = typeData.post_id AND typeData.meta_key = 'type'

        INNER JOIN wp_postmeta AS bedData
          ON post.ID = bedData.post_id AND bedData.meta_key = 'bedrooms_count'

        INNER JOIN wp_postmeta AS bathData
          ON post.ID = bathData.post_id AND bathData.meta_key = 'bathrooms_count'
  
        INNER JOIN wp_postmeta AS priceData
          ON post.ID = priceData.post_id AND priceData.meta_key = 'price'

        INNER JOIN wp_postmeta AS imageData
          ON post.ID = imageData.post_id AND imageData.meta_key = 'coverImage'
      ";

    $whereTab = [];

    if (($args['surface']['min'] ?? -1.0)!=-1.0)
      $whereTab[] = 'surfaceData.meta_value >= :surfmin';
    
    if (($args['surface']['max'] ?? -1.0)!=-1.0)
      $whereTab[] = 'surfaceData.meta_value <= :surfmax';

    if (($args['price']['min'] ?? null)!=null)
      $whereTab[] = 'priceData.meta_value >= :pricemin';
    
    if (($args['price']['max'] ?? null)!=null)
      $whereTab[] = 'priceData.meta_value <= :pricemax';

    if (($args['rooms'] ?? -1)!=-1.0)
      $whereTab[] = 'bedData.meta_value >= :roomneed';
    
    if (($args['bathRooms'] ?? -1)!=-1.0)
      $whereTab[] = 'bathData.meta_value >= :bathneed';

    if (($args['types'] ?? null)!=null)
      $whereTab[] = 'typeData.meta_value = :typeneed';

    
    $command .= " WHERE post.post_type='room'"; 
    

    if (count($whereTab) > 0)
    {
      $command .= " AND " . implode(' AND ', $whereTab);
    }

    $command .= " Group by post.post_author";

    $stmt = $this->getDB()->prepare($command);

    if (($args['surface']['min'] ?? -1.0)!=-1.0)
      $stmt->bindParam('surfmin', $args['surface']['min'], PDO::PARAM_INT);
    
    if (($args['surface']['max'] ?? -1.0)!=-1.0)
      $stmt->bindParam('surfmax', $args['surface']['max'], PDO::PARAM_INT);

    if (($args['price']['min'] ?? null)!=null)
      $stmt->bindParam('pricemin', $args['price']['min']);
    
    if (($args['price']['max'] ?? null)!=null)
      $stmt->bindParam('pricemax', $args['price']['max']);

    if (($args['rooms'] ?? -1)!=-1.0)
      $stmt->bindParam('roomneed', $args['rooms'], PDO::PARAM_INT);
    
    if (($args['bathRooms'] ?? -1)!=-1.0)
      $stmt->bindParam('bathneed', $args['bathRooms'], PDO::PARAM_INT);

    if (($args['types'] ?? null)!=null)
      $stmt->bindParam('typeneed', $args['types'][0]);

    
    $stmt->execute();
      
    $roomdata = $stmt->fetch( PDO::FETCH_ASSOC );
    
    
    
    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
    if ( $roomdata == null || $roomdata['ID'] == null)
      throw new FilterException( "Aucune chambre ne correspond aux critères" );
    
    //dump($roomdata);
    $room = new RoomEntity();
    $room->setId($roomdata['ID']);
    $room->setTitle($roomdata['title']);
    $room->setPrice($roomdata['price']);
    $room->setCoverImageUrl($roomdata['coverimage']);
    $room->setBedRoomsCount($roomdata['bedrooms']);
    $room->setBathRoomsCount($roomdata['bathrooms']);
    $room->setSurface($roomdata['surface']);
    $room->setType($roomdata['type']);

    return $room;
  }
  
  
  /**
   * Calcule la distance entre deux coordonnées GPS
   *
   * @param $latitudeFrom
   * @param $longitudeFrom
   * @param $latitudeTo
   * @param $longitudeTo
   *
   * @return float|int
   */
  protected function computeDistance ( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo ) : float|int {
    return ( 111.111 * rad2deg( acos( min( 1.0, cos( deg2rad( $latitudeTo ) )
          * cos( deg2rad( $latitudeFrom ) )
          * cos( deg2rad( $longitudeTo - $longitudeFrom ) )
          + sin( deg2rad( $latitudeTo ) )
          * sin( deg2rad( $latitudeFrom ) ) ) ) ) );
  }
  
  
  /**
   * Construit une ShopEntity depuis un tableau associatif de données
   *
   * @throws Exception
   */
  protected function convertEntityFromArray ( array $data, array $args ) : HotelEntity {
    $hotel = ( new HotelEntity() )
      ->setId( $data['ID'] )
      ->setName( $data['display_name'] );
    
    // Charge les données meta de l'hôtel
    $timer = Timers::getInstance();
    $idtimermeta = $timer->startTimer("MetaTimer");
    $metasData = $this->getMetas( $hotel );
    $hotel->setAddress( $metasData['address'] );
    $hotel->setGeoLat( $metasData['geo_lat'] );
    $hotel->setGeoLng( $metasData['geo_lng'] );
    $hotel->setImageUrl( $metasData['coverImage'] );
    $hotel->setPhone( $metasData['phone'] );
    $timer->endTimer("MetaTimer", $idtimermeta);
    
    // Définit la note moyenne et le nombre d'avis de l'hôtel
    $idtimerreview = $timer->startTimer("ReviewTimer");
    $reviewsData = $this->getReviews( $hotel );
    $hotel->setRating( $reviewsData['rating'] );
    $hotel->setRatingCount( $reviewsData['count'] );
    $timer->endTimer("ReviewTimer", $idtimerreview);
    
    // Charge la chambre la moins chère de l'hôtel
    $idtimercheap = $timer->startTimer("CheapTimer");
    $cheapestRoom = $this->getCheapestRoom( $hotel, $args );
    $hotel->setCheapestRoom($cheapestRoom);
    $timer->endTimer("CheapTimer", $idtimercheap);
    
    // Verification de la distance
    if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
      $hotel->setDistance( $this->computeDistance(
        floatval( $args['lat'] ),
        floatval( $args['lng'] ),
        floatval( $hotel->getGeoLat() ),
        floatval( $hotel->getGeoLng() )
      ) );
      
      if ( $hotel->getDistance() > $args['distance'] )
        throw new FilterException( "L'hôtel est en dehors du rayon de recherche" );
    }
    
    return $hotel;
  }
  
  
  /**
   * Retourne une liste de boutiques qui peuvent être filtrées en fonction des paramètres donnés à $args
   *
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   bedrooms: int | null,
   *   bathrooms: int | null,
   *   types: string[]
   * } $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws Exception
   * @return HotelEntity[] La liste des boutiques qui correspondent aux paramètres donnés à args
   */
  public function list ( array $args = [] ) : array {
    
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_users" );
    $stmt->execute();
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      try {
        $results[] = $this->convertEntityFromArray( $row, $args );
      } catch ( FilterException ) {
        // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
      }
    }
    
    return $results;
  }
}