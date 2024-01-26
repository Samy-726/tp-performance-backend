<?php

namespace App\Services\Hotel;

use App\Common\SingletonDB;
use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use PDO;
use PDOStatement;

class OneRequestHotelService extends AbstractHotelService {
  
  use SingletonTrait;
  private PDO $db;
  
  
  protected function __construct () {
    $this->db = new PDO( "mysql:host=db;dbname=tp;charset=utf8mb4", "root", "root" );
    parent::__construct( new RoomService() );
  }
  
  
  
  /**
   * Récupère une nouvelle instance de connexion à la base de donnée
   *
   * @return PDO
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDB () : PDO {
    $timerId = Timers::getInstance()->startTimer('getDB');
    
    $pdo = SingletonDB::getInstance() ->getPDO();
    
    Timers::getInstance()->endTimer('getDB', $timerId);
    return $pdo;
  }
  
  /**
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   distance: int | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   bedrooms: int | null,
   *   bathrooms: int | null,
   *   types: string[]
   * } $args Une liste de paramètres pour filtrer les résultats
   *
   * @return PDOStatement
   * @noinspection SqlResolve
   */
  protected function buildQuery ( array $args ) : PDOStatement {
    $selectDistanceKM = isset( $args['lat'] ) && isset( $args['lng'] )
      ? " 111.111
      * DEGREES(ACOS(LEAST(1.0, COS(RADIANS( latData.meta_value ))
      * COS(RADIANS( :lat ))
      * COS(RADIANS( lngData.meta_value - :lng ))
      + SIN(RADIANS( latData.meta_value ))
      * SIN(RADIANS( :lat ))))) as distanceKM,"
      : null;
    
    $query = "SELECT
		user.ID AS id,
	display_name AS name,
	latData.meta_value AS lat,
	lngData.meta_value AS lng,
	mailData.meta_value AS mail,
	phoneData.meta_value AS phone,
	addr1Data.meta_value AS address_1,
	addr2Data.meta_value AS address_2,
	cityData.meta_value AS address_city,
	zipData.meta_value AS address_zip,
	countryData.meta_value AS address_country,
	imageData.meta_value AS cover_image,
	$selectDistanceKM
	
	review.count AS review_count,
	review.rating AS review_rating,
	
	post.ID AS cheapestRoomId,
	post.price AS price,
	post.surface AS surface,
	post.bedRoomsCount AS bed_rooms_count,
	post.bathRoomsCount AS bath_rooms_count,
	post.roomType AS type
	
FROM
	wp_users AS USER
	
	-- geo coords
	INNER JOIN tp.wp_usermeta AS latData ON latData.user_id = user.ID
		AND latData.meta_key = 'geo_lat'
	INNER JOIN tp.wp_usermeta AS lngData ON lngData.user_id = user.ID
		AND lngData.meta_key = 'geo_lng'
	-- email
	INNER JOIN tp.wp_usermeta AS mailData ON mailData.user_id = user.ID
		AND mailData.meta_key = 'email'
	-- phone
	INNER JOIN tp.wp_usermeta AS phoneData ON phoneData.user_id = user.ID
		AND phoneData.meta_key = 'phone'
	-- address_1
	INNER JOIN tp.wp_usermeta AS addr1Data ON addr1Data.user_id = user.ID
		AND addr1Data.meta_key = 'address_1'
	-- address_2
	INNER JOIN tp.wp_usermeta AS addr2Data ON addr2Data.user_id = user.ID
		AND addr2Data.meta_key = 'address_2'
	-- address_city
	INNER JOIN tp.wp_usermeta AS cityData ON cityData.user_id = user.ID
		AND cityData.meta_key = 'address_city'
	-- address_zip
	INNER JOIN tp.wp_usermeta AS zipData ON zipData.user_id = user.ID
		AND zipData.meta_key = 'address_zip'
	-- address_country
	INNER JOIN tp.wp_usermeta AS countryData ON countryData.user_id = user.ID
		AND countryData.meta_key = 'address_country'
	-- image
	INNER JOIN tp.wp_usermeta AS imageData ON imageData.user_id = user.ID
		AND imageData.meta_key = 'coverImage'
	
	-- rating
	INNER JOIN (
	SELECT
		post.post_author,
		COUNT( CAST(meta.meta_value AS UNSIGNED) ) as count,
		AVG( CAST(meta.meta_value AS UNSIGNED) ) as rating
	FROM
		tp.wp_posts as post
		INNER JOIN tp.wp_postmeta as meta ON post.ID = meta.post_id
	WHERE
		post_type = 'review'
	GROUP BY
		post_author
	) AS review ON user.ID = post_author
	
	-- room
  INNER JOIN (
		SELECT
			post.ID,
			post.post_title,
			post.post_author,
			MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
			CAST(surfaceData.meta_value AS UNSIGNED) as surface,
			CAST(bedData.meta_value AS UNSIGNED) as bedRoomsCount,
			CAST(bathData.meta_value AS UNSIGNED) as bathRoomsCount,
			typeData.meta_value as roomType
		FROM
			tp.wp_posts AS post
			-- price
			INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id
				AND priceData.meta_key = 'price'
			-- surface
			INNER JOIN tp.wp_postmeta AS surfaceData ON surfaceData.post_id = post.ID
				AND surfaceData.meta_key = 'surface'
			-- bedrooms count
			INNER JOIN tp.wp_postmeta AS bedData ON bedData.post_id = post.ID
				AND bedData.meta_key = 'bedrooms_count'
			-- bathrooms count
			INNER JOIN tp.wp_postmeta AS bathData ON bathData.post_id = post.ID
				AND bathData.meta_key = 'bathrooms_count'
			-- room type
			INNER JOIN tp.wp_postmeta AS typeData ON typeData.post_id = post.ID
				AND typeData.meta_key = 'type'
		WHERE
			post_type = 'room'
		GROUP BY
			post.ID) AS post ON user.ID = post.post_author
";
    
    // WHERE
    $whereStmt = [];
    $havingStmt = [];
    $orderByStmt = [];
    
    // Price
    if ( isset ( $args['price']['min'] ) || isset( $args['price']['max'] ) ) {
      $orderByStmt[] = "price ASC";
      
      if ( isset( $args['price']['min'] ) )
        $whereStmt[] = 'price >= :priceMin';
      
      if ( isset( $args['price']['max'] ) )
        $whereStmt[] = 'price <= :priceMax';
    }
    
    // Surface
    if ( isset( $args['surface']['min'] ) )
      $whereStmt[] = 'surface >= :surfaceMin';
    
    // Surface
    if ( isset( $args['surface']['max'] ) )
      $whereStmt[] = 'surface <= :surfaceMax';
    
    // bedrooms
    if ( isset( $args['rooms'] ) )
      $whereStmt[] = 'bedRoomsCount >= :bedRoomsCount';
    
    // bathrooms
    if ( isset( $args['bathRooms'] ) )
      $whereStmt[] = 'bathRoomsCount >= :bathRoomsCount';
    
    // Types
    if ( isset( $args['types'] ) && count( $args['types'] ) > 0 )
      $whereStmt[] = 'roomType IN ('. "'" . implode( '\', \'', $args['types'] ) . "'" .')';
    
    // distance
    if ( isset( $selectDistanceKM ) && isset( $args['distance'] ) && $args['distance'] > 0) {
      $havingStmt[] = 'distanceKM <= :distanceKM';
      $orderByStmt = [
        'distanceKM ASC',
        ...$orderByStmt,
      ];
    }
    
    if ( count( $whereStmt ) > 0 )
      $query .= ' WHERE ' . implode( ' AND ', $whereStmt );
    
    $query .= ' GROUP BY user.ID';
    
    if ( count( $havingStmt ) > 0 )
      $query .= ' HAVING ' . implode( ' AND ', $havingStmt );
    
    if ( count( $orderByStmt ) > 0 )
      $query .= ' ORDER BY ' . implode( ', ', $orderByStmt );
    
    $stmt = $this->getDB()->prepare( $query );
    
    // Price
    if ( isset( $args['price']['min'] ) )
      $stmt->bindParam( 'priceMin', $args['price']['min'], PDO::PARAM_INT );
    
    // Price
    if ( isset( $args['price']['max'] ) )
      $stmt->bindParam( 'priceMax', $args['price']['max'], PDO::PARAM_INT );
    
    
    // Surface
    if ( isset( $args['surface']['min'] ) )
      $stmt->bindParam( 'surfaceMin', $args['surface']['min'], PDO::PARAM_INT );
    
    // Surface
    if ( isset( $args['surface']['max'] ) )
      $stmt->bindParam( 'surfaceMax', $args['surface']['max'], PDO::PARAM_INT );
    
    // bedrooms
    if ( isset( $args['rooms'] ) )
      $stmt->bindParam( 'bedRoomsCount', $args['rooms'], PDO::PARAM_INT );
    
    // bathrooms
    if ( isset( $args['bathRooms'] ) )
      $stmt->bindParam( 'bathRoomsCount', $args['bathRooms'], PDO::PARAM_INT );
    
    if (isset($selectDistanceKM)) {
      $stmt->bindParam( 'lat', $args['lat'], PDO::PARAM_STR );
      $stmt->bindParam( 'lng', $args['lng'], PDO::PARAM_STR );
    }
    
    // distance
    if ( isset( $selectDistanceKM ) && isset( $args['distance'] ) && $args['distance'] > 0)
      $stmt->bindParam( 'distanceKM', $args['distance'], PDO::PARAM_INT );
    
    
    
    return $stmt;
  }
  
  
  /**
   * Convertit un array de données en HotelEntity
   *
   * @param array $data
   *
   * @return HotelEntity
   */
  protected function convertEntityFromArray( array $data ) : HotelEntity {
    $hotel = ( new HotelEntity() )
      ->setId( $data['id'] )
      ->setName( $data['name'] )
      ->setGeoLat( strval( $data['lat'] ) )
      ->setGeoLng( strval( $data['lng'] ) )
      ->setMail( $data['mail'] )
      ->setPhone( $data['phone'] )
      ->setImageUrl( $data['cover_image'] )
      ->setRatingCount( strval( $data['review_count'] ) )
      ->setRating( round( $data['review_rating'] ) )
      ->setAddress( [
        'address_1' => $data['address_1'],
        'address_2' => $data['address_2'],
        'address_city' => $data['address_city'],
        'address_zip' => $data['address_zip'],
        'address_country' => $data['address_country'],
      ] );
  
    if (isset($data['distanceKM']))
      $hotel->setDistance( floatval($data['distanceKM']) ?? null );
  
    $cheapestRoom = ( new RoomEntity() )
      ->setId( $data['cheapestRoomId'] )
      ->setPrice( $data['price'] )
      ->setSurface( $data['surface'] )
      ->setBedRoomsCount( $data['bed_rooms_count'] )
      ->setBathRoomsCount( $data['bath_rooms_count'] )
      ->setType( $data['type'] );
    $hotel->setCheapestRoom( $cheapestRoom );
    
    return $hotel;
  }
  
  /**
   * @inheritDoc
   */
  public function list ( array $args = [] ) : array {
    $stmt = $this->buildQuery( $args );
    $stmt->execute();
    
    $output = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      $output[] = $this->convertEntityFromArray($row);
    }
    
    return $output;
  }
}