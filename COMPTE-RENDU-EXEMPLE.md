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
| Nombre d'appels de `getDB()` | 200    | 200    |
 | Temps de `getMeta()`            | 3.07     | 170.66     |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 2201    | 601    |
| Temps de chargement global   | 6.49     | 4.43     |

**Requête SQL**

```SQL
-- GIGA REQUÊTE
-- INDENTATION PROPRE ET COMMENTAIRES SERONT APPRÉCIÉS MERCI !
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`

**Requête SQL d'ajout des indexes** 

```sql
-- REQ SQL CREATION INDEXES
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | TEMPS       | TEMPS        |
| `OneRequestService`            | TEMPS       | TEMPS        |
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
