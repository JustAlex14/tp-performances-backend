### ℹ️ Indice n°3 : Comment obtenir plusieurs valeurs des tables `meta` dans la même requête ?

Vous pouvez faire des `INNER JOIN` avec alias. Par exemple :

```sql
SELECT 
    user.ID            AS id,
    user.display_name  AS name,
    latData.meta_value AS lat,
    lngData.meta_value AS lng

FROM 
    wp_users AS USER

    -- geo lat
    INNER JOIN tp.wp_usermeta AS latData 
        ON latData.user_id = user.ID AND latData.meta_key = 'geo_lat'
        
    -- geo lng
    INNER JOIN tp.wp_usermeta AS lngData 
        ON lngData.user_id = user.ID AND lngData.meta_key = 'geo_lng'
```
Ce qui m'affichera :

|  `id`  | `name`             | `lat`    | `lng`   |
|:------:|--------------------|----------|---------|
|   1    | 	Roy GIE           | 	45.4645 | 	1.5997 |
|   2    | 	Carpentier SCOP   | 	46.5729 | 	2.3380 |
|   3    | 	Schmitt et Dupont | 	46.7807 | 	2.2344 |