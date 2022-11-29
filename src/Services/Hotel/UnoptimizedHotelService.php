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
    $stmt = $this->getDB()->prepare( "SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetchAll( PDO::FETCH_ASSOC );
    
    // Sur les lignes, ne garde que la note de l'avis
    $reviews = array_map( function ( $review ) {
      return intval( $review['meta_value'] );
    }, $reviews );
    
    $output = [
      'rating' => round( array_sum( $reviews ) / count( $reviews ) ),
      'count' => count( $reviews ),
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
    $command = "SELECT MIN(priceData.meta_value) AS price,
      surfaceData.meta_value as surface
     
      FROM wp_posts AS post
  
        INNER JOIN wp_postmeta AS surfaceData 
          ON post.ID = surfaceData.post_id AND surfaceData.meta_key = 'surface'

        INNER JOIN wp_postmeta AS typeData 
          ON post.ID = typeData.post_id AND typeData.meta_key = 'type'

        INNER JOIN wp_postmeta AS bedData
          ON post.ID = bedData.post_id AND bedData.meta_key = 'bedRoomsCount'

        INNER JOIN wp_postmeta AS bathData
          ON post.ID = bathData.post_id AND bathData.meta_key = 'bathRoomsCount'
  
        INNER JOIN wp_postmeta AS priceData
          ON post.ID = priceData.post_id AND priceData.meta_key = 'price'
      ";

    $whereTab = [];

    $surfmin = $args['surface']['min'] ?? -1.0;
    $surfmax = $args['surface']['max'] ?? -1.0;
    $pricemin = $args['price']['min'] ?? null;
    $pricemax = $args['price']['max'] ?? null;
    $roomneed = $args['rooms'] ?? -1;
    $bathneed = $args['bathRooms'] ?? -1;
    $typeneed = $args['types'] ?? null;

    if ($surfmin!=-1.0)
      $whereTab[] = 'surfaceData.meta_value >= :surfmin';
    
    if ($surfmax!=-1.0)
      $whereTab[] = 'surfaceData.meta_value <= :surfmax';

    if ($pricemin!=null)
      $whereTab[] = 'priceData.meta_value >= :pricemin';
    
    if ($pricemax!=null)
      $whereTab[] = 'priceData.meta_value <= :pricemax';

    if ($roomneed!=-1.0)
      $whereTab[] = 'bedData.meta_value >= :roomneed';
    
    if ($bathneed!=-1.0)
      $whereTab[] = 'bathData.meta_value >= :bathneed';

    if ($typeneed!=null)
      $whereTab[] = 'typeData.meta_value = :typeneed';


    if (count($whereTab) > 0)
    {
      $command .= " WHERE " . implode(' AND ', $whereTab);
    }

    $stmt = $this->getDB()->prepare($command);

    if ($surfmin!=-1.0)
      $stmt->bindParam('surfmin', $surfmin, PDO::PARAM_INT);
    
    if ($surfmax!=-1.0)
      $stmt->bindParam('surfmax', $surfmax, PDO::PARAM_INT);

    if ($pricemin!=null)
      $stmt->bindParam('pricemin', $pricemin, PDO::PARAM_STRING);
    
    if ($pricemax!=null)
      $stmt->bindParam('pricemax', $pricemax, PDO::PARAM_STRING);

    if ($roomneed!=-1.0)
      $stmt->bindParam('roomneed', $roomneed, PDO::PARAM_INT);
    
    if ($bathneed!=-1.0)
      $stmt->bindParam('bathneed', $bathneed, PDO::PARAM_INT);

    if ($typeneed!=null)
      $stmt->bindParam('typeneed', $typeneed, PDO::PARAM_STRING);
    
    $stmt->execute();
    
    dump($stmt->fetchAll( PDO::FETCH_ASSOC ));

    /**
     * On convertit les lignes en instances de chambres (au passage ça charge toutes les données).
     *
     * @var RoomEntity[] $rooms ;
     */
    $rooms = array_map( function ( $row ) {
      return $this->getRoomService()->get( $row['ID'] );
    }, $stmt->fetchAll( PDO::FETCH_ASSOC ) );

    
    
    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
    if ( count( $rooms ) < 1 )
      throw new FilterException( "Aucune chambre ne correspond aux critères" );
    
    return $rooms;
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