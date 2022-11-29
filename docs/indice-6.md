### ℹ️ Indice n°6 : Calculer une distance entre deux points GPS en SQL

Vous pouvez utiliser la formule ci-dessous qui va calculer la distance en Kilomètres séparant deux coordonnées GPS. Attention ici à ne pas utiliser `CAST()`.
```sql
SELECT 
    111.111
        * DEGREES(ACOS(LEAST(1.0, COS(RADIANS( latData.meta_value ))
        * COS(RADIANS( :userLat ))
        * COS(RADIANS( lngData.meta_value - :userLng ))
        + SIN(RADIANS( latData.meta_value ))
        * SIN(RADIANS( :userLat ))))) AS distanceKM
```
Exemple dans une requête plus concrète
```sql
SELECT
    user.ID AS id,
    display_name AS name,
    latData.meta_value AS lat,
    lngData.meta_value AS lng,
    111.111
        * DEGREES(ACOS(LEAST(1.0, COS(RADIANS( latData.meta_value ))
        * COS(RADIANS( 46.9903264 ))
        * COS(RADIANS( lngData.meta_value - 3.163412 ))
        + SIN(RADIANS( latData.meta_value ))
        * SIN(RADIANS( 46.9903264 ))))) AS distanceKM

FROM
    wp_users AS USER
	
    -- geo coords
    INNER JOIN tp.wp_usermeta AS latData ON latData.user_id = user.ID AND latData.meta_key = 'geo_lat'
    INNER JOIN tp.wp_usermeta AS lngData ON lngData.user_id = user.ID AND lngData.meta_key = 'geo_lng'

-- Condition de distance maximale
HAVING 
    distanceKM < 50

ORDER BY 
    distanceKM ASC;
```

Ce qui me retourne :

| `id` | `name`         | `lat`    | `lng`   | `distanceKM`        |
|:----:|----------------|----------|---------|---------------------|
 | 180  | 	Meyer SEM     | 	46.9942 | 	3.1675 | 	0.5303152844565747 |
 | 173  | 	Berger SCOP   | 	46.9914 | 	3.1715 | 	0.6244923521036668 |
 | 191  | 	Francois EURL | 	46.9859 | 	3.1563 | 	0.7297006925364777 |