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


class OneRequestHotelService extends AbstractHotelService {
  
  use SingletonTrait;

  protected function __construct () {
    parent::__construct( new RoomService() );
  }
  
  protected function getDB () : PDO {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('TimerGetBD');
    $pdo = PDOSingleton::get();
    $timer->endTimer('TimerGetBD', $timerId);
    return $pdo;
  }

  public function list ( array $args = [] ) : array { 
    
    $db = $this->getDB();

    $command = "SELECT
        user.ID AS id,
        user.display_name AS name,
        COUNT(reviewData.meta_value) as ratingCount,
        AVG(reviewData.meta_value) as rating,
        address_1Data.meta_value as address_1,
        address_2Data.meta_value as address_2,
        address_cityData.meta_value as address_city,
        address_zipData.meta_value as address_zip,
        address_countryData.meta_value as address_country,
        geo_latData.meta_value as geo_lat,
        geo_lngData.meta_value as geo_lng,
        phoneData.meta_value as phone,
        coverImageData.meta_value as coverImage,
        postData.title as title,
        postData.ID as roomid,
        postData.price as price,
        postData.surface as surface,
        postData.bedroom  as bedRoomsCount,
        postData.bathroom as bathRoomsCount,
        postData.type as type";

        if (($args['distance'] ?? -1)!=-1.0){ 
          $command .= ",
          111.111
          * DEGREES(ACOS(LEAST(1.0, COS(RADIANS( geo_latData.meta_value ))
          * COS(RADIANS( :lat ))
          * COS(RADIANS( geo_lngData.meta_value - :lng ))
          + SIN(RADIANS( geo_latData.meta_value ))
          * SIN(RADIANS( :lat ))))) AS distanceKM";
        }
        
        $command .= "
        FROM wp_users AS user

        INNER JOIN wp_usermeta as address_1Data
          ON address_1Data.user_id = user.ID     AND address_1Data.meta_key = 'address_1'
        
        INNER JOIN wp_usermeta as address_2Data 
          ON address_2Data.user_id = user.ID AND address_2Data.meta_key = 'address_2'
        
        INNER JOIN wp_usermeta as address_cityData 
          ON address_cityData.user_id = user.ID AND address_cityData.meta_key = 'address_city'
        
        INNER JOIN wp_usermeta as address_zipData 
          ON address_zipData.user_id = USER.ID AND address_zipData.meta_key = 'address_zip'
        
        INNER JOIN wp_usermeta as address_countryData 
          ON address_countryData.user_id = USER.ID AND address_countryData.meta_key = 'address_country'
        
        INNER JOIN wp_usermeta as geo_latData 
          ON geo_latData.user_id = USER.ID AND geo_latData.meta_key = 'geo_lat'
        
        INNER JOIN wp_usermeta as geo_lngData 
          ON geo_lngData.user_id = USER.ID AND geo_lngData.meta_key = 'geo_lng'

        INNER JOIN wp_usermeta as coverImageData 
          ON coverImageData.user_id = USER.ID AND coverImageData.meta_key = 'coverImage'

        INNER JOIN wp_usermeta as phoneData 
          ON phoneData.user_id = USER.ID AND phoneData.meta_key = 'phone'
        
        INNER JOIN wp_posts as rating_postData 
          ON rating_postData.post_author = USER.ID AND rating_postData.post_type = 'review'

        INNER JOIN wp_postmeta as reviewData 
          ON reviewData.post_id = rating_postData.ID AND reviewData.meta_key = 'rating'
    

        INNER JOIN (SELECT
            post.ID,
            post.post_author,
            MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
            CAST(surfaceData.meta_value  AS UNSIGNED) AS surface,
            CAST(roomsData.meta_value AS UNSIGNED) AS bedroom,
            CAST(bathRoomsData.meta_value AS UNSIGNED) AS bathroom,
            post_title as title,
            typeData.meta_value AS type

            FROM tp.wp_posts AS post

            INNER JOIN tp.wp_postmeta AS priceData 
              ON post.ID = priceData.post_id AND priceData.meta_key = 'price'
            
            INNER JOIN wp_postmeta as surfaceData 
              ON surfaceData.post_id = post.ID AND surfaceData.meta_key = 'surface'
            
            INNER JOIN wp_postmeta as roomsData 
              ON roomsData.post_id = post.ID AND roomsData.meta_key = 'bedrooms_count'
            
            INNER JOIN wp_postmeta as bathRoomsData 
              ON bathRoomsData.post_id = post.ID AND bathRoomsData.meta_key = 'bathrooms_count'
            
            INNER JOIN wp_postmeta as typeData 
              ON typeData.post_id = post.ID AND typeData.meta_key = 'type'
            
            WHERE  post.post_type = 'room'

            GROUP BY post.ID
        ) AS postData ON user.ID = postData.post_author";


        $whereTab = [];
        if (isset($args['surface']['min']))
          $whereTab[] = "postData.surface >= :surfmin";
        
        if (isset($args['surface']['max']))
          $whereTab[] = "postData.surface <= :surfmax";
        
        if (isset($args['price']['min']))
          $whereTab[] = "postData.price >= :pricemin";
        
        if (isset($args['price']['max']))
          $whereTab[] = "postData.price <= :pricemax";
        
        if (isset ($args['rooms']))
          $whereTab[] = "postData.bedroom >= :roomneed";
        
        if (isset ($args['bathRooms'])){
          $whereTab[] = "postData.bathroom >= :bathneed";
        }
        if (isset ($args['types']) && count($args['types']) > 0)
          $whereTab[] = 'postData.type IN ("'.implode('","',$args['types']).'")';

        if (count($whereTab) > 0)
        {
          $command .= " WHERE " . implode(' AND ', $whereTab);
        }

      $command .= "
      GROUP BY user.ID";  

      if(!empty($args["distance"])){
        $command .= " \n HAVING distanceKM <= :distance";
      }
      
      $command .="
      ORDER BY `roomid` ASC";

    $stmt = $db->prepare($command);
    
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

    if (($args['distance'] ?? -1)!=-1.0){
      $stmt->bindParam('lat', $args['lat']);
      $stmt->bindParam('lng', $args['lng']);
      $stmt->bindParam('distance', $args['distance']);
    }
   
    $stmt->execute();
    $results = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $hotels = [];

    foreach($results as $result){

      $hotel = ( new HotelEntity() )
        ->setId( $result['id'] )
        ->setName( $result['name'] )
        ->setAddress( [
          'address_1' => $result['address_1'],
          'address_2' => $result['address_2'],
          'address_city' => $result['address_city'],
          'address_zip' => $result['address_zip'],
          'address_country' => $result['address_country']
        ] )
        ->setGeoLat( $result['geo_lat'] )
        ->setGeoLng( $result['geo_lng'] )
        ->setImageUrl( $result['coverImage'] )
        ->setPhone( $result['phone'] )
        ->setRating( intval($result['rating']) )
        ->setRatingCount( $result['ratingCount'] )
        ->setCheapestRoom( (new RoomEntity())
        ->setId( $result['roomid'] )
        ->setTitle( $result['title'] )
        ->setSurface( $result['surface'] )
        ->setPrice( $result['price'] )
        ->setBedRoomsCount( $result['bedRoomsCount'] )
        ->setBathRoomsCount( $result['bathRoomsCount'] )
        ->setType( $result['type'] ) );

      $hotels[] = $hotel;

    }

    return $hotels;

  }

}