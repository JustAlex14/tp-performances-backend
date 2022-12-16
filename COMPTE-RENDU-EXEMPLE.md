Vous pouvez utiliser ce [GSheets](https://docs.google.com/spreadsheets/d/13Hw27U3CsoWGKJ-qDAunW9Kcmqe9ng8FROmZaLROU5c/copy?usp=sharing) pour suivre l'évolution de l'amélioration de vos performances au cours du TP 

## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : TEMPS

**Choix des méthodes à analyser** :

- `getMetas` 4.41 secs
- `getReviews` 8.96 secs
- `loadCheapestRoom` 15.35 secs



## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : 30 secs

**Temps consommé par `getDB()`** 

- **Avant** 1.21 secs

- **Après** 7.11 ms



## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux** 

- **Avant** 30 secs

- **Après** 17 secs



#### Amélioration de la méthode `getMeta()` et donc de la méthode `getMetas()` :

- **Avant** 3.07 secs

```sql
-- SELECT * FROM wp_usermeta
```

- **Après** 170.66 ms

```sql
-- SELECT meta_key,meta_value FROM wp_usermeta WHERE user_id = :userid
```



#### Amélioration de la méthode `getCheapestRoom()` :

- **Avant** 15.35 secs

```sql
-- SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
```

- **Après** 11.04 secs

```sql
-- $command = "SELECT post.ID as ID,
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
      $whereTab[] = 'typeData.meta_value IN ("'.implode('","',$args['types']).'")';

      

    
    $command .= " WHERE post.post_type='room' AND post.post_author=:hotelId"; 
```



#### Amélioration de la méthode `getReview()` :

- **Avant** 8.96 secs

```sql
-- SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```

- **Après** 6.36 secs

```sql
-- SELECT AVG(ratingData.meta_value) as average,
      Count(ratingData.meta_value) as counted
    FROM wp_posts AS post
        INNER JOIN wp_postmeta AS ratingData
            ON post.ID = ratingData.post_id AND ratingData.meta_key = 'rating'
            
      WHERE post.post_author=:hotelId AND post.ID = ratingData.post_id AND meta_key = 'rating' AND post.post_type = 'review'
```



## Question 5 : Réduction du nombre de requêtes SQL pour `getMeta()`

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 2201    | 601    |
 | Temps de `getMeta()`            | 6.49     | 4.43     |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `list` | 2201    | 601    |
| Temps de chargement global   | 18.88secs     | 2.98sec     |

**Requête SQL**

```SQL
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
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `wp_postmeta` : `post_id`
- `wp_usermeta` : `user_id`
- `wp_posts` : `post_author`

**Requête SQL d'ajout des indexes** 

```sql
ALTER TABLE wp_postmeta ADD INDEX(post_id)
ALTER TABLE wp_usermeta ADD INDEX(user_id)
ALTER TABLE wp_posts ADD INDEX(post_author)
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | 18.88secs       | 427ms        |
| `OneRequestService`            | 2.98secs       | 377ms        |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | TEMPS       | TEMPS        |
| `ReworkedHotelService`         | TEMPS       | TEMPS        |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `rooms` (1 200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `reviews` (19 700 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```


## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| TEMPS      | TEMPS      |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec  |
|-----------------------|-------|-------|
| Total des fichiers JS | POIDS | POIDS |
| `lodash.js`           | POIDS | POIDS |

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : POIDS
- **Après** : POIDS

## Question 17 : Cache NGINX

**Temps de chargement cache FastCGI**

- **Avant** : TEMPS
- **Après** : TEMPS

#### Que se passe-t-il si on actualise la page après avoir coupé la base de données ?

REPONSE

#### Pourquoi ?

REPONSE
